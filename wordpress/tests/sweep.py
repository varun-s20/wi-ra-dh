"""Every page x every shared control, plus each page's own features, with
script errors recorded for the whole session on each page. Catches features
that break each other on one page only (the Resources menu bug). Submits no
form: form requests are blocked and counted.

usage: python wordpress/tests/sweep.py [BASE] [--static]
  BASE      site root, default http://127.0.0.1:9400/
  --static  BASE is the static preview (page.html addresses)
"""
import re
import sys
from urllib.parse import urlsplit

from playwright.sync_api import sync_playwright

args = [a for a in sys.argv[1:] if not a.startswith("--")]
STATIC = "--static" in sys.argv
BASE = (args[0] if args else "http://127.0.0.1:9400/").rstrip("/") + "/"

if STATIC:
    PAGES = ["index.html", "about.html", "coordination.html", "coordination-process.html", "arcs.html",
             "membership.html", "resources.html", "news.html", "clubs.html", "contact.html", "privacy.html", "terms.html",
             "404.html", "news-501c3-public-charity-status.html", "news-arcs-development-update.html",
             "news-open-letter.html", "news-war-transition.html", "news-welcome.html"]
else:
    PAGES = ["", "about/", "frequency-coordination/", "repeater-coordination/", "arcs/", "membership/",
             "resources/", "news/", "clubs/", "contact/", "privacy-policy/", "terms-of-use/", "no-such-page/",
             "wisconsin-repeater-alliance-receives-federal-501c3-public-charity-status/",
             "arcs-amateur-radio-coordination-system/",
             "an-open-letter-to-the-wisconsin-amateur-radio-community/",
             "wisconsin-association-of-repeaters-transition/", "news-welcome-to-wra/",
             "category/news/", "?s=coordination"]

FAILS = []
# Zeffy's bot protection refuses browsers that call themselves HeadlessChrome
UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
ORIGIN = "{0.scheme}://{0.netloc}".format(urlsplit(BASE))


def own_page_error(e):
    """An uncaught error in this site's code. Errors inside Zeffy's cross-origin
    frame reach the page with no stack (e.g. 'requestStorageAccess not allowed')."""
    return ORIGIN in (e.stack or "")


def own_console_error(m):
    """console.error from this site (not from Zeffy or other embedded pages)."""
    return m.type == "error" and (m.location or {}).get("url", "").startswith(ORIGIN) and "404" not in m.text


def check(page, label, ok, detail=""):
    if not ok:
        FAILS.append(f"{page}: {label}")
        print(f"  FAIL {label}" + (f"  ({detail})" if detail else ""))


def path_of(url):
    s = urlsplit(url)
    return s.path + ("?" + s.query if s.query else "")


def is_open(pg, name):
    return pg.evaluate(f"document.querySelector('#nav-{name}').closest('.nav__item').classList.contains('is-open')")


def clickable(pg, loc):
    """The element at the centre of loc is loc itself (nothing covering it)."""
    return pg.evaluate("""el => { const r = el.getBoundingClientRect();
        if (!r.width || !r.height) return false;
        const at = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
        return !!at && (at === el || el.contains(at)); }""", loc.element_handle())


def as_visitor(ctx, base):
    """Local Playground started with --login signs every new browser in as admin on
    its first request. Warm up, then keep only its "already happened" cookie so the
    checks see what a logged-out visitor sees (as compare.py and a11y.py do)."""
    jar = [{"name": "playground_auto_login_already_happened", "value": "1", "url": base}]
    ctx.add_cookies(jar)
    pg = ctx.new_page()
    pg.goto(base, wait_until="load")
    ctx.clear_cookies()
    ctx.add_cookies(jar)
    pg.close()
    return ctx


