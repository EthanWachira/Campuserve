"""
CampuServe - retrain the classifier from staff feedback (human-in-the-loop learning).

Called by api.py (POST /retrain) when the administrator clicks "Retrain now" (or the automatic job fires).
It can also be tested from the command line:

    python retrain.py --demo         # retrain from data/feedback_labelled.csv (written by the last retrain)

How the learning is measured honestly
-------------------------------------
* Staff-labelled requests (confirmed or corrected categories) are split by ticket id:
  every 4th ticket becomes a permanent TEST set that no model ever trains on; the rest are TRAINING examples.
* The new model is trained on the original dataset + the training feedback (repeated REPEAT times, because real
  student wording is rarer and more valuable than generated wording).
* Both the PREVIOUS model and the NEW model are scored on the same held-out staff-labelled tickets, and the new
  model is scored on the same held-out synthetic issue types used by train.py. Figures are measured, not made up.
* The new model is only deployed if it is not worse than the previous one (override with force).
* The previous model is archived in model/archive/ so nothing is ever lost.
"""
import argparse
import json
import os
import time
from datetime import datetime
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.metrics import accuracy_score, f1_score
from sklearn.model_selection import GroupShuffleSplit

HERE = Path(__file__).parent
MODEL_DIR = HERE / "model"
ARCHIVE = MODEL_DIR / "archive"
ALGO = "linear_svm_calibrated"
REPEAT = 3            # how many times each staff-labelled training example is repeated in the training set
TEST_MOD = 4          # ticket_id % 4 == 0  ->  permanent test example (about 25%)
NOT_A_CLASS = {"General Enquiries"}   # catch-all bucket, not something the model should learn
MAX_SYNTH_DROP = 0.02  # reject a new model that loses more than 2 points on the synthetic hold-out


def _clean(rows) -> pd.DataFrame:
    df = pd.DataFrame(rows, columns=["ticket_id", "text", "category"]) if rows else pd.DataFrame(columns=["ticket_id", "text", "category"])
    if df.empty:
        return df
    df = df.dropna(subset=["ticket_id", "text", "category"]).copy()
    df["ticket_id"] = df["ticket_id"].astype(int)
    df["text"] = df["text"].astype(str).str.replace(r"\s+", " ", regex=True).str.strip()
    df = df[(df["text"].str.len() >= 5) & (~df["category"].isin(NOT_A_CLASS))]
    return df.drop_duplicates("ticket_id").reset_index(drop=True)


def _stack(base: pd.DataFrame, fb: pd.DataFrame) -> pd.DataFrame:
    parts = [base[["text", "category"]]]
    if len(fb):
        parts += [fb[["text", "category"]]] * REPEAT
    return pd.concat(parts, ignore_index=True)


