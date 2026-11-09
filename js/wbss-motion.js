/* Wilude Birhan Sunday School — motion behaviour.
   ---------------------------------------------------------------------------
   Enhancement only. Every page works without this file; it adds scroll
   reveals, the sticky-header slide, the reading-progress bar, the popup, the
   photo lightbox and animated accordions. Everything is skipped when the visitor prefers reduced motion. */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var hasObserver = 'IntersectionObserver' in window;
    var body = document.body;

    /* ── Stagger indexes ───────────────────────────────────────────────── */
    function staggerGroups() {
        var selector = '.grid, .highlights, ol.steps, ol.timeline, .contact-cards, .news-list, .site-footer__grid';
        Array.prototype.forEach.call(document.querySelectorAll(selector), function (group) {
            Array.prototype.forEach.call(group.children, function (child, index) {
                if (child.hasAttribute('data-reveal') || child.querySelector('[data-reveal]')) {
                    child.style.setProperty('--i', Math.min(index, 8));
                    if (!child.hasAttribute('data-reveal') && /^(LI|ARTICLE|DIV)$/.test(child.tagName)) {
                        child.setAttribute('data-reveal', 'up');
                    }
                }
            });
        });
    }

    /* ── 1. Page ready: hero entrance ──────────────────────────────────── */
    requestAnimationFrame(function () {
        body.classList.add('is-ready');
    });

    /* ── 2. Scroll reveals ─────────────────────────────────────────────── */
    staggerGroups();
    var revealObserver = null;
    if (hasObserver && !reduceMotion) {
        revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
    }

    function observeReveals(elements) {
        Array.prototype.forEach.call(elements, function (el) {
            if (revealObserver) { revealObserver.observe(el); }
            else { el.classList.add('is-visible'); }
        });
    }

    observeReveals(document.querySelectorAll('[data-reveal]'));

    /* ── 3. Sticky header that makes way when reading down ─────────────── */
    var header = document.querySelector('[data-header]');
    var lastY = window.scrollY;
    var ticking = false;

    function onScroll() {
        var y = window.scrollY;

        if (header) {
            header.classList.toggle('is-scrolled', y > 8);
            if (!reduceMotion) {
                var goingDown = y > lastY + 4;
                var goingUp = y < lastY - 4;
                if (goingDown && y > 260 && !body.classList.contains('nav-open')) {
                    header.classList.add('is-hidden');
                } else if (goingUp || y <= 120) {
                    header.classList.remove('is-hidden');
                }
            }
        }

        var bar = document.getElementById('scroll-progress');
        if (bar) {
            var doc = document.documentElement;
            var max = (doc.scrollHeight - window.innerHeight) || 1;
            var ratio = Math.min(1, Math.max(0, y / max));
            bar.style.transform = 'scaleX(' + ratio.toFixed(4) + ')';
        }

        // Gentle parallax on the home hero, wide screens only.
        var heroMedia = document.querySelector('.hero__media');
        if (heroMedia && !reduceMotion && window.innerWidth > 900 && y < window.innerHeight) {
            heroMedia.style.transform = 'translate3d(0,' + (y * 0.06).toFixed(2) + 'px,0)';
        }

        lastY = y;
        ticking = false;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(onScroll);
        }
    }, { passive: true });
    onScroll();

    /* ── 4. Modal plumbing shared by the popup and the lightbox ────────── */
    var openModal = null;
    var lastFocused = null;

    function focusables(container) {
        return Array.prototype.slice.call(container.querySelectorAll(
            'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])'
        )).filter(function (el) { return el.offsetParent !== null; });
    }

    function openSheet(modal, opener) {
        if (!modal || openModal) { return; }
        lastFocused = opener || document.activeElement;
        modal.hidden = false;
        body.classList.add('modal-open');
        openModal = modal;
        var items = focusables(modal);
        if (items.length) { items[1] && modal.querySelector('[data-modal-close]') ? modal.querySelector('[data-modal-close]').focus() : items[0].focus(); }
        document.addEventListener('keydown', onModalKeydown);
    }

    function closeSheet(modal) {
        var sheet = modal || openModal;
        if (!sheet) { return; }
        sheet.hidden = true;
        body.classList.remove('modal-open');
        openModal = null;
        document.removeEventListener('keydown', onModalKeydown);
        if (lastFocused && typeof lastFocused.focus === 'function') { lastFocused.focus(); }
    }

    function onModalKeydown(event) {
        if (!openModal) { return; }
        if (event.key === 'Escape') {
            event.preventDefault();
            closeSheet(openModal);
            return;
        }
        if (event.key !== 'Tab') { return; }
        var items = focusables(openModal);
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

    document.addEventListener('click', function (event) {
        var closer = event.target.closest('[data-modal-close]');
        if (closer) {
            event.preventDefault();
            closeSheet(closer.closest('.modal'));
            return;
        }
        if (event.target.classList && event.target.classList.contains('modal')) {
            closeSheet(event.target);
        }
    });

    /* ── 5. Popup: once per session, dismissible, never a trap ─────────── */
    var popup = document.querySelector('[data-popup]');
    if (popup) {
        var key = popup.getAttribute('data-store-key') || 'wbss-popup';
        var delay = parseInt(popup.getAttribute('data-delay') || '9000', 10);
        var seen = false;
        try { seen = window.sessionStorage.getItem(key) === '1'; } catch (error) { seen = false; }
        if (!seen) {
            window.setTimeout(function () {
                if (openModal || body.classList.contains('nav-open')) { return; }
                openSheet(popup, null);
                try { window.sessionStorage.setItem(key, '1'); } catch (error) { /* private mode */ }
            }, Math.max(1500, delay));
        }
    }

    /* ── 6. Photo lightbox ─────────────────────────────────────────────── */
    var lightbox = document.querySelector('[data-lightbox-modal]');
    if (lightbox) {
        var lightboxImage = lightbox.querySelector('[data-lightbox-img]');
        var lightboxCaption = lightbox.querySelector('[data-lightbox-caption]');
        var lightboxCounter = lightbox.querySelector('[data-lightbox-counter]');
        var triggers = [];
        var index = 0;

        // The set is read when a photograph is opened, and only photographs
        // the visitor can actually see take part — a hidden container (the
        // offline preview holds several pages at once) never inflates the
        // counter or sends Next to another page.
        var visibleTriggers = function () {
            return Array.prototype.slice
                .call(document.querySelectorAll('[data-lightbox]'))
                .filter(function (el) { return el.getClientRects().length > 0; });
        };

        var paint = function () {
            var trigger = triggers[index];
            if (!trigger) { return; }
            var image = trigger.querySelector('img');
            var src = trigger.getAttribute('data-src') || (image && image.getAttribute('src')) || '';
            if (lightboxImage) {
                lightboxImage.setAttribute('src', src);
                lightboxImage.setAttribute('alt', (image && image.getAttribute('alt')) || '');
            }
            if (lightboxCaption) {
                lightboxCaption.textContent = trigger.getAttribute('data-caption') || (image && image.getAttribute('alt')) || '';
            }
            if (lightboxCounter) {
                lightboxCounter.textContent = (index + 1) + ' / ' + triggers.length;
            }
        };

        var move = function (step) {
            if (triggers.length < 2) { return; }
            index = (index + step + triggers.length) % triggers.length;
            paint();
        };

        var openLightbox = function (trigger) {
            triggers = visibleTriggers();
            var position = triggers.indexOf(trigger);
            if (position < 0 || !triggers.length) { return; }
            index = position;
            paint();
            openSheet(lightbox, trigger);
        };

        // The photographs are focusable buttons, so Enter and Space open them.
        Array.prototype.forEach.call(document.querySelectorAll('[data-lightbox]'), function (trigger) {
            if (!trigger.hasAttribute('tabindex') && trigger.tagName !== 'BUTTON' && trigger.tagName !== 'A') {
                trigger.setAttribute('tabindex', '0');
                trigger.setAttribute('role', 'button');
            }
        });

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest && event.target.closest('[data-lightbox]');
            if (!trigger) { return; }
            event.preventDefault();
            openLightbox(trigger);
        });

        document.addEventListener('keydown', function (event) {
            var focused = event.target.closest && event.target.closest('[data-lightbox]');
            if (focused && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault();
                openLightbox(focused);
                return;
            }
            if (openModal !== lightbox) { return; }
            if (event.key === 'ArrowLeft') { move(-1); }
            if (event.key === 'ArrowRight') { move(1); }
        });

        var prev = lightbox.querySelector('[data-lightbox-prev]');
        var next = lightbox.querySelector('[data-lightbox-next]');
        if (prev) { prev.addEventListener('click', function () { move(-1); }); }
        if (next) { next.addEventListener('click', function () { move(1); }); }
    }

    /* ── 7. Accordions: animate the height instead of snapping ─────────── */
    if (!reduceMotion) {
        Array.prototype.forEach.call(document.querySelectorAll('.accordion details'), function (details) {
            var panel = details.querySelector('p');
            var summary = details.querySelector('summary');
            if (!panel || !summary) { return; }

            var settle = function () {
                panel.style.height = '';
                panel.style.opacity = '';
            };

            panel.style.height = details.open ? '' : '0px';
            panel.style.opacity = details.open ? '' : '0';

            summary.addEventListener('click', function (event) {
                event.preventDefault();
                if (details.open) {
                    panel.style.height = panel.scrollHeight + 'px';
                    panel.style.opacity = '1';
                    void panel.offsetHeight;
                    panel.style.height = '0px';
                    panel.style.opacity = '0';
                    window.setTimeout(function () { details.open = false; settle(); }, 300);
                } else {
                    details.open = true;
                    var target = panel.scrollHeight;
                    panel.style.height = '0px';
                    panel.style.opacity = '0';
                    void panel.offsetHeight;
                    panel.style.height = target + 'px';
                    panel.style.opacity = '1';
                    window.setTimeout(settle, 320);
                }
            });
        });
    }

    /* ── 8. Counters: <span data-count-to="12"> ────────────────────────── */
    var counters = document.querySelectorAll('[data-count-to]');
    if (counters.length) {
        var runCounter = function (el) {
            var target = parseFloat(el.getAttribute('data-count-to')) || 0;
            if (reduceMotion || !window.requestAnimationFrame) {
                el.textContent = String(target);
                return;
            }
            var started = null;
            var duration = 1100;
            var step = function (now) {
                if (started === null) { started = now; }
                var progress = Math.min(1, (now - started) / duration);
                var eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = String(Math.round(target * eased));
                if (progress < 1) { window.requestAnimationFrame(step); }
            };
            window.requestAnimationFrame(step);
        };
        if (hasObserver && !reduceMotion) {
            var counterObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        runCounter(entry.target);
                        counterObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.6 });
            Array.prototype.forEach.call(counters, function (el) { counterObserver.observe(el); });
        } else {
            Array.prototype.forEach.call(counters, runCounter);
        }
    }
    /* ── 9. Gallery: items arrive from the API, so reveal them as they land ─ */
    var galleryGrid = document.querySelector('[data-gal="grid"]');
    if (galleryGrid && 'MutationObserver' in window) {
        var markGalleryItems = function () {
            Array.prototype.forEach.call(galleryGrid.children, function (child, index) {
                if (child.nodeType !== 1 || child.hasAttribute('data-reveal')) { return; }
                child.setAttribute('data-reveal', 'pop');
                child.style.setProperty('--i', Math.min(index, 8));
                observeReveals([child]);
            });
        };
        new MutationObserver(markGalleryItems).observe(galleryGrid, { childList: true });
        markGalleryItems();
    }
})();
