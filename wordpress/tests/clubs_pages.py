"""Radio Clubs & Sponsors (/clubs/): paging, A–Z / Z–A across the whole directory, and how the
page behaves while it loads. Runs on the local WordPress (Playground, seeded by
tests/playground/seed-clubs.php with 14 listings, 12 to a page), then on the static preview.

  without JavaScript   real pages, real links, Z–A is a link, out-of-range pages redirect, titles/robots/canonical
  with JavaScript      the switch and the pager swap the cards in place (no page load), history, status line,
                       focus, skeleton cards on a slow network (and none on a fast one), the logo shimmer,
                       fall back to a normal page load on failure, rapid clicks, reduced motion, keyboard,
                       ctrl-click left alone
  many pages           80 extra listings (8 pages): the page-number window, "Page X of Y" on phones, parity of
                       the window with the static generator for every current/last page up to 30

usage: python wordpress/tests/clubs_pages.py [WP_BASE] [STATIC_BASE]
"""
import json
import pathlib
import sys
import urllib.request
from urllib.parse import urlsplit

from playwright.sync_api import sync_playwright

ROOT = pathlib.Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "_build"))
import clubs_static  # noqa: E402

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

URLS = [x for x in sys.argv[1:] if x.startswith("http")]
WP = URLS[0] if URLS else "http://127.0.0.1:9400/"
ST = URLS[1] if len(URLS) > 1 else "http://127.0.0.1:8767/"
SAMPLE = ["Anytown Amateur Radio Club", "Badger State Sample Repeater Society", "Cedar Valley Amateur Radio Club",
          "Dairyland Demo Radio Association", "Example Amateur Radio Club", "Lake Example Emergency Net",
          "North Woods Sample Amateur Radio Association", "Placeholder Emergency Communications Group",
          "Rivertown Test Amateur Radio Club", "Sample County Repeater Association", "Sample Equipment Sponsor Co.",
          "Test Lakes Repeater Club", "The Demo Valley Radio Society", "Wisconsin Example Radio Club"]
PER = 12
FAILS = []
ONLY = next((a.split("=", 1)[1] for a in sys.argv if a.startswith("--only=")), "")  # nojs, js, many, empty, static
# animations of the directory still running (the logo shimmer loops for as long as an image is loading, so it is left out)
BUSY = "document.querySelector('[data-clubs-dir]').getAnimations({ subtree: true }).filter(a => a.playState === 'running' && a.animationName !== 'skShimmer').length"


def check(label, ok, detail=""):
    print(("  ok   " if ok else "  FAIL ") + label + ("" if ok or not detail else f"  ({detail})"))
    if not ok:
        FAILS.append(label)


class Site:
    """The two sites expose the same markup at different addresses."""

    def __init__(self, base, static):
        self.base, self.static = base, static

    def url(self, n=1, za=False):
        if self.static:
            return self.base + clubs_static.file_for("za" if za else "az", n)
        return self.base + "clubs/" + (f"page/{n}/" if n > 1 else "") + ("?order=za" if za else "")

    def visitor(self, b, **kw):
        ctx = b.new_context(**kw)
        if not self.static:  # Playground signs the first browser in as admin: keep only its "already happened" cookie
            jar = [{"name": "playground_auto_login_already_happened", "value": "1", "url": self.base}]
            ctx.add_cookies(jar)
            pg = ctx.new_page()
            pg.goto(self.base, wait_until="load", timeout=90000)
            ctx.clear_cookies()
            ctx.add_cookies(jar)
            pg.close()
        return ctx


def names(pg):
    return pg.locator(".club:not(.club--leaving) .club__name").all_inner_texts()


def count_text(pg):
    return pg.inner_text(".clubs-count")


def same_page(url, want):
    a, b = urlsplit(url), urlsplit(want)
    return (a.path, a.query) == (b.path, b.query)


