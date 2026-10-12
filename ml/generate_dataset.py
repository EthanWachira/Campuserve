"""
CampuServe - synthetic dataset generator.

The proposal calls for a hybrid training set: public IT/helpdesk tickets plus
synthetic university-specific samples. This script produces the synthetic part.
Public data can be merged later with:  python train.py --extra data/public.csv
(the CSV needs the columns: text,category).

Every sample carries an `issue_id`. The trainer uses it for group-wise
cross-validation so that paraphrases of one issue never appear in both the
training and test folds. That gives an honest accuracy estimate instead of a
score inflated by near-duplicate sentences.
"""
import csv
import random
from pathlib import Path

random.seed(42)

# category -> list of issue sentences (student voice)
ISSUES = {
    "IT Support": [
        "I cannot log in to my student portal",
        "my university email password has expired and I cannot reset it",
        "the campus wifi keeps disconnecting in the library",
        "the eLearning site shows an error when I open my course",
        "I forgot my password for the learning management system",
        "my account has been locked after too many login attempts",
        "the computer lab machines are not connecting to the network",
        "I am not able to access my Outlook mailbox",
        "the printing system charged me but nothing was printed",
        "I need help installing the licensed software on my laptop",
        "the VPN will not connect when I am off campus",
        "my student account says access denied on the portal",
        "the projector in the lab is not displaying from my laptop",
        "I did not receive the verification code to my phone",
    ],
    "Academic Records & Results": [
        "my results for last semester are missing on the portal",
        "there is a wrong grade recorded for one of my units",
        "I want to request an official transcript",
        "my CAT marks were not uploaded by the lecturer",
        "I need to apply for a remarking of my exam script",
        "I am missing a unit from my academic record",
        "I want a letter confirming that I am a student here",
        "my GPA calculation looks incorrect",
        "I need my results slip for a scholarship application",
        "the system shows a supplementary exam I did not sit",
        "I would like to apply for a deferral of my studies",
        "my name is spelt wrongly on my academic record",
        "I want to know the status of my graduation clearance",
        "I need a certified copy of my certificate",
    ],
    "Fees & Billing Enquiries": [
        "my fee statement shows a balance that I already paid",
        "I paid my tuition by bank but it is not reflected",
        "I would like to ask about a fee instalment plan",
        "I was charged twice for the same semester",
        "I need a fee clearance letter for my internship",
        "the fee structure on my statement does not match the letter",
        "I want to know the deadline for paying tuition",
        "my sponsor has paid but my account still shows arrears",
        "I need to request a refund for an overpayment",
        "can I get a receipt for the fees I paid last week",
        "I want to apply for a bursary or fee waiver",
        "my fee balance is blocking me from registering for units",
        "I paid via M-Pesa and the payment is missing from my statement",
        "I need a breakdown of the charges on my invoice",
    ],
    "Timetable & Examinations": [
        "my exam timetable clashes with another paper",
        "I cannot find my class timetable for this semester",
        "two of my lectures are scheduled at the same time",
        "I missed an exam because I was ill and need a special sitting",
        "when will the end of semester exam timetable be released",
        "I do not have an exam card for this semester",
        "the exam venue was changed and I was not informed",
        "I need to reschedule my CAT because of a clash",
        "my lecture room has been changed but the timetable still shows the old one",
        "I want to confirm the date of my supplementary exam",
        "the timetable has a unit that I did not register for",
        "I need an extra time arrangement for my exams",
        "there is no lecture slot for one of my units on the timetable",
        "my exam number is missing from the exam list",
    ],
    "Admissions & Registration": [
        "I want to register for units for the new semester",
        "I cannot add a unit during course registration",
        "I need to change my programme to a different degree",
        "I am a new student and need help with my admission documents",
        "my admission letter has not been sent to me",
        "I want to defer my admission to the next intake",
        "I need to change my intake from September to January",
        "registration is closed and I could not register in time",
        "I want to transfer from another university",
        "I need to drop a unit that I registered by mistake",
        "how do I apply for the next academic year",
        "my student number has not been issued yet",
        "I need to upload my admission requirements again",
        "I want to confirm whether my application was successful",
    ],
    "Library Services": [
        "I need to renew the books I borrowed from the library",
        "I cannot access the online journals from home",
        "the library says I have an overdue fine I do not owe",
        "I want to request a book that is not on the shelves",
        "I need a discussion room booked at the library",
        "my library card is not working at the gate",
        "I cannot download articles from the e-resources database",
        "I lost a library book and need to know how to replace it",
        "the library catalogue shows a book as available but it is missing",
        "I need help with referencing and finding research sources",
        "can I get an extended loan for my final year project books",
        "the library silent study area is too noisy",
        "I want to suggest the library buys a new textbook",
        "my library account is blocked and I cannot borrow",
    ],
    "Student Welfare": [
        "I am struggling with stress and would like to see a counsellor",
        "I need support because of a family emergency",
        "I have a medical condition and need support at school",
        "I would like to report harassment by another student",
        "I am facing financial hardship and might drop out",
        "I want to book a session with the counselling office",
        "I need help finding mental health support on campus",
        "I want to join the peer mentorship programme",
        "I feel overwhelmed by my workload and need someone to talk to",
        "I need a welfare letter for compassionate leave",
        "I have a disability and need reasonable accommodation",
        "I want to report a safety concern around campus at night",
        "I lost a close relative and need time off from classes",
        "I need guidance on career and personal development support",
    ],
    "Facilities & Accommodation": [
        "the lights in my lecture hall are not working",
        "I want to apply for a room in the student hostel",
        "the air conditioning in the classroom is broken",
        "there is a water leak in the hostel bathroom",
        "my hostel room key is lost and I need a replacement",
        "the washrooms near the cafeteria are dirty",
        "I need a parking permit for my car on campus",
        "the lift in the main building is out of order",
        "my hostel roommate allocation needs to be changed",
        "the sockets in the study area are not working",
        "I need to book a venue for a club event",
        "the gate lock at the hostel is faulty",
        "there is no hot water in the residence block",
        "a window in my classroom is broken and unsafe",
    ],
    "Academic Advising": [
        "I need advice on which electives to choose",
        "I want to meet my lecturer to discuss my project topic",
        "my academic advisor has not responded to my emails",
        "I am failing a unit and need advice on how to recover",
        "I need a recommendation letter from a lecturer",
        "I want guidance on choosing a final year project supervisor",
        "can a lecturer explain how my CAT was marked",
        "I need advice about changing my specialisation",
        "I want to discuss my academic progress with my mentor",
        "I need help understanding the prerequisites for next semester",
        "I would like feedback on my assignment from my lecturer",
        "I need guidance on my industrial attachment placement",
        "I want to book consultation hours with my unit lecturer",
        "I need help planning my units so I graduate on time",
    ],
}


