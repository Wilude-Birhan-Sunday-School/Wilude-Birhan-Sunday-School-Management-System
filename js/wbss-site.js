/* Wilude Birhan Sunday School — public website behaviour.
   Progressive enhancement only: every page works without this file, and the
   forms post normally when scripting is unavailable. */
(function () {
    'use strict';

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ── Header shadow on scroll ───────────────────────────────────────── */
    var header = document.querySelector('[data-header]');
    if (header) {
        var setHeaderState = function () {
            header.classList.toggle('is-scrolled', window.scrollY > 8);
        };
        setHeaderState();
        window.addEventListener('scroll', setHeaderState, { passive: true });
    }

    /* ── Mobile drawer ─────────────────────────────────────────────────── */
    var toggle = document.getElementById('nav-toggle');
    var drawer = document.getElementById('mobile-drawer');
    var lastFocused = null;

    function focusable(container) {
        return Array.prototype.slice.call(container.querySelectorAll(
            'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])'
        )).filter(function (el) { return el.offsetParent !== null; });
    }

    function openDrawer() {
        if (!drawer || !toggle) { return; }
        lastFocused = document.activeElement;
        drawer.hidden = false;
        document.body.classList.add('nav-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', toggle.getAttribute('data-label-close') || 'Close menu');
        var items = focusable(drawer);
        if (items.length) { items[0].focus(); }
        document.addEventListener('keydown', onDrawerKeydown);
    }

    function closeDrawer() {
        if (!drawer || !toggle) { return; }
        drawer.hidden = true;
        document.body.classList.remove('nav-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', toggle.getAttribute('data-label-open') || 'Open menu');
        document.removeEventListener('keydown', onDrawerKeydown);
        if (lastFocused && typeof lastFocused.focus === 'function') { lastFocused.focus(); }
    }

    function onDrawerKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDrawer();
            return;
        }
        if (event.key !== 'Tab' || !drawer) { return; }
        var items = focusable(drawer);
        if (!items.length) { return; }
        var first = items[0];
        var last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    if (toggle && drawer) {
        toggle.addEventListener('click', function () {
            if (drawer.hidden) { openDrawer(); } else { closeDrawer(); }
        });
        drawer.addEventListener('click', function (event) {
            if (event.target === drawer) { closeDrawer(); }
        });
        drawer.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (link.getAttribute('href') && link.getAttribute('href').charAt(0) !== '#') {
                    closeDrawer();
                }
            });
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1279 && !drawer.hidden) { closeDrawer(); }
        });
    }

    /* ── Scroll reveal ─────────────────────────────────────────────────── */
    var revealables = document.querySelectorAll('[data-reveal]');
    if (revealables.length) {
        if (reducedMotion || !('IntersectionObserver' in window)) {
            revealables.forEach(function (el) { el.classList.add('is-visible'); });
        } else {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
            revealables.forEach(function (el) { observer.observe(el); });
        }
    }

    /* ── Back to top ───────────────────────────────────────────────────── */
    var toTop = document.getElementById('to-top');
    if (toTop) {
        var setToTop = function () {
            toTop.hidden = window.scrollY < 600;
        };
        setToTop();
        window.addEventListener('scroll', setToTop, { passive: true });
        toTop.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
        });
    }

    /* ── Accordions use native <details>; make the summary keyboard clear ─ */
    document.querySelectorAll('.accordion details').forEach(function (details) {
        details.addEventListener('toggle', function () {
            var summary = details.querySelector('summary');
            if (summary) { summary.setAttribute('aria-expanded', details.open ? 'true' : 'false'); }
        });
    });

    /* ── Public forms (progressive enhancement over the same endpoint) ─── */
    /* Every message is supplied by the page in the visitor's language
       (Amharic or English), so nothing here is hard-coded English. */
    var bodyData = document.body.dataset || {};
    var MSG = {
        required: bodyData.msgRequired || 'Please complete the required fields marked with *.',
        email: bodyData.msgEmail || 'Please check the email address.',
        sending: bodyData.msgSending || 'Sending your request…',
        failed: bodyData.msgFailed || 'We could not send the request. Please try again.',
        joining: bodyData.msgJoining || 'Joining'
    };
    function setStatus(form, message, kind) {
        var box = form.querySelector('[data-form-status]');
        if (!box) { return; }
        box.textContent = message;
        box.className = 'form-status is-' + kind;
        box.hidden = false;
    }

    var forms = document.querySelectorAll('form.site-form');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.fetch || !window.FormData) { return; }

            event.preventDefault();
            var button = form.querySelector('[data-submit]');
            var label = form.querySelector('[data-submit-label]');
            var originalLabel = label ? label.textContent : '';
            var data = new FormData(form);

            // The public endpoint stores one free-text message per lead. The
            // name of the person joining (a child, or an adult registering
            // themselves) is a separate field on the admissions form, so it is
            // folded into the message here — and asked for inside the message in
            // the no-script notice — instead of inventing a new endpoint field.
            var joining = (form.querySelector('[name="child_name"]') || {}).value;
            if (joining && String(joining).trim() !== '') {
                var existing = String(data.get('message') || '').trim();
                data.set('message', MSG.joining + ': ' + String(joining).trim() + (existing ? '\n' + existing : ''));
            }

            form.querySelectorAll('[aria-invalid="true"]').forEach(function (field) {
                field.removeAttribute('aria-invalid');
            });
            var invalid = null;
            form.querySelectorAll('[required]').forEach(function (field) {
                if (invalid) { return; }
                if (String(field.value || '').trim() === '') {
                    invalid = field;
                    field.setAttribute('aria-invalid', 'true');
                }
            });
            if (invalid) {
                setStatus(form, MSG.required, 'error');
                invalid.focus();
                return;
            }
            var email = form.querySelector('[name="email"]');
            if (email && String(email.value || '').trim() !== '' && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value)) {
                email.setAttribute('aria-invalid', 'true');
                setStatus(form, MSG.email, 'error');
                email.focus();
                return;
            }

            if (button) { button.disabled = true; }
            if (label) { label.textContent = MSG.sending; }
            setStatus(form, MSG.sending, 'busy');

            fetch(form.getAttribute('data-endpoint') || form.action, {
                method: 'POST',
                body: data,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            }).then(function (response) {
                var type = response.headers.get('content-type') || '';
                if (type.indexOf('application/json') === -1) { throw new Error('unexpected response'); }
                return response.json();
            }).then(function (payload) {
                if (payload && payload.status === 'success') {
                    setStatus(form, payload.message || MSG.sending, 'success');
                    form.reset();
                    if (label) { label.textContent = label.getAttribute('data-sent-label') || 'Sent'; }
                    window.setTimeout(function () {
                        if (label) { label.textContent = originalLabel; }
                        if (button) { button.disabled = false; }
                    }, 4000);
                } else {
                    setStatus(form, (payload && payload.message) || MSG.failed, 'error');
                    if (button) { button.disabled = false; }
                    if (label) { label.textContent = originalLabel; }
                }
            }).catch(function () {
                setStatus(form, MSG.failed, 'error');
                if (button) { button.disabled = false; }
                if (label) { label.textContent = originalLabel; }
            });
        });
    });

    /* ── Gallery filter/search persistence on the gallery page ─────────── */
    var gallery = document.querySelector('.fkss-gal');
    if (gallery && window.location.hash === '#photos') {
        var grid = document.getElementById('photos');
        if (grid) { window.addEventListener('load', function () { grid.scrollIntoView(); }); }
    }
})();