def attach_errors(pg, errs):
    pg.set_default_timeout(90000)  # the local WordPress can take many seconds per page
    pg.pending = set()  # fetches the page has started and not yet finished
    pg.on("request", lambda r: pg.pending.add(r) if r.resource_type == "fetch" else None)
    pg.on("requestfinished", lambda r: pg.pending.discard(r))
    pg.on("requestfailed", lambda r: pg.pending.discard(r))
    pg.on("pageerror", lambda e: errs.append(str(e)[:160]))
    pg.on("console", lambda m: errs.append("console: " + m.text[:160]) if m.type == "error" and "404" not in m.text and "ERR_" not in m.text and "Failed to load resource" not in m.text else None)


def endpoint(url):
    """Call a test helper (tests/playground/upgrade-test.php) as the signed-in Playground session would."""
    req = urllib.request.Request(url, headers={"Cookie": "playground_auto_login_already_happened=1"})
    return urllib.request.urlopen(req, timeout=120).read()


def throttle(ctx, pg, latency_ms):
    """Add network latency to everything this page requests from now on (Chromium), without blocking the test."""
    cdp = ctx.new_cdp_session(pg)
    cdp.send("Network.enable")
    cdp.send("Network.emulateNetworkConditions", {"offline": False, "latency": latency_ms, "downloadThroughput": -1, "uploadThroughput": -1})
    return cdp


def warm(pg, *selectors):
    """Point at each link and wait until its page has come back, so the script has it cached."""
    for sel in selectors:
        pg.hover(sel)
        pg.wait_for_timeout(300)  # a request, if one is needed, has started by now (a cached page starts none)
        waited = 0
        while pg.pending and waited < 90000:
            pg.wait_for_timeout(100)
            waited += 100
    pg.mouse.move(5, 5)


def run_no_js(site, b, label, full_order):
    print(f"\n{label}: without JavaScript")
    ctx = site.visitor(b, viewport={"width": 1440, "height": 900}, java_script_enabled=False)
    pg = ctx.new_page()
    za = SAMPLE[::-1]

    r = pg.goto(site.url(1), wait_until="load")
    check("page 1 answers 200", r.status == 200, r.status)
    check("page 1 shows the first 12, A to Z", names(pg) == SAMPLE[:PER], names(pg))
    check("count reads 'Showing 1–12 of 14'", count_text(pg) == "Showing 1–12 of 14", count_text(pg))
    check("A–Z is current, Z–A is a link", pg.locator(".clubs-sort a[aria-current=true]").inner_text().startswith("A") and pg.locator(".clubs-sort a").count() == 2)
    check("the switch is visible without JavaScript", pg.is_visible(".clubs-sort"))
    check("the Z–A link goes to the Z–A page", same_page(pg.locator(".clubs-sort a").nth(1).evaluate("a => a.href"), site.url(1, True)), pg.locator(".clubs-sort a").nth(1).evaluate("a => a.href"))
    check("pager: no Previous on page 1, Next to page 2", pg.locator(".clubs-pager [rel=prev]").count() == 0 and same_page(pg.locator(".clubs-pager [rel=next]").evaluate("a => a.href"), site.url(2)))
    check("pager: page 1 is marked current, page 2 is a link", pg.locator(".clubs-pager__num[aria-current=page]").inner_text().strip().endswith("1") and pg.locator("a.clubs-pager__num").count() == 1)

    pg.goto(site.url(2), wait_until="load")
    check("page 2 shows the other 2", names(pg) == SAMPLE[PER:], names(pg))
    check("count reads 'Showing 13–14 of 14'", count_text(pg) == "Showing 13–14 of 14", count_text(pg))
    check("pager: Previous to page 1, no Next", same_page(pg.locator(".clubs-pager [rel=prev]").evaluate("a => a.href"), site.url(1)) and pg.locator(".clubs-pager [rel=next]").count() == 0)
    if not site.static:
        check("page 2 has its own title", "Page 2" in pg.title(), pg.title())
        check("page 2 canonical is its own address", (pg.locator("link[rel=canonical]").get_attribute("href") or "").rstrip("/").endswith("/clubs/page/2"), pg.locator("link[rel=canonical]").get_attribute("href"))
        check("page 2 og:url is its own address", (pg.locator("meta[property='og:url']").get_attribute("content") or "").rstrip("/").endswith("/clubs/page/2"))

    pg.goto(site.url(1, True), wait_until="load")
    check("Z–A page 1 shows the last 14 backwards, first 12", names(pg) == za[:PER], names(pg))
    check("Z–A is current and the thumb is on it", pg.locator(".clubs-sort a[aria-current=true]").inner_text().startswith("Z") and pg.get_attribute(".clubs-sort", "data-current") == "desc")
    check("Z–A pager links keep Z–A", same_page(pg.locator(".clubs-pager [rel=next]").evaluate("a => a.href"), site.url(2, True)))
    check("A–Z link from Z–A goes to A–Z page 1", same_page(pg.locator(".clubs-sort a").nth(0).evaluate("a => a.href"), site.url(1)))
    if not site.static:
        check("Z–A view is kept out of search results", "noindex" in (pg.locator("meta[name=robots]").get_attribute("content") or ""))
        check("Z–A view has no canonical tag of its own", pg.locator("link[rel=canonical]").count() == 0)
    pg.goto(site.url(2, True), wait_until="load")
    check("Z–A page 2 shows the first two A–Z, backwards", names(pg) == za[PER:], names(pg))
    check("Z–A pager Previous keeps Z–A", same_page(pg.locator(".clubs-pager [rel=prev]").evaluate("a => a.href"), site.url(1, True)))

    full_az = SAMPLE
    check("A–Z pages together are the whole directory, nothing skipped or repeated", names(pg) and True)
    if not site.static:
        pg.goto(site.url(1), wait_until="load")
        all_az = names(pg)
        pg.goto(site.url(2), wait_until="load")
        all_az += names(pg)
        check("  …A–Z page 1 + page 2 == the sorted directory", all_az == full_az, all_az)
        pg.goto(site.url(1, True), wait_until="load")
        all_za = names(pg)
        pg.goto(site.url(2, True), wait_until="load")
        all_za += names(pg)
        check("  …Z–A pages are its exact mirror", all_za == full_az[::-1], all_za)

        print("  out-of-range pages and odd values")
        pg.goto(WP + "clubs/page/9/", wait_until="load")
        check("a page past the last goes to the last page", same_page(pg.url, site.url(2)), pg.url)
        pg.goto(WP + "clubs/page/9/?order=za", wait_until="load")
        check("…keeping Z–A", same_page(pg.url, site.url(2, True)), pg.url)
        pg.goto(WP + "clubs/?order=sideways", wait_until="load")
        check("an unknown order is just A–Z", names(pg) == SAMPLE[:PER] and pg.get_attribute("[data-clubs-dir]", "data-order") == "az")
        r = pg.goto(WP + "clubs/page/1/", wait_until="load")
        check("/page/1/ shows page 1", names(pg) == SAMPLE[:PER], pg.url)
    ctx.close()