# Topic vocabulary per class. Real tickets are often short and keyword-like
# ("fee balance wrong", "wifi not working"), so we also generate those.
NOUNS = {
    "IT Support": ["student portal login", "wifi", "university email", "eLearning account", "password reset", "lab computer",
                   "printing account", "VPN access", "Outlook", "software licence", "internet connection", "account lockout",
                   "projector", "two-factor code"],
    "Academic Records & Results": ["transcript", "exam results", "semester grades", "remarking", "academic record", "student status letter",
                                   "certificate", "CAT marks", "GPA", "graduation clearance", "results slip", "degree classification",
                                   "missing marks", "academic standing"],
    "Fees & Billing Enquiries": ["fee statement", "tuition balance", "fee instalments", "payment receipt", "fee clearance",
                                 "refund", "fee arrears", "bursary", "sponsor payment", "M-Pesa payment", "invoice", "fee waiver",
                                 "bank payment", "double charge"],
    "Timetable & Examinations": ["exam timetable", "class timetable", "exam clash", "exam card", "special exam", "supplementary exam",
                                 "exam venue", "lecture schedule", "CAT date", "exam number", "extra time", "lecture room change",
                                 "timetable clash", "exam date"],
    "Admissions & Registration": ["unit registration", "course registration", "programme change", "admission letter", "intake deferral",
                                  "transfer application", "dropping a unit", "student number", "new student admission", "application status",
                                  "admission documents", "late registration", "adding a unit", "intake change"],
    "Library Services": ["library book renewal", "library fine", "e-journal access", "library card", "book request", "study room booking",
                         "research database", "overdue book", "lost library book", "library catalogue", "extended loan",
                         "reference help", "library account", "interlibrary loan"],
    "Student Welfare": ["counselling", "stress", "mental health support", "harassment complaint", "family emergency", "financial hardship",
                        "peer mentorship", "disability support", "compassionate leave", "welfare support", "safety concern",
                        "bereavement", "medical support", "wellbeing"],
    "Facilities & Accommodation": ["hostel room", "broken lights", "air conditioning", "water leak", "room key", "parking permit",
                                   "lift", "washrooms", "hot water", "classroom window", "venue booking", "hostel allocation",
                                   "power sockets", "gate lock"],
    "Academic Advising": ["elective advice", "lecturer consultation", "project supervisor", "academic advisor", "recommendation letter",
                          "unit prerequisites", "specialisation change", "assignment feedback", "mentor meeting", "industrial attachment",
                          "study plan", "project topic", "consultation hours", "course guidance"],
}
NOUN_TEMPLATES = [
    "{n}", "{n} issue", "problem with {n}", "need help with {n}", "question about {n}",
    "{n} not working", "{n} is wrong", "{n} missing", "urgent {n} request", "please assist with {n}",
    "I have an issue with my {n}", "enquiry about {n}",
]

