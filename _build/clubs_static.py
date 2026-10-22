"""Static preview of the Radio Clubs & Sponsors directory (used by build.py).

One sample list (_build/clubs-sample.json) becomes four real pages, so the preview pages and
sorts like the WordPress site:

    clubs.html       A-Z, page 1          clubs-2.html       A-Z, page 2
    clubs-za.html    Z-A, page 1          clubs-za-2.html    Z-A, page 2

The card, count, sort and pager markup mirrors theme/template-parts/clubs.php (the WordPress
version), so the pixel comparison in wordpress/tests/compare.py holds page for page.
"""
import html
import json
import pathlib

SAMPLE = pathlib.Path(__file__).resolve().parent / "clubs-sample.json"
EN = "–"
LEFT, RIGHT, GAP = "←", "→", "…"


def load():
    return json.loads(SAMPLE.read_text(encoding="utf8"))


def page_count(data):
    return max(1, -(-len(data["clubs"]) // data["per_page"]))


def file_for(order, page):
    return "clubs" + ("-za" if order == "za" else "") + ("" if page == 1 else f"-{page}") + ".html"


def window(cur, last):
    """Page numbers to show (same rule as wra_clubs_window() in the theme): all of them up to 7,
    otherwise the first, the last and the neighbours of the current page, with None for a gap."""
    if last <= 7:
        return list(range(1, last + 1))
    keep = sorted({1, last} | {n for n in range(cur - 1, cur + 2) if 1 <= n <= last})
    out = []
    for n in keep:
        if out and n - out[-1] == 2:
            out.append(out[-1] + 1)  # a gap of one number shows the number
        elif out and n - out[-1] > 2:
            out.append(None)
        out.append(n)
    return out


def card(c, idx):
    name = html.escape(c["name"])
    if c.get("logo"):
        logo = (f'<div class="club__logo"><img src="assets/img/samples/{c["logo"]}" '
                f'width="{c["w"]}" height="{c["h"]}" alt="" loading="lazy"></div>')
    else:
        logo = ('<div class="club__logo club__logo--none"><img src="assets/img/brand/wra-logo-nav-144.webp" '
                'srcset="assets/img/brand/wra-logo-nav-144.webp 1x, assets/img/brand/wra-logo-nav-216.webp 2x" '
                'width="151" height="144" alt="" loading="lazy"></div>')
    link = ""
    if c.get("url"):
        link = ('\n              <a class="alink alink--ext club__link" href="' + html.escape(c["url"]) +
                '" target="_blank" rel="noopener">Visit website<span class="visually-hidden">: ' + name +
                ' (opens in a new tab)</span></a>')
    cls = "club club--link" if c.get("url") else "club"
    return "\n".join([
        f'          <li class="{cls}" data-club="s{idx}">',
        f'            {logo}',
        '            <div class="club__body">',
        f'              <h3 class="club__name">{name}</h3>{link}',
        '            </div>',
        '          </li>',
    ])


def block(data, order, page):
    """The directory for one order and page: heading, count, sort switch, cards and pager."""
    per = data["per_page"]
    ranked = sorted(enumerate(data["clubs"], start=1), key=lambda t: t[1]["name"].lower(), reverse=(order == "za"))
    total = len(ranked)
    last = page_count(data)
    shown = ranked[(page - 1) * per: page * per]
    if last > 1:
        count = f"Showing {(page - 1) * per + 1}{EN}{(page - 1) * per + len(shown)} of {total}"
    else:
        count = "1 listing" if total == 1 else f"{total} listings"
    current_az = ' aria-current="true"' if order == "az" else ""
    current_za = ' aria-current="true"' if order == "za" else ""
    out = [
        f'        <div class="clubs-dir" id="directory" data-clubs-dir data-order="{order}" data-page="{page}" data-pages="{last}" data-total="{total}" data-per="{per}">',
        '          <div class="clubs-head">',
        '            <h2 class="eyebrow" id="dir-h" tabindex="-1">Directory</h2>',
        '            <div class="clubs-tools">',
        f'              <p class="clubs-count">{count}</p>',
        f'              <div class="clubs-sort" role="group" aria-label="Sort listings" data-clubs-sort data-current="{"asc" if order == "az" else "desc"}">',
        f'                <a href="{file_for("az", 1)}#directory"{current_az}><span aria-hidden="true">A{EN}Z</span><span class="visually-hidden">A to Z</span></a>',
        f'                <a href="{file_for("za", 1)}#directory"{current_za}><span aria-hidden="true">Z{EN}A</span><span class="visually-hidden">Z to A</span></a>',
        '              </div>',
        '              <p class="visually-hidden" role="status" data-clubs-status></p>',
        '            </div>',
        '          </div>',
        '          <div class="clubs-frame">',
        '          <ul class="clubs" role="list" data-reveal>',
    ]
    out += [card(c, i) for i, c in shown]
    out.append('          </ul>')
    out.append('          </div>')
    if last > 1:
        out.append(f'          <nav class="clubs-pager{" clubs-pager--few" if last <= 4 else ""}" aria-label="Directory pages">')
        if page > 1:
            out.append(f'            <a class="clubs-pager__step clubs-pager__step--prev" href="{file_for(order, page - 1)}#directory" rel="prev"><span aria-hidden="true">{LEFT}</span><span class="clubs-pager__word">Previous</span><span class="visually-hidden"> page</span></a>')
        out.append('            <ol class="clubs-pager__pages" role="list">')
        for n in window(page, last):
            if n is None:
                out.append(f'              <li class="clubs-pager__gap" aria-hidden="true">{GAP}</li>')
            elif n == page:
                out.append(f'              <li><span class="clubs-pager__num" aria-current="page"><span class="visually-hidden">Page </span>{n}</span></li>')
            else:
                out.append(f'              <li><a class="clubs-pager__num" href="{file_for(order, n)}#directory" aria-label="Page {n}">{n}</a></li>')
        out.append('            </ol>')
        out.append(f'            <p class="clubs-pager__of">Page {page} of {last}</p>')
        if page < last:
            out.append(f'            <a class="clubs-pager__step clubs-pager__step--next" href="{file_for(order, page + 1)}#directory" rel="next"><span class="clubs-pager__word">Next</span><span class="visually-hidden"> page</span><span aria-hidden="true">{RIGHT}</span></a>')
        out.append('          </nav>')
    out.append('        </div>')
    return "\n".join(out)


def variants(meta, body):
    """Yield (file name, page meta, page body) for every order and page of the directory."""
    data = load()
    for order in ("az", "za"):
        for page in range(1, page_count(data) + 1):
            v_meta = dict(meta)
            v_meta.pop("clubs_sample", None)
            if page > 1:
                v_meta["title"] = meta["title"].replace(f" {EN} ", f" {EN} Page {page} {EN} ", 1)
            if (order, page) != ("az", 1):
                v_meta["noindex"] = True  # one canonical page; the others stay out of the sitemap
            yield file_for(order, page), v_meta, body.replace("@@CLUBS@@", block(data, order, page))
