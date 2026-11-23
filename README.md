# Campuserve: A Web-Based System for Integrated Service Request and Academic Scheduling

Web-based service request and academic scheduling system for BBIT 4D.
Stack follows the proposal: HTML/CSS/JavaScript front end, PHP (MVC-style), MySQL, Python/scikit-learn ML service.

## What is built in this phase

| Area | Included |
|---|---|
| Landing page | Hero with a live classifier demo, process, roles, category-to-department table |
| Authentication | Sign in, student self-registration, sign out, bcrypt hashing, CSRF tokens, session regeneration, lock-out after 5 failed attempts (10 min), audit log, role guards |
| Dashboards | Student, support staff, lecturer, administrator (KPIs, charts, queues) |
| Requests (tickets) | Submit with automatic classification, track with status trail, reply, close/reopen, staff status/assign/internal notes, confirm or correct category (re-routes department), notifications |
| Appointments | Students book a free slot with a lecturer, lecturers confirm, decline or cancel and edit weekly availability, both sides are notified, double-booking is prevented |
| Admin | User list with suspend/reactivate, classifier performance page |
| Database | 10 tables, indexes, foreign keys, demo seed (30 users, 48 requests) |
| ML module | Dataset generator, training with model comparison, honest evaluation, Flask API, keyword fallback when the API is down |

### Added after the first prototype
| Area | Included |
|---|---|
| Email | Every notification is also emailed (queued, retried, per-user opt-out, admin on/off switch); SMTP or "log" mode; admin outbox with retry and test email |
| Passwords | Forgot password (one-use link, 60 min, throttled, generic reply), change password on the profile page, admin "send reset link", new users get a link to choose their own password |
| Reports and analytics | Date/category/department filters, KPIs, trend, status, priority, category, department, workload per person, busiest hours, appointments by lecturer; CSV export (requests, summary, appointments) and print / save as PDF |
| Service targets (SLA) | Hours per priority (editable), Due soon / Overdue badges, overdue KPI, one-time alert to staff and admins when a target is missed, "resolved within target" rate |
| Reminders | Appointment reminders to both people about a day and an hour ahead |
| Background jobs | Run automatically (at most every 10 minutes) when someone opens the dashboard; optional `cron/run_jobs.php` for a schedule |
| Profile | Name, programme and year, email opt-out, change password |
| Admin | Add/edit users, send reset links, Settings page, Categories and Departments management, audit log viewer, email outbox |
| ML feedback loop | Staff confirmations and corrections are stored as labelled examples. **Retrain now** (Classifier > Retrain) adds them to the original dataset, retrains, compares old and new on held-out requests, archives the old model and switches the live service to the new one with no restart. Optional automatic retraining after N new corrections. Dashboard shows examples, accuracy before and after, version history and categories learned. |

## Upgrading an existing database
Run these in phpMyAdmin (database `campuserve` -> SQL tab), in this order. Fresh installs only need `schema.sql` then `seed.sql`.
1. `database/add_email_and_reset.sql`
2. `database/add_phase2_5.sql`
3. `database/add_ml_runs.sql`
4. `database/add_auto_assign.sql`
5. `database/add_ml_retraining.sql` (needed for the retraining dashboard)
6. `database/add_ticket_attachments.sql` (request attachments)
7. `database/add_dob_age.sql` (date of birth and automatic age)

## Email setup
Default mail mode is `log`: emails are written to `storage/mail/` so you can open reset links without a mail server.
For real email set `CS_MAIL_DRIVER=smtp`, `CS_SMTP_HOST`, `CS_SMTP_PORT`, `CS_SMTP_USER`, `CS_SMTP_PASS`, `CS_MAIL_FROM` (or edit `config/config.php`). Gmail needs an App Password. Set `app_url` in `config/config.php` to the real address so email links work. Use Settings > Email outbox > "Send me a test email" to check it.

## Optional schedule (Windows Task Scheduler, every 10 minutes)
`C:\xampp\php\php.exe C:\xampp\htdocs\Campuserve\cron\run_jobs.php`

## Run it on XAMPP

1. Copy this folder to `C:\xampp\htdocs\Campuserve`.
2. Start Apache and MySQL in the XAMPP control panel. Make sure `extension=curl` and `extension=mbstring` are enabled in `php.ini` (they are by default).
3. Open phpMyAdmin, import `database/schema.sql`, then `database/seed.sql` (existing install? see "Upgrading an existing database" below).
   (Defaults assume MySQL user `root` with an empty password. Change `config/config.php` or set `CS_DB_USER` / `CS_DB_PASS` if yours differ.)
4. Start the ML service (needs Python 3.10+):
   ```
   cd ml
   pip install -r requirements.txt
   python api.py
   ```
   A trained model is included. To rebuild it: `python generate_dataset.py` then `python train.py`.
5. Browse to http://localhost/Campuserve/

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


## Continuous learning (retraining from staff feedback)
Student submits request -> model predicts category -> staff confirm or correct it -> the answer is stored as a labelled example -> **Retrain now** -> new model compared with the old -> deployed live -> future predictions use it.

* Code: `ml/retrain.py` (training and honest evaluation), `POST /retrain` and `/reload` in `ml/api.py`, `app/services/MLTrainer.php`, `FeedbackController` (retrain, auto-retrain settings), sections 5 on the Classifier page.
* Tables: `ml_models` (one row per retraining: examples, accuracy before and after, version, categories learned) and `ml_feedback_used` (which request taught which model). Run `database/add_ml_retraining.sql`.
* Fair measurement: every 4th staff-labelled request is held out and never trained on. The previous and new models are both scored on those requests and on the original synthetic hold-out. A model that is worse is rejected (tick "Deploy even if accuracy drops" to override). The previous model is kept in `ml/model/archive/`.
* Staff-labelled examples are repeated 3 times in training because real student wording is rarer than generated wording.
* Automatic retraining: Retraining page > "Retrain automatically once N new staff corrections have been collected" (runs with the background jobs).
