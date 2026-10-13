"""
Campusserve - ML classification API (Flask).

    pip install -r requirements.txt
    python api.py            # listens on http://127.0.0.1:5000

POST /classify  {"subject": "...", "description": "..."}
  -> {category, confidence, department, priority, needs_review, alternatives[], model_version}
GET  /health
"""
import re
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


@app.post("/classify")
def classify():
    data = request.get_json(silent=True) or {}
    subject = str(data.get("subject", "")).strip()
    description = str(data.get("description", "")).strip()
    text = f"{subject}. {description}".strip(". ").strip()
    if len(text) < 5:
        return jsonify(error="Provide a subject or description of at least 5 characters."), 400

    probs = PIPELINE.predict_proba([text])[0]
    ranked = sorted(zip(PIPELINE.classes_, probs), key=lambda p: p[1], reverse=True)
    category, confidence = ranked[0]
    return jsonify(
        category=category,
        confidence=round(float(confidence), 4),
        department=ROUTING.get(category),
        priority=priority_for(text),
        needs_review=bool(confidence < REVIEW_THRESHOLD),
        alternatives=[{"category": c, "confidence": round(float(p), 4)} for c, p in ranked[1:3]],
        model_version=VERSION,
    )


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000)
