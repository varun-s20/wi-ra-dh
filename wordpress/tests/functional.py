"""Functional checks on a running WordPress build (local Playground by default).

Covers what visitors and the WRA team actually do: send the Contact and
Volunteer forms, get spam dropped, read Messages, change the ARCS status, and
publish a News post that then appears on the home and News pages.

usage: python wordpress/tests/functional.py [WP_BASE]
"""
import json
import sys
import time
import urllib.parse
import urllib.request
from playwright.sync_api import sync_playwright

WP = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:9400/"
FAILS = []
RUN = str(int(time.time()))[-6:]  # makes this run's spam entries identifiable


def check(label, ok, detail=""):
    print(("PASS " if ok else "FAIL ") + label + (f"  ({detail})" if detail and not ok else ""))
    if not ok:
        FAILS.append(label)


def post_form(fields):
    data = urllib.parse.urlencode(fields, doseq=True).encode()
    req = urllib.request.Request(WP + "wp-json/wra/v1/message", data=data, headers={"Cookie": "playground_auto_login_already_happened=1"})
    try:
        with urllib.request.urlopen(req) as r:
            return r.status, json.loads(r.read())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read() or b"{}")


with sync_playwright() as p:
    browser = p.chromium.launch()

    # ---- visitor (logged out)
    ctx = browser.new_context(viewport={"width": 1280, "height": 900})
    page = ctx.new_page()
    page.goto(WP)
    ctx.clear_cookies()
    ctx.add_cookies([{"name": "playground_auto_login_already_happened", "value": "1", "url": WP}])

    page.goto(WP + "contact/?topic=interference", wait_until="load")
    check("contact: ?topic preselects the subject", page.input_value("#c-subject") == "Interference report", page.input_value("#c-subject"))
    page.fill("#c-name", "Test Visitor")
    page.fill("#c-call", "kd9tst")
    page.fill("#c-email", "visitor@example.com")
    page.fill("#c-msg", "Testing the contact form.\nSecond line.")
    page.check("form[data-form] input[name=consent]")
    page.wait_for_timeout(3200)  # the time trap rejects anything faster than 3 seconds
    page.click("form[data-form] button[type=submit]")
    page.wait_for_function("document.querySelector('.form__status').textContent.length > 0", timeout=20000)
    status = page.text_content(".form__status")
    check("contact: visitor sees the thank-you message", "Thank you" in status, status)

    page.goto(WP + "resources/#volunteer", wait_until="load")
    page.fill("#v-name", "Volunteer Person")
    page.fill("#v-email", "volunteer@example.com")
    page.check("input[value='Technical/Engineering']")
    page.check("input[value='Board/Leadership']")
    page.fill("#v-time", "A few hours a month")
    page.check("#volunteer form[data-form] input[name=consent]") if page.query_selector("#volunteer form[data-form]") else page.check("form[data-form] input[name=consent]")
    page.wait_for_timeout(3200)
    page.click("form[data-form] button[type=submit]")
    page.wait_for_function("document.querySelector('form[data-form] .form__status').textContent.length > 0", timeout=20000)
    check("volunteer: visitor sees the thank-you message", "Thank you" in page.text_content("form[data-form] .form__status"))

    # ---- spam and bad input, straight at the endpoint
    old = str(int(time.time()) - 60)
    s, _ = post_form({"_form": "contact", "_ts": old, "_hp": "bot", "name": "Spam" + RUN, "email": "spam@example.com", "subject": "Membership", "message": "buy", "consent": "yes"})
    check("spam: honeypot answered 200 (so bots learn nothing)", s == 200)
    # a timestamp that is not at least 3 seconds old must be dropped; it is set ahead of now so the
    # check does not depend on how slow the test server is (local WordPress Playground takes seconds per request)
    s, _ = post_form({"_form": "contact", "_ts": str(int(time.time()) + 60), "name": "Fast" + RUN, "email": "fast@example.com", "subject": "Membership", "message": "too fast", "consent": "yes"})
    check("spam: instant submission answered 200", s == 200)
    s, body = post_form({"_form": "contact", "_ts": old, "name": "No Email", "subject": "Membership", "message": "x", "consent": "yes"})
    check("validation: missing email rejected", s == 400 and body.get("error") == "missing_email", f"{s} {body}")
    s, body = post_form({"_form": "nope", "_ts": old})
    check("validation: unknown form rejected", s == 400)
    ctx.close()

    # ---- the WRA team (logged in as an administrator)
    adm = browser.new_context(viewport={"width": 1280, "height": 900})
    a = adm.new_page()
    a.goto(WP + "wp-admin/", wait_until="load")
    check("admin: logged in", a.query_selector("#wpadminbar") is not None)

    a.goto(WP + "wp-admin/edit.php?post_type=wra_message", wait_until="load")
    titles = [t.inner_text() for t in a.query_selector_all("#the-list .row-title")]
    check("messages: both real submissions saved", any("Interference report from Test Visitor (KD9TST)" in t for t in titles) and any("Volunteer interest from Volunteer Person" in t for t in titles), titles)
    check("messages: spam not saved", not any(("Spam" + RUN) in t or ("Fast" + RUN) in t for t in titles), titles)
    vol = [t for t in a.query_selector_all("#the-list .row-title") if "Volunteer Person" in t.inner_text()][0]
    vol.click()
    a.wait_for_load_state("load")
    body = a.inner_text("#wra-message")
    check("messages: both interest areas kept", "Technical/Engineering, Board/Leadership" in body, body[:300])
    check("messages: reply button offered", a.query_selector("#wra-message a.button-primary[href^='mailto:volunteer@example.com']") is not None)

    a.goto(WP + "wp-admin/admin.php?page=wra-settings", wait_until="load")
    a.fill("#wra-arcs-message", "Registration opens Monday. <a href=\"https://arcsonline.org/\">Sign in</a>")
    a.fill("#wra-arcs-updated", "2026-10-12")
    a.click("#submit")
    a.wait_for_load_state("load")
    a.goto(WP + "arcs/", wait_until="load")
    notice = a.inner_text(".notice")
    check("ARCS status: new message shows on the ARCS page", "Registration opens Monday." in notice and "Updated October 12, 2026" in notice, notice)
    a.goto(WP + "wp-admin/admin.php?page=wra-settings", wait_until="load")
    a.fill("#wra-arcs-message", "ARCS is up and running at arcsonline.org. New user registration is disabled while we complete setup and testing.")
    a.fill("#wra-arcs-updated", "2026-10-08")
    a.click("#submit")
    a.wait_for_load_state("load")

    # publish a News post the way the editor does, then remove it
    a.goto(WP + "wp-admin/", wait_until="load")
    new = a.evaluate("""async () => wp.apiFetch({ path: '/wp/v2/posts', method: 'POST', data: {
        title: 'Test post: spring meeting', status: 'publish', excerpt: 'Short summary for the cards.',
        content: '<!-- wp:paragraph --><p>First paragraph.</p><!-- /wp:paragraph -->',
        meta: { _wra_byline: 'Board of Directors' } } })""")
    a.goto(WP, wait_until="load")
    first = a.inner_text(".posts--home .post h3")
    check("news: new post is the first card on the home page", "Test post: spring meeting" in first, first)
    check("news: cards renumber to 1 of 3", a.inner_text(".posts--home .post__idx") == "1 of 3")
    a.goto(WP + "news/", wait_until="load")
    check("news: new post is the News page feature", "Test post: spring meeting" in a.inner_text(".feature h3"))
    a.goto(new["link"], wait_until="load")
    check("news: post page shows the byline", a.inner_text(".byline b") == "Board of Directors")
    check("news: post without an image falls back to the WRA logo", "wra-logo-320" in (a.get_attribute(".aside-plate img", "src") or ""))
    a.goto(WP + "wp-admin/", wait_until="load")
    a.evaluate(f"async () => wp.apiFetch({{ path: '/wp/v2/posts/{new['id']}?force=true', method: 'DELETE' }})")
    a.goto(WP, wait_until="load")
    check("news: deleting the post removes it from the home page", "Test post" not in a.inner_text(".posts--home"))
    adm.close()
    browser.close()

print(f"\n{len(FAILS)} failed" if FAILS else "\nall passed")
sys.exit(1 if FAILS else 0)
