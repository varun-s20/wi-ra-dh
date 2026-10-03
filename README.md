# Wisconsin Repeater Alliance: site rebuild (v4)

This is the full rebuild of **wi-ra.org**, made to the client brief agreed with Corey Becker (see `client-chat/`). It is plain HTML with one stylesheet (`css/wra.css`) and one script (`js/wra.js`), assembled from shared partials by a small build script. Every page has been rebuilt; the first design is archived in `_archive/v1-initial-design/`.

## Editing and building

Source lives in `_build/`:

```
_build/partials/head.html     <head>: title, description, canonical, Open Graph, fonts, CSS, JS
_build/partials/header.html   header (utility strip, navigation bar, dropdowns) and the mobile drawer
_build/partials/footer.html   footer: 501(c)(3) statement, EIN, mailing address, policies
_build/pages/*.html           one file per page: JSON front matter + <main>
_build/build.py               assembles the pages into the site root
```

Edit a page or partial, then run:

```
python _build/build.py
```

The build does the following:

- Writes every page to the site root.
- Marks the current section in the header and drawer.
- Writes the breadcrumbs (`{{crumbs}}`) and their JSON-LD, the NGO markup on the home page, and NewsArticle markup on posts.
- Injects the Wisconsin repeater map and band counts into the home page.
- Writes `sitemap.xml` and `robots.txt`.

Don't edit the root `*.html` files directly; the next build overwrites them.

**Front matter keys:**
- `title`, `description`, `canonical` (clean WordPress-style path)
- `nav` (the section to mark as current)
- `crumbs` (`[label, href|null]` pairs)
- `preload` (hero image)
- `head_extra`
- `article` (`date`, `author`) and `h1`, for news posts
- `noindex`

## Pages

