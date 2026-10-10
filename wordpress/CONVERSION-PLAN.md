# Conversion plan: wi-ra.org (static design → WordPress)

## Route

**Child theme of Hello Elementor + one small site plugin.**

- `wra-child` (theme): every page is a PHP template generated from the approved
  static design (`_build/pages/*.html` + `_build/partials/*`), so the markup and
  CSS are identical to what Corey signed off. Elementor and Hello's own styles are
  switched off on the front end so nothing can repaint the design.
- `wra-site` (plugin): the things that must survive a theme change: the ARCS
  status setting, the form handler and stored messages, the post byline field,
  and the old-URL redirects.
- The static site stays the single source of truth for page design.
  `_build/wp_build.py` regenerates the theme from it; never hand-edit the
  generated `page-*.php` files.

## Environment

| Item | Value |
| --- | --- |
| Host | Microsoft Azure App Service (WordPress on App Service) |
| WordPress / PHP | WordPress 7.1.3, PHP 8.4, nginx |
| Theme today | Hello Elementor (classic theme, folder `hello-elementor`) |
| Plugins today | Elementor 4.3.4, Elementor Pro, Google Site Kit, App Service Email |
| Mail | `app_service_email` (Azure Communication Services) handles `wp_mail()` |
| Build/test | Local first (WordPress Playground, no Docker needed). Then **staging on our own WordPress (temporary domain)** for Corey's review. Once approved, **migrated to wi-ra.org with All-in-One WP Migration Pro** (GO-LIVE.md). Nothing is changed on the live site without asking. |
| Install method | Theme + plugin uploaded as `.zip` in wp-admin; a one-time setup script (`deploy/setup.php`) run with WP-CLI or the bundled one-click admin action |

## Pages

Existing live pages keep their URLs, so nothing indexed today breaks.

| Static source | WordPress page | URL | Template |
| --- | --- | --- | --- |
| index.html | Home (id 22, front page) | `/` | `front-page.php` |
| about.html | About WRA (376) | `/about/` | `page-about.php` |
| coordination.html | What Is Frequency Coordination? (380) | `/frequency-coordination/` | `page-frequency-coordination.php` |
| coordination-process.html | Repeater Coordination (384) | `/repeater-coordination/` | `page-repeater-coordination.php` |
| arcs.html | ARCS (386) | `/arcs/` | `page-arcs.php` |
| membership.html | Membership (388) | `/membership/` | `page-membership.php` |
| resources.html | **new** Resources | `/resources/` | `page-resources.php` |
| news.html | News & Announcements (406) | `/news/` | `page-news.php` (lists posts) |
| contact.html | Contact Us (396) | `/contact/` | `page-contact.php` |
| privacy.html | Privacy Policy (reuses WP's draft page if present) | `/privacy-policy/` | `page-privacy-policy.php` |
| terms.html | **new** Terms of Use | `/terms-of-use/` | `page-terms-of-use.php` |
| 404.html | — | any missing URL | `404.php` |
| news-*.html | the 5 existing posts | `/%postname%/` (unchanged) | `single.php` |

Any page the team adds later falls back to `page.php` (title + editor content in
the site design).

## Editable content (what Corey's team changes in wp-admin)

Per Corey: "Only part of this website that will ever need to change is news,"
plus the ARCS status he asked about.

| Content | Where in wp-admin | Fields | Appears on |
| --- | --- | --- | --- |
| News | Posts | Title, content, date, featured image, excerpt (card summary), optional **Byline** | Home (latest 3), News page (latest as feature + archive), the post page |
| ARCS status | WRA Settings | Show/hide, title, message, "Updated" date | ARCS page |
| Form recipient | WRA Settings | Email address (default info@wi-ra.org) | Contact + Volunteer forms |
| Form submissions | Messages (read-only) | Everything the visitor sent | — |

## Static content (developer-edited, in the theme)

Every page's copy, the navigation, the footer, the map snapshot, the documents
library links and the photos. These live in the generated templates. Changing
them is a design edit, not a wp-admin task, and the handoff says so.

## Plugins

| Plugin | Status | Why |
| --- | --- | --- |
| `wra-site` (ours) | new | ARCS status, forms + Messages, byline, redirects |
| Elementor / Elementor Pro | keep installed, not used by these templates | Removing them is not needed; their front-end CSS/JS is dequeued on our templates |
| Google Site Kit | keep | Analytics/Search Console already connected |
| App Service Email | keep | Delivers `wp_mail()` through Azure |
| SEO plugin | none added | The theme prints title, description, canonical, Open Graph and JSON-LD itself (same values as the approved static build); WordPress core sitemap at `/wp-sitemap.xml` |

## Assets

- CSS/JS: `css/wra.css` and `js/wra.js` copied unchanged into the theme
  (`assets/css`, `assets/js`), cache-busted with `filemtime()`.
- Fonts: Google Fonts `<link>` in `<head>` (same as the static build; one route).
- Design images (logo, photos, ARCS screenshots, icons): in the theme, so the
  templates stay byte-identical. News featured images: Media Library.
- Corey's PDFs (final, received Oct 9): Bylaws, Coordination Policy, Wisconsin
  Band Plan in `assets/docs/`, linked from Resources.

## Forms

| Form | Page | Sends to | Stored |
| --- | --- | --- | --- |
| Contact | `/contact/` | WRA Settings recipient (info@wi-ra.org) | Messages |
| Volunteer interest | `/resources/#volunteer` | same | Messages |

POST to `/wp-json/wra/v1/message`; the existing `js/wra.js` already posts
`FormData` to `data-endpoint`, so no JS change. Visitor address goes in
Reply-To, never From. Spam: honeypot + time trap + per-IP rate limit.

## SEO

- Titles/descriptions: the approved values from the static build, per page.
- Posts: title + excerpt; `NewsArticle` JSON-LD; `BreadcrumbList` on inner
  pages; `NGO` organisation node on the home page (no SEO plugin, so no
  duplicate graph).
- Redirects (301), in the plugin:
  - `/faq/` → `/resources/#faq`
  - `/volunteer/` → `/resources/#volunteer`
  - `/membership-pay-dues/` → `/membership/#dues`
  - `/lorem-ipsum-dolor-sit-amet-consectetur-2/` →
    `/wisconsin-association-of-repeaters-transition/` (in the plugin's map since
    1.0.1; WordPress's own old-slug redirect is lost on a fresh install or an
    All-in-One WP Migration import)

## Content migration (one-time setup script)

- Set the existing pages' template to Default (they are on Elementor's
  "Full Width" template today), create Resources and Terms of Use, publish the
  Privacy Policy page.
