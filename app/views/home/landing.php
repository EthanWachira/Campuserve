<section class="hero">
    <div class="hero-inner">
        <div class="hero-copy">
            <h1>Ask once. We route it, track it, and tell you when it is done.</h1>
            <p class="lede">Campusserve sends each student request to the department that handles it, gives it an owner, and keeps a status you can check. One place, instead of five inboxes.</p>
            <div class="hero-cta">
                <?php if (!empty($user)): ?>
                    <a class="btn btn-stub" href="<?= e(url('dashboard/index')) ?>">Open your dashboard</a>
                <?php else: ?>
                    <a class="btn btn-stub" href="<?= e(url('auth/register')) ?>">Create a student account</a>
                    <a class="btn btn-ghost" href="<?= e(url('auth/login')) ?>">Sign in</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="ticket" aria-labelledby="demo-title">
            <div class="ticket-body">
                <h2 id="demo-title">Try it: describe a problem</h2>
                <p class="sub">The same classifier that routes real requests reads what you write. Nothing is saved.</p>
                <div class="chips">
                    <button type="button" class="chip-btn" data-example="My fee payment from M-Pesa is not showing on my statement and I am blocked from registering">Fee payment missing</button>
                    <button type="button" class="chip-btn" data-example="The wifi keeps dropping in the library and I cannot open the eLearning site">Wi-Fi keeps dropping</button>
                    <button type="button" class="chip-btn" data-example="Urgent: my exam clashes with another paper tomorrow morning">Exam clash</button>
                </div>
                <label class="label" for="demo-text">What do you need help with?</label>
                <textarea id="demo-text" data-endpoint="<?= e(url('api/classify')) ?>" maxlength="1500" placeholder="For example: I need an official transcript for a scholarship application."></textarea>
                <p style="margin:12px 0 0"><button type="button" id="demo-go" class="btn btn-primary">Show where it would go</button></p>
            </div>
            <div class="ticket-cut" aria-hidden="true"></div>
            <div class="ticket-result" id="demo-result" aria-live="polite">
                <p class="idle">Your result appears here: the department, the category, and how urgent it looks.</p>
            </div>
        </div>
    </div>
</section>

<section class="band" id="how">
    <div class="band-in">
        <h2>What happens after you press submit</h2>
        <p class="intro">Every request follows the same path, and you see each step in your dashboard.</p>
        <ol class="steps">
            <li><h3>You describe the problem</h3><p>Plain words are enough. No need to know which office handles it.</p></li>
            <li><h3>It is classified and routed</h3><p>A trained text classifier picks the category and the owning department, and rates urgency.</p></li>
            <li><h3>Someone takes ownership</h3><p>The department is notified, assigns a person, and staff confirm or correct the category.</p></li>
            <li><h3>You are told at every change</h3><p>Status updates and replies reach you as notifications until the request is resolved.</p></li>
        </ol>
    </div>
</section>

<section class="band band-white">
    <div class="band-in">
        <h2>Built for the three groups who use it</h2>
        <p class="intro">Each role signs in to a dashboard that shows only what it needs.</p>
        <div class="aud">
            <div>
                <h3>Students</h3>
                <ul>
                    <li>Submit a request and watch it move through its stages</li>
                    <li>Reply to staff, close it when fixed, or reopen it</li>
                    <li>See upcoming lecturer appointments</li>
                </ul>
            </div>
            <div>
                <h3>Lecturers</h3>
                <ul>
                    <li>See requests assigned to you, newest urgent first</li>
                    <li>View your appointment requests and weekly availability</li>
                    <li>Reply to students in one thread</li>
                </ul>
            </div>
            <div>
                <h3>Support staff and administrators</h3>
                <ul>
                    <li>A queue ordered by urgency and age</li>
                    <li>Confirm or correct the automatic category</li>
                    <li>Response times, workload and classifier accuracy</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="band" id="routing">
    <div class="band-in">
        <h2>Where requests go</h2>
        <p class="intro">These are the categories the classifier chooses between, and the department that owns each one.</p>
        <div class="tbl-wrap">
        <table class="route-table">
            <thead><tr><th scope="col">Category</th><th scope="col">Handled by</th></tr></thead>
            <tbody>
            <?php foreach ($routing as $r): ?>
                <tr><td><?= e($r['name']) ?></td><td><?= e($r['department_name']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</section>
