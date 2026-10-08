#!/usr/bin/env python3
"""Baut die statische Website nach public/.

Seiteninhalte liegen in src/pages/*.html. Jede Datei beginnt mit einem
Kopfblock (zwischen <!--- und --->) mit Metadaten im Format "schluessel: wert".
Header, Footer und Kontakt-Dialog stehen einmal hier im Layout.

Aufruf:  python3 build.py
"""

import base64
import datetime
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).parent
SRC = ROOT / "src" / "pages"
OUT = ROOT / "public"

SITE = "https://concetta-sapienza.com"   # Domain bei Bedarf hier ändern
BRAND = "SAPiENZA"
EMAIL = "kontakt@concetta-sapienza.com"

NAV = [("/", "Startseite"), ("/beratung/", "Beratung"), ("/ueber-mich/", "Über mich")]
FOOTER_NAV = [
    ("/impressum/", "Impressum"),
    ("/datenschutz/", "Datenschutz"),
    ("/haftungshinweis/", "Haftungshinweis"),
]

# Setzt die Klasse "js" vor dem ersten Rendern (verhindert Aufblitzen der Scroll-Animationen).
INLINE_JS = "document.documentElement.classList.add('js')"
INLINE_JS_HASH = base64.b64encode(hashlib.sha256(INLINE_JS.encode()).digest()).decode()

ICON = {
    "close": '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>',
    "check": '<svg viewBox="0 0 64 64" aria-hidden="true"><path pathLength="1" d="M32 4a28 28 0 1 1 0 56a28 28 0 1 1 0-56Z"/><path pathLength="1" d="M20 33l8 8 16-17"/></svg>',
}

ORG_SCHEMA = {
    "@type": "ProfessionalService",
    "@id": SITE + "/#beratung",
    "name": BRAND,
    "alternateName": "SAPiENZA Gesundheitsberatung",
    "description": "Gesundheitsberatung mit psychologischen Aspekten in Kempten (Allgäu) – vor Ort, beim Spaziergang, online und telefonisch.",
    "url": SITE + "/",
    "logo": SITE + "/assets/img/logo.svg",
    "image": SITE + "/assets/img/og-image.jpg",
    "email": EMAIL,
    "address": {
        "@type": "PostalAddress",
        "streetAddress": "Am Feilbergbach 2b",
        "addressLocality": "Kempten (Allgäu)",
        "addressRegion": "Bayern",
        "addressCountry": "DE",
    },
    "areaServed": [
        {"@type": "City", "name": "Kempten (Allgäu)"},
        {"@type": "AdministrativeArea", "name": "Allgäu"},
        {"@type": "Country", "name": "Deutschland"},
    ],
    "priceRange": "0–80 €",
    "founder": {"@id": SITE + "/ueber-mich/#person"},
    "knowsLanguage": "de",
}


def esc(s):
    return (s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace('"', "&quot;"))


def parse(path):
    raw = path.read_text(encoding="utf-8")
    m = re.match(r"<!---\n(.*?)\n--->\n", raw, re.S)
    meta = {}
    for line in m.group(1).splitlines():
        k, _, v = line.partition(":")
        meta[k.strip()] = v.strip()
    return meta, raw[m.end():]


def logo_inline():
    """Logo als Inline-SVG, je Buchstabe eine Gruppe (für den Hover-Effekt im Header)."""
    raw = (OUT / "assets/img/logo.svg").read_text(encoding="utf-8")
    vb = re.search(r'viewBox="([^"]+)"', raw).group(1)
    d = re.search(r' d="([^"]+)"', raw).group(1)
    subs = ["M" + x for x in d.split("M") if x.strip()]

    def xr(path):
        n = [float(v) for v in re.findall(r"-?\d*\.?\d+", path)]
        return min(n[0::2]), max(n[0::2])

    groups = []
    for x0, x1, path in sorted((*xr(p), p) for p in subs):
        for g in groups:
            if x0 < g["x1"] - 0.5 and x1 > g["x0"] + 0.5:
                g["p"].append(path); g["x0"] = min(g["x0"], x0); g["x1"] = max(g["x1"], x1)
                break
        else:
            groups.append({"x0": x0, "x1": x1, "p": [path]})
    letters = "".join(f'<path d="{"".join(g["p"])}"/>' for g in groups)
    return (f'<svg class="brand__logo" viewBox="{vb}" width="413" height="61" aria-hidden="true" focusable="false">'
            f'<g fill="#33251F" fill-rule="evenodd">{letters}</g></svg>')


