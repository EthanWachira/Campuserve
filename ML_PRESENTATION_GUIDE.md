# Presenting the machine learning module

Based on your supervisor's guide: a labelled historical dataset -> the machine is trained to find patterns in it -> the trained model is integrated into the application -> it classifies the records in your database.

## Before you present (10 minutes, do it tonight)
1. Start XAMPP: Apache and MySQL green.
2. In phpMyAdmin, SQL tab, run `database/add_ml_runs.sql` (creates the two tables that record each classification run).
3. Copy `app/controllers/AdminController.php` and `app/views/admin/ml.php` into your project, replacing the old ones. Save.
4. In the `ml` folder: `py api.py` and leave it running.
5. Sign in as `admin@campuserve.test` / `Password@123`, open Classifier in the sidebar, click **Classify all records now** once, so a run already exists if something fails tomorrow.
6. Run `py watch_training.py` in `ml` and keep the charts in `ml/model/figures/` open as a backup.
7. Check the app in a second browser window signed in as a student (`kevin.omondi@campuserve.test`).

## The four steps, mapped to what you show

### 1. Identify and describe the historical dataset
Show: Classifier page, section 1.
Say: "The dataset has 3,720 labelled university service requests. Each record has four attributes: the request text, the category it belongs to, an issue id that groups paraphrases, and a source tag. There are nine categories, about 400 records each, so the classes are balanced."
Be upfront: "I could not access Strathmore's own records for privacy and access reasons, so I constructed this dataset from real university processes: fee statements and M-Pesa payments, HELB loans, CATs, supplementary exams, attachment, hostels. My plan to replace it with real data is a student survey and the staff corrections the system already records."

### 2. Train the model on the dataset
Show: section 2, then the charts from `watch_training.py` (learning in action, model comparison, top words).
Say: "Each request is converted to numbers with TF-IDF. I compared four models by cross-validation: a baseline that always guesses the biggest category (11%), naive Bayes (80%), logistic regression (88%) and a linear SVM (89%). The SVM won and was calibrated so it outputs probabilities. The test uses issue types the model never saw, so paraphrases of one problem never sit in both the training and test sets."

### 3. Integrate the model into the application
Show: section 3, type a request into "Try it now"; then submit a real request as a student and show it routed.
Say: "The trained model runs as a small Flask service. When a student submits a request, the PHP application sends the text to it and receives the category, a confidence, the department and a priority. If confidence is below 45%, staff are asked to check it. If the service is down, the application falls back to keyword matching so nobody is blocked."

### 4. It classifies the records in the database
Show: section 4 and click **Classify all records now**.
Say: "The application has 48 requests stored. This button sends every one through the trained model and stores the result with the model version, confidence and time. It classified all 48 in under a second, with 98% agreement with the stored category."
Point at the one row marked Differs: request CS-2026-000043, "I cannot find my class timetable", was filed under Admissions & Registration. The model says Timetable & Examinations at 99.6% confidence, so it caught a request that had been mis-filed.
Be accurate: "Agreement is high partly because the demo records use the same kinds of wording as the training data, so it is a consistency check, not independent validation."

## Questions to expect, and honest answers
- **Is this real historical data?** No. It is a constructed labelled dataset, because institutional records were not available. The pipeline is the real thing, and the plan is to add survey data and staff-corrected predictions.
- **How accurate is it?** About 89% on unseen issue types in the synthetic data. Expect lower on real student text. An independent test on a public student-portal FAQ set is prepared (`prepare_external.py`) and I will report it separately.
- **Why a linear SVM and not deep learning?** Short texts, a small dataset and a need to explain predictions. A linear model trains in seconds, gives probabilities after calibration, and I can show the words that drive each category.
- **What happens when it is wrong?** Staff confirm or correct the category, the request is re-routed, and the correction is stored as a labelled example for retraining.
- **What is not machine learning?** Priority uses transparent keyword rules, deliberately.
- **What next?** Retraining from staff corrections, a student survey for real data, a priority model, and demand forecasting for appointments.

## If the demo breaks
- Page says "Offline": start `py api.py`. The app still works with the keyword fallback.
- Classify all fails: show the existing run from step 5 above, plus the charts.
- Database error page: check MySQL is green in XAMPP.
