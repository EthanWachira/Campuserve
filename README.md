# CampuServe: first build (about 30% of the system)

Web-based service request and academic scheduling system for BBIT 4D.
Stack follows the proposal: HTML/CSS/JavaScript front end, PHP (MVC-style), MySQL, Python/scikit-learn ML service.

## What is built in this phase

| Area | Included |
|---|---|
| Landing page | Hero with a live classifier demo, process, roles, category-to-department table |
| Authentication | Sign in, student self-registration, sign out, bcrypt hashing, CSRF tokens, session regeneration, lock-out after 5 failed attempts (10 min), audit log, role guards |
| Dashboards | Student, support staff, lecturer, administrator (KPIs, charts, queues) |
| Requests (tickets) | Submit with automatic classification, track with status trail, reply, close/reopen, staff status/assign/internal notes, confirm or correct category (re-routes department), notifications |
| Admin | User list with suspend/reactivate, classifier performance page |
| Database | 10 tables, indexes, foreign keys, demo seed (30 users, 48 requests) |
| ML module | Dataset generator, training with model comparison, honest evaluation, Flask API, keyword fallback when the API is down |

Not yet built (remaining ~70%): appointment booking workflow (tables, seed data and read-only dashboard widgets exist), lecturer availability editing, reports/exports, email notifications, retraining from staff corrections, file attachments, SLA rules.

## Run it on XAMPP

1. Copy this folder to `C:\xampp\htdocs\campuserve`.
2. Start Apache and MySQL in the XAMPP control panel. Make sure `extension=curl` and `extension=mbstring` are enabled in `php.ini` (they are by default).
3. Open phpMyAdmin, import `database/schema.sql`, then `database/seed.sql`.
   (Defaults assume MySQL user `root` with an empty password. Change `config/config.php` or set `CS_DB_USER` / `CS_DB_PASS` if yours differ.)
4. Start the ML service (needs Python 3.10+):
   ```
   cd ml
   pip install -r requirements.txt
   python api.py
   ```
   A trained model is included. To rebuild it: `python generate_dataset.py` then `python train.py`.
5. Browse to http://localhost/campuserve/

If step 4 is skipped the app still works: requests are classified by a keyword fallback and flagged "needs review".

### Demo accounts (password for all: `Password@123`)
| Role | Email |
|---|---|
| Student | 110201@campuserve.test |
| Support staff (ICT) | st1001@campuserve.test (st1003 = Finance, st1004 = Exams, ...) |
| Lecturer | lc2001@campuserve.test |
| Administrator | admin@campuserve.test |

Set `demo_mode` to `false` in `config/config.php` to hide the demo buttons on the sign-in page.

## Architecture

```
Browser (HTML/CSS/JS)  ->  PHP front controller index.php?r=controller/action
                              |- controllers -> models -> MySQL (PDO, prepared statements)
                              `- MLClient (cURL, 3 s timeout) -> Flask API -> scikit-learn pipeline
```

## The ML module, and what to say about its accuracy

* Task: classify a request into 9 categories (each mapped to an owning department) and rate urgency.
* Features: TF-IDF word 1-2 grams plus character 3-5 grams. Three models are compared (logistic regression, calibrated linear SVM, naive Bayes) and the best macro-F1 wins. Probabilities drive the "needs review" flag (below 45%).
* Priority is deliberately rule-based (transparent, auditable), not learned.
* Data is synthetic and university-specific, as the proposal's hybrid dataset describes. Public helpdesk data can be merged with `python train.py --extra data/public.csv` (columns `text,category`); I could not download one in the build environment.
* Evaluation uses **group-wise cross-validation**, so paraphrases of one issue never appear in both train and test. The ordinary random split scores 100% because of near-duplicate sentences; that figure is stored in `metrics.json` only as an optimistic reference and should not be quoted as accuracy. Expect lower numbers on real student tickets.
* Every staff confirmation or correction is stored (`ml_predictions.final_category_id`), which is the labelled data for retraining in the next phase.

## Security notes
Prepared statements everywhere, output escaping, CSRF on every state-changing form, HttpOnly/SameSite cookies, security headers and a CSP, login throttling, 404 (not 403) for tickets a user may not see, per-session rate limit on the public demo endpoint, `.htaccess` blocks direct web access to `config`, `core`, `app`, `database` and `ml`.
Before any real deployment: disable `demo_mode`, set a MySQL password, serve over HTTPS and run the ML API behind the firewall.
