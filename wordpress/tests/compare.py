"""Visual regression: the approved static design vs the WordPress build.

Screenshots every page from both servers (WordPress as a logged-out visitor),
at desktop and phone widths, and reports how much of each page differs.

usage: python wordpress/tests/compare.py [STATIC_BASE] [WP_BASE] [OUT_DIR]
defaults: http://127.0.0.1:8767/  http://127.0.0.1:9400/  wordpress/tests/out
"""
import sys
import pathlib
from PIL import Image, ImageChops
from playwright.sync_api import sync_playwright

STATIC = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8767/"
WP = sys.argv[2] if len(sys.argv) > 2 else "http://127.0.0.1:9400/"
OUT = pathlib.Path(sys.argv[3] if len(sys.argv) > 3 else pathlib.Path(__file__).parent / "out")
OUT.mkdir(parents=True, exist_ok=True)

PAIRS = [
    ("index.html", ""),
    ("about.html", "about/"),
    ("coordination.html", "frequency-coordination/"),
    ("coordination-process.html", "repeater-coordination/"),
    ("arcs.html", "arcs/"),
    ("membership.html", "membership/"),
    ("resources.html", "resources/"),
    ("news.html", "news/"),
    ("contact.html", "contact/"),
    ("privacy.html", "privacy-policy/"),
    ("terms.html", "terms-of-use/"),
    ("404.html", "this-page-does-not-exist/"),
    ("news-501c3-public-charity-status.html", "wisconsin-repeater-alliance-receives-federal-501c3-public-charity-status/"),
    ("news-open-letter.html", "an-open-letter-to-the-wisconsin-amateur-radio-community/"),
    ("news-war-transition.html", "wisconsin-association-of-repeaters-transition/"),
    ("news-welcome.html", "news-welcome-to-wra/"),
]
SETTLE = """() => {
  document.querySelectorAll('[data-reveal]').forEach(e => e.classList.add('is-in'));
  const f = document.querySelector('.map__fig'); if (f) f.classList.add('is-in');
  document.querySelectorAll('.hero__slide').forEach((s, n) => s.classList.toggle('is-active', n === 0));
  const h = document.querySelector('.hdr'); if (h) h.style.position = 'absolute';
}"""


def shoot(page, url, path):
    page.goto(url, wait_until="load", timeout=90000)
    page.evaluate("document.fonts.ready")
    h = page.evaluate("document.documentElement.scrollHeight")
    for y in range(0, h, 700):
        page.evaluate(f"window.scrollTo(0,{y})")
        page.wait_for_timeout(40)
    page.evaluate(SETTLE)
    page.evaluate("window.scrollTo(0,0)")
    page.wait_for_timeout(1500)
    page.evaluate("document.querySelectorAll('img').forEach(i=>{i.loading='eager'})")
    page.screenshot(path=str(path), full_page=True, animations="disabled")


def diff(a_path, b_path, out_path):
    a, b = Image.open(a_path).convert("RGB"), Image.open(b_path).convert("RGB")
    h = max(a.height, b.height)
    ca, cb = Image.new("RGB", (a.width, h), "white"), Image.new("RGB", (a.width, h), "white")
    ca.paste(a, (0, 0)); cb.paste(b.resize((a.width, b.height)) if b.width != a.width else b, (0, 0))
    d = ImageChops.difference(ca, cb).convert("L").point(lambda v: 255 if v > 40 else 0)
    changed = sum(d.histogram()[255:]) / (a.width * h)
    if changed > 0.002:
        strip = Image.new("RGB", (a.width * 3, h), "white")
        strip.paste(ca, (0, 0)); strip.paste(cb, (a.width, 0)); strip.paste(Image.merge("RGB", (d, d.point(lambda v: 0), d.point(lambda v: 0))), (a.width * 2, 0))
        strip.thumbnail((1800, 6000)); strip.save(out_path, quality=70)
    return changed, a.height, b.height


with sync_playwright() as p:
    browser = p.chromium.launch()
    worst = []
    for width, tag in ((1440, "d"), (390, "m")):
        ctx_s = browser.new_context(viewport={"width": width, "height": 900})
        ctx_w = browser.new_context(viewport={"width": width, "height": 900})
        ctx_w.add_cookies([{"name": "playground_auto_login_already_happened", "value": "1", "url": WP}])
        ps, pw = ctx_s.new_page(), ctx_w.new_page()
        # Playground may auto-login on the first request: warm up, then keep only the
        # "auto-login already happened" cookie so every capture is a logged-out visitor.
        pw.goto(WP, wait_until="load")
        ctx_w.clear_cookies()
        ctx_w.add_cookies([{"name": "playground_auto_login_already_happened", "value": "1", "url": WP}])
        pw.goto(WP, wait_until="load")
        assert not pw.query_selector("#wpadminbar"), "still logged in"
        errors = []
        pw.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)
        pw.on("pageerror", lambda e: errors.append(str(e)))
        pw.on("response", lambda r: errors.append(f"{r.status} {r.url}") if r.status >= 400 and "this-page-does-not-exist" not in r.url else None)
        for static, wp in PAIRS:
            name = f"{static[:-5]}-{tag}"
            shoot(ps, STATIC + static, OUT / f"{name}-static.png")
            shoot(pw, WP + wp, OUT / f"{name}-wp.png")
            changed, hs, hw = diff(OUT / f"{name}-static.png", OUT / f"{name}-wp.png", OUT / f"{name}-diff.jpg")
            flag = "OK " if changed <= 0.002 else "!! "
            print(f"{flag}{name:46s} differs {changed*100:5.2f}%   height static {hs} / wp {hw}")
            worst.append((changed, name))
        if errors:
            print("   browser errors on WordPress pages:", sorted(set(errors))[:8])
        ctx_s.close(); ctx_w.close()
    browser.close()