def header(current):
    items = []
    for href, label in NAV:
        cur = ' aria-current="page"' if href == current else ""
        items.append(f'<li><a href="{href}"{cur}>{label}</a></li>')
    return f"""<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>
<header class="site-header">
  <div class="site-header__inner">
    <a class="brand" href="/" aria-label="{BRAND} – zur Startseite">{logo_inline()}</a>
    <nav class="nav" aria-label="Hauptnavigation">
      <ul>{''.join(items)}</ul>
    </nav>
  </div>
</header>"""


def footer():
    links = "".join(f'<li><a href="{h}">{l}</a></li>' for h, l in FOOTER_NAV)
    main_links = "".join(f'<li><a href="{h}">{l}</a></li>' for h, l in NAV)
    year = datetime.date.today().year
    return f"""<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <img src="/assets/img/logo.svg" alt="{BRAND}" width="413" height="61" loading="lazy">
        <p>Gesundheitsberatung mit psychologischen Aspekten. In Kempten (Allgäu), beim Spaziergang, online und am Telefon.</p>
      </div>
      <nav class="footer-nav" aria-label="Seiten">
        <p class="footer-title">Seiten</p>
        <ul>{main_links}</ul>
      </nav>
      <nav class="footer-nav" aria-label="Rechtliches">
        <p class="footer-title">Rechtliches</p>
        <ul>{links}</ul>
      </nav>
    </div>
    <div class="footer-bottom">
      <span>© {year} {BRAND} · Concetta Sapienza</span>
      <span>Die Beratung ersetzt keine ärztliche oder psychotherapeutische Behandlung. · <a href="/sitemap.xml">Sitemap</a></span>
    </div>
    <p class="footer-credit">Created by <a href="https://valentino-sapienza.de" target="_blank" rel="noopener">Valentino Sapienza</a> – Webdesign aus Berlin</p>
  </div>
</footer>"""


def privacy_notice():
    return """<aside class="privacy-note" role="region" aria-label="Hinweis zum Datenschutz" hidden>
  <p class="privacy-note__title">Ganz ohne Cookies.</p>
  <p>Diese Website verwendet keine Cookies, kein Tracking und keine Werbe- oder Analysedienste.</p>
  <div class="privacy-note__actions">
    <button class="btn btn--small" type="button" data-privacy-ok>Alles klar</button>
    <a class="text-link" href="/datenschutz/">Datenschutz</a>
  </div>
</aside>"""


