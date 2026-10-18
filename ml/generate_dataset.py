"""
CampuServe - training data generator (university-specific, Kenyan higher-education context).

HONESTY NOTE (put this in the report): this is SYNTHETIC data written for this project. It is not
historical Strathmore data. It is built from hand-written student requests that reflect real
university processes (fee statements and M-Pesa paybill payments, HELB loans, CATs and
supplementary exams, retakes, industrial attachment, hostel and shuttle problems, project
supervision) and then varied with typos, SMS-style shortcuts, unit codes and polite openers.
Because the same author wrote every sentence, scores on it are optimistic, so the project also
validates on an independently written EXTERNAL set (see prepare_external.py and train.py --external).

Every sample carries an `issue_id`. The trainer uses it for group-wise cross-validation so
paraphrases of one issue never appear in both the training and test folds.
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

EXTRA_ISSUES = {
    "IT Support": [
        "I cannot access the student portal because it says my session has expired every time I log in",
        "the eLearning platform is not showing the units I am enrolled in",
        "my student email was suspended and I need it reactivated",
        "I keep getting a wrong credentials error on the portal even though my password is correct",
        "the wifi in the hostel is very slow and drops every few minutes",
        "I cannot submit my assignment on eLearning because the upload keeps failing",
        "my exam card page does not load on the portal",
        "I need a new password because I cannot answer my security question",
        "the computer lab machines do not accept my admission number to log in",
        "I cannot connect my phone to the campus wifi network",
        "Microsoft Teams says I do not have a licence on my student account",
        "the online exam platform logged me out in the middle of my paper",
        "my fee statement page on the portal shows a blank screen",
        "I need to update my phone number on the portal but the page gives an error",
    ],
    "Academic Records & Results": [
        "my CAT 2 marks for one unit are missing on the portal",
        "I want to apply for a retake because I failed a unit last semester",
        "the final grade for my unit shows as incomplete",
        "I need an academic transcript to apply for a master's programme abroad",
        "my provisional results have not been released yet",
        "I want to appeal the grade I got in my exam",
        "I need a letter confirming my current year of study for a visa application",
        "my student ID card has expired and needs to be renewed",
        "one of the units I completed is not on my transcript",
        "I need a certificate of completion for my diploma",
        "the system shows a supplementary for a unit that I passed",
        "I want to apply for a change of name on my records",
        "I need to know my class of degree after the final results",
        "my graduation clearance form was rejected",
    ],
    "Fees & Billing Enquiries": [
        "I paid through the paybill but the money has not reflected on my statement",
        "I want to know the fee balance I must clear before I can sit exams",
        "my HELB loan was disbursed but my account does not show it",
        "I need an invoice for the coming semester to give to my sponsor",
        "I was charged for a unit that I dropped",
        "I want to request to pay my fees in three instalments",
        "my parent paid fees twice and we want a refund",
        "I need a fee clearance for my industrial attachment",
        "the fee structure for my programme changed without any notice",
        "my scholarship amount has not been applied to my fees",
        "I need a payment receipt for the bank deposit I made",
        "I have been blocked from the portal because of a fee balance",
        "I want to defer paying part of my fees until my loan comes in",
        "the amount on my statement does not match my payment slip",
    ],
    "Timetable & Examinations": [
        "I have two exams scheduled on the same day at the same time",
        "the CAT dates for my unit have not been announced",
        "my class was moved to another venue but the timetable still shows the old room",
        "I missed my CAT because I was sick and I need a makeup",
        "the exam timetable shows the wrong unit code for my paper",
        "I cannot find my exam room number",
        "I need the supplementary exam timetable",
        "two of my lectures overlap on Tuesday afternoon",
        "the lecturer did not come to class and nobody told us about a makeup session",
        "my exam card does not list all the units I registered for",
        "I was not allowed into the exam because my name was missing on the register",
        "I would like to know when the end of semester exams start",
        "the online exam link did not work at the scheduled time",
        "I want to reschedule my exam because I will represent the university at an event",
    ],
    "Admissions & Registration": [
        "I am a continuing student and need to register for next semester's units",
        "I want to change from full time to part time study",
        "I applied for admission and I have not received any feedback",
        "I need to submit my KCSE certificate for my admission file",
        "I want to be added to a unit that is already full",
        "I registered for a wrong unit and I want to swap it",
        "I want to apply for the January intake",
        "my admission number is not working on the registration page",
        "I want to transfer my credits from another university",
        "I need to know the documents required for registration as a new student",
        "I want to withdraw from the university",
        "I want to rejoin after taking a semester off",
        "I need to change my specialisation before I register",
        "my unit registration was not approved by my department",
    ],
    "Library Services": [
        "I want to borrow a reference book that is only for use inside the library",
        "my library clearance is pending because of an unreturned book",
        "I cannot log in to the library e-resources with my student account",
        "I need a past paper from the library archive",
        "the library closes too early during exams",
        "I want to borrow a laptop from the library",
        "I need help accessing journal articles for my final year project",
        "I returned the book but the system still shows it as borrowed",
        "I want to join the library orientation session",
        "the printers in the library are not working",
        "I want to reserve a book that someone else has borrowed",
        "I need a library fine waiver because I was sick",
        "the study cubicles in the library are always full during exams",
        "I need help with referencing styles from a librarian",
    ],
    "Student Welfare": [
        "I have been feeling very low and I cannot concentrate on my studies",
        "I need a counsellor because I am struggling to cope with the pressure",
        "my father passed away and I will miss classes",
        "I have been bullied by classmates and I need help",
        "I cannot afford food and I need support",
        "I need information about the emergency fund for students",
        "I want to report an incident that happened in the hostel",
        "I got injured and I need to see the university clinic",
        "I need a reasonable accommodation such as a ground floor classroom because of my disability",
        "I am worried about a friend who has stopped attending classes and seems depressed",
        "I am a young mother and need support with childcare during exams",
        "I feel unsafe walking back to the hostel at night",
        "I need a letter from the clinic to excuse my absence",
        "I would like to join the mentorship programme for first year students",
    ],
    "Facilities & Accommodation": [
        "the hostel has had no water since morning",
        "I want to apply for a room in the hostel next semester",
        "my room has a broken window and mosquitoes come in",
        "the shuttle bus did not come this morning",
        "I need a parking sticker for my car",
        "the toilets in the lecture block are blocked",
        "I want to book the auditorium for a club meeting",
        "the power has been out in the hostel for two days",
        "I lost my hostel key and need a replacement",
        "the sockets in the lecture hall are not working so I cannot charge my laptop",
        "the cafeteria does not have clean drinking water",
        "the gym equipment is broken",
        "the security light near the parking is not working",
        "I need to change my room because my roommate is very noisy",
    ],
    "Academic Advising": [
        "I want to discuss which specialisation suits my career goals",
        "I need a supervisor for my final year project",
        "my project supervisor has not given me feedback for three weeks",
        "I need guidance on how to find an industrial attachment placement",
        "I want to book a meeting with my lecturer about a low CAT mark",
        "I am failing two units and need advice on what to do",
        "I need a reference letter from my lecturer for a job application",
        "can my mentor help me plan my remaining units",
        "I need help deciding between two elective units",
        "I want to understand the prerequisites for the units I want to take next semester",
        "I need to see my academic advisor before I can register",
        "I want feedback on my assignment because I do not understand my grade",
        "I need career advice about data engineering jobs",
        "I want to meet the head of department about my project topic",
    ],
}
for _k, _v in EXTRA_ISSUES.items():
    ISSUES[_k] = ISSUES[_k] + _v

EXTRA_NOUNS = {
    "IT Support": ["student email password", "portal session", "hostel wifi", "eLearning upload", "Teams licence", "lab login", "online exam link", "OTP code"],
    "Academic Records & Results": ["retake application", "provisional results", "grade appeal", "transcript request", "student ID renewal", "completion certificate", "supplementary result", "name change on records"],
    "Fees & Billing Enquiries": ["paybill payment", "M-Pesa receipt", "HELB loan", "sponsor invoice", "fee instalment plan", "scholarship deduction", "caution money refund", "fee balance"],
    "Timetable & Examinations": ["CAT timetable", "supplementary exam date", "makeup CAT", "exam room", "lecture venue change", "exam register", "online exam", "end of semester exams"],
    "Admissions & Registration": ["unit swap", "January intake", "credit transfer", "readmission", "withdrawal form", "KCSE certificate upload", "part time study", "specialisation change"],
    "Library Services": ["past papers", "e-resources login", "library clearance", "reference book loan", "laptop loan", "discussion room", "library printing", "journal access"],
    "Student Welfare": ["counsellor appointment", "bereavement support", "emergency fund", "clinic letter", "bullying report", "mentorship programme", "childcare support", "hostel incident"],
    "Facilities & Accommodation": ["hostel water", "hostel allocation", "shuttle bus", "parking sticker", "broken sockets", "auditorium booking", "roof leak", "security light"],
    "Academic Advising": ["project supervisor", "reference letter", "elective choice", "attachment placement", "advisor meeting", "feedback on CAT", "career advice", "unit prerequisites"],
}
for _k, _v in EXTRA_NOUNS.items():
    NOUNS[_k] = NOUNS[_k] + _v

UNIT_PREFIXES = ["BBT", "ICS", "BIT", "ACF", "BCM", "LLB"]

OPENERS = [
    "", "", "", "Hello, ", "Good morning, ", "Good evening, ", "Hi team, ", "Dear support, ",
    "Please help. ", "Urgent: ", "Kindly assist, ", "Good afternoon, ", "To whom it may concern, ",
    "Hi, ", "Hello admin, ", "Dear Sir/Madam, ", "Hi, I hope you are well. ",
]
DETAILS = [
    "", "", "", "",
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
    " I am a final year student.",
    " My admission number is on the portal.",
    " I need this sorted before the end of the week.",
    " I have the receipt with me.",
    " I am a continuing student.",
    " I came to the office but I was told to apply online.",
]
CLOSERS = [
    "", "", "", " Thank you.", " Please assist.", " Regards.", " Kindly advise.",
    " I appreciate your help.", " Thanks in advance.", " Please let me know the next step.",
    " Asante.", " Looking forward to your response.", " Kindly help as soon as possible.",
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
    ("I cannot register for units because the portal is not loading", "IT Support"),
    ("I paid my fees but the portal will not show my receipt", "IT Support"),
    ("the library fine is on my fee statement and I do not know why", "Library Services"),
    ("I need a letter from the counsellor to defer my exams", "Student Welfare"),
    ("my hostel fee was charged but I have no room", "Facilities & Accommodation"),
    ("I want my lecturer to explain why my transcript shows a retake", "Academic Advising"),
    ("my supplementary exam fee is not on the statement", "Fees & Billing Enquiries"),
    ("the exam card says I am not registered for a unit I am attending", "Admissions & Registration"),
    ("I cannot open my results on the portal and need a printed copy", "Academic Records & Results"),
    ("my lecturer has not uploaded the exam venue for my unit", "Timetable & Examinations"),
]

TYPO_MAP = {"the": "teh", "because": "becuase", "registration": "registeration",
            "password": "pasword", "library": "libary", "timetable": "time table"}


SMS = {"please": "pls", "kindly": "kindly", "because": "coz", "thanks": "thanx", "tomorrow": "tmrw",
       "message": "msg", "with": "wit", "you": "u", "your": "ur"}


def sms_style(text: str) -> str:
    words = text.split()
    return " ".join(SMS.get(w.lower().strip(".,"), w) if random.random() < 0.7 else w for w in words)


def unit_code() -> str:
    return f"{random.choice(UNIT_PREFIXES)} {random.randint(1, 4)}{random.randint(100, 399)}"


def noisy(text: str) -> str:
    """Randomly lowercase, strip punctuation or inject a typo to mimic real tickets."""
    r = random.random()
    if random.random() < 0.10:
        text = sms_style(text)
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


def build(samples_per_issue: int = 8):
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
                detail = random.choice(DETAILS)
                if random.random() < 0.12:
                    detail += f" The unit is {unit_code()}."
                text = opener + body + "." + detail + random.choice(CLOSERS)
                text = noisy(" ".join(text.split()))
                if text not in seen:
                    seen.add(text)
                    rows.append({"text": text, "category": category, "issue_id": issue_id, "source": "synthetic-sentence"})
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