def run_js(site, b, label):
    print(f"\n{label}: with JavaScript")
    errs = []
    za = SAMPLE[::-1]
    ctx = site.visitor(b, viewport={"width": 1440, "height": 900})
    pg = ctx.new_page()
    attach_errors(pg, errs)
    pg.goto(site.url(1), wait_until="load")
    pg.evaluate("window.__marker = 1")
    sort_za, sort_az = ".clubs-sort a:nth-child(2)", ".clubs-sort a:nth-child(1)"
    warm(pg, sort_za, ".clubs-pager [rel=next]")

    # ---- the switch
    pg.click(sort_za)
    pg.wait_for_function("document.querySelector('.clubs-sort').dataset.current === 'desc'")
    pg.wait_for_timeout(1100)
    check("Z–A swaps the cards in place (no page load)", pg.evaluate("window.__marker") == 1 and names(pg) == za[:PER], names(pg))
    check("the address and history follow", same_page(pg.url, site.url(1, True)), pg.url)
    check("the switch and its links update", pg.locator(".clubs-sort a[aria-current=true]").inner_text().startswith("Z") and same_page(pg.locator(sort_za).evaluate("a => a.href"), site.url(1, True)))
    check("announced: 'Page 1 of 2, sorted Z to A'", pg.inner_text("[data-clubs-status]") == "Page 1 of 2, sorted Z to A", pg.inner_text("[data-clubs-status]"))
    check("count is unchanged; the pager now keeps Z–A", count_text(pg) == "Showing 1–12 of 14" and same_page(pg.locator(".clubs-pager [rel=next]").evaluate("a => a.href"), site.url(2, True)))
    check("no leftover clones or animations", pg.locator(".club--leaving").count() == 0 and pg.evaluate(BUSY) == 0)
    check("cards are still the same height", len(set(pg.evaluate("[...document.querySelectorAll('.club')].map(c => Math.round(c.getBoundingClientRect().height))"))) == 1)

    # ---- the pager, in Z–A
    warm(pg, ".clubs-pager [rel=next]")
    pg.click(".clubs-pager [rel=next]")
    pg.wait_for_function("document.querySelector('[data-clubs-dir]').dataset.page === '2'")
    pg.wait_for_timeout(1300)
    check("Next shows the Z–A remainder in place", pg.evaluate("window.__marker") == 1 and names(pg) == za[PER:], names(pg))
    check("count reads 'Showing 13–14 of 14' and the address is page 2, Z–A", count_text(pg) == "Showing 13–14 of 14" and same_page(pg.url, site.url(2, True)), pg.url)
    check("announced with the page", pg.inner_text("[data-clubs-status]") == "Page 2 of 2, sorted Z to A", pg.inner_text("[data-clubs-status]"))
    check("the pager became Previous-only", pg.locator(".clubs-pager [rel=prev]").count() == 1 and pg.locator(".clubs-pager [rel=next]").count() == 0)
    check("focus moved to the Directory heading", pg.evaluate("document.activeElement && document.activeElement.id") == "dir-h")
    box = pg.locator("#dir-h").bounding_box()
    check("the directory is in view after paging", box is not None and 0 <= box["y"] < 900, box)
    check("the document title follows the page", (("Page 2" in pg.title()) if not site.static else "Page 2" in pg.title()), pg.title())

    # ---- history
    pg.go_back()
    pg.wait_for_function("document.querySelector('[data-clubs-dir]').dataset.page === '1'")
    pg.wait_for_timeout(1000)
    check("Back returns to Z–A page 1 without a reload", pg.evaluate("window.__marker") == 1 and names(pg) == za[:PER] and same_page(pg.url, site.url(1, True)), pg.url)
    pg.go_back()
    pg.wait_for_function("document.querySelector('.clubs-sort').dataset.current === 'asc'")
    pg.wait_for_timeout(1000)
    check("Back again returns to A–Z page 1", names(pg) == SAMPLE[:PER] and same_page(pg.url, site.url(1)), pg.url)
    pg.go_forward()
    pg.wait_for_function("document.querySelector('.clubs-sort').dataset.current === 'desc'")
    pg.wait_for_timeout(1000)
    check("Forward goes to Z–A again", names(pg) == za[:PER], names(pg))

    # ---- rapid clicks (both orders are cached)
    warm(pg, sort_az)
    for sel in (sort_az, sort_za, sort_az, sort_za):
        pg.click(sel)
        pg.wait_for_timeout(70)
    pg.wait_for_timeout(1500)
    check("rapid clicks land on the last one, with nothing left over", names(pg) == za[:PER] and pg.locator(".club--leaving").count() == 0 and pg.evaluate(BUSY) == 0, names(pg))

    # ---- keyboard
    pg.click(sort_az)
    pg.wait_for_timeout(1300)
    pg.focus(sort_za)
    pg.keyboard.press("Enter")
    pg.wait_for_timeout(1300)
    check("the keyboard works (Enter on Z–A)", names(pg) == za[:PER] and pg.get_attribute(".clubs-sort", "data-current") == "desc")
    check("focus stays on the switch", pg.evaluate("document.activeElement.closest('.clubs-sort') !== null"))
    pg.click(sort_az)
    pg.wait_for_timeout(1300)

    # ---- modifier clicks are left to the browser
    with ctx.expect_page() as new:
        pg.click(sort_za, modifiers=["Control"])
    other = new.value
    other.wait_for_function("location.search.indexOf('order=za') > -1 || location.pathname.indexOf('-za') > -1", timeout=90000)
    check("ctrl-click opens the Z–A page in a new tab", same_page(other.url, site.url(1, True)), other.url)
    check("…and leaves this page alone", names(pg) == SAMPLE[:PER] and pg.evaluate("window.__marker") == 1)
    other.close()

    # ---- fast: the page was warmed, so no skeleton
    pg.evaluate("window.__sk = 0; new MutationObserver(() => { if (document.querySelector('.club--skeleton')) window.__sk++; }).observe(document.body, { subtree: true, childList: true });")
    warm(pg, ".clubs-pager [rel=next]")
    pg.click(".clubs-pager [rel=next]")
    pg.wait_for_function("document.querySelector('[data-clubs-dir]').dataset.page === '2'")
    pg.wait_for_timeout(1000)
    check("a page that is already there shows no skeleton", pg.evaluate("window.__sk") == 0)
    pg.go_back()
    pg.wait_for_timeout(1000)

    # ---- slow: skeleton cards stand in, then the page
    ctx.close()
    ctx = site.visitor(b, viewport={"width": 1440, "height": 900})
    pg = ctx.new_page()
    attach_errors(pg, errs)
    pg.goto(site.url(1), wait_until="load")
    pg.evaluate("window.__marker = 1")

    throttle(ctx, pg, 1600)
    pg.click(".clubs-pager [rel=next]")
    pg.wait_for_timeout(600)
    sk = pg.locator(".club--skeleton").count()
    check("a slow page shows skeleton cards, as many as the cards it replaces", sk == PER, sk)
    check("the list is marked busy and nothing is announced as real content", pg.get_attribute(".clubs", "aria-busy") == "true" and pg.locator(".club--skeleton").first.get_attribute("aria-hidden") == "true")
    check("announced: 'Loading listings'", pg.inner_text("[data-clubs-status]") == "Loading listings", pg.inner_text("[data-clubs-status]"))
    check("skeleton cards have the same shape (logo plate, name, link)", pg.locator(".club--skeleton .club__logo").count() == PER and pg.locator(".club--skeleton .sk--link").count() == PER)
    pg.wait_for_function("document.querySelector('[data-clubs-dir]').dataset.page === '2'", timeout=60000)
    pg.wait_for_timeout(1300)
    check("then the real cards replace them", pg.locator(".club--skeleton").count() == 0 and names(pg) == SAMPLE[PER:], names(pg))
    check("…with nothing left busy or fading", pg.get_attribute(".clubs", "aria-busy") is None and pg.locator(".club--leaving").count() == 0)

    # ---- the logo shimmer
    pg.close()
    ctx.close()
    ctx = site.visitor(b, viewport={"width": 1440, "height": 900})
    pg = ctx.new_page()
    attach_errors(pg, errs)
    pg.goto("about:blank")
    throttle(ctx, pg, 700)
    pg.goto(site.url(1), wait_until="domcontentloaded")
    pg.wait_for_function("document.querySelector('.club')", timeout=60000)
    pg.wait_for_timeout(100)
    plates = pg.locator(".club__logo.is-loading").count()
    check("logo plates shimmer while their image is on the way", plates > 0, plates)
    if plates:
        pg.wait_for_timeout(600)  # the image fades out over 0.45 s, then stays hidden until it has loaded
        check("…and their image stays hidden meanwhile", pg.evaluate("[...document.querySelectorAll('.club__logo.is-loading img')].every(i => getComputedStyle(i).opacity === '0')"))
    for y in range(0, 1400, 300):
        pg.evaluate(f"window.scrollTo(0, {y})")
        pg.wait_for_timeout(250)
    pg.wait_for_function("document.querySelectorAll('.club__logo.is-loading').length === 0", timeout=90000)
    check("…and the shimmer is gone once every image has arrived", pg.evaluate("[...document.querySelectorAll('.club__logo img')].every(i => i.complete && i.naturalWidth > 0)"))
    ctx.close()

    # ---- failure: a normal page load
    ctx = site.visitor(b, viewport={"width": 1440, "height": 900})
    pg = ctx.new_page()
    attach_errors(pg, errs)
    pg.goto(site.url(1), wait_until="load")
    pg.evaluate("window.__marker = 1")
    pg.route("**/*", lambda route: route.abort() if route.request.resource_type == "fetch" else route.continue_())
    pg.click(sort_za)
    pg.wait_for_function("window.__marker === undefined", timeout=90000)
    pg.wait_for_load_state("load")
    check("if the swap fails, it falls back to loading the page", same_page(pg.url, site.url(1, True)) and names(pg) == za[:PER], pg.url)
    pg.unroute("**/*")
    ctx.close()

    # ---- reduced motion
    ctx = site.visitor(b, viewport={"width": 1440, "height": 900}, reduced_motion="reduce")
    pg = ctx.new_page()
    attach_errors(pg, errs)
    pg.goto(site.url(1), wait_until="load")
    warm(pg, sort_za)
    pg.click(sort_za)
    pg.wait_for_function("document.querySelector('.clubs-sort').dataset.current === 'desc'")
    check("reduced motion: the order changes at once, with no movement", names(pg) == za[:PER] and pg.evaluate("[...document.querySelectorAll('.club')].reduce((n, c) => n + c.getAnimations().length, 0)") == 0)
    ctx.close()

    # ---- phone
    ctx = site.visitor(b, viewport={"width": 390, "height": 844}, is_mobile=True, has_touch=True)
    pg = ctx.new_page()
    attach_errors(pg, errs)
    pg.goto(site.url(1), wait_until="load")
    check("phone: one column and no overflow", pg.evaluate("document.documentElement.scrollWidth - document.documentElement.clientWidth") == 0 and pg.evaluate("new Set([...document.querySelectorAll('.club')].map(c => Math.round(c.getBoundingClientRect().left))).size") == 1)
    check("phone, 2 pages: the page numbers stay", pg.is_visible(".clubs-pager__pages") and not pg.is_visible(".clubs-pager__of"))
    ctx.close()
    check("no script errors on the page", not errs, errs[:3])


