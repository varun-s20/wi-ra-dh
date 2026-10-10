# SEO

No SEO plugin is installed; the theme prints the head tags itself
(`inc/head.php`), so there is exactly one source of titles, descriptions and
structured data.

| Item | Source |
| --- | --- |
| `<title>`, meta description | Pages: the approved values from the static build (`inc/page-meta.php`). Posts: title + " – Wisconsin Repeater Alliance", and the Excerpt (or the post's opening) |
| Canonical | WordPress core (`rel_canonical`) on pages and posts |
| Open Graph / Twitter | Theme. Posts use the featured image; everything else `assets/img/og-wra.jpg` |
| JSON-LD | Home: `NGO` (name, address, EIN, 501(c)(3), logo). Inner pages: `BreadcrumbList`. Posts: `BreadcrumbList` + `NewsArticle` + `NGO` |
| Sitemap | WordPress core: `/wp-sitemap.xml` |
| noindex | 404 and search results |
| Analytics / Search Console | Google Site Kit (unchanged) |

## Redirects (301, `wra-site/inc/redirects.php`)

| Old | New |
| --- | --- |
| `/faq/` | `/resources/#faq` |
| `/volunteer/` | `/resources/#volunteer` |
| `/membership-pay-dues/` | `/membership/#dues` |
| `/lorem-ipsum-dolor-sit-amet-consectetur-2/` | `/wisconsin-association-of-repeaters-transition/` (WRA Site plugin 1.0.1; WordPress's own old-slug redirect does not survive a migration) |
| `/coordination/`, `/coordination/process/`, `/privacy/`, `/terms/`, `/*.html` | their WordPress pages (addresses from the design preview) |

All other live URLs are unchanged.

## After launch

- Submit `https://wi-ra.org/wp-sitemap.xml` in Search Console.
- Watch Search Console → Pages for two weeks for any 404s and add redirects.
- Check one post and the home page in Google's Rich Results Test.
