# Troubleshooting

Symptom → cause → fix. Add to it whenever something new comes up.

| Symptom | Cause | Fix |
| --- | --- | --- |
| A page shows the old Elementor layout | The page is still on Elementor's "Full Width" template and has no matching design template (slug changed?) | Check the page's slug matches `page-{slug}.php`; re-run Tools → WRA one-time setup on a fresh install, or set Page → Template → Default |
| Fonts/colours look wrong, buttons in the wrong colour | Another plugin or Elementor kit styles reaching the page | The theme dequeues them on its templates; check for a new plugin printing inline CSS, then add its handle prefix to `wra_neutralise_assets()` |
| A new post shows without a picture | No Featured image | Set one; otherwise the WRA logo is used by design |
| Cards on the home page show odd text | No Excerpt, so the opening of the post is used | Fill in the Excerpt |
| Form says "Sorry, the message could not be sent" | The endpoint is unreachable (REST API blocked by a security plugin or firewall) or the visitor hit the rate limit | Check `/wp-json/wra/v1/message` is reachable; allow the `wra/v1` namespace |
| Messages are saved but "Email sent: No" | App Service Email / Azure Communication Services not sending | See FORMS.md → Delivery on Azure |
| Email arrives but replies go to the site | Reply-To missing (should not happen) | Check another plugin is not overwriting mail headers |
| Header hidden under the black admin bar | Only when logged in; covered by `wp.css` | If seen logged out, a cache is serving a logged-in page: purge the cache |
| Change not visible | Browser, plugin or Azure/CDN cache | Ctrl+F5; purge caches; check in a private window |
| A Resources document opens the old version | Browser or CDN cache, or the file was renamed | Keep the file name (`assets/docs/...pdf`), re-upload the theme, purge caches |
| "The package could not be installed" on upload | Zip built without a single top-level folder or with Windows paths | Rebuild with the packager (README-WORDPRESS.md → Packaging) |
| Theme shows "The parent theme is missing" | Hello Elementor deleted | Reinstall Hello Elementor (Appearance → Themes → Add New) |
| Everything looks right logged in but not logged out | A cache or optimisation plugin serving stale or combined CSS/JS to visitors | Exclude `wra-child/assets/` from combining/minifying; purge |
| After the All-in-One WP Migration import, nobody can log in | The import replaced the users with staging's | Log in with the staging credentials; create the WRA accounts on staging before exporting (GO-LIVE.md) |
| Live site not in Google after migration | "Discourage search engines" carried over from staging | Settings → Reading → untick it |
| Forms save but do not email after migration | App Service Email deactivated by the import | Plugins → activate App Service Email |
| An old post address gives "Page not found" | WordPress's old-slug memory is not carried by a fresh install or a migration import | Add the old → new path to `wra_site_redirect_map()` in the WRA Site plugin (1.0.1 already covers the WAR post) |

## Build-time notes

- Git Bash on Windows rewrites `/wordpress/...` arguments into Windows paths;
  set `MSYS_NO_PATHCONV=1` before running the Playground CLI.
- WordPress Playground logs the browser in on the first request. For logged-out
  checks, clear cookies and keep only `playground_auto_login_already_happened=1`.
- In PHP, `?>` inside a `//` comment ends PHP mode. Never write a template tag
  example in a single-line comment.
- WordPress Playground runs PHP in WebAssembly: 3–6 seconds per request. Time-based
  checks (the form's 3-second trap) must not assume a fast server in tests.
- Background auto-updates can replace a plugin's files mid-test in Playground;
  start it with `--define-bool AUTOMATIC_UPDATER_DISABLED true --define-bool DISABLE_WP_CRON true`.
- Two posts on the same date need distinct times, or a fresh install orders them
  arbitrarily (the generator writes order-preserving times into posts.json).
- The form tag gets its PHP endpoint attribute after the hidden fields are
  inserted; the other order breaks the tag because the attribute contains `?>`.
