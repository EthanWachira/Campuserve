"""
CampuServe - watch the classifier being trained.

    python watch_training.py            # prints each step and saves figures to model/figures/
    python watch_training.py --show     # also opens the figures in windows

Figures produced (use them in the report and the demo):
  1_class_balance.png       how many training examples each category has
  2_tfidf_example.png       what the model actually "sees" for one request
  3_epoch_curve.png         accuracy climbing as the classifier is fed requests in small batches ("watch it learn")
  4_learning_curve.png      accuracy as the amount of training data grows (more data helps?)
  5_model_comparison.png    baseline vs naive Bayes vs logistic regression vs SVM
  6_top_words.png           the words that push a request towards each category (explainability)
  7_confusion_matrix.png    where the model confuses categories on unseen issue types
"""
import argparse
import time
from pathlib import Path

import matplotlib
import numpy as np
import pandas as pd

HERE = Path(__file__).parent
FIG = HERE / "model" / "figures"
FIG.mkdir(parents=True, exist_ok=True)


def step(n, title):
    print(f"\n[{n}/7] {title}")
    return time.time()


def done(t0):
    print(f"      done in {time.time() - t0:.1f}s")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--show", action="store_true", help="open figures in windows as well as saving them")
    args = ap.parse_args()
    if not args.show:
        matplotlib.use("Agg")
    import matplotlib.pyplot as plt
    from sklearn.feature_extraction.text import TfidfVectorizer
    from sklearn.linear_model import SGDClassifier
    from sklearn.metrics import ConfusionMatrixDisplay, accuracy_score
    from sklearn.model_selection import GroupKFold, GroupShuffleSplit, cross_val_predict, learning_curve
    from sklearn.svm import LinearSVC
    from train import CANDIDATES

    plt.rcParams.update({"font.size": 10, "axes.spines.top": False, "axes.spines.right": False})
    df = pd.read_csv(HERE / "data" / "tickets_synthetic.csv")
    X, y, groups = df["text"].astype(str), df["category"], df["issue_id"]
    classes = sorted(y.unique())

    # ---- 1. data
    t0 = step(1, "Load the labelled requests and check the classes are balanced")
    counts = y.value_counts().sort_values()
    print(counts.to_string())
    fig, ax = plt.subplots(figsize=(8, 4.5))
    ax.barh(counts.index, counts.values, color="#1f4fd8")
    ax.set_xlabel("training examples")
    ax.set_title(f"Training data: {len(df)} labelled requests, {len(classes)} categories")
    fig.tight_layout(); fig.savefig(FIG / "1_class_balance.png", dpi=160); done(t0)

    # ---- 2. features
    t0 = step(2, "Turn text into numbers (TF-IDF): what the model actually sees")
    word = TfidfVectorizer(ngram_range=(1, 2), sublinear_tf=True)
    word.fit(X)
    example = "I paid through the paybill but the money has not reflected on my fee statement"
    vec = word.transform([example])
    names = np.array(word.get_feature_names_out())
    idx = vec.nonzero()[1]
    top = sorted(zip(names[idx], vec.data), key=lambda p: -p[1])[:12]
    for w_, v_ in top:
        print(f"      {w_:28s} weight {v_:.3f}")
    fig, ax = plt.subplots(figsize=(8, 4.5))
    ax.barh([w_ for w_, _ in top][::-1], [v_ for _, v_ in top][::-1], color="#f5b83d")
    ax.set_title("TF-IDF weights for one request\n" + '"' + example[:60] + '..."')
    ax.set_xlabel("weight (distinctive words score higher)")
    fig.tight_layout(); fig.savefig(FIG / "2_tfidf_example.png", dpi=160); done(t0)

    # ---- 3. learning in action
    t0 = step(3, "Watch a linear classifier learn: feed it requests in small batches, test on unseen issue types")
    tr, te = next(GroupShuffleSplit(n_splits=1, test_size=0.2, random_state=7).split(X, y, groups))
    vec_all = TfidfVectorizer(ngram_range=(1, 2), sublinear_tf=True).fit(X.iloc[tr])
    Xtr, Xte = vec_all.transform(X.iloc[tr]), vec_all.transform(X.iloc[te])
    ytr = y.iloc[tr].values
    sgd = SGDClassifier(loss="hinge", alpha=1e-5, random_state=1)
    rng = np.random.RandomState(0)
    order = rng.permutation(Xtr.shape[0])
    batch = 40
    seen, test_acc, train_acc = [], [], []
    for start in range(0, len(order), batch):
        idx = order[start:start + batch]
        sgd.partial_fit(Xtr[idx], ytr[idx], classes=classes)
        seen.append(start + len(idx))
        test_acc.append(accuracy_score(y.iloc[te], sgd.predict(Xte)))
        train_acc.append(accuracy_score(ytr[order[:seen[-1]]], sgd.predict(Xtr[order[:seen[-1]]])))
        if (start // batch) % 6 == 0 or start + batch >= len(order):
            print(f"      after {seen[-1]:5d} requests seen   accuracy on unseen issue types {test_acc[-1]:.3f}")
    fig, ax = plt.subplots(figsize=(8, 4.5))
    ax.plot(seen, test_acc, color="#be3a34", lw=2, label="unseen issue types (test)")
    ax.plot(seen, train_acc, color="#1f4fd8", lw=1.5, alpha=.7, label="requests it has already seen")
    ax.set_xlabel("training requests seen so far"); ax.set_ylabel("accuracy"); ax.set_ylim(0, 1.02)
    ax.axhline(1 / len(classes), color="#9aa5bd", ls="--", lw=1); ax.text(seen[-1], 1 / len(classes) + .015, "random guessing", ha="right", color="#66738d", fontsize=9)
    ax.set_title("Learning in action: accuracy rises as the model sees more requests"); ax.legend(frameon=False, loc="center right")
    fig.tight_layout(); fig.savefig(FIG / "3_epoch_curve.png", dpi=160); done(t0)

    # ---- 4. learning curve
    t0 = step(4, "Does more data help? (learning curve, grouped cross-validation)")
    sizes, tr_s, va_s = learning_curve(CANDIDATES["logistic_regression"](), X, y, groups=groups,
                                       cv=GroupKFold(n_splits=5), train_sizes=np.linspace(0.1, 1.0, 6), n_jobs=1)
    for n, a, b in zip(sizes, tr_s.mean(1), va_s.mean(1)):
        print(f"      {int(n):5d} training rows   train {a:.3f}   validation {b:.3f}")
    fig, ax = plt.subplots(figsize=(8, 4.5))
    ax.plot(sizes, tr_s.mean(1), "o-", label="training score", color="#1f4fd8")
    ax.plot(sizes, va_s.mean(1), "o-", label="validation score (unseen issues)", color="#be3a34")
    ax.set_xlabel("number of training rows"); ax.set_ylabel("accuracy"); ax.set_ylim(0, 1.02)
    ax.set_title("Learning curve: the validation score keeps rising with more data"); ax.legend(frameon=False)
    fig.tight_layout(); fig.savefig(FIG / "4_learning_curve.png", dpi=160); done(t0)

    # ---- 5. model comparison
    t0 = step(5, "Compare candidate models (grouped 5-fold cross-validation)")
    scores = {}
    for name, make in CANDIDATES.items():
        pred = cross_val_predict(make(), X, y, groups=groups, cv=GroupKFold(n_splits=5))
        scores[name] = accuracy_score(y, pred)
        print(f"      {name:26s} {scores[name]:.3f}")
    fig, ax = plt.subplots(figsize=(8, 4.5))
    labels = [n.replace("_", " ") for n in scores]
    bars = ax.barh(labels, list(scores.values()), color=["#9aa5bd", "#7a67e0", "#1f4fd8", "#1b8a57"][: len(scores)])
    ax.bar_label(bars, fmt="%.2f", padding=3); ax.set_xlim(0, 1.08)
    ax.set_xlabel("accuracy on unseen issue types"); ax.set_title("Model comparison")
    fig.tight_layout(); fig.savefig(FIG / "5_model_comparison.png", dpi=160); done(t0)

    # ---- 6. top words
    t0 = step(6, "What did it learn? The top words for each category")
    svm = LinearSVC(C=1.0).fit(word.transform(X), y)
    fig, axes = plt.subplots(3, 3, figsize=(13, 9))
    for ax, cls_idx in zip(axes.ravel(), range(len(svm.classes_))):
        coefs = svm.coef_[cls_idx]
        top_i = np.argsort(coefs)[-8:]
        print(f"      {svm.classes_[cls_idx]:28s} " + ", ".join(names_ for names_ in word.get_feature_names_out()[top_i][::-1][:6]))
        ax.barh(word.get_feature_names_out()[top_i], coefs[top_i], color="#1f4fd8")
        ax.set_title(svm.classes_[cls_idx], fontsize=10)
        ax.tick_params(labelsize=8)
    fig.suptitle("Words that push a request towards each category", y=1.0)
    fig.tight_layout(); fig.savefig(FIG / "6_top_words.png", dpi=160); done(t0)

    # ---- 7. confusion matrix
    t0 = step(7, "Where does the final model get confused? (predictions on unseen issue types)")
    pred = cross_val_predict(CANDIDATES["linear_svm_calibrated"](), X, y, groups=groups, cv=GroupKFold(n_splits=5))
    fig, ax = plt.subplots(figsize=(9, 8))
    ConfusionMatrixDisplay.from_predictions(y, pred, ax=ax, xticks_rotation=40, colorbar=False, cmap="Blues")
    ax.set_title("Confusion matrix (grouped cross-validation)")
    fig.tight_layout(); fig.savefig(FIG / "7_confusion_matrix.png", dpi=160); done(t0)

    print(f"\nFigures saved in {FIG}")
    if args.show:
        plt.show()


if __name__ == "__main__":
    main()