def contact_dialog():
    return f"""<dialog class="modal" id="kontakt" aria-labelledby="kontakt-titel">
  <div class="modal__inner">
    <button class="modal__close" type="button" data-close aria-label="Fenster schließen">{ICON['close']}</button>
    <div data-view="form">
      <h2 id="kontakt-titel">Kennenlernen anfragen</h2>
      <p class="intro">Schreiben Sie mir kurz, worum es geht. Ich rufe Sie zurück – das Kennenlerngespräch (20&nbsp;Min.) ist kostenlos.</p>
      <form class="form" action="/kontakt.php" method="post" novalidate>
        <div class="field">
          <label for="f-vorname">Vorname</label>
          <input id="f-vorname" name="vorname" type="text" autocomplete="given-name" maxlength="60" required aria-describedby="e-vorname">
          <span class="error" id="e-vorname" data-error-for="vorname" aria-live="polite"></span>
        </div>
        <div class="field">
          <label for="f-telefon">Telefonnummer</label>
          <input id="f-telefon" name="telefon" type="tel" inputmode="tel" autocomplete="tel" maxlength="30" required aria-describedby="e-telefon">
          <span class="error" id="e-telefon" data-error-for="telefon" aria-live="polite"></span>
        </div>
        <div class="field">
          <label for="f-email">E-Mail</label>
          <input id="f-email" name="email" type="email" inputmode="email" autocomplete="email" maxlength="120" required aria-describedby="e-email">
          <span class="error" id="e-email" data-error-for="email" aria-live="polite"></span>
        </div>
        <div class="field">
          <label for="f-anliegen">Kurz: Worum geht es? <span class="opt">(optional)</span></label>
          <textarea id="f-anliegen" name="anliegen" rows="4" maxlength="2000"></textarea>
        </div>
        <div class="hp" aria-hidden="true">
          <label for="f-website">Website (bitte leer lassen)</label>
          <input id="f-website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <input type="hidden" name="ts" value="">
        <div class="check">
          <input id="f-einwilligung" name="einwilligung" type="checkbox" value="ja" required aria-describedby="e-einwilligung">
          <label for="f-einwilligung">Ich willige ein, dass meine Angaben – auch Angaben zu meiner Gesundheit – zur Bearbeitung meiner Anfrage verarbeitet werden (Art.&nbsp;9 Abs.&nbsp;2 lit.&nbsp;a DSGVO). Die Einwilligung kann ich jederzeit widerrufen. Mehr in der <a class="text-link" href="/datenschutz/">Datenschutzerklärung</a>.</label>
          <span class="error" id="e-einwilligung" data-error-for="einwilligung" aria-live="polite"></span>
        </div>
        <div class="form-hints">
          <p>Ich melde mich telefonisch oder per E-Mail bei Ihnen. Die Beratung ersetzt keine ärztliche oder psychotherapeutische Behandlung.</p>
          <p>In einer akuten Krise wenden Sie sich bitte an den Notruf <a class="text-link" href="tel:112">112</a> oder die Telefonseelsorge <a class="text-link" href="tel:08001110111">0800&nbsp;111&nbsp;0&nbsp;111</a> (kostenfrei, rund um die Uhr).</p>
        </div>
        <p class="form-status" role="alert"></p>
        <button class="btn" type="submit"><span class="label">Anfrage senden</span></button>
      </form>
    </div>
    <div data-view="success" class="success" hidden>
      {ICON['check']}
      <h2>Vielen Dank.</h2>
      <p>Ihre Anfrage ist angekommen. Ich melde mich bei Ihnen.</p>
      <p><button class="btn" type="button" data-close>Schließen</button></p>
    </div>
  </div>
</dialog>"""


def schema_for(meta, url):
    graph = [dict(ORG_SCHEMA)]
    graph.append({
        "@type": "WebPage",
        "@id": url + "#webpage",
        "url": url,
        "name": meta["title"],
        "description": meta["description"],
        "inLanguage": "de",
        "isPartOf": {"@type": "WebSite", "@id": SITE + "/#website", "url": SITE + "/", "name": BRAND},
        "about": {"@id": SITE + "/#beratung"},
    })
    extra = SRC / (meta["_name"] + ".schema.json")
    if extra.exists():
        graph.extend(json.loads(extra.read_text(encoding="utf-8").replace("{SITE}", SITE)))
    return json.dumps({"@context": "https://schema.org", "@graph": graph}, ensure_ascii=False, separators=(",", ":"))


def asset_version(rel):
    return hashlib.sha1((OUT / rel).read_bytes()).hexdigest()[:8]