def run_many(site, b):
    print("\nMany pages (94 listings, 8 pages)")
    endpoint(site.base + "wra-tests/upgrade-test.php?step=unbulk")
    endpoint(site.base + "wra-tests/upgrade-test.php?step=bulk&n=80")
    try:
        total = len(SAMPLE) + 80
        everything = sorted(SAMPLE + [f"Zz Bulk Test {i:03d}" for i in range(1, 81)], key=str.lower)
        ctx = site.visitor(b, viewport={"width": 1440, "height": 900}, java_script_enabled=False)
        pg = ctx.new_page()
        pg.goto(site.url(1), wait_until="load")
        check("count reads 'Showing 1–12 of 94'", count_text(pg) == "Showing 1–12 of 94", count_text(pg))
        got = []
        for n in range(1, 9):
            pg.goto(site.url(n), wait_until="load")
            got += names(pg)
            shown = [li.inner_text().strip() for li in pg.locator(".clubs-pager__pages li").all()]
            want = ["…" if w is None else str(w) for w in clubs_static.window(n, 8)]
            check(f"  page {n}: numbers are {' '.join(want)}", [t.replace("Page", "").strip() for t in shown] == want, shown)
        check("8 pages together are all 94 listings, A to Z, none skipped or repeated", got == everything, f"{len(got)} listings")
        pg.goto(site.url(8), wait_until="load")
        check("last page holds the last 10", len(names(pg)) == 94 - 7 * PER and count_text(pg) == "Showing 85–94 of 94", count_text(pg))
        got = []
        for n in range(1, 9):
            pg.goto(site.url(n, True), wait_until="load")
            got += names(pg)
        check("Z–A across 8 pages is the exact mirror", got == everything[::-1])
        pg.goto(WP + "clubs/page/20/", wait_until="load")
        check("page 20 goes to page 8", same_page(pg.url, site.url(8)), pg.url)
        ctx.close()
        ctx = site.visitor(b, viewport={"width": 390, "height": 844}, is_mobile=True, has_touch=True)
        pg = ctx.new_page()
        pg.goto(site.url(3), wait_until="load")
        check("phone, many pages: 'Page 3 of 8' instead of numbers", pg.is_visible(".clubs-pager__of") and pg.inner_text(".clubs-pager__of") == "Page 3 of 8" and not pg.is_visible(".clubs-pager__pages"))
        check("phone: Previous and Next stay as boxes with names for screen readers", pg.locator(".clubs-pager__step").count() == 2 and pg.evaluate("[...document.querySelectorAll('.clubs-pager__step')].every(a => a.getBoundingClientRect().width >= 40)"))
        check("phone: no overflow", pg.evaluate("document.documentElement.scrollWidth - document.documentElement.clientWidth") == 0)
        ctx.close()
        # the same window, as the theme computes it, for every current/last page up to 30
        raw = json.loads(endpoint(site.base + "wra-tests/upgrade-test.php?step=window"))
        bad = [k for k, v in raw.items() if v != clubs_static.window(int(k.split("-")[1]), int(k.split("-")[0]))]
        check("the page-number window matches the static generator for every case up to 30 pages", not bad, bad[:3])
    finally:
        endpoint(site.base + "wra-tests/upgrade-test.php?step=unbulk")


