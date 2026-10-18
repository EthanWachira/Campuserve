"""
Prepare an INDEPENDENT test set from a public student-portal FAQ dataset.

    python prepare_external.py                       # downloads the Hugging Face CSVs, writes data/external_review.csv
    python prepare_external.py --files test.csv train.csv   # use files you downloaded yourself

Source: dhirajdj30/EduplusDataset on Hugging Face (student-portal FAQ questions, MIT licence per its page;
re-check the licence and cite it in your report). The questions were written by someone else, for another
institution, so they are a fair test of whether the model generalises beyond the author's own wording.

THIS SCRIPT ONLY SUGGESTS LABELS using simple keyword rules. You must review them by hand:
  * fix `category` where it is wrong,
  * set category to SKIP for questions that fit none of the nine categories (events, feedback, project tabs...),
  * put y in the `reviewed` column once you have checked a row.
train.py --external data/external_review.csv then tests on reviewed rows only. The model is never trained on them.
"""
import argparse
import csv
import io
import urllib.request
from pathlib import Path

URLS = ["https://huggingface.co/datasets/dhirajdj30/EduplusDataset/resolve/main/test.csv",
        "https://huggingface.co/datasets/dhirajdj30/EduplusDataset/resolve/main/train.csv"]

RULES = {
    "IT Support": ["password", "login", "log in", "portal", "technical", "upload", "mobile device", "it support"],
    "Academic Records & Results": ["grade", "marksheet", "certificate", "transcript", "result", "revaluation", "re-evaluation", "backlog"],
    "Fees & Billing Enquiries": ["fee", "fees", "payment", "pay ", "receipt", "e-mandate", "refund", "fine"],
    "Timetable & Examinations": ["timetable", "time table", "exam", "hall ticket", "schedule", "quiz"],
    "Admissions & Registration": ["admission", "register", "registration", "subject registration", "defer", "documents"],
    "Library Services": ["library"],
    "Student Welfare": ["mental health", "complaint", "struggling", "orientation"],
    "Facilities & Accommodation": ["hostel", "facilities"],
    "Academic Advising": ["advisor", "instructor", "faculty", "supervisor", "feedback on my project"],
}


def suggest(q: str) -> str:
    t = q.lower()
    best, score = "SKIP", 0
    for cat, words in RULES.items():
        n = sum(w in t for w in words)
        if n > score:
            best, score = cat, n
    return best


def read_csv(path_or_url: str) -> list[str]:
    if path_or_url.startswith("http"):
        raw = urllib.request.urlopen(path_or_url, timeout=30).read().decode("utf-8")
    else:
        raw = Path(path_or_url).read_text(encoding="utf-8")
    return [r["question"].strip() for r in csv.DictReader(io.StringIO(raw)) if r.get("question")]


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--files", nargs="*", help="local CSVs with a `question` column (default: download)")
    args = ap.parse_args()
    questions, seen = [], set()
    for src in (args.files or URLS):
        try:
            for q in read_csv(src):
                if q.lower() not in seen:
                    seen.add(q.lower())
                    questions.append(q)
        except Exception as e:  # train.csv may not exist or be offline
            print(f"skipped {src}: {e}")
    out = Path(__file__).parent / "data" / "external_review.csv"
    with out.open("w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["text", "category", "reviewed"])
        for q in questions:
            w.writerow([q, suggest(q), ""])
    print(f"Wrote {len(questions)} questions to {out}\nNow open it, correct the categories, mark reviewed=y, then run: python train.py --external data/external_review.csv")
