# Public website

**Status of this document:** source-reviewed description of the public site at
the commit that introduced it. It is not a deployment record, a privacy-policy
approval or a claim that the copy was accepted by the school.

The public website is the only unauthenticated HTML surface in this
repository. Everything inside `/admin`, `/api`, `/monitor`, `/frontend` and the
mobile API is separate and keeps its own authentication, roles and CSRF rules.

## 1. Pages and routes

| Clean URL | Handler | Purpose |
|---|---|---|
| `/` | `index.php` | Home: identity, commitments, curriculum, notices with photographs, contact |
| `/about` | `about.php` | Story, mission, vision, values, milestones, governance, teaching team |
| `/programs` | `programs.php` | Curriculum subjects, classes published by the school, what a Sunday looks like |
| `/admissions` | `admissions.php` | Registration steps, requirements, FAQ and the registration request form |
| `/news` | `news.php` | Dated CMS announcements (newest first) and standing notices, each able to carry a photograph |
| `/gallery` | `gallery.php` | Public photo gallery, served by `public_gallery.php` |
| `/contact` | `contact.php` | Address, phone, email, hours and the enquiry form |
| `/privacy` | `privacy.php` | What the site collects, publishes and does not publish |
| `/search` | `search.php` | Server-side search over pages, curriculum subjects, notices and FAQ |
| — | `404.php` | Not-found page (`ErrorDocument 404` in the root `.htaccess`) |

Clean URLs are **not** a generic rewrite. Each slug is an explicit entry in
`App\Services\BrowserPageRoutes::PAGES`
([file](../admin/backend/services/BrowserPageRoutes.php)), and the root
`.htaccess` dispatch rule lists the same slugs. A new page therefore requires
both entries plus a test row in
[`tests/security/test_public_website.py`](../tests/security/test_public_website.py).
Direct requests to `/about.php` and the other handlers are redirected to their
clean URL for document navigation by `BrowserPageRoutes::navigationRedirect`.

## 2. Files

| Path | Role |
|---|---|
| `index.php` and the page files | Markup for each page; each sets a `$page` array (slug, title, description, breadcrumbs) and calls the shared layout |
| `public_site.php` | Bootstrap and layout: content loading, read-only CMS access, SEO/JSON-LD head, header, drawer, breadcrumbs, call to action, footer, shared form and contact cards. Refuses direct requests |
| `site_content.php` | All editorial copy as plain PHP data, written as `L('English', 'አማርኛ')` pairs — the only file a non-developer needs to edit for wording |
| `site_lang.php` | Language layer: `site_lang()` (Amharic by default), `L()` pairs, `t()` resolution, `cms_text()` for CMS rows and `site_lang_url()` for the switch |
| `css/wbss-site.css` | Design system: WBSS green `#004820` and gold `#F0B818`, typography, components, responsive and print rules |
| `js/wbss-site.js` | Progressive enhancement: sticky header state, accessible drawer, reveal animation, form submission, back-to-top |
| `css/wbss-motion.css`, `js/wbss-motion.js` | Motion layer: scroll reveals, header slide, reading progress, popup, photo lightbox, accordion height animation. Additive only — see §5a |
| `css/public_gallery.css`, `js/public_gallery.js` | Existing gallery component, restyled to the WBSS palette for this site |

No external CDN, icon font or web font is loaded. Icons are inline SVG and the
photographs live in `themes/fkss/assets/` (`wbss-hero-courtyard.jpg`,
`wbss-teaching-room.jpg`, `wbss-mezmur-practice.jpg`). They show Ethiopian
Orthodox parish life — a round thatched church with frescoes, netela shawls, a
hand cross and a kebero drum — and contain no baked-in lettering, so the school
name is always set as HTML text rather than as picture text. This keeps the site fast on
mobile connections, avoids third-party tracking and keeps it inside the
site's own Content-Security-Policy.

## 2a. Languages — Amharic first, English one click away

The site is fully bilingual and **Amharic is the default**: a first-time visitor
gets Amharic, and the `አማ / EN` control in the header (and inside the mobile
drawer) switches to English.

- `?lang=am` / `?lang=en` are the only accepted values; anything else falls back
  to Amharic. The choice is remembered in the first-party cookie `wbss_lang`
  for a year (SameSite=Lax), never in a session and never server-side.
