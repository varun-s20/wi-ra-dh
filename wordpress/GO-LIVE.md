# Go-live: staging first, then wi-ra.org

Two stages:

1. **Staging.** Build the site on our own WordPress under a temporary domain.
   Corey's team reviews it there.
2. **Go-live.** Once approved, install the same theme and plugins on Corey's
   own WordPress (wi-ra.org, Azure App Service) and run the one-time setup
   there. This keeps his existing plugins, users and integrations running
   throughout — see "Why this route" below.

Updates after launch are at the end: **Updating the live site**.

---

## Stage 1: staging on our WordPress (about 15 minutes)

What the staging site needs: WordPress 6.4 or newer, PHP 7.4 or newer (8.x
recommended), an administrator login. A fresh install is best. Elementor /
Elementor Pro may be installed or not; the theme does not use them.

1. **Settings → Reading → Search engine visibility: tick "Discourage search
   engines".** The temporary domain must not be indexed.
2. **Appearance → Themes → Add New** → search **Hello Elementor** → Install
   (do not activate). It is the parent theme.
3. **Appearance → Themes → Add New → Upload Theme** → `dist/wra-child.zip` →
   Install. **Do not activate**; the setup does that.
4. **Plugins → Add New → Upload Plugin** → `dist/wra-site.zip` → Install → **Activate**.
5. **Plugins → Add New → Upload Plugin** → `dist/wra-setup.zip` → Install → **Activate**.
6. **Tools → WRA one-time setup**: read the plan. On a fresh install it lists:
   creating the 11 pages, the 5 News posts with their images, the News
   category, binning WordPress's sample post and page, the site title,
   tagline and time zone, permalinks, the site icon, and the theme.
7. Click **Run setup**.
8. **WRA Settings → Send form emails to**: for staging, use an address on our
   side so test messages do not reach WRA.
9. Check it (logged out, private window): every page in the menu, the five News
   posts, the Resources documents open, Terms of Use and Privacy Policy, the
   mobile menu, **Become a Member** opens Zeffy, and the Contact and Volunteer
   forms send (and appear under **Messages**).
10. Send Corey the staging link.

Changes after review: edit the static source, run `python _build/build.py` and
`python _build/wp_build.py`, re-package (README-WORDPRESS.md → Packaging), then
**Appearance → Themes → Add New → Upload Theme → Replace current with uploaded**.
Content edits (News, ARCS status) are made directly in staging's wp-admin.

Leave **WRA One-time Setup** installed on staging until go-live; it is
harmless and keeps the Undo button available.

---

## Stage 2: go live on wi-ra.org (install directly, no migration import)

### Why this route

wi-ra.org already has plugins Corey relies on: **App Service Email** (site
mail through Azure), **Redis Object Cache** (Redis is on his Azure plan),
**Google Site Kit** (Analytics, Search Console) and **Classic Editor** (how he
posts News), plus **Elementor / Elementor Pro**. A full-site migration import
(All-in-One WP Migration) *replaces* the entire destination database —
plugins, users, settings — with the source site's. That would deactivate all
of those, disconnect Site Kit, and leave a stale Redis cache, even though none
of it is needed to bring the new design live.

Installing the theme and the two WRA plugins directly on wi-ra.org, then
running the same one-time setup used on staging, avoids all of that: nothing
on his site is replaced. His users, Elementor licence, Site Kit connection and
App Service Email stay exactly as they are. The setup reuses his existing
pages and posts (updating them to the approved text and images), logs every
change it makes, and has an **Undo** button that reverses all of it.

### Steps

1. **Back up first.** In Corey's wp-admin: **All-in-One WP Migration → Export →
   File**, download the `.wpress` file and keep it. This does not touch any
   plugin or setting — it only reads the site. Also take an Azure backup
   (Azure portal → the App Service → Backups) if available.
2. **Appearance → Themes → Add New → Upload Theme** → `dist/wra-child.zip` →
   Install. **Do not activate**; the setup does that.
3. **Plugins → Add New → Upload Plugin** → `dist/wra-site.zip` → Install → **Activate**.
4. **Plugins → Add New → Upload Plugin** → `dist/wra-setup.zip` → Install → **Activate**.
5. **Tools → WRA one-time setup**: read the plan before running anything.
   Check it against his site (it should update, not duplicate, his existing
   pages and the 5 News posts) and send a screenshot for a second check if
   unsure.
6. Click **Run setup**.
7. **Settings → Redis → Flush Cache**, so visitors see the new design
   immediately rather than a cached copy of the old one.