OPENERS = [
    "", "", "Hello, ", "Good morning, ", "Hi team, ", "Dear support, ",
    "Please help. ", "Urgent: ", "Kindly assist, ", "Good afternoon, ",
    "To whom it may concern, ", "Hi, ",
]
DETAILS = [
    "", "", "",
    " This has been happening since Monday.",
    " I am in my third year.",
    " I am a first year student.",
    " I tried again this morning with the same result.",
    " It started after the mid semester break.",
    " I have already asked my classmates and nobody knows.",
    " I am in the BBIT programme.",
    " I have attached a screenshot.",
    " This is affecting my coursework.",
    " I reported this last week and nothing has changed.",
]
CLOSERS = [
    "", "", " Thank you.", " Please assist.", " Regards.",
    " Kindly advise.", " I appreciate your help.", " Thanks in advance.",
    " Please let me know the next step.",
]
# Mixed-signal sentences: the surface words belong to one class, the label to another.
AMBIGUOUS = [
    ("I cannot log in to the portal to check my fee balance", "IT Support"),
    ("I cannot see my exam timetable on the portal, it shows a login error", "IT Support"),
    ("my fee statement is not loading on the portal", "IT Support"),
    ("I want to book a lecturer consultation about my missing grade", "Academic Advising"),
    ("I need my results transcript for registration into the next semester", "Academic Records & Results"),
    ("the library wifi is down and I cannot access the journals", "IT Support"),
    ("my hostel fees are not showing on my fee statement", "Fees & Billing Enquiries"),
    ("I am stressed because of my exam timetable clash and need counselling", "Student Welfare"),
    ("registration is blocked because of my fee balance", "Fees & Billing Enquiries"),
    ("my unit is missing from the timetable after I registered", "Timetable & Examinations"),
]

TYPO_MAP = {"the": "teh", "because": "becuase", "registration": "registeration",
            "password": "pasword", "library": "libary", "timetable": "time table"}


def noisy(text: str) -> str:
    """Randomly lowercase, strip punctuation or inject a typo to mimic real tickets."""
    r = random.random()
    if r < 0.25:
        text = text.lower()
    if r < 0.12:
        text = text.replace(".", "").replace(",", "")
    if 0.12 <= r < 0.22:
        words = text.split()
        for i, w in enumerate(words):
            if w.lower() in TYPO_MAP and random.random() < 0.7:
                words[i] = TYPO_MAP[w.lower()]
                break
        text = " ".join(words)
    return text


def build(samples_per_issue: int = 9):
    rows = []
    issue_id = 0
    for category, issues in ISSUES.items():
        for issue in issues:
            issue_id += 1
            seen = set()
            tries = 0
            while len(seen) < samples_per_issue and tries < 200:
                tries += 1
                opener = random.choice(OPENERS)
                body = issue[0].upper() + issue[1:] if opener == "" or opener.endswith(". ") else issue
                text = opener + body + "." + random.choice(DETAILS) + random.choice(CLOSERS)
                text = noisy(" ".join(text.split()))
                if text not in seen:
                    seen.add(text)
                    rows.append({"text": text, "category": category, "issue_id": issue_id, "source": "synthetic"})
    for category, nouns in NOUNS.items():
        for noun in nouns:
            issue_id += 1                       # one group per (class, topic)
            for tpl in random.sample(NOUN_TEMPLATES, 8):
                text = noisy(tpl.format(n=noun))
                rows.append({"text": text, "category": category, "issue_id": issue_id, "source": "synthetic-keyword"})
    for sentence, category in AMBIGUOUS:
        for _ in range(6):
            issue_id += 1
            text = noisy(random.choice(OPENERS) + sentence + "." + random.choice(DETAILS) + random.choice(CLOSERS))
            rows.append({"text": " ".join(text.split()), "category": category,
                         "issue_id": issue_id, "source": "synthetic-ambiguous"})
    random.shuffle(rows)
    return rows


if __name__ == "__main__":
    out = Path(__file__).parent / "data" / "tickets_synthetic.csv"
    out.parent.mkdir(exist_ok=True)
    rows = build()
    with out.open("w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=["text", "category", "issue_id", "source"])
        w.writeheader()
        w.writerows(rows)
    print(f"Wrote {len(rows)} rows to {out}")
    counts = {}
    for r in rows:
        counts[r["category"]] = counts.get(r["category"], 0) + 1
    for k, v in sorted(counts.items()):
        print(f"  {k:32s} {v}")