def retrain(rows, force: bool = False) -> dict:
    from train import CANDIDATES   # imported here so the API starts fast

    t0 = time.time()
    base = pd.read_csv(HERE / "data" / "tickets_synthetic.csv")
    fb = _clean(rows)
    if fb.empty:
        raise ValueError("There are no staff-labelled requests to learn from yet.")

    old_bundle = joblib.load(MODEL_DIR / "classifier.joblib")
    old, old_version = old_bundle["pipeline"], old_bundle["version"]
    old_classes = set(old.classes_)
    metrics_path = MODEL_DIR / "metrics.json"
    old_metrics = json.loads(metrics_path.read_text(encoding="utf-8")) if metrics_path.exists() else {}
    prev_synth = (old_metrics.get("holdout") or {}).get("accuracy")
    prev_rows = int(old_metrics.get("trained_rows") or len(base))

    # a brand-new category needs at least 3 examples (the calibrated SVM uses 3-fold calibration)
    counts = fb["category"].value_counts()
    keep = fb["category"].isin(old_classes) | fb["category"].map(counts).ge(3)
    skipped = int((~keep).sum())
    fb = fb[keep].reset_index(drop=True)
    if fb.empty:
        raise ValueError("Not enough examples of any category to learn from yet.")

    is_test = (fb["ticket_id"] % TEST_MOD == 0)
    fb_test, fb_train = fb[is_test].reset_index(drop=True), fb[~is_test].reset_index(drop=True)
    corrected = int(sum(1 for _, r in fb.iterrows() if r["category"] != _predicted_by(old, r["text"])))

    # synthetic hold-out: same split as train.py, so the figure is comparable with the previous one
    X, y, groups = base["text"].astype(str), base["category"], base["issue_id"]
    tr, te = next(GroupShuffleSplit(n_splits=1, test_size=0.2, random_state=7).split(X, y, groups))
    probe = CANDIDATES[ALGO]()
    probe_df = _stack(base.iloc[tr], fb_train)
    probe.fit(probe_df["text"], probe_df["category"])
    sp = probe.predict(X.iloc[te])
    synth = {"accuracy": round(float(accuracy_score(y.iloc[te], sp)), 4),
             "macro_f1": round(float(f1_score(y.iloc[te], sp, average="macro")), 4), "test_rows": int(len(te))}

    # final model: everything except the permanent test tickets
    all_df = _stack(base, fb_train)
    final = CANDIDATES[ALGO]()
    final.fit(all_df["text"], all_df["category"])

    real_new = real_old = None
    if len(fb_test):
        real_new = round(float(accuracy_score(fb_test["category"], final.predict(fb_test["text"]))), 4)
        real_old = round(float(accuracy_score(fb_test["category"], old.predict(fb_test["text"]))), 4)

    new_classes = sorted(set(final.classes_) - old_classes)
    learned = fb_train["category"].value_counts().to_dict()

    reason = None
    if prev_synth is not None and synth["accuracy"] < prev_synth - MAX_SYNTH_DROP:
        reason = f"accuracy on the original hold-out fell from {prev_synth:.1%} to {synth['accuracy']:.1%}"
    if real_new is not None and len(fb_test) >= 5 and real_new < real_old:
        reason = f"accuracy on held-out staff-labelled requests fell from {real_old:.1%} to {real_new:.1%}"
    deploy = force or reason is None
    version = f"{ALGO}-{datetime.now():%Y%m%d-%H%M%S}-fb{len(fb_train)}"

    result = {
        "version": version, "previous_version": old_version, "status": "deployed" if deploy else "rejected",
        "base_rows": int(len(base)), "feedback_rows": int(len(fb)), "feedback_train": int(len(fb_train)),
        "feedback_test": int(len(fb_test)), "corrected_rows": corrected, "skipped_rows": skipped,
        "trained_rows": int(len(base) + len(fb_train)), "prev_trained_rows": prev_rows,
        "synthetic_accuracy": synth["accuracy"], "prev_synthetic_accuracy": prev_synth, "macro_f1": synth["macro_f1"],
        "real_accuracy": real_new, "prev_real_accuracy": real_old,
        "classes": list(map(str, final.classes_)), "new_classes": new_classes,
        "categories_learned": {str(k): int(v) for k, v in learned.items()},
        "used_ticket_ids": [int(i) for i in fb["ticket_id"]],
        "duration_ms": 0,
        "message": ("New model deployed." if deploy else f"New model NOT deployed: {reason}. The previous model is still in use."),
    }

    # keep a plain labelled file for the command-line trainer (python train.py --extra data/corrections.csv)
    fb[["text", "category", "ticket_id"]].to_csv(HERE / "data" / "feedback_labelled.csv", index=False)

    if deploy:
        ARCHIVE.mkdir(exist_ok=True)
        joblib.dump(old_bundle, ARCHIVE / f"classifier-{old_version}.joblib")
        tmp = MODEL_DIR / "classifier.joblib.tmp"
        joblib.dump({"pipeline": final, "version": version}, tmp)
        os.replace(tmp, MODEL_DIR / "classifier.joblib")           # atomic swap: never a half-written model
        m = dict(old_metrics)
        m.update({"model": ALGO, "version": version, "trained_rows": result["trained_rows"],
                  "classes": sorted(map(str, final.classes_)), "holdout": synth,
                  "feedback": {k: result[k] for k in ("feedback_rows", "feedback_train", "feedback_test", "corrected_rows",
                                                      "real_accuracy", "prev_real_accuracy", "categories_learned")},
                  "retrained_at": datetime.now().isoformat(timespec="seconds")})
        metrics_path.write_text(json.dumps(m, indent=2), encoding="utf-8")
        hist_path = MODEL_DIR / "history.json"
        hist = json.loads(hist_path.read_text(encoding="utf-8")) if hist_path.exists() else []
        hist.append({k: v for k, v in result.items() if k != "used_ticket_ids"} | {"at": datetime.now().isoformat(timespec="seconds")})
        hist_path.write_text(json.dumps(hist, indent=2), encoding="utf-8")
        result["_pipeline"] = final
    result["duration_ms"] = int((time.time() - t0) * 1000)
    return result


def _predicted_by(model, text: str) -> str:
    """What the model that was live before this retrain would have said (used only to count corrections)."""
    try:
        return str(model.predict([text])[0])
    except Exception:
        return ""


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--demo", action="store_true", help="retrain from data/feedback_labelled.csv")
    args = ap.parse_args()
    df = pd.read_csv(HERE / "data" / "feedback_labelled.csv")
    out = retrain(df.to_dict("records"))
    out.pop("_pipeline", None); out.pop("used_ticket_ids", None)
    print(json.dumps(out, indent=2))