- Every visible string is a pair written with `L('English', 'አማርኛ')` and printed
  through `t()`. Interface text (buttons, labels, `aria-label`s, form
  messages) lives in the `ui` dictionary of `site_content.php`. When an Amharic
  value is missing, `t()` shows the English text rather than an empty element.
- `<html lang>` follows the visitor, `hreflang` alternates (`am`, `en`,
  `x-default`) and the Open Graph locale are declared on every page, and the
  JSON-LD `inLanguage` matches.
- CMS rows are read with `cms_text()`, which prefers `title_am` / `body_am`
  style columns in Amharic and the plain column in English.
- Search indexes **both** languages, so an Amharic query finds English content
  and the matched sentence is shown in the language it was written in.
- The registration endpoint answers in the language the form was submitted in
  (`lang` field); anything else gets the English message it has always sent.
- Typography: `--font-am` (Noto Serif Ethiopic → Nyala → Abyssinica SIL →
  Ebrima) with `html[lang="am"]` rules that add leading, never uppercase, and
  cap heading sizes — Ethiopic sets wider than Latin. No web font is loaded, so
  the site still has no third-party request.
- Copy is deliberately short: one lead line per page and one sentence per card.
  Long explanatory paragraphs are what make a school website look
  unprofessional, and they double the translation burden.

## 3. Content sources

1. **`site_content.php`** — editorial copy, contact details, FAQ, privacy text,
   standing notices and the page index used by navigation, search, footer and
   sitemap.
2. **Existing CMS tables** (read-only): `cms_programs`, `cms_teachers`,
   `cms_social_links`, plus the optional `cms_announcements`
   (`sql/079_public_announcements.sql`, photographs via
   `sql/080_announcement_images.sql`). When a CMS table
   has no active rows the site falls back to the standard structure in
   `site_content.php`, so no page is ever empty.
3. **Photos** — `public_gallery.php` (`action=boot|albums|photos|featured`).
   That service already returns only active photos and no member personal
   information; the redesign did not change its contract.

Every CMS read is a fixed `SELECT`; no request value reaches SQL, and every
stored value is printed through `e()`.

## 4. Forms

Both public forms post to the existing `register_submit.php` handler. The
redesign did not add a second write path:

- the handler keeps its honeypot, `LedgerValidation` checks, phone/email
  validation, age range 4–18 and `SecurityRateLimiter` limit of five
  submissions per hour per address;
- submissions are stored in `cms_registration_submissions` and are **leads**,
  not member accounts and not enrolment;
- the admissions form's separate "child's name" field is folded into the
  free-text `message` by the browser (and requested inside the message in the
  no-JavaScript notice) because the endpoint has no `child_name` parameter;
- without JavaScript both forms still submit and render the handler's JSON
  confirmation as plain text.

## 5. Accessibility, SEO and performance

- Skip link, visible focus rings, one `h1` per page, labelled form controls,
  `aria-expanded`/`aria-controls` on the menu button, Escape and focus-trap in
  the drawer, `prefers-reduced-motion` support and print styles.
- Reveal animations are gated on a `js` class set by an inline script, so a
  scripting failure can never leave content invisible.
- The popup and the lightbox are `role="dialog" aria-modal="true"` sheets with
  a labelled close button, Escape to dismiss, a focus trap while open and focus
  returned to the element that opened them. Photographs are reachable by
  keyboard (`role="button" tabindex="0"`), and the lightbox counter is announced.
- axe-core (WCAG 2.1 A/AA) reports no violations on the ten public pages in both
  languages, with the popup and the lightbox open. The two best-practice
  advisories that remain — a top bar outside a landmark and an `h3` that follows
  an `h1` on three pages — predate this work and are unchanged.
- Per-page title, description, canonical URL, Open Graph/Twitter tags and
  JSON-LD (`EducationalOrganization`, `WebSite` with `SearchAction`,
  `BreadcrumbList`).
- Layout gutters belong to `.wrap`; hero and call-to-action bands set
  `padding-block` only, so text keeps its 16 px gutter down to 320 px wide.
  There is no horizontal scrolling at 320, 360, 390, 430, 768 or 1024 px in
  either language.