def render(meta, body):
    path = meta["path"]
    css_v, js_v = asset_version("assets/css/style.css"), asset_version("assets/js/main.js")
    url = SITE + path
    noindex = meta.get("robots") == "noindex"
    robots = "noindex, follow" if noindex else "index, follow, max-image-preview:large"
    canonical = "" if noindex else f'<link rel="canonical" href="{url}">\n'
    og_type = "website" if path == "/" else "article"
    preload = "\n".join(
        f'<link rel="preload" href="/assets/fonts/{f}" as="font" type="font/woff2" crossorigin>'
        for f in meta.get("preload", "cormorant-garamond-latin-500-normal.woff2 dm-sans-latin-400-normal.woff2").split()
    )
    schema = "" if noindex else f'<script type="application/ld+json">{schema_for(meta, url)}</script>\n'
    return f"""<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{esc(meta['title'])}</title>
<meta name="description" content="{esc(meta['description'])}">
<meta name="robots" content="{robots}">
{canonical}<meta name="theme-color" content="#ffffff">
<meta name="format-detection" content="telephone=no">
<meta property="og:type" content="{og_type}">
<meta property="og:locale" content="de_DE">
<meta property="og:site_name" content="{BRAND}">
<meta property="og:title" content="{esc(meta.get('og_title', meta['title']))}">
<meta property="og:description" content="{esc(meta['description'])}">
<meta property="og:url" content="{url}">
<meta property="og:image" content="{SITE}/assets/img/og-image.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
{preload}
<link rel="stylesheet" href="/assets/css/style.css?v={css_v}">
<script>{INLINE_JS}</script>
<script src="/assets/js/main.js?v={js_v}" defer></script>
{schema}</head>
<body>
{header(meta.get('nav', path))}
<main id="inhalt">
{body.strip()}
</main>
{footer()}
{contact_dialog()}
{privacy_notice()}
</body>
</html>
"""


def version_images(html):
    """Hängt an Bild-URLs eine Prüfsumme an – geänderte Bilder werden so nie aus dem Cache geladen."""
    def repl(m):
        f = OUT / m.group(1).lstrip("/")
        return f"{m.group(1)}?v={hashlib.sha1(f.read_bytes()).hexdigest()[:8]}" if f.exists() else m.group(1)
    return re.sub(r"(/assets/img/[\w.-]+\.(?:webp|jpg|png|svg))(?![?\w])", repl, html)


def main():
    pages = []
    for f in sorted(SRC.glob("*.html")):
        meta, body = parse(f)
        meta["_name"] = f.stem
        html = version_images(render(meta, body))
        target = OUT / meta["path"].lstrip("/") / "index.html" if meta["path"].endswith("/") else OUT / meta["path"].lstrip("/")
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(html, encoding="utf-8")
        pages.append(meta)
        print("✓", target.relative_to(ROOT))

    urls = "".join(
        f"  <url>\n    <loc>{SITE}{m['path']}</loc>\n    <changefreq>{m.get('changefreq', 'monthly')}</changefreq>\n    <priority>{m.get('priority', '0.5')}</priority>\n  </url>\n"
        for m in sorted(pages, key=lambda m: -float(m.get("priority", "0.5"))) if m.get("robots") != "noindex" and m["path"] != "/404.html"
    )
    (OUT / "sitemap.xml").write_text(
        f'<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n{urls}</urlset>\n',
        encoding="utf-8")
    (OUT / "robots.txt").write_text(
        f"User-agent: *\nAllow: /\nDisallow: /kontakt.php\n\nSitemap: {SITE}/sitemap.xml\n", encoding="utf-8")

    # CSP-Hash für das Inline-Skript in .htaccess aktuell halten
    ht = OUT / ".htaccess"
    if ht.exists():
        ht.write_text(re.sub(r"'sha256-[^']+'", f"'sha256-{INLINE_JS_HASH}'", ht.read_text(encoding="utf-8")), encoding="utf-8")
    print("✓ sitemap.xml, robots.txt")


if __name__ == "__main__":
    main()
