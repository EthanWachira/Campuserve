/* CampuServe front-end. Plain JavaScript, no dependencies, no inline scripts (see the CSP in core/App.php). */
(function () {
    'use strict';

    var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    }

    /* ---- mobile menu ---- */
    document.querySelectorAll('[data-toggle="side"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var side = document.getElementById('side');
            if (side) side.classList.toggle('is-open');
        });
    });

    /* ---- show / hide password ---- */
    document.querySelectorAll('[data-pw]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-pw'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    });

    /* ---- one-click demo accounts on the sign-in page ---- */
    document.querySelectorAll('[data-fill-email]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var email = document.getElementById('email');
            var pw = document.getElementById('password');
            if (email) email.value = btn.getAttribute('data-fill-email');
            if (pw) { pw.value = 'Password@123'; pw.focus(); }
        });
    });

    /* ---- confirm before risky forms ---- */
    document.querySelectorAll('form[data-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
        });
    });

    /* ---- classification preview (landing demo + new request form) ---- */
    function classify(endpoint, text) {
        return fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ text: text })
        }).then(function (r) {
            return r.json().then(function (data) { return { ok: r.ok, data: data }; });
        });
    }

    function renderResult(box, res, compact) {
        box.textContent = '';
        if (!res.ok) {
            box.appendChild(el('p', 'idle', res.data.error || 'Something went wrong. Try again.'));
            return;
        }
        var d = res.data;
        var wrap = el('div', 'stamp is-new');
        wrap.appendChild(el('div', 'to', 'This would go to'));
        wrap.appendChild(el('div', 'dept', d.department));
        var facts = el('div', 'facts');
        facts.appendChild(el('span', 'badge badge-open', d.category));
        facts.appendChild(el('span', 'badge badge-p-' + d.priority, d.priority.charAt(0).toUpperCase() + d.priority.slice(1) + ' priority'));
        facts.appendChild(el('span', 'badge ' + (d.needs_review ? 'badge-review' : 'badge-ok'),
            d.source === 'ml' ? d.confidence + '% sure' : 'Keyword match'));
        wrap.appendChild(facts);
        if (d.needs_review) {
            wrap.appendChild(el('div', 'alt', 'We are not fully sure, so a staff member will confirm the category.'));
        } else if (!compact && d.alternatives && d.alternatives.length) {
            wrap.appendChild(el('div', 'alt', 'Next closest: ' + d.alternatives.map(function (a) { return a.category + ' (' + a.confidence + '%)'; }).join(', ')));
        }
        box.appendChild(wrap);
    }

    /* Landing page demo */
    var demoText = document.getElementById('demo-text');
    if (demoText) {
        var endpoint = demoText.getAttribute('data-endpoint');
        var out = document.getElementById('demo-result');
        var go = document.getElementById('demo-go');
        var run = function () {
            var text = demoText.value.trim();
            go.disabled = true;
            go.textContent = 'Checking';
            classify(endpoint, text).then(function (res) { renderResult(out, res, false); })
                .catch(function () { out.textContent = ''; out.appendChild(el('p', 'idle', 'The classifier could not be reached.')); })
                .then(function () { go.disabled = false; go.textContent = 'Show where it would go'; });
        };
        go.addEventListener('click', run);
        document.querySelectorAll('[data-example]').forEach(function (b) {
            b.addEventListener('click', function () { demoText.value = b.getAttribute('data-example'); run(); });
        });
    }

    /* New request form: live routing preview while typing */
    var desc = document.getElementById('description');
    var preview = document.getElementById('preview');
    if (desc && preview) {
        var ep = preview.getAttribute('data-endpoint');
        var subject = document.getElementById('subject');
        var timer = null;
        var last = '';
        var update = function () {
            var text = ((subject ? subject.value : '') + '. ' + desc.value).trim();
            if (text.length < 25 || text === last) return;
            last = text;
            classify(ep, text).then(function (res) { renderResult(preview, res, true); }).catch(function () {});
        };
        [desc, subject].forEach(function (n) {
            if (!n) return;
            n.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(update, 800); });
        });
    }
})();