- Replace the 5 posts' bodies with the cleaned, approved text (removes the
  Elementor-built body on the WAR post and carries the ARCS wording fixes), set
  their excerpts, bylines and new featured images (new logo / IRS badge / ARCS logo).
- Mark the FAQ, Volunteer and Pay Dues pages as drafts (their URLs redirect).

## Launch

- Domain stays wi-ra.org; no DNS change.
- Before install: full backup/snapshot of the App Service site + database.
- Rollback: re-activate Hello Elementor (one click) and restore the page
  templates; the setup script logs every change it makes so it can be reversed.

## Assumptions (change any of these cheaply now)

1. Existing URLs are kept rather than switching to new ones.
2. Posts keep root-level URLs (`/post-name/`), as today.
3. Byline defaults to "Wisconsin Repeater Alliance" when left empty.
4. Elementor stays installed (harmless) unless Corey wants it removed.

## Open questions

| # | Question | Blocking? |
| --- | --- | --- |
| 1 | wp-admin access for deploy (Corey's own login from the brief, or a new admin account he creates for us) | Blocks deploy only |
| 2 | Corey's 3 PDFs | Done: received Oct 9 and linked |
| 3 | Terms of Use text | Done: received Oct 9, published at /terms-of-use/ |
| 4 | Any Elementor Pro Theme Builder templates active on the live site? | No: checked at deploy; the theme overrides them anyway |

## Status (October 8, 2026)

Built and verified locally on WordPress Playground (WordPress latest, PHP 8.4,
Hello Elementor + Elementor installed, the live site's current state recreated):

- Setup rehearsal: run, undo (everything restored), run again: 22/22 checks pass.
- Visual diff against the approved static design: 28 of 32 page captures
  (16 pages × desktop/phone) within 0.03%; the rest differ only by the shorter
  501(c)(3) summary on the News feature card and image resampling.
- Functional: forms, spam handling, Messages, ARCS status editing, publishing
  and deleting a News post: 19/19 pass. Email delivery itself must be tested on
  Azure (Playground has no mail server).
- Accessibility/links/overflow/console: all pages pass at 1440 and 390 px.
- PHP syntax: 37 files clean on PHP 7.4 and 8.4.

Oct 9–10: Terms of Use page and the three PDFs added (final copies); coordination
wording aligned with the adopted policy (periodic updates every two calendar
years with a 90-day reply; notify WRA if off air more than 60 days). The setup
now also runs on a fresh WordPress (rehearsal: 35/35 pass, including undo).

Next: staging on our WordPress (temporary domain) → Corey's review →
All-in-One WP Migration Pro to wi-ra.org (GO-LIVE.md).
