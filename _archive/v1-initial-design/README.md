# Wisconsin Repeater Alliance — front-end rebuild (static phase)

A custom static front-end for **wi-ra.org**: semantic HTML, one global stylesheet, one global script. No framework, no CMS, no build step required to run it. Open `index.html` directly, or serve the folder with any static server (the repeater preview on the home page needs HTTP, not `file://`).

This phase is the design system and page templates only. It does not touch WordPress, Elementor, Azure, Redis, ARCS or Zeffy.

## Structure

```
index.html                  Home
about.html                  About WRA (governance, board, principles, history)
coordination.html           Frequency Coordination (apply, checklist, existing station)
coordination-process.html   The five-stage process + modification / renewal / interference
arcs.html                   ARCS platform page
membership.html             Membership (join / renew via Zeffy)
resources.html              Resource library, FAQ, volunteer
news.html                   News archive
news-*.html                 Article template, one page per existing post
contact.html                Contact routes + form
privacy.html, terms.html    Policy pages (text to be supplied)
css/global.css              The whole design system
js/global.js                All behaviour (progressive enhancement)
assets/logos | images | icons | data | documents
sitemap.xml, robots.txt
```

## Brand tokens (measured from the official logo files)

| Token | Value | Source |
|---|---|---|
| `--color-brand-primary` | `#012158` · `1 33 88` | ARCS logo navy |
| `--color-brand-secondary` | `#0355AF` · `3 85 175` | ARCS logo signal blue |
| `--color-brand-navy` | `#021C42` | WRA seal navy (dark surfaces, footer) |
| `--color-brand-red` | `#9E0B0F` | WRA seal red (sparingly: required marks, errors, map legend) |

Neutrals: ink `#151515`, surface `#F7F7F4`, surface-alt `#ECEDE8`, border `#D9DDD7`, muted `#5F645E` (darkened from `#70756F` to pass WCAG AA on the surface colour).

Type: IBM Plex Sans (400/500/600) with IBM Plex Mono for technical values only. Body is 18px with a 1.65 line height.

## Components (all in `global.css`)

`wra-header`, `wra-nav` (sliding indicator), `wra-utility`, `wra-mobile-nav`, `wra-button` (`--secondary`, `--light`, `--ghost-light`, `--small`), `wra-link`, `wra-eyebrow`, `wra-section-head`, `wra-page-hero` (+ `wra-ruler` band-scale motif), `wra-breadcrumb`, `wra-subnav`, `wra-toc`, `wra-service`, `wra-process`, `wra-relation`, `wra-lookup`, `wra-register`, `wra-publics`, `wra-news-card`, `wra-archive`, `wra-feature-story`, `wra-resource` + filter chips, `wra-spec` (technical checklist tables, printable), `wra-stage` (process detail), `wra-paths`, `wra-timeline`, `wra-accordion`, `wra-flow`, `wra-features`, `wra-roles`, `wra-price`, `wra-steps`, `wra-routes`, `wra-contact-card`, `wra-notice` (editable status), `wra-callout`, `wra-screen` (ARCS screenshots), form fields and states, `wra-search` (dialog), `wra-toast`, `wra-footer`, `wra-tbc` (placeholder marker).

## Content still to be supplied by WRA

Every gap is marked on the page with an amber **"To be supplied"** tag (`.wra-tbc`). Search the HTML for `wra-tbc` to find them all.

- **Mailing address** (footer, Contact)
- **Full nine-member Board roster** (About → Board). Only the officers named in the Sept 11 open letter are shown.
- **Bylaws PDF, Coordination policies PDF** → put them in `assets/documents/` and replace the "Awaiting upload" buttons in `resources.html`
- **Stage timelines** from the written coordination policy (Process page, stages 03 and 04)
- **Privacy Policy and Terms of Use text**
- **WRA photographs.** The files named in the chat (IMG_2778, Saddle-Peak-Equipment, IMG_5800, s-l1200, 20161222_110557, 20240812155213311, arrlBands) were not in this project folder, nor on the live site or in its media library. Imagery is therefore the real ARCS screenshots, the logos and a data map. `.wra-photo-slot` is ready for the photos when they arrive.

## Integrations (unchanged systems, linked only)

- **ARCS**: "Sign in to ARCS" → `https://arcsonline.org/` (new tab). The application uses `…/accounts/login/?next=/coordination/apply/`, and the directory uses `…/repeaters/`.
- **Repeater search** (home) submits a GET to the ARCS directory (`frequency`, `callsign`, `city`). The live preview reads `assets/data/arcs-directory-snapshot.json`, taken from ARCS's public `/repeaters/map-data/` on 2026-09-30. Refresh it, or point `data-snapshot` at the live endpoint if ARCS allows cross-origin requests.
- **ARCS status notices** are marked `<!-- EDITABLE NOTICE -->` (Coordination, ARCS). Change `data-status` (`online` | `attention` | `offline`), the text and the date.
- **Zeffy**: Join and Renew link to `https://www.zeffy.com/en-US/ticketing/wra-memberships` (`data-zeffy` attribute). For the modal embed, add Zeffy's script and `zeffy-form-link`; keep the href as the fallback.
- **Forms** (Contact, Volunteer) validate inline. With `data-endpoint="…"` they POST, with loading, success and error states. Without it they compose an email to info@wi-ra.org. In WordPress, swap in the form plugin and keep the markup and classes.
- **Hero map** is inline SVG from ARCS public data plus public-domain state and county outlines.

## Redirects for launch (old → new)

```
/frequency-coordination/   → /coordination/
/repeater-coordination/    → /coordination/
/membership-pay-dues/      → /membership/
/volunteer/                → /resources/#volunteer
/faq/                      → /resources/#faq
/lorem-ipsum-dolor-sit-amet-consectetur-2/ → WAR transition post
```
Canonical URLs assume clean paths (`/coordination/`, `/coordination/process/`, `/news/<slug>/`). Adjust them if the WordPress permalink structure differs.

## Motion

Custom curves: ease-out `cubic-bezier(0.23,1,0.32,1)` for enter/exit, ease-in-out for things moving while visible (the nav indicator), plain ease for hovers. UI transitions run 120–260ms. Only transform and opacity are animated, with one exception: the accordion height, which is intentional. Everything that moves is listed below; there is no other scroll animation.
- hero entrance and map line draw, once
- process steps and the ARCS flow, once when scrolled into view
- the nav indicator, mobile menu, search dialog, accordions, toasts and button press

`prefers-reduced-motion` removes travel and keeps state feedback.

## Porting to WordPress / Elementor later

Each section is a self-contained block with `wra-` classes, so global.css can be enqueued as-is and sections rebuilt as Elementor containers or template parts. News cards, the archive and resource items are marked `<!-- CMS: … -->` with the fields each needs.
