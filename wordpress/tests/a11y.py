"""Accessibility (axe), console errors, broken images, internal links and
horizontal overflow on every WordPress page, as a logged-out visitor, at
desktop and phone widths. Also renders a page the team might add later
(page.php) by creating and deleting a temporary page.

usage: python wordpress/tests/a11y.py [WP_BASE]
"""
import sys
import urllib.request
from playwright.sync_api import sync_playwright

WP = next((x for x in sys.argv[1:] if x.startswith("http")), "http://127.0.0.1:9400/")
AXE = "https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.2/axe.min.js"
PATHS = ["", "about/", "frequency-coordination/", "repeater-coordination/", "arcs/", "membership/", "resources/",
         "news/", "contact/", "privacy-policy/", "terms-of-use/", "no-such-page/", "category/news/", "?s=coordination",
         "wisconsin-repeater-alliance-receives-federal-501c3-public-charity-status/",
         "an-open-letter-to-the-wisconsin-amateur-radio-community/", "news-welcome-to-wra/"]
FAILS = []

with sync_playwright() as p:
    b = p.chromium.launch()
    READONLY = "--readonly" in sys.argv  # a real site: no temporary page, nothing written
    adm = b.new_context()
    a = adm.new_page()
    tmp = None
    if not READONLY:
        a.goto(WP + "wp-admin/", wait_until="load")
    tmp = None if READONLY else a.evaluate("""async () => wp.apiFetch({ path: '/wp/v2/pages', method: 'POST', data: { title: 'Test added page', status: 'publish',
        content: '<!-- wp:paragraph --><p>Body text added in the editor.</p><!-- /wp:paragraph --><!-- wp:heading --><h2 class="wp-block-heading">A heading</h2><!-- /wp:heading -->' } })""")
    paths = PATHS + ([tmp["link"].replace(WP, "")] if tmp else [])

    seen_links = set()
    for width in (1440, 390):
        ctx = b.new_context(viewport={"width": width, "height": 900})
        pg = ctx.new_page()
        pg.goto(WP)
        ctx.clear_cookies()
        ctx.add_cookies([{"name": "playground_auto_login_already_happened", "value": "1", "url": WP}])
        for path in paths:
            errs = []
            pg.on("console", lambda m: errs.append(m.text) if m.type == "error" else None)
            pg.on("pageerror", lambda e: errs.append(str(e)))
            resp = pg.goto(WP + path, wait_until="load")
            pg.evaluate("document.querySelectorAll('[data-reveal]').forEach(e=>e.classList.add('is-in'))")
            h = pg.evaluate("document.documentElement.scrollHeight")
            for y in range(0, h, 800):
                pg.evaluate(f"window.scrollTo(0,{y})")
                pg.wait_for_timeout(30)
            pg.wait_for_timeout(2500)  # let entrance animations (up to ~2.1s) finish before measuring contrast
            over = pg.evaluate("document.documentElement.scrollWidth - document.documentElement.clientWidth")
            broken = pg.evaluate("[...document.images].filter(i=>i.complete && i.naturalWidth===0).map(i=>i.src)")
            h1 = pg.evaluate("document.querySelectorAll('h1').length")
            problems = []
            if over > 0:
                problems.append(f"overflow {over}px")
            if broken:
                problems.append(f"broken images {broken[:3]}")
            if h1 != 1:
                problems.append(f"{h1} h1 elements")
            errs = [e for e in errs if not ("404" in e and path == "no-such-page/")]
            if errs:
                problems.append(f"console {errs[:3]}")
            if width == 1440:
                pg.add_script_tag(url=AXE)
                vio = pg.evaluate("async () => (await axe.run(document, {resultTypes:['violations']})).violations.map(v => v.id + ' x' + v.nodes.length + ' ' + v.nodes[0].target.join(' '))")
                if vio:
                    problems.append(f"axe {vio}")
                here = pg.url.split("#")[0]
                for href in pg.evaluate("[...document.querySelectorAll('a[href]')].map(a=>a.href)"):
                    base = href.split("#")[0]
                    if base.startswith(WP) and "/wp-admin" not in base and base != here:
                        seen_links.add(base)
            code = resp.status if resp else 0
            expected = 404 if path == "no-such-page/" else 200
            if code != expected:
                problems.append(f"HTTP {code}")
            label = f"{width:4d} /{path}"
            print(("FAIL " if problems else "PASS ") + label + ("  " + "; ".join(problems) if problems else ""))
            if problems:
                FAILS.append(label)
            pg.remove_listener("console", pg.listeners("console")[0]) if hasattr(pg, "listeners") else None
        ctx.close()

    dead = []
    for href in sorted(seen_links):
        try:
            req = urllib.request.Request(href, headers={"Cookie": "playground_auto_login_already_happened=1"}, method="GET")
            urllib.request.urlopen(req)
        except urllib.error.HTTPError as e:
            if e.code >= 400:
                dead.append(f"{e.code} {href}")
    print(("FAIL " if dead else "PASS ") + f"internal links: {len(seen_links)} checked" + (f", dead: {dead}" if dead else ""))
    if dead:
        FAILS.append("links")

    if tmp:
        a.evaluate(f"async () => wp.apiFetch({{ path: '/wp/v2/pages/{tmp['id']}?force=true', method: 'DELETE' }})")
    b.close()

print(f"\n{len(FAILS)} failed" if FAILS else "\nall passed")
sys.exit(1 if FAILS else 0)