- `robots.txt` disallows `/admin/`, `/api/`, `/monitor/`, `/app/`, `/uploads/`
  and `/search`. It advertises no sitemap: the XML sitemap and its
  `/sitemap.xml` route were withdrawn at the school's request, and the footer
  no longer links to one.
- Announcement photographs are restricted to same-site, root-relative image
  paths (no scheme, no host, no `..`, image extension required); anything else
  renders nothing.

## 5a. Motion

Movement is decoration, so the whole layer is additive: the CSS hides nothing
until the `js` class proves the script is running, and
`prefers-reduced-motion: reduce` restores a still page with every word visible.
Nothing new is downloaded — no animation library, no web font, no CDN.

| Where | What happens |
|---|---|
| Every page | Sections and cards fade/slide in once as they reach the viewport, staggered within a row. `reveal('up', $index)` in the templates; the helper only emits the five variants the stylesheet defines (`up`, `left`, `right`, `zoom`, `pop`) |
| Page heroes | The eyebrow, title and lead rise in sequence; the home hero photograph zooms slowly and drifts a little on scroll (transform only) |
| Top of the page | A gold reading-progress bar follows the scroll position; the header slides away when reading down and returns on the first upward scroll |
| Home | The welcome sheet (`popup`) appears once per session, 9 s after load, and never over an open menu or lightbox |
| News | Photographs open in a keyboard-operable lightbox: Enter or Space opens, arrows step through the set, Escape closes, and focus returns to the photograph |
| Admissions | The FAQ answers animate open instead of snapping |
| Menu | The mobile drawer slides in and its items follow, one behind the other |
| Everywhere | Small state changes — buttons, card hover, form feedback, link arrows — use the same 150–450 ms easing curves |

Two rules keep it honest: transforms and opacity only on scroll (no layout
properties, so a school phone keeps its frame rate), and no element is wider
than the page after its slide — sideways reveals live inside
`overflow-x: clip` sections, and nothing scrolls horizontally at 320–1024 px in
either language.

The strings the motion layer adds — close, photo viewer, previous/next photo —
are bilingual pairs in `site_content.php` like every other word on the site.

## 6. Published copy and the remaining office input

`site_content.php` sets `WBSS_SITE_CONTENT_DRAFT` to `false`; the former
site-wide warning bar has been removed from the layout, the stylesheet and the
script, and the copy is published as approved by the school office.

Items still flagged `'draft' => true` inside `site_content.php` are an internal
editorial note only — nothing on the page reads them:

- the office phone number, which is intentionally **empty** rather than a
  placeholder: `+251 XXX XXX XXX` is no longer published, and every surface
  that would print a phone number hides itself while the value is empty;
- the email address `info@wulidebirhan.org` and the parish address lines;
- the milestones ("our beginning", "growing structure", "keeping records").

Send the office phone (and any corrections) to the developer and the value is
published without a code change elsewhere. Nothing numeric or personal is
invented: there are no pupil counts, exam results, staff names, fees, founding
years or awards in the copy.

The privacy page describes what the code actually does (leads are stored,
gallery returns no member information, no analytics scripts are loaded). It is
**not** an approved privacy policy and says so on the page; the school and
parish must review it.

## 7. Publishing announcements

Apply `sql/079_public_announcements.sql` (additive, idempotent) and insert rows
with `title`, optional `title_am`, `body`, optional `link_url`/`link_label` and
`published_on`. Dated rows appear on `/news` and on the home page, newest
first, and are searchable. `link_url` is rendered only when it is a same-site
root-relative path. The website keeps working before the migration is applied
and if the table is removed.

There is no announcement editor in the admin dashboards yet; rows are inserted
through phpMyAdmin or a future CMS screen.

## 8. Verification boundary

- Static contracts live in
  [`tests/security/test_public_website.py`](../tests/security/test_public_website.py).
  They always run and never skip, because the security suite fails on any skip.
- The live render check is
  [`tests/e2e/public_website_render.py`](../tests/e2e/public_website_render.py),
  run deliberately against a provisioned site:
  `WBSS_SITE_BASE=http://127.0.0.1:8089 python3 tests/e2e/public_website_render.py`.
- What this documentation cannot claim: real Apache/.htaccess behaviour, a
  database-backed render, the gallery with real photos, form delivery against a
  live database and mail, search-engine indexing, screen-reader testing on real
  devices, or the school's approval of the copy and contact details.
