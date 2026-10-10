# wi-ra.org on WordPress: build notes

For whoever installs or maintains the build. The owner's guide is
`OWNER-HANDOFF.md`; the launch steps are `GO-LIVE.md`.

## What is here

```
wordpress/
├── theme/wra-child/      child theme of Hello Elementor (the design)
├── plugins/wra-site/     ARCS status setting, forms + Messages, byline, redirects
├── plugins/wra-setup/    one-time switch-over (plan / run / undo), deleted after launch
├── dist/                 the three installable .zip files
├── tests/                visual, functional, accessibility and syntax checks
├── CONVERSION-PLAN.md    decisions and assumptions
├── OWNER-HANDOFF.md      wp-admin guide for the WRA team
├── GO-LIVE.md            launch checklist and rollback
├── FORMS.md  SEO.md  TROUBLESHOOTING.md
```

## The one rule: the static site is the source

The approved design lives in `_build/pages/*.html`, `_build/partials/*.html`,
`css/wra.css` and `js/wra.js` (assembled for the static preview by
`_build/build.py`). The WordPress page templates are **generated** from it:

```bash
python _build/build.py      # static preview (unchanged)
python _build/wp_build.py   # regenerates the theme's templates, assets and posts.json
```

Generated, never edit by hand (each starts with a GENERATED comment):
`front-page.php`, `page-*.php`, `404.php`, `template-parts/chrome-*.php`,
`template-parts/map.php`, `inc/page-meta.php`, `assets/css/wra.css`,
`assets/js/wra.js`, `assets/img/**`, `assets/icons/**`,
`plugins/wra-setup/content/posts.json`.

Hand-written: `functions.php`, `header.php`, `footer.php`, `single.php`,
`page.php`, `index.php`, `inc/{helpers,news,setup,head}.php`,
`template-parts/{news-home,news-list,news-row,arcs-status}.php`,
`assets/css/wp.css`.

What the generator does to the static HTML: asset paths become theme URLs,
`.html` links become WordPress URLs (`wra_u()`), breadcrumbs and nav state
become helpers, the home/News post lists, the ARCS notice and the document links
become template parts, and the forms get their endpoint and hidden spam fields.
It fails loudly if a template calls a helper the theme does not define.

After any design change: re-run both scripts, run the tests, bump the theme
version in `style.css`, re-package (below) and upload the theme zip.

## Why the design cannot drift

- Hello Elementor's own styles, header/footer layer and description tag are
  switched off through its filters (`inc/setup.php`).
- On our templates, every Elementor / Elementor Pro / kit / swiper style and
  script is dequeued, as are WordPress block styles (kept on posts for editor
  content). `<body>` keeps only admin-bar classes plus `wra wra-{page}`, so
  Elementor's kit class cannot restyle anything.
- `template_include` (priority 999) routes front page, design pages, posts and
  404 to our templates even though the live pages were on Elementor's Full Width
  template. Our templates never call `elementor_theme_do_location()`, so Theme
  Builder headers/footers/singles cannot render into them.
- `wra.css` is the static stylesheet unchanged; WordPress-only rules are the
  few lines in `wp.css`.

## Content model

| Content | Storage | Rendered by |
| --- | --- | --- |
| News | core posts (category News), excerpt, featured image, `_wra_byline` meta | `single.php`, `template-parts/news-*.php` |
| ARCS status | option `wra_site_options` | `template-parts/arcs-status.php` (falls back to the approved wording if the plugin is off) |
| Messages | CPT `wra_message`, meta `_wra_form`, `_wra_fields`, `_wra_mailed` | wp-admin only |
| Everything else | the generated templates | — |

## Plugin API (wra-site)

- `wra_site_arcs_status()`: `show`, `title`, `message`, `updated` (Y-m-d)
- `wra_site_byline( $post_id )`: string, '' when unset
- `POST /wp-json/wra/v1/message`: form fields + `_form` (contact|volunteer), `_ts`, `_hp`
- Redirect map: `wra_site_redirect_map()` in `inc/redirects.php`
- Uninstall deletes the settings only; Messages and bylines are kept.

## Packaging

```bash
python <skill>/scripts/package_plugin.py wordpress/theme/wra-child   --out wordpress/dist
python <skill>/scripts/package_plugin.py wordpress/plugins/wra-site  --out wordpress/dist
python <skill>/scripts/package_plugin.py wordpress/plugins/wra-setup --out wordpress/dist
```

Zips must have one top-level folder and forward-slash paths; the packager
checks both. Keep the version in the plugin header and its constant in step.

## Tests

Run against a local WordPress (WordPress Playground, no Docker needed):

Start the local site with the command below. Its blueprint installs Hello
Elementor and Elementor, recreates the live site's current state
(`tests/playground/simulate-live.php`), then rehearses the setup: run, undo,
check everything is back, run again. Results land in `<logs-dir>/setup-test.txt`.
Then, with the static preview also running on port 8767:

```bash
python wordpress/tests/compare.py     # pixel diff vs the static design, desktop + phone
python wordpress/tests/functional.py  # forms, spam, Messages, ARCS status, publishing News
python wordpress/tests/a11y.py        # axe, console, images, links, overflow, one h1
python wordpress/tests/interact.py    # menus, dropdown, hero, FAQ filter, checklist, Zeffy (read-only)
# against staging (submits nothing): a11y.py <url> --readonly, interact.py <url>
# syntax check on any PHP version, without PHP installed:
npx @wp-playground/cli php --php=7.4 --wordpress-install-mode=do-not-attempt-installing   --mount-dir wordpress /w -- /w/tests/lint.php /w
```

Playground command used (Git Bash on Windows needs `MSYS_NO_PATHCONV=1`):

```bash
npx @wp-playground/cli server --port=9400 --php=8.4 --login \
  --mount-dir wordpress/theme/wra-child /wordpress/wp-content/themes/wra-child \
  --mount-dir wordpress/plugins/wra-site /wordpress/wp-content/plugins/wra-site \
  --mount-dir wordpress/plugins/wra-setup /wordpress/wp-content/plugins/wra-setup \
  --mount-dir wordpress/tests/playground /wordpress/wra-tests \
  --mount-dir <logs-dir> /wordpress/wralogs \
  --define-bool WP_DEBUG true --define-bool WP_DEBUG_DISPLAY false \
  --define WP_DEBUG_LOG /wordpress/wralogs/debug.log \
  --blueprint wordpress/tests/playground/blueprint.json --blueprint-may-read-adjacent-files
```

## Documents (Resources page)

The Bylaws, Coordination Policy and Wisconsin Band Plan are in the static
source at `assets/docs/{bylaws,coordination-policy,wisconsin-band-plan}.pdf`
(copied from Corey's final files in `ref/`). `_build/wp_build.py` copies
`assets/docs/` into the theme, and the Resources page links them like any other
asset. To replace one, overwrite the file under the same name, re-run the
generator, re-package and upload the theme.

## Changelog

- 1.0.0: first WordPress release of the approved design.
- 1.0.0 (Oct 9): Terms of Use page; Bylaws, Coordination Policy and Band Plan PDFs linked; coordination wording aligned with the adopted policy. Setup 1.1.0: works on a fresh WordPress (creates posts in a News category with order-preserving times, bins the untouched sample post/page, sets site title, tagline and time zone).
- WRA Site 1.0.1 (Oct 10): redirects the WAR post's old address (`/lorem-ipsum-dolor-sit-amet-consectetur-2/`) itself, because WordPress's old-slug redirect is lost on a fresh install and on an All-in-One WP Migration import.