| Page | File | What the brief asked for |
|---|---|---|
| Home | `index.html` | One H1 and two primary actions. Four quick-access cards: Apply for Coordination, Look Up a Repeater, Join or Renew, ARCS Login. "WRA coordinates, ARCS does the work" shown as one 4-step process. Trust stated plainly, the public service role kept, the 3 latest news items. |
| About WRA | `about.html` | Who we are, mission and vision, what we do, WRA vs ARCS, governance (#governance), Board (#board), guiding principles |
| Coordination | `coordination.html` | A plain-language landing page for people new to coordination |
| Repeater coordination | `coordination-process.html` | Four separate paths (new, modify, renew/records, interference). The exact information to supply. 5 stages, each with an owner and a timeline slot. A printable pre-application checklist (#checklist). |
| ARCS | `arcs.html` | A platform page, not a login page. What ARCS does, explained for non-engineers, who uses it, real screenshots, "Sign in to ARCS" in a new tab |
| Membership | `membership.html` | $20 a year shown up front, Join and Renew side by side, where dues go, benefits, paying dues via Zeffy (#dues) |
| Resources | `resources.html` | Filterable FAQ (#faq), document library (#library), volunteer roles (#volunteer) and interest form (#interest) |
| News | `news.html` + 5 `news-*.html` | Announcement template: byline, reading time, copy link, more news, previous/next |
| Contact | `contact.html` | Contact routes (coordination, interference, membership, volunteering) and the form; `?topic=` preselects the subject |
| Privacy, Terms, 404 | `privacy.html`, `terms.html`, `404.html` | Placeholders until WRA supplies the policy text |

**Navigation** follows the agreed 8-item structure, identical in the header, the mobile drawer and the footer: Home (the logo), About WRA, Coordination, ARCS, Membership, Resources, News, Contact.

- **Header** (`.hdr`, `_build/partials/header.html`):
  - A utility strip carries the email, the ARCS repeater directory and "Sign in to ARCS".
  - A full-width bar holds the logo, the 8 sections and one "Become a Member" button, aligned to the page grid.
  - It is fixed to the top of every page at all times: transparent over the dark page heads, solid white once you scroll.
- **Dropdowns:** Coordination, Membership and Resources are disclosure buttons (`aria-expanded`). They open on click, Enter or Space, or on mouse hover, and close on Escape, on click outside, or when focus leaves.
- **Current page:** the current section is underlined (`data-current`); exact links carry `aria-current="page"`.
- **Below 1200px:** a "Menu" button opens a right-hand drawer with the same structure as collapsible groups. The current group opens automatically, and the page behind it becomes inert.
- **Footer:** six columns mirroring the nav, then EIN, status and the mailing address, then copyright and policies.
- **ARCS links:** "Sign in to ARCS" always opens `https://arcsonline.org/` in a new tab; "ARCS" in the nav is the on-site about page.

## Design system

**Look and feel:** modelled on tlc-engineers.com, an Awwwards nominee, with institutional discipline. Every section aligns to one 12-column frame, there is at most one deliberate overlap per section, and decorative rings and halftone textures are gone from page heads.
- A hero lens that turns the headline into outlines (tinted ARCS blue)
- White sheets laid over gradient planes, and caption cells
- "1 of N" index columns

**Palette:** from the ARCS logo files, as the client approved.

| Token | Value | Use |
|---|---|---|
| `--navy` | `#022259` | ARCS navy: headings, UI, dark sections |
| `--blue` | `#0455B0` | ARCS signal blue: buttons, links, focus, hero lens |
| `--navy-950` | `#011940` | WRA navy: deepest surfaces |
| `--red` | `#9B0B0E` | WRA red, kept as a brand mark only (label squares, wordmark) |
| `--blue-soft` | `#7DB3F5` | Blue on navy (AA) |

**Type:** Archivo at normal width only, in sentence case. Wide (expanded) and all-caps display type was removed in the professionalism pass, because it read as a poster rather than an institution.
- 700 with tight tracking (-0.03em) for the hero and page titles
- 700 for statements
- 400 at 18px for body copy
- Uppercase only for small labels (eyebrows, meta, table headers)

**Components** (all in `css/wra.css`, sections 18–30):
- Page structure: `phead` (page head, with photo, screen, plain and article variants), `toc` (on-this-page cells), `split`, `sec-head`
- Lists and cells: `cells`, `icols`, `duo` / `sheet`, `ticks`, `pull`
- Process and reference: `steps` with `owner` chips, `spec` table, `checklist`
- Content blocks: `faq` / `qa`, `docs`, `people`, `price`, `notice`
- Forms: `form` / `field`
- News: `article` / `prose`, `feature`, `archive`
- Closing band: `endcap`

## Content still needed from WRA

Search the site for `tbc` (the amber "To be supplied" tags) and `EDITABLE` comments.

- Mailing address (footer, Contact)
- The full nine-member Board roster (About → Board). The officers named in the open letter are shown.
- Stage timelines for the coordination process (Repeater coordination → Review process). These are in WRA's coordination policy.
- PDFs for `assets/documents/` (bylaws, coordination policies, the band chart), linked from Resources → Documents
- Privacy Policy and Terms of Use text
- WRA's own photographs, to replace the stock photos from wi-ra.org's media library
- The status notices (ARCS registration closed, updated September 29, 2026), which need updating as things change

## Integrations

- **ARCS** (`arcsonline.org`):
  - "Sign in to ARCS" goes to the site root.
  - "Look up a repeater" goes to `/repeaters/`.
  - "Start an application" goes to `/accounts/login/?next=/coordination/apply/`.
- **Zeffy:** Join and Renew buttons carry `zeffy-form-link`. `js/wra.js` opens Zeffy's embed form in an accessible `<dialog>`, using the same open/close messages as Zeffy's own embed script. It doesn't use that script, because it preloads the whole payment page on every visit and only initialises at page load. The plain link to Zeffy is the fallback.
- **Forms:** Contact and volunteer forms validate inline. Set `data-endpoint` on the form to POST to a handler. Without one, the form opens a pre-filled email to info@wi-ra.org. In WordPress, swap in the form plugin and keep the field names.
- **Repeater map:** plotted from `assets/data/arcs-directory-snapshot.json` (the ARCS public directory, 388 records, taken September 30, 2026). Regenerating it means updating that snapshot and re-running the map step (see `_build/map.svg.html`).

## Checks run

**Responsive:** every page at 19 widths: 320, 360, 375, 390, 414, 480, 600, 768, 820, 900, 1024, 1100, 1180, 1280, 1366, 1440, 1600, 1920 and 2560px. At each width there is no horizontal scrolling, nothing pushed off-screen, no text spilling out of its box, and no content under the fixed header. Content widens to 1320px from 1680px and to 1440px from 2200px.

On all 17 pages, at 1440px and 390px wide:
- **axe-core:** 0 violations.
- **Console:** 0 errors.
- **Links:** no broken links or in-page anchors.
- **Layout:** no horizontal overflow, and exactly one H1 per page.
- **Contrast:** text over photos and gradients was checked by sampling the actual background pixels.
- **Interactions:** the FAQ filter, checklist persistence and printing, form validation, topic preselect, the Zeffy dialog (focus returns on close), the menu and dropdowns.
- **Reduced motion:** honoured.

## Porting to WordPress

Each section is a self-contained block with its own classes, so `css/wra.css` and `js/wra.js` can be enqueued as-is.

- **Static pages:** become Gutenberg or Elementor pages built from the same markup.
- **News:** maps to posts, kept on the Classic Editor as WRA staff use now. Point the `article` / `prose` template at the post content.
- **Plugins:** the required ones stay (Azure email, Redis, Classic Editor). Nothing here needs Elementor Pro.