8. **WRA Settings:** confirm **Send form emails to** is `info@wi-ra.org` and
   the ARCS status notice reads correctly (both are already set by the
   setup's defaults).
9. If anything was changed in staging's wp-admin after Corey's review (a News
   edit, the ARCS notice), repeat that edit here — content is not copied over
   on this route.
10. **Settings → Reading:** confirm **"Discourage search engines" is unticked**.
11. **Check, logged out:** every page, the five posts, the redirects (`/faq/`,
    `/volunteer/`, `/membership-pay-dues/`,
    `/lorem-ipsum-dolor-sit-amet-consectetur-2/`), the PDFs, Zeffy.
12. **Forms deliver mail.** Send the Contact and Volunteer forms. Both must
    arrive at info@wi-ra.org, and Reply must address the sender. If a Message
    shows "Email sent: No", see FORMS.md → Delivery on Azure.
13. **Search Console** (via Site Kit, already connected): submit
    `https://wi-ra.org/wp-sitemap.xml`.
14. Purge any cache (Azure Front Door/CDN) and check one page in a private
    window.

### Rollback

**Tools → WRA one-time setup → Undo** reverses every change the setup made
and restores the pages/posts it touched. The step 1 `.wpress` export is a
second, full-site fallback if something outside the setup's reach needs
restoring.

---

## Alternative: full migration with All-in-One WP Migration Pro

Only use this if Corey specifically wants wi-ra.org to become an exact copy
of staging (same database throughout), and is fine with reconnecting his
integrations afterward. **Not the default route** — it deactivates plugins he
relies on; see "Why this route" above.

**What a migration does.** All-in-One WP Migration *replaces* the destination
site with the staging site: database, users, posts, pages, settings, themes,
plugins and uploads. Anything on wi-ra.org that is not on staging stops
existing there. Plan for these before you start:

| On wi-ra.org today | After the import | What to do |
| --- | --- | --- |
| Corey's and Ian's wp-admin logins | Replaced by staging's users | Before exporting, create Corey's and Ian's accounts on **staging** (Users → Add New, Administrator), or Corey will be locked out |
| App Service Email (sends the site's mail through Azure) | Deactivated (not active on staging) | After import: **Plugins → activate App Service Email**, then send a test form |
| Google Site Kit (Analytics, Search Console) | Not connected | After import: reactivate Site Kit and reconnect it with Corey's Google account |
| Redis Object Cache (plus its `object-cache.php` drop-in; Redis is on Corey's Azure plan) | The drop-in stays and keeps serving the old site's cached settings | Before import: **Settings → Redis → Disable Object Cache**. After import: activate the plugin, **Enable Object Cache**, **Flush Cache** |
| Classic Editor (Corey posts News with it) | Inactive unless staging has it | Install and activate Classic Editor on staging before exporting |
| The 5 existing News posts | Replaced by staging's copies (same approved text) | Nothing; they keep the same addresses |
| Old pages FAQ, Volunteer, Pay Dues | Gone (staging never had them) | Nothing; the WRA Site plugin redirects those addresses |
| Any Elementor Pro licence on wi-ra.org | Plugin settings come from staging | Re-enter Corey's licence if he still wants Elementor Pro; the site does not need it |

### Steps

1. **Staging, final prep**
   - Corey's team has approved staging.
   - Create Corey's and Ian's administrator accounts on staging (see the table).
   - **WRA Settings → Send form emails to** → `info@wi-ra.org`.
   - Delete test Messages and any test posts.
   - Plugins: delete **WRA One-time Setup** (no longer needed on the live site);
     install and activate **Classic Editor**.
2. **Back up wi-ra.org.** In Corey's wp-admin: **All-in-One WP Migration → Export →
   File**, download the `.wpress` file and keep it (this is the rollback). Also
   take an Azure backup (Azure portal → the App Service → Backups) if available.
   Then **Settings → Redis → Disable Object Cache**.
3. **Export staging.** Staging wp-admin: **All-in-One WP Migration → Export → File**.
   The domain is replaced automatically on import; no find/replace is needed.
4. **Import on wi-ra.org.** Corey's wp-admin: **All-in-One WP Migration → Import**
   → upload the staging `.wpress` → confirm. (The free plugin's upload limit
   does not apply; we have Pro. If Azure's PHP upload limit stops the upload,
   use the Pro **Import from file** / FTP method.)
5. **Log in again** with the staging credentials (the database was replaced),
   then **Settings → Permalinks → Save Changes** once.
6. **Settings → Reading → untick "Discourage search engines".** Staging had it
   ticked, and the import carries that setting over. This is the most common
   launch mistake.
7. **Plugins:** activate **App Service Email**; activate **Redis Object Cache**,
   then **Settings → Redis → Enable Object Cache → Flush Cache**; reactivate and
   reconnect **Google Site Kit**. Leave Elementor and Elementor Pro inactive (the
   site does not use them; Pro runs on the previous developer's licence).
8. **Check, logged out:** every page, the five posts, the redirects (`/faq/`,
   `/volunteer/`, `/membership-pay-dues/`,
   `/lorem-ipsum-dolor-sit-amet-consectetur-2/`), the PDFs, Zeffy.
9. **Forms deliver mail.** Send the Contact and Volunteer forms. Both must
   arrive at info@wi-ra.org, and Reply must address the sender. If a Message
   shows "Email sent: No", see FORMS.md → Delivery on Azure.
10. **Search Console** (via Site Kit): submit `https://wi-ra.org/wp-sitemap.xml`.
11. Purge any cache (Azure Front Door/CDN, a cache plugin) and check one page in
    a private window.

### Rollback

**All-in-One WP Migration → Import** the wi-ra.org `.wpress` backup from step 2.
That restores the site exactly as it was before the migration.

---

## Updating the live site

The site is live, so an update replaces the theme and plugin in place. No setup
is run again. Do it on staging first, let Corey check it there, then repeat on
wi-ra.org.

### Clubs release (theme 1.1.0, WRA Site 1.1.0)

Adds the Clubs page (`/clubs/`), "Clubs" between News and Contact in the menu,
and **Clubs** in wp-admin. **Order matters: plugin first, then theme.** The
plugin creates the Clubs page, and the theme adds the menu link to it.

1. **Back up**: All-in-One WP Migration → Export → File, and keep the download.
2. **Plugins → Add New → Upload Plugin** → `dist/wra-site.zip` → Install Now →
   **Replace current with uploaded**. The plugin stays active.
3. The next wp-admin page you open (by an administrator) creates the **Clubs**
   page once. Check **Pages**: "Clubs" is there and Published, and **Clubs** is in
   the left-hand menu.
4. **Appearance → Themes → Add New → Upload Theme** → `dist/wra-child.zip` →
   Install Now → **Replace active with uploaded**.
5. On wi-ra.org: **Settings → Redis → Flush Cache**, then purge any CDN cache.
6. **Check, logged out**: the Clubs page (before any clubs are added it says "The
   directory is being compiled"), "Clubs" in the header between News and
   Contact on two or three pages, the phone menu, the footer, and that the rest
   of the site is unchanged.
7. **Add the clubs**: Clubs → Add New Club (OWNER-HANDOFF.md, section 2), or
   load the list Corey sends.

**Rollback**: upload the previous zips the same way (theme 1.0.1, WRA Site
1.0.1; both are in git at commit `0df855d`, `wordpress/dist/`). Club records
and the Clubs page stay in the database and do no harm. To remove them, move the
Clubs page to the Trash.

### Radio Clubs & Sponsors update (theme 1.1.1, WRA Site 1.1.1)

Live has 1.1.0. This renames the directory **Radio Clubs & Sponsors**, moves its
menu link from the top level into **Resources**, and renames wp-admin **Clubs** to
**Clubs & Sponsors**. The address stays `/clubs/`, and any clubs already entered
stay as they are.

1. **Back up** (All-in-One WP Migration → Export → File).
2. **Plugins → Add New → Upload Plugin** → `dist/wra-site.zip` → **Replace current
   with uploaded**.
3. Open the **Dashboard** once. This renames the page from "Clubs" to "Radio Clubs &
   Sponsors" (check **Pages**), and the left-hand menu now says **Clubs & Sponsors**.
4. **Appearance → Themes → Add New → Upload Theme** → `dist/wra-child.zip` →
   **Replace active with uploaded**.
5. **Settings → Redis → Flush Cache**, then purge any CDN cache.
6. **Check, logged out**: the header has no "Clubs" link; **Resources** opens with
   "Radio clubs & sponsors" as its last item; the page heading and the breadcrumb
   (Home / Resources / Radio clubs & sponsors) read correctly; the phone menu and
   the footer list it under Resources.

**Rollback**: upload the 1.1.0 zips the same way (theme first is fine here, as the
page already exists).

### Radio Clubs & Sponsors paging (theme 1.1.2, theme only)

Adds pages of 12 and the A–Z / Z–A switch across pages. **WRA Site is not touched**, so
this is a single upload and nothing needs doing in wp-admin. Listings already entered stay.

1. **Back up** (All-in-One WP Migration → Export → File).
2. **Appearance → Themes → Add New → Upload Theme** → `dist/wra-child.zip` →
   **Replace active with uploaded**.
3. **Settings → Redis → Flush Cache**, then purge any CDN cache.
4. **Check, logged out**, on `/clubs/`:
   - 12 cards and, once there are more than 12 listings, page links under them;
   - **Next** opens `/clubs/page/2/` and **Previous** returns;
   - **Z–A** shows the last listing first, and its page links keep Z–A.
5. **Cache check (do not skip).** Open `https://wi-ra.org/clubs/?order=za` in a private
   window. It must list Z–A (Z first). If it lists A–Z, a cache in front of the site is
   ignoring the part after the "?": ask the host to include the query string in that
   cache key (or to bypass the cache for `/clubs/`), and tell us.
6. **Edge cases:** `/clubs/page/99/` should land on the last page; with JavaScript off
   (browser settings), the switch and the page links still work as ordinary links.

**Rollback**: upload the previous theme zip (1.1.1) the same way.