def desktop(b, rel):
    ctx = as_visitor(b.new_context(viewport={"width": 1440, "height": 900}, user_agent=UA, permissions=["clipboard-read", "clipboard-write"]), BASE)
    pg = ctx.new_page()
    errs, posts = [], []
    pg.on("pageerror", lambda e: errs.append(str(e)[:160]) if own_page_error(e) else None)
    pg.on("console", lambda m: errs.append("console: " + m.text[:160]) if own_console_error(m) else None)

    def block(route):
        if route.request.method == "POST" and route.request.url.startswith(ORIGIN):  # our forms; Zeffy's own traffic passes
            posts.append(route.request.url)
            return route.abort()
        route.continue_()
    pg.route("**/*", block)
    url = BASE + rel
    pg.goto(url, wait_until="load")
    pg.wait_for_timeout(400)
    here = path_of(pg.url)
    pg.add_style_tag(content="html { scroll-behavior: auto !important; }")  # instant scrolls for the checks

    # dropdowns: open, close (Escape, outside click), every link reachable
    names = pg.eval_on_selector_all("button.nav__toggle", "els => els.map(e => e.dataset.nav)")
    check(rel, "three dropdowns", len(names) == 3, str(names))
    for name in names:
        tog = pg.locator(f"button.nav__toggle[data-nav='{name}']")
        tog.click(); pg.wait_for_timeout(250)
        check(rel, f"{name} dropdown opens", is_open(pg, name))
        links = pg.locator(f"#nav-{name} a")
        for i in range(links.count()):
            check(rel, f"{name} link {i + 1} clickable", clickable(pg, links.nth(i)))
        pg.keyboard.press("Escape"); pg.wait_for_timeout(200)
        check(rel, f"{name} closes on Escape", not is_open(pg, name))
        tog.click(); pg.wait_for_timeout(200)
        pg.mouse.click(5, 700); pg.wait_for_timeout(200)
        check(rel, f"{name} closes on outside click", not is_open(pg, name))

    # links into the page we are on: follow each in sequence, menus keep working
    same = [(n, i) for n in names for i, h in enumerate(pg.eval_on_selector_all(f"#nav-{n} a", "els => els.map(e => e.href)"))
            if path_of(h.split("#")[0]) == here.split("#")[0] and "#" in h]
    for n, i in same:
        pg.click(f"button.nav__toggle[data-nav='{n}']"); pg.wait_for_timeout(250)
        a = pg.locator(f"#nav-{n} a").nth(i)
        want = a.get_attribute("href").split("#")[1]
        try:
            a.click(timeout=3000)
        except Exception:
            check(rel, f"same-page link #{want} can be clicked", False)
            continue
        pg.wait_for_timeout(700)
        check(rel, f"same-page link #{want}: goes there, panel closes",
              pg.url.endswith("#" + want) and not is_open(pg, n), pg.url)

    # header turns white on scroll, and back
    pg.evaluate("scrollTo(0, 1600)"); pg.wait_for_timeout(500)
    check(rel, "header solid on scroll", "is-solid" in pg.get_attribute(".hdr", "class"))
    pg.evaluate("scrollTo(0, 0)"); pg.wait_for_timeout(500)
    check(rel, "header clear at top", "is-solid" not in pg.get_attribute(".hdr", "class"))

    # scroll reveals: everything ends up shown
    pg.evaluate("""async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); } }""")
    pg.wait_for_timeout(900)
    hidden = pg.evaluate("[...document.querySelectorAll('[data-reveal]')].filter(e => !e.classList.contains('is-in')).length")
    check(rel, "all reveal sections shown after scrolling", hidden == 0, f"{hidden} not shown")
    pg.evaluate("scrollTo(0, 0)")

    # page features
    if pg.query_selector(".hero__slide"):
        check(rel, "hero has no pause control", not pg.query_selector(".hero__pause, [class*=pause]"))
    if pg.query_selector(".map__marks polygon"):
        pg.locator(".map__marks polygon").first.scroll_into_view_if_needed()
        pg.locator(".map__marks polygon").first.hover(force=True); pg.wait_for_timeout(300)
        check(rel, "map tooltip shows on hover", pg.evaluate("(() => { const t = document.querySelector('.map__tip'); return !!t && t.textContent.trim().length > 0 && getComputedStyle(t).visibility !== 'hidden' && getComputedStyle(t).opacity !== '0'; })()"))
    if pg.query_selector(".faq__filter input"):
        total = pg.evaluate("document.querySelectorAll('.qa').length")
        pg.fill(".faq__filter input", "interference"); pg.wait_for_timeout(250)
        shown = pg.evaluate("[...document.querySelectorAll('.qa')].filter(q => !q.hidden).length")
        check(rel, "FAQ filter narrows", 0 < shown < total, f"{shown}/{total}")
        pg.fill(".faq__filter input", "zzzzqqq"); pg.wait_for_timeout(250)
        check(rel, "FAQ 'no results' message", pg.is_visible(".faq__empty"))
        pg.fill(".faq__filter input", ""); pg.wait_for_timeout(250)
        check(rel, "FAQ filter clears", pg.evaluate("[...document.querySelectorAll('.qa')].filter(q => !q.hidden).length") == total)
        qid = pg.evaluate("document.querySelector('.qa').id")
        pg.goto(url.split("#")[0] + "#" + qid, wait_until="load"); pg.reload(wait_until="load"); pg.wait_for_timeout(400)
        check(rel, "FAQ question opens from its link", pg.evaluate(f"document.getElementById('{qid}').open"))
    if pg.query_selector(".checklist input[type=checkbox]"):
        boxes = pg.locator(".checklist input[type=checkbox]")
        boxes.nth(0).check(); boxes.nth(3).check()
        pg.reload(wait_until="load"); pg.wait_for_timeout(300)
        check(rel, "checklist remembers ticks", boxes.nth(0).is_checked() and boxes.nth(3).is_checked())
        if pg.query_selector("[data-clear-checklist]"):
            pg.click("[data-clear-checklist]"); pg.wait_for_timeout(200)
            check(rel, "checklist clears", not boxes.nth(0).is_checked() and not boxes.nth(3).is_checked())
    for form in pg.locator("form[data-form]").all():
        form.scroll_into_view_if_needed()
        form.locator("button[type=submit]").click(); pg.wait_for_timeout(400)
        bad = form.locator(".field.is-invalid").count()
        check(rel, f"form '{form.get_attribute('data-form')}' blocks an empty send with field errors", bad > 0 and not posts, f"invalid={bad} posts={posts}")
    if "contact" in rel:
        pg.goto(url.split("?")[0].split("#")[0] + "?topic=interference", wait_until="load"); pg.wait_for_timeout(300)
        val = pg.evaluate("(() => { const s = document.querySelector('form[data-form] select'); return s ? s.value : ''; })()")
        check(rel, "contact topic preselected from link", "interference" in val.lower(), val)
    if pg.query_selector("[zeffy-form-link]"):
        pg.locator("[zeffy-form-link]").first.click(); pg.wait_for_timeout(1200)
        check(rel, "Zeffy dialog opens", pg.evaluate("!!document.querySelector('dialog.zeffy[open]')"))
        pg.keyboard.press("Escape"); pg.wait_for_timeout(400)
        check(rel, "Zeffy dialog closes on Escape", pg.evaluate("!document.querySelector('dialog.zeffy[open]')"))
    if pg.query_selector("[data-copy-link]"):
        pg.click("[data-copy-link]"); pg.wait_for_timeout(300)
        check(rel, "copy link confirms", "copied" in pg.inner_text("[data-copy-link]").lower())

    # after everything on the page, the menu still works
    pg.keyboard.press("Escape")
    pg.click("button.nav__toggle[data-nav='coordination']"); pg.wait_for_timeout(250)
    check(rel, "menu still works after page features", is_open(pg, "coordination"))
    check(rel, "no script errors", not errs, "; ".join(errs[:3]))
    check(rel, "no form was sent", not posts, str(posts))
    ctx.close()