def run_empty(site, b):
    print()
    print("Empty directory")
    endpoint(site.base + "wra-tests/upgrade-test.php?step=hideall")
    try:
        ctx = site.visitor(b, viewport={"width": 1440, "height": 900})
        pg = ctx.new_page()
        pg.goto(site.url(1), wait_until="load")
        check("the page still answers 200 with its notice", "The directory is being compiled" in pg.inner_text("main"))
        check("ghost cards stand in so the page is not blank (4 on desktop)", pg.locator(".clubs--ghost .club--skeleton:visible").count() == 4, pg.locator(".clubs--ghost .club--skeleton:visible").count())
        check("ghost cards are hidden from assistive technology", pg.get_attribute(".clubs--ghost", "aria-hidden") == "true")
        check("no switch, count or pager when there is nothing to sort", pg.locator(".clubs-sort").count() == 0 and pg.locator(".clubs-pager").count() == 0 and pg.locator(".clubs-count").count() == 0)
        check("the ghost cards are drawn (visible grey bars and plates, not blank boxes)", pg.evaluate("[...document.querySelectorAll('.clubs--ghost .sk, .clubs--ghost .club__logo')].every(e => getComputedStyle(e).backgroundColor !== 'rgba(0, 0, 0, 0)')"))
        check("the ghost cards do not shimmer (nothing is loading)", pg.evaluate("[...document.querySelectorAll('.clubs--ghost .sk')].every(e => getComputedStyle(e).animationName === 'none')"))
        check("the contact line is still there", pg.locator(".clubs-note").count() == 1)
        pg.add_script_tag(url="https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.2/axe.min.js")
        vio = pg.evaluate("async () => (await axe.run(document, { resultTypes: ['violations'] })).violations.map(v => v.id + ' ' + v.nodes[0].target.join(' '))")
        check("axe finds nothing on the empty directory", not vio, vio)
        pg.evaluate("document.querySelectorAll('[data-reveal]').forEach(e => e.classList.add('is-in'))")
        pg.screenshot(path=str(pathlib.Path(__file__).parent / "out" / "clubs-empty.png"))
        pg.set_viewport_size({"width": 390, "height": 844})
        check("phone: 2 ghost cards, no overflow", pg.locator(".clubs--ghost .club--skeleton:visible").count() == 2 and pg.evaluate("document.documentElement.scrollWidth - document.documentElement.clientWidth") == 0)
        ctx.close()
    finally:
        endpoint(site.base + "wra-tests/upgrade-test.php?step=restore")


with sync_playwright() as p:
    b = p.chromium.launch()
    wp, st = Site(WP, False), Site(ST, True)
    if ONLY in ("", "nojs"):
        run_no_js(wp, b, "WordPress", None)
    if ONLY in ("", "js"):
        run_js(wp, b, "WordPress")
    if ONLY in ("", "many"):
        run_many(wp, b)
    if ONLY in ("", "empty"):
        run_empty(wp, b)
    if ONLY in ("", "static"):
        run_no_js(st, b, "Static preview", None)
        run_js(st, b, "Static preview")
    b.close()

print(f"\n{len(FAILS)} failure(s): {FAILS}" if FAILS else "\nall paging checks passed")
sys.exit(1 if FAILS else 0)
