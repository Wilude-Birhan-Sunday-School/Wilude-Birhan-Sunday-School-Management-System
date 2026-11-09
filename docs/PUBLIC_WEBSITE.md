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
| `/` | `index.php` | Home: identity, commitments, class preview, weekly rhythm, notices, contact |
| `/about` | `about.php` | Story, mission, vision, values, milestones, governance, teaching team |
| `/programs` | `programs.php` | Class levels, curriculum subjects, what a Sunday looks like |
| `/schedule` | `schedule.php` | Weekly programme and school office hours |
| `/admissions` | `admissions.php` | Registration steps, requirements, FAQ and the registration request form |
| `/news` | `news.php` | Dated CMS announcements (when published) plus standing notices |
| `/gallery` | `gallery.php` | Public photo gallery, served by `public_gallery.php` |
| `/contact` | `contact.php` | Address, phone, email, hours and the enquiry form |
| `/privacy` | `privacy.php` | What the site collects, publishes and does not publish |
| `/search` | `search.php` | Server-side search over pages, programmes, schedule, notices and FAQ |
| `/sitemap.xml` | `sitemap.php` | XML sitemap of the public pages |
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
| `site_content.php` | All editorial copy as plain PHP data — the only file a non-developer needs to edit for wording |
| `css/wbss-site.css` | Design system: WBSS green `#004820` and gold `#F0B818`, typography, components, responsive and print rules |
| `js/wbss-site.js` | Progressive enhancement: sticky header state, accessible drawer, reveal animation, form submission, back-to-top |
| `css/public_gallery.css`, `js/public_gallery.js` | Existing gallery component, restyled to the WBSS palette for this site |

No external CDN, icon font or web font is loaded. Icons are inline SVG and the
two hero photographs live in `themes/fkss/assets/`. This keeps the site fast on
mobile connections, avoids third-party tracking and keeps it inside the
site's own Content-Security-Policy.

## 3. Content sources

1. **`site_content.php`** — editorial copy, contact details, FAQ, privacy text,
   standing notices and the page index used by navigation, search, footer and
   sitemap.
2. **Existing CMS tables** (read-only): `cms_programs`, `cms_schedule`,
   `cms_teachers`, `cms_social_links`, plus the optional
   `cms_announcements` (`sql/079_public_announcements.sql`). When a CMS table
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
- Per-page title, description, canonical URL, Open Graph/Twitter tags and
  JSON-LD (`EducationalOrganization`, `WebSite` with `SearchAction`,
  `BreadcrumbList`).
- `robots.txt` disallows `/admin/`, `/api/`, `/monitor/`, `/app/`, `/uploads/`
  and `/search`, and points to the sitemap.

## 6. Draft content and what still needs the school's approval

`site_content.php` sets `WBSS_SITE_CONTENT_DRAFT`. While it is `true`, a slim
bar at the top of every page states that contact details and some text are
placeholders. The values flagged `'draft' => true` are:

- office phone `+251 XXX XXX XXX` and email `info@wulidebirhan.org` (both were
  already published on the previous site);
- the parish address lines;
- the admissions requirements list;
- the milestones ("our beginning", "growing structure", "keeping records").

Nothing numeric or personal was invented: there are no pupil counts, exam
results, staff names, fees, founding years or awards in the copy. Replace the
placeholders, confirm the remaining text, then set
`WBSS_SITE_CONTENT_DRAFT` to `false`.

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
