"""Radio Clubs & Sponsors directory (/clubs/), end to end on the local WordPress
(Playground, seeded by tests/playground/seed-clubs.php): the public page, then every
wp-admin task the team does (Add New Listing with a logo, show/hide, bulk show/hide,
a bad address, a missing name, clearing the website, the Trash), checking the public
page after each. Then the update paths: a WRA Site 1.0.x site gets its directory page
on the first admin visit after the update, and a 1.1.0 site gets the page renamed.
Leaves the site as it found it.

usage: python wordpress/tests/clubs.py [WP_BASE]
"""
import pathlib
import sys
from playwright.sync_api import sync_playwright

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

WP = next((x for x in sys.argv[1:] if x.startswith("http")), "http://127.0.0.1:9400/")
LOGO = pathlib.Path(__file__).resolve().parents[2] / "assets" / "img" / "samples" / "club-anytown.webp"
SAMPLE = ["Anytown Amateur Radio Club", "Badger State Sample Repeater Society", "Cedar Valley Amateur Radio Club",
          "Dairyland Demo Radio Association", "Example Amateur Radio Club", "Lake Example Emergency Net",
          "North Woods Sample Amateur Radio Association", "Placeholder Emergency Communications Group",
          "Rivertown Test Amateur Radio Club", "Sample County Repeater Association", "Sample Equipment Sponsor Co.",
          "Test Lakes Repeater Club", "The Demo Valley Radio Society", "Wisconsin Example Radio Club"]
NO_LOGO = ["Cedar Valley Amateur Radio Club", "Dairyland Demo Radio Association", "Example Amateur Radio Club",
           "Rivertown Test Amateur Radio Club", "Test Lakes Repeater Club", "Wisconsin Example Radio Club"]
PER_PAGE = 12
NEW = "Zebra Test Radio Club"
FAILS = []


def check(label, ok, detail=""):
    print(("  ok   " if ok else "  FAIL ") + label + ("" if ok or not detail else f"  ({detail})"))
    if not ok:
        FAILS.append(label)


def page_cards(v, n=1):
    """One page of the directory as a visitor sees it: [(name, href or None, logo src)]."""
    v.goto(WP + "clubs/" + (f"page/{n}/" if n > 1 else ""), wait_until="load")
    return v.evaluate("""() => [...document.querySelectorAll('.club')].map(c => [
        c.querySelector('.club__name').textContent.trim(),
        c.querySelector('.club__link') ? c.querySelector('.club__link').getAttribute('href') : null,
        c.querySelector('.club__logo img').getAttribute('src')])""")


def cards(v):
    """The whole directory, A to Z, read page by page (ends on page 1)."""
    v.goto(WP + "clubs/", wait_until="load")
    pages = int(v.get_attribute("[data-clubs-dir]", "data-pages") or 1) if v.query_selector("[data-clubs-dir]") else 1
    out = []
    for n in range(pages, 0, -1):
        out = page_cards(v, n) + out
    return out


def names(v):
    return [c[0] for c in cards(v)]


def list_screen(a):
    a.goto(WP + "wp-admin/edit.php?post_type=wra_club", wait_until="load")


def row(a, title):
    return a.locator("#the-list tr").filter(has=a.locator("a.row-title", has_text=title)).first


def row_action(a, title, label):
    r = row(a, title)
    r.hover()
    r.locator(".row-actions a", has_text=label).click()
    a.wait_for_load_state("load")


