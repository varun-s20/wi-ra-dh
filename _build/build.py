"""Assemble the static site from _build/pages/*.html and the shared partials.

Each page file starts with a JSON front-matter comment:
    <!--{ "title": ..., "description": ..., "canonical": "/about/", "nav": "about",
          "crumbs": [["About WRA", null]], "preload": {...}, "head_extra": "...",
          "article": {"date": "2026-09-24", "author": "..."} }-->
followed by the page's <main>. The script adds the head, title block, menu and
footer, marks the current section, builds breadcrumbs and JSON-LD, injects the
repeater map on the home page, and writes sitemap.xml and robots.txt.

Run from anywhere:  python _build/build.py
"""
import html
import json
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent.parent
BUILD = ROOT / "_build"
SITE = "https://wi-ra.org"

head_tpl = (BUILD / "partials/head.html").read_text(encoding="utf8")
header_tpl = (BUILD / "partials/header.html").read_text(encoding="utf8")
footer_tpl = (BUILD / "partials/footer.html").read_text(encoding="utf8")
map_svg = (BUILD / "map.svg.html").read_text(encoding="utf8")
map_counts = json.loads((BUILD / "map-counts.json").read_text())

ORG = {
    "@type": "NGO",
    "@id": SITE + "/#org",
    "name": "Wisconsin Repeater Alliance, Inc.",
    "alternateName": ["WRA", "WI-RA"],
    "url": SITE + "/",
    "logo": SITE + "/assets/img/brand/wra-logo-640.webp",
    "email": "info@wi-ra.org",
    "address": {"@type": "PostalAddress", "streetAddress": "1148 N. Sunnyslope Dr", "addressLocality": "Mount Pleasant", "addressRegion": "WI", "postalCode": "53406", "addressCountry": "US"},
    "nonprofitStatus": "Nonprofit501c3",
    "taxID": "42-4481796",
    "areaServed": {"@type": "State", "name": "Wisconsin"},
    "description": "Fair, transparent and engineering-based frequency coordination for amateur radio repeater owners, radio clubs and operators across Wisconsin.",
}


def esc(s):
    return html.escape(s, quote=True)


def render_crumbs(crumbs):
    items = ['<li><a href="index.html">Home</a></li>']
    for label, href in crumbs:
        if href:
            items.append(f'<li><a href="{href}">{label}</a></li>')
        else:
            items.append(f'<li aria-current="page">{label}</li>')
    return '<nav class="crumbs" aria-label="Breadcrumb"><ol>' + "".join(items) + "</ol></nav>"


def jsonld_for(meta, path):
    graph = []
    if path == "index.html":
        graph.append(ORG)
    else:
        trail = [{"@type": "ListItem", "position": 1, "name": "Home", "item": SITE + "/"}]
        for i, (label, href) in enumerate(meta.get("crumbs", []), start=2):
            node = {"@type": "ListItem", "position": i, "name": re.sub("<[^>]+>", "", label)}
            node["item"] = SITE + (canonical_of(href) if href else meta["canonical"])
            trail.append(node)
        graph.append({"@type": "BreadcrumbList", "itemListElement": trail})
    art = meta.get("article")
    if art:
        graph.append({
            "@type": "NewsArticle",
            "headline": meta["h1"],
            "datePublished": art["date"],
            "author": {"@type": "Person", "name": art["author"]} if art.get("author") else {"@id": SITE + "/#org"},
            "publisher": {"@id": SITE + "/#org"} if path != "index.html" else None,
            "mainEntityOfPage": SITE + meta["canonical"],
        })
        if not any(g.get("@type") == "NGO" for g in graph):
            graph.append(ORG)
    data = {"@context": "https://schema.org", "@graph": graph}
    return '  <script type="application/ld+json">' + json.dumps(data, ensure_ascii=False) + "</script>"


CANON = {}


def canonical_of(href):
    return CANON.get(href.split("#")[0], "/" + href)


def build():
    pages = sorted((BUILD / "pages").glob("*.html"))
    parsed = []
    for p in pages:
        raw = p.read_text(encoding="utf8")
        m = re.match(r"\s*<!--(.*?)-->\s*", raw, re.S)
        meta = json.loads(m.group(1))
        body = raw[m.end():]
        out_name = meta.get("file", p.name)
        CANON[out_name] = meta["canonical"]
        parsed.append((out_name, meta, body))

    for out_name, meta, body in parsed:
        pre = meta.get("preload")
        preload = ""
        if pre:
            preload = (f'  <link rel="preload" as="image" href="{pre["href"]}"'
                       + (f' imagesrcset="{pre["srcset"]}" imagesizes="{pre.get("sizes", "100vw")}"' if pre.get("srcset") else "")
                       + ' fetchpriority="high">')
        head = (head_tpl
                .replace("{{title}}", esc(meta["title"]))
                .replace("{{description}}", esc(meta["description"]))
                .replace("{{canonical}}", meta["canonical"])
                .replace("{{og_type}}", "article" if meta.get("article") else "website")
                .replace("{{og_title}}", esc(meta.get("og_title", meta["title"])))
                .replace("{{preload}}", preload)
                .replace("{{head_extra}}", meta.get("head_extra", ""))
                .replace("{{jsonld}}", jsonld_for(meta, out_name)))

        header = header_tpl
        nav = meta.get("nav")
        if nav:  # the section this page belongs to
            header = header.replace(f'data-nav="{nav}"', f'data-nav="{nav}" data-current')
        # aria-current="page" on exact links in the nav, dropdowns and drawer (never on buttons)
        header = re.sub(rf'(<a (?:(?!class="btn)[^>])*href="{re.escape(out_name)}")', r'\1 aria-current="page"', header)

        body = body.replace("{{crumbs}}", render_crumbs(meta.get("crumbs", [])))
        body = (body.replace("@@MAP@@", map_svg)
                    .replace("@@V@@", str(map_counts["v"]))
                    .replace("@@U@@", str(map_counts["u"]))
                    .replace("@@X@@", str(map_counts["x"])))
        out = head + header + body.rstrip() + "\n\n" + footer_tpl
        assert "{{" not in out and "@@" not in out, out_name
        (ROOT / out_name).write_text(out, encoding="utf8")
        print(f"  {out_name:44s} {len(out):>7,d} bytes")

    # sitemap + robots
    urls = [meta["canonical"] for name, meta, _ in parsed if not meta.get("noindex")]
    sm = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">']
    for u in sorted(set(urls), key=lambda x: (x != "/", x)):
        sm.append(f"  <url><loc>{SITE}{u}</loc></url>")
    sm.append("</urlset>")
    (ROOT / "sitemap.xml").write_text("\n".join(sm) + "\n", encoding="utf8")
    (ROOT / "robots.txt").write_text(f"User-agent: *\nAllow: /\n\nSitemap: {SITE}/sitemap.xml\n", encoding="utf8")
    print(f"  sitemap.xml ({len(urls)} urls), robots.txt")


if __name__ == "__main__":
    build()
