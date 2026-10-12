"""
CampuServe - train and evaluate the service-request classifier.

    python generate_dataset.py
    python train.py                       # synthetic data only
    python train.py --extra data/public.csv   # merge extra rows (text,category)

Outputs (in ./model):
    classifier.joblib        fitted sklearn Pipeline
    metrics.json             cross-validation + hold-out scores and model metadata
    classification_report.txt
    confusion_matrix.png     for the project report
"""
import argparse
import json
from datetime import datetime
from pathlib import Path

import joblib
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import numpy as np
import pandas as pd
from sklearn.calibration import CalibratedClassifierCV
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (ConfusionMatrixDisplay, accuracy_score,
                             classification_report, f1_score)
from sklearn.model_selection import GroupKFold, GroupShuffleSplit, StratifiedKFold, cross_val_predict
from sklearn.naive_bayes import MultinomialNB
from sklearn.pipeline import FeatureUnion, Pipeline
from sklearn.svm import LinearSVC

HERE = Path(__file__).parent
MODEL_DIR = HERE / "model"
MODEL_DIR.mkdir(exist_ok=True)


def features():
    return FeatureUnion([
        ("word", TfidfVectorizer(ngram_range=(1, 2), sublinear_tf=True, min_df=1, lowercase=True)),
        ("char", TfidfVectorizer(analyzer="char_wb", ngram_range=(3, 5), sublinear_tf=True, min_df=2)),
    ])


CANDIDATES = {
    "logistic_regression": lambda: Pipeline([("f", features()), ("c", LogisticRegression(max_iter=2000, C=10))]),
    "linear_svm_calibrated": lambda: Pipeline([("f", features()),
                                              ("c", CalibratedClassifierCV(LinearSVC(C=1.0), cv=3))]),
    "naive_bayes": lambda: Pipeline([("f", TfidfVectorizer(ngram_range=(1, 2), sublinear_tf=True)),
                                     ("c", MultinomialNB(alpha=0.1))]),
}


def load(extra: str | None) -> pd.DataFrame:
    df = pd.read_csv(HERE / "data" / "tickets_synthetic.csv")
    if extra:
        add = pd.read_csv(extra)[["text", "category"]].dropna()
        add["issue_id"] = np.arange(100000, 100000 + len(add))   # each row its own group
        add["source"] = "extra"
        df = pd.concat([df, add], ignore_index=True)
    return df


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--extra", help="optional CSV with text,category columns to merge")
    args = ap.parse_args()

    df = load(args.extra)
    X, y, groups = df["text"].astype(str), df["category"], df["issue_id"]
    print(f"{len(df)} rows, {y.nunique()} classes")

    # 1. Group-wise CV: paraphrases of the same issue never straddle train/test.
    gkf = GroupKFold(n_splits=5)
    scores = {}
    for name, make in CANDIDATES.items():
        pred = cross_val_predict(make(), X, y, groups=groups, cv=gkf)
        scores[name] = {"accuracy": round(accuracy_score(y, pred), 4),
                        "macro_f1": round(f1_score(y, pred, average="macro"), 4)}
        print(f"  {name:24s} acc={scores[name]['accuracy']:.3f}  macro-F1={scores[name]['macro_f1']:.3f}")
    best = max(scores, key=lambda k: scores[k]["macro_f1"])
    # Reference only: random split lets paraphrases of one issue leak across folds, so this is optimistic.
    skf = StratifiedKFold(n_splits=5, shuffle=True, random_state=1)
    rpred = cross_val_predict(CANDIDATES[best](), X, y, cv=skf)
    random_cv = {"accuracy": round(accuracy_score(y, rpred), 4), "macro_f1": round(f1_score(y, rpred, average="macro"), 4)}
    print(f"  random 5-fold ({best}, optimistic upper bound): {random_cv}")
    print("Selected:", best)

    # 2. Hold-out report for the selected model (unseen issues only).
    gss = GroupShuffleSplit(n_splits=1, test_size=0.2, random_state=7)
    tr, te = next(gss.split(X, y, groups))
    model = CANDIDATES[best]()
    model.fit(X.iloc[tr], y.iloc[tr])
    pred = model.predict(X.iloc[te])
    report = classification_report(y.iloc[te], pred, digits=3)
    holdout = {"accuracy": round(accuracy_score(y.iloc[te], pred), 4),
               "macro_f1": round(f1_score(y.iloc[te], pred, average="macro"), 4),
               "test_rows": int(len(te))}
    print("\nHold-out (unseen issues):", holdout)
    print(report)
    (MODEL_DIR / "classification_report.txt").write_text(report, encoding="utf-8")

    fig, ax = plt.subplots(figsize=(9, 8))
    ConfusionMatrixDisplay.from_predictions(y.iloc[te], pred, ax=ax, xticks_rotation=40,
                                            colorbar=False, cmap="Blues")
    ax.set_title("CampuServe request classifier - hold-out confusion matrix")
    fig.tight_layout()
    fig.savefig(MODEL_DIR / "confusion_matrix.png", dpi=160)

    # 3. Final model trained on everything.
    final = CANDIDATES[best]()
    final.fit(X, y)
    version = datetime.now().strftime("%Y%m%d-%H%M")
    joblib.dump({"pipeline": final, "version": f"{best}-{version}"}, MODEL_DIR / "classifier.joblib")
    (MODEL_DIR / "metrics.json").write_text(json.dumps({
        "model": best, "version": f"{best}-{version}", "trained_rows": int(len(df)),
        "classes": sorted(y.unique().tolist()), "group_cv_scores": scores, "random_cv_optimistic": random_cv, "holdout": holdout,
        "note": "Scores come from synthetic data and are optimistic. Re-evaluate on real tickets before relying on them.",
    }, indent=2), encoding="utf-8")
    print(f"Saved model {best}-{version}")


if __name__ == "__main__":
    main()