with sync_playwright() as p:
    b = p.chromium.launch()
    vc = b.new_context(viewport={"width": 1440, "height": 900})
    v = vc.new_page()
    v.goto(WP)
    vc.clear_cookies()
    vc.add_cookies([{"name": "playground_auto_login_already_happened", "value": "1", "url": WP}])
    ac = b.new_context(viewport={"width": 1440, "height": 1000})
    a = ac.new_page()
    a.goto(WP + "wp-admin/", wait_until="load")
    errs = []
    a.on("pageerror", lambda e: errs.append(str(e)[:160]))

    a.goto(WP + "wra-tests/upgrade-test.php?step=cleanup", wait_until="load")  # anything an interrupted run left behind

    print("Public page")
    resp = v.goto(WP + "clubs/", wait_until="load")
    check("/clubs/ answers 200", resp.status == 200, resp.status)
    check("visitor is logged out", not v.query_selector("#wpadminbar"))
    c = cards(v)
    check("the 14 published sample listings, A to Z across both pages", [x[0] for x in c] == SAMPLE, [x[0] for x in c])
    on_page = v.evaluate("[...document.querySelectorAll('.club__name')].map(x => x.textContent.trim())")
    check("page 1 shows the first 12", on_page == SAMPLE[:PER_PAGE], on_page)
    check("the draft club is not shown", "Hidden Draft Radio Club" not in [x[0] for x in c])
    check("count reads 'Showing 1–12 of 14'", v.inner_text(".clubs-count") == "Showing 1–12 of 14", v.inner_text(".clubs-count"))
    check("listings without a logo show the WRA placeholder (header logo)", [x[0] for x in c if "wra-logo-nav" in x[2]] == NO_LOGO, [x[0] for x in c if "wra-logo-nav" in x[2]])
    links_by_name = dict((x[0], x[1]) for x in c)
    check("listings without a website have no link", links_by_name["Placeholder Emergency Communications Group"] is None and links_by_name["Dairyland Demo Radio Association"] is None)
    links = v.evaluate("[...document.querySelectorAll('.club__link')].map(l => [l.target, l.rel, l.textContent])")
    check("website links open in a new tab, rel=noopener", all(t == "_blank" and "noopener" in r for t, r, _ in links))
    check("link text names the club for screen readers", all(t.startswith("Visit website: ") and "(opens in a new tab)" in t for _, _, t in links))
    v.evaluate("document.querySelectorAll('img').forEach(i => i.loading = 'eager')")
    v.wait_for_timeout(1500)
    check("every logo loads", v.evaluate("[...document.querySelectorAll('.club img')].every(i => i.complete && i.naturalWidth > 0)"))
    check("logos have empty alt (the name is beside them)", v.evaluate("[...document.querySelectorAll('.club img')].every(i => i.getAttribute('alt') === '')"))
    check("title is 'Radio Clubs & Sponsors – ...'", v.title().startswith("Radio Clubs & Sponsors"), v.title())
    check("heading reads 'Radio clubs & sponsors'", v.inner_text("h1") == "Radio clubs & sponsors", v.inner_text("h1"))
    check("breadcrumb: Home / Resources / Radio clubs & sponsors", v.evaluate("[...document.querySelectorAll('.crumbs li')].map(l => l.textContent.trim())") == ["Home", "Resources", "Radio clubs & sponsors"])
    check("Resources is the current nav section", v.evaluate("!!document.querySelector('.nav__link[data-nav=resources][data-current]')"))
    order = v.evaluate("[...document.querySelectorAll('.nav__list > li > .nav__link')].map(l => l.textContent.trim())")
    check("no top-level Clubs link: ... Resources, News, Contact", order[-3:] == ["Resources", "News", "Contact"] and "Clubs" not in order, order)
    check("Resources dropdown links to it, last", v.evaluate("(() => { const a = [...document.querySelectorAll('#nav-resources a')].pop(); return a.getAttribute('href').endsWith('/clubs/') && a.querySelector('strong').textContent === 'Radio clubs & sponsors' && a.hasAttribute('aria-current'); })()"))
    check("phone menu and footer list it under Resources", v.evaluate("!!document.querySelector('#dr-resources a[href$=\"/clubs/\"]') && [...document.querySelectorAll('.footer__nav > div')].some(d => d.querySelector('h2').textContent.trim() === 'Resources' && d.querySelector('a[href$=\"/clubs/\"]'))"))
    check("footer column is 'News and contact' again", v.evaluate("[...document.querySelectorAll('.footer__nav h2')].map(h => h.textContent.trim())").count("News and contact") == 1)
    check("one h1", v.evaluate("document.querySelectorAll('h1').length") == 1)
    check("12 cards and a pager on page 1", v.locator(".club").count() == PER_PAGE and v.locator(".clubs-pager").count() == 1)
    check("canonical URL is /clubs/", v.evaluate("(document.querySelector('meta[property=\"og:url\"]')||{}).content||''").endswith("/clubs/"))
    heights = v.evaluate("[...document.querySelectorAll('.club')].map(c => Math.round(c.getBoundingClientRect().height))")
    check("all cards are the same height", len(set(heights)) == 1, heights)
    cols = v.evaluate("new Set([...document.querySelectorAll('.club')].map(c => Math.round(c.getBoundingClientRect().left))).size")
    check("4 across at 1440px", cols == 4, cols)
    check("hero has the page photo", v.evaluate("!!document.querySelector('.phead__media img[src*=\"ham-beam\"]')"))
    # (paging, the A–Z / Z–A switch and loading states are in clubs_pages.py)

    print("wp-admin: Clubs menu and list")
    list_screen(a)
    menu = a.evaluate("[...document.querySelectorAll('#menu-posts-wra_club .wp-submenu a')].map(x => x.textContent.trim())")
    check("menu Clubs & Sponsors → All Listings, Add New Listing", menu[:2] == ["All Listings", "Add New Listing"], menu)
    check("menu is named 'Clubs & Sponsors'", a.inner_text("#menu-posts-wra_club .wp-menu-name").strip() == "Clubs & Sponsors")
    titles = a.evaluate("[...document.querySelectorAll('#the-list a.row-title')].map(x => x.textContent.trim())")
    check("list is A to Z by name", titles == sorted(titles, key=str.lower), titles)
    heads = a.evaluate("[...document.querySelectorAll('#posts-filter thead th, #posts-filter thead td')].map(x => x.textContent.trim()).filter(Boolean)")
    heads = [h.replace("Sort ascending.", "").replace("Sort descending.", "").strip() for h in heads]
    check("columns: Logo, Name, Website, In the directory", heads[-4:] == ["Logo", "Name", "Website", "In the directory"], heads)
    views = a.inner_text(".subsubsub")
    check("filters read Shown / Hidden", "Shown" in views and "Hidden" in views and "Published" not in views, views)
    check("draft row is marked Hidden", "Hidden" in row(a, "Hidden Draft Radio Club").inner_text())
    check("help notice links to the Clubs page", a.locator(".wra-club-help a[href$='/clubs/']").count() == 1)
    check("no Quick Edit", a.locator("#the-list .row-actions .inline").count() == 0)

    print("wp-admin: Add New Listing")
    a.goto(WP + "wp-admin/post-new.php?post_type=wra_club", wait_until="load")
    check("name field says 'Club or sponsor name' and is required", a.inner_text("#title-prompt-text") == "Club or sponsor name" and a.evaluate("document.getElementById('title').required"))
    boxes = a.evaluate("[...document.querySelectorAll('#normal-sortables > .postbox')].map(x => x.id)")
    check("form order: logo box, then website box", boxes[:2] == ["postimagediv", "wra-club-website"], boxes)
    check("no Visibility or date rows in the Publish box", not a.is_visible("#visibility") and not a.is_visible(".misc-pub-curtime"))
    check("Publish box explains Draft = hidden", "set its Status to Draft" in a.inner_text("#submitdiv"))
    a.fill("#title", NEW)
    a.fill("#wra-club-url", "zebra.example.org")
    a.click("#set-post-thumbnail")
    a.wait_for_selector(".media-modal", state="visible")
    a.set_input_files(".media-modal input[type=file]", str(LOGO))
    a.wait_for_selector(".media-modal .attachment.selected.save-ready, .media-modal .attachment.details", timeout=60000)
    a.wait_for_function("() => { const b = document.querySelector('.media-modal .media-button-select'); return b && !b.disabled; }", timeout=60000)
    check("media window button says 'Set logo'", a.inner_text(".media-modal .media-button-select").strip() == "Set logo")
    a.click(".media-modal .media-button-select")
    a.wait_for_selector("#postimagediv img", timeout=30000)
    a.wait_for_timeout(500)
    a.click("#publish")
    a.wait_for_load_state("load")
    check("published notice", "Listing published" in a.inner_text("#message"), a.inner_text("#wpbody-content")[:200])
    check("address saved as https://zebra.example.org", a.input_value("#wra-club-url") == "https://zebra.example.org")
    edit_url = a.url
    c = dict((x[0], x) for x in cards(v))
    check("new club appears on the Clubs page", NEW in c)
    check("in A to Z place (last, on page 2)", names(v)[-1] == NEW)
    check("with its logo, not the placeholder", NEW in c and "wra-logo-nav" not in c[NEW][2], c.get(NEW))
    check("and its website link", NEW in c and c[NEW][1] == "https://zebra.example.org")

    print("wp-admin: hide and show")
    list_screen(a)
    row_action(a, NEW, "Hide from directory")
    check("hide notice", "now hidden" in a.inner_text("#wpbody-content"))
    check("hidden club leaves the page", NEW not in names(v))
    check("hidden club is still in the list (not deleted)", "Hidden" in row(a, NEW).inner_text())
    row_action(a, NEW, "Show in directory")
    check("shown club comes back", NEW in names(v))

    print("wp-admin: bulk hide and show")
    list_screen(a)
    for t in (NEW, "Anytown Amateur Radio Club"):
        row(a, t).locator(".check-column input").check()
    a.select_option("#bulk-action-selector-top", "wra_hide")
    a.click("#doaction")
    a.wait_for_load_state("load")
    check("bulk notice: 2 listings hidden", "2 listings are now hidden" in a.inner_text("#wpbody-content"))
    n = names(v)
    check("both left the page", NEW not in n and "Anytown Amateur Radio Club" not in n, n)
    for t in (NEW, "Anytown Amateur Radio Club"):
        row(a, t).locator(".check-column input").check()
    a.select_option("#bulk-action-selector-top", "wra_show")
    a.click("#doaction")
    a.wait_for_load_state("load")
    n = names(v)
    check("both are back", NEW in n and "Anytown Amateur Radio Club" in n, n)

    print("wp-admin: a bad address, then no address")
    a.goto(edit_url, wait_until="load")
    a.fill("#wra-club-url", "not a web address")
    a.click("#publish")
    a.wait_for_load_state("load")
    check("bad address is refused with a notice", "doesn’t look right" in a.inner_text("#wpbody-content"))
    check("the old address is kept", a.input_value("#wra-club-url") == "https://zebra.example.org")
    a.fill("#wra-club-url", "")
    a.click("#publish")
    a.wait_for_load_state("load")
    c = dict((x[0], x) for x in cards(v))
    check("club with its address cleared shows no link", NEW in c and c[NEW][1] is None)
    page_cards(v, 2)
    check("and is still the same card size", len(set(v.evaluate("[...document.querySelectorAll('.club')].map(c => Math.round(c.getBoundingClientRect().height))"))) == 1)

    print("wp-admin: a club with no name")
    a.goto(WP + "wp-admin/post-new.php?post_type=wra_club", wait_until="load")
    a.fill("#wra-club-url", "www.example.org/noname")
    a.click("#publish")
    a.wait_for_timeout(800)
    check("browser stops Publish without a name", "post-new.php" in a.url)
    a.evaluate("document.getElementById('title').required = false")
    a.click("#publish")
    a.wait_for_load_state("load")
    check("server keeps it as a draft and says why", "Enter the name" in a.inner_text("#wpbody-content") and "Listing published" not in a.inner_text("#wpbody-content"))
    noname_url = a.url
    check("not on the Clubs page", len(names(v)) == 15)
    list_screen(a)

    print("wp-admin: Trash")
    row_action(a, NEW, "Trash")
    check("trashed club leaves the page", NEW not in names(v))

    print("Clean up")
    a.goto(WP + "wp-admin/edit.php?post_status=trash&post_type=wra_club", wait_until="load")
    if a.locator("#delete_all").count():
        a.click("#delete_all")
        a.wait_for_load_state("load")
    pid = noname_url.split("post=")[1].split("&")[0] if "post=" in noname_url else None
    if pid:
        a.goto(WP + f"wp-admin/post.php?post={pid}&action=edit", wait_until="load")
        a.locator("#delete-action a").click()
        a.wait_for_load_state("load")
        a.goto(WP + "wp-admin/edit.php?post_status=trash&post_type=wra_club", wait_until="load")
        if a.locator("#delete_all").count():
            a.click("#delete_all")
            a.wait_for_load_state("load")
    check("site back to the 14 sample listings", names(v) == SAMPLE, names(v))

    print("Update path (WRA Site 1.0.x site, plugin replaced by upload)")
    r = v.goto(WP + "wra-tests/upgrade-test.php?step=reset", wait_until="load")
    check("reset: no Clubs page, no version", "reset ok" in v.inner_text("body"), v.inner_text("body")[:200])
    check("/clubs/ is now a 404", v.goto(WP + "clubs/", wait_until="load").status == 404)
    a.goto(WP + "wp-admin/", wait_until="load")  # first admin visit after the update
    v.goto(WP + "wra-tests/upgrade-test.php?step=check", wait_until="load")
    check("first admin visit creates the directory page, titled Radio Clubs & Sponsors", "check ok" in v.inner_text("body"), v.inner_text("body")[:200])
    check("/clubs/ works again with the directory", v.goto(WP + "clubs/", wait_until="load").status == 200 and names(v) == SAMPLE)

    print("Update path (WRA Site 1.1.0 site: page still titled 'Clubs')")
    v.goto(WP + "wra-tests/upgrade-test.php?step=reset110", wait_until="load")
    check("reset: page titled 'Clubs', version 1.1.0", "reset110 ok" in v.inner_text("body"), v.inner_text("body")[:200])
    a.goto(WP + "wp-admin/", wait_until="load")
    v.goto(WP + "wra-tests/upgrade-test.php?step=check", wait_until="load")
    check("first admin visit renames the page and records the version", "check ok" in v.inner_text("body"), v.inner_text("body")[:200])

    check("no script errors in wp-admin", not errs, errs[:3])
    b.close()

print(f"\n{len(FAILS)} failure(s)" if FAILS else "\nall clubs checks passed")
sys.exit(1 if FAILS else 0)