def phone(b, rel):
    ctx = as_visitor(b.new_context(viewport={"width": 390, "height": 844}, user_agent=UA, is_mobile=True, has_touch=True), BASE)
    pg = ctx.new_page()
    errs = []
    pg.on("pageerror", lambda e: errs.append(str(e)[:160]) if own_page_error(e) else None)
    pg.goto(BASE + rel, wait_until="load"); pg.wait_for_timeout(300)
    open_ = lambda: pg.evaluate("document.documentElement.classList.contains('menu-open')")
    pg.tap(".hdr__menu"); pg.wait_for_timeout(400)
    check(rel + " (phone)", "menu opens", open_())
    for t in pg.locator(".drawer__toggle").all():
        exp = t.get_attribute("aria-expanded")
        t.tap(); pg.wait_for_timeout(200)
        check(rel + " (phone)", f"group {t.get_attribute('aria-controls')} toggles", t.get_attribute("aria-expanded") != exp)
    pg.keyboard.press("Escape"); pg.wait_for_timeout(300)
    check(rel + " (phone)", "Escape closes the menu", not open_())
    pg.tap(".hdr__menu"); pg.wait_for_timeout(400)
    pg.tap(".hdr__menu"); pg.wait_for_timeout(400)
    check(rel + " (phone)", "menu button closes it", not open_())
    over = pg.evaluate("document.documentElement.scrollWidth > innerWidth")
    check(rel + " (phone)", "no sideways scrolling", not over)
    check(rel + " (phone)", "no script errors", not errs, "; ".join(errs[:3]))
    ctx.close()


with sync_playwright() as p:
    b = p.chromium.launch()
    for rel in PAGES:
        print("/" + rel)
        desktop(b, rel)
        phone(b, rel)
    b.close()

print(f"\n{len(FAILS)} failed" if FAILS else f"\nall passed ({len(PAGES)} pages, desktop + phone)")
sys.exit(1 if FAILS else 0)
