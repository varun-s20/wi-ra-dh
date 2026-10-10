"""Read-only interaction checks on a running site: menus, dropdowns, hero, FAQ
filter, checklist, Zeffy dialog, ARCS notice, forms present. Submits nothing.

usage: python wordpress/tests/interact.py [WP_BASE]
"""
import sys
from playwright.sync_api import sync_playwright

WP = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:9400/"
FAILS = []


def check(label, ok, detail=""):
    print(("PASS " if ok else "FAIL ") + label + (f"  ({detail})" if detail and not ok else ""))
    if not ok:
        FAILS.append(label)


with sync_playwright() as p:
    b = p.chromium.launch()

    # phone: drawer menu
    m = b.new_context(viewport={"width": 390, "height": 844}).new_page()
    m.goto(WP, wait_until="load")
    m.click(".hdr__menu")
    m.wait_for_timeout(600)
    check("phone: menu opens", m.evaluate("document.documentElement.classList.contains('menu-open')") and m.is_visible(".drawer__panel"))
    m.click(".drawer__toggle[aria-controls='dr-coordination']")
    m.wait_for_timeout(300)
    check("phone: Coordination group expands", m.is_visible("#dr-coordination"))
    m.keyboard.press("Escape")
    m.wait_for_timeout(500)
    check("phone: Escape closes the menu", not m.evaluate("document.documentElement.classList.contains('menu-open')"))

    # desktop: dropdown, hero, header on scroll
    d = b.new_context(viewport={"width": 1440, "height": 900}).new_page()
    d.goto(WP, wait_until="load")
    d.click("button.nav__toggle[data-nav='coordination']")
    d.wait_for_timeout(400)
    check("desktop: Coordination dropdown opens", d.is_visible("#nav-coordination"))
    d.keyboard.press("Escape")
    d.wait_for_timeout(9000)
    slides = d.evaluate("[...document.querySelectorAll('.hero__slide')].map(s => s.classList.contains('is-active'))")
    check("home: hero images rotate", slides.count(True) == 1 and slides.index(True) != 0, str(slides))
    loaded = d.evaluate("[...document.querySelectorAll('.hero__slide img')].every(i => i.complete && i.naturalWidth > 0)")
    check("home: all three hero images load", loaded)
    d.evaluate("window.scrollTo(0, 1200)")
    d.wait_for_timeout(600)
    check("header turns white on scroll", "is-solid" in (d.get_attribute(".hdr", "class") or ""))

    d.goto(WP + "arcs/", wait_until="load")
    notice = d.inner_text(".notice") if d.query_selector(".notice") else ""
    check("ARCS: status notice shows", "New user registration is disabled" in notice, notice[:120])

    d.goto(WP + "resources/", wait_until="load")
    n_all = d.evaluate("[...document.querySelectorAll('details.qa')].filter(q => q.offsetParent !== null).length")
    filt = d.query_selector("input[type=search]")
    if filt:
        filt.fill("interference")
        d.wait_for_timeout(500)
        n_f = d.evaluate("[...document.querySelectorAll('details.qa')].filter(q => q.offsetParent !== null).length")
        check("resources: FAQ filter narrows the list", 0 < n_f < n_all, f"{n_f} of {n_all}")
    docs = d.evaluate("[...document.querySelectorAll('.doc a[href$=\".pdf\"]')].length")
    check("resources: three PDF links", docs == 3, str(docs))
    check("resources: volunteer form present with endpoint", bool(d.get_attribute("#volunteer form[data-form], form[data-form]", "data-endpoint")))

    d.goto(WP + "repeater-coordination/#checklist", wait_until="load")
    boxes = d.query_selector_all(".checklist input[type=checkbox]")
    check("checklist: 12 items", len(boxes) == 12, str(len(boxes)))

    d.goto(WP + "contact/", wait_until="load")
    ep = d.get_attribute("form[data-form]", "data-endpoint") or ""
    check("contact: form posts to this site", ep.startswith(WP.rstrip("/")) and ep.endswith("/wra/v1/message"), ep)

    d.goto(WP + "membership/", wait_until="load")
    d.click("[zeffy-form-link] >> nth=0")
    d.wait_for_timeout(1500)
    check("membership: Zeffy payment dialog opens", d.evaluate("!!document.querySelector('dialog.zeffy[open] iframe')"))
    b.close()

print(f"\n{len(FAILS)} failed" if FAILS else "\nall passed")
sys.exit(1 if FAILS else 0)
