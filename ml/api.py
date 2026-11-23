"""
Campuserve - ML classification API (Flask).

    pip install -r requirements.txt
    python api.py            # listens on http://127.0.0.1:5000

POST /classify  {"subject": "...", "description": "..."}
  -> {category, confidence, department, priority, needs_review, alternatives[], model_version}
GET  /health
"""
import re
import threading
from pathlib import Path

import joblib
from flask import Flask, jsonify, request

HERE = Path(__file__).parent
bundle = joblib.load(HERE / "model" / "classifier.joblib")
PIPELINE, VERSION = bundle["pipeline"], bundle["version"]

# Category -> department that normally owns it (mirrors the `categories` table in MySQL).
ROUTING = {
    "IT Support": "ICT Services",
    "Academic Records & Results": "Registry",
    "Fees & Billing Enquiries": "Finance Office",
    "Timetable & Examinations": "Examinations Office",
    "Admissions & Registration": "Admissions Office",
    "Library Services": "Library",
    "Student Welfare": "Student Affairs",
    "Facilities & Accommodation": "Estates & Facilities",
    "Academic Advising": "Faculty Office",
}
REVIEW_THRESHOLD = 0.45   # below this the ticket is flagged for a human to confirm the category

HIGH = re.compile(r"\b(urgent|emergency|asap|immediately|today|tomorrow|deadline|harass\w*|unsafe|safety|"
                  r"locked out|exam (is|starts)|can'?t sit|cannot sit|missed (an )?exam|bereave\w*|stranded)\b", re.I)
MEDIUM = re.compile(r"\b(clash|missing|wrong|incorrect|blocked|not working|cannot|can'?t|unable|error|overdue|"
                    r"expired|failed|refund|twice)\b", re.I)

app = Flask(__name__)
_lock = threading.Lock()      # one retrain at a time; also guards the model swap


def priority_for(text: str) -> str:
    """Rule-based urgency. Kept separate from the classifier on purpose: it is transparent and auditable."""
    if HIGH.search(text):
        return "high"
    if MEDIUM.search(text):
        return "medium"
    return "low"


@app.get("/health")
def health():
    return jsonify(status="ok", model_version=VERSION, classes=list(PIPELINE.classes_))


@app.post("/reload")
def reload_model():
    """Load whatever is in model/classifier.joblib without restarting the service."""
    global PIPELINE, VERSION
    with _lock:
        b = joblib.load(HERE / "model" / "classifier.joblib")
        PIPELINE, VERSION = b["pipeline"], b["version"]
    return jsonify(status="ok", model_version=VERSION)


@app.post("/retrain")
def retrain_endpoint():
    """Learn from staff feedback: body {"rows":[{ticket_id,text,category},...], "force":false}.
    A deployed model replaces the live one immediately, so the very next request is classified by it."""
    global PIPELINE, VERSION
    data = request.get_json(silent=True) or {}
    if not _lock.acquire(blocking=False):
        return jsonify(error="A retraining run is already in progress."), 409
    try:
        from retrain import retrain
        result = retrain(data.get("rows") or [], force=bool(data.get("force")))
        new = result.pop("_pipeline", None)
        if new is not None:
            PIPELINE, VERSION = new, result["version"]
        return jsonify(result)
    except ValueError as e:
        return jsonify(error=str(e)), 400
    except Exception as e:                      # noqa: BLE001 - report to the admin page instead of a stack trace
        return jsonify(error=f"Retraining failed: {e}"), 500
    finally:
        _lock.release()


@app.post("/classify")
def classify():
    data = request.get_json(silent=True) or {}
    subject = str(data.get("subject", "")).strip()
    description = str(data.get("description", "")).strip()
    text = f"{subject}. {description}".strip(". ").strip()
    if len(text) < 5:
        return jsonify(error="Provide a subject or description of at least 5 characters."), 400

    pipe, version = PIPELINE, VERSION
    probs = pipe.predict_proba([text])[0]
    ranked = sorted(zip(pipe.classes_, probs), key=lambda p: p[1], reverse=True)
    category, confidence = ranked[0]
    return jsonify(
        category=category,
        confidence=round(float(confidence), 4),
        department=ROUTING.get(category),
        priority=priority_for(text),
        needs_review=bool(confidence < REVIEW_THRESHOLD),
        alternatives=[{"category": c, "confidence": round(float(p), 4)} for c, p in ranked[1:3]],
        model_version=version,
    )


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000)
