"""Erzeugt eine statische Demo von CineScope fuer GitHub Pages.

GitHub Pages kann kein PHP ausfuehren. Deshalb laeuft in GitHub Actions die echte
Anwendung (PHP-Testserver + MariaDB). Dieses Skript

1. legt optional Demo-Filme ueber den echten OMDb-Import an (nur mit OMDB_API_KEY),
2. vergibt mit Demo-Konten einige Bewertungen und Kommentare,
3. ruft danach als Besucher alle oeffentlichen Seiten ab und speichert sie als HTML.

Links werden auf die erzeugten Dateien umgeschrieben. Formulare werden in der Demo
per JavaScript abgefangen, damit klar ist, dass dort nichts gespeichert wird.

Aufruf (Server muss laufen):
    python scripts/build_demo.py --base http://127.0.0.1:8000 --out demo
"""
from __future__ import annotations

import argparse
import hashlib
import html
import http.cookiejar
import json
import os
import re
import shutil
import sys
import urllib.error
import urllib.parse
import urllib.request
from collections import deque
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REPO_URL = 'https://github.com/Ario-Mi/cinescope-php'

# Seiten, die Anmeldung, Formulare oder einen Server brauchen, gibt es in der Demo nicht.
NICHT_IN_DEMO = {'login.php', 'register.php', 'contact.php', 'profile.php', 'omdb_import.php',
                 'omdb_datei_import.php', 'tmdb_import.php', 'search_suggest.php'}
SORTIERUNGEN = ['id_desc', 'title_asc', 'year_desc', 'imdb_desc', 'rating_desc', 'rating_asc']
LINK = re.compile(r'(href|src)="([^"]*)"')

BANNER = ('<div class="demo-banner" role="note"><strong>Statische Demo</strong> mit Beispieldaten. '
          'Anmeldung, Bewertungen und Kommentare funktionieren nur in der Vollversion. '
          f'<a href="{REPO_URL}">Zum Quellcode</a></div>')

DEMO_CSS = """\
.demo-banner{background:#3a2a08;color:#ffe3a8;border:1px solid #F5B027;border-radius:12px;
padding:.6rem 1rem;margin:0 auto 14px;max-width:1200px;text-align:center;font-size:.95rem}
.demo-banner a{color:#F5B027;font-weight:bold}
.demo-notice{position:fixed;left:50%;bottom:1.5rem;transform:translateX(-50%);max-width:min(90vw,34rem);
background:#1e1a18;color:#ffe3a8;border:1px solid #F5B027;padding:.8rem 1.1rem;border-radius:10px;
box-shadow:0 6px 24px rgba(0,0,0,.45);z-index:2000;font-size:.95rem}
"""

DEMO_JS = """\
// In der statischen Demo gibt es keinen PHP-Server: Formulare werden abgefangen.
(function () {
  function hinweis(text) {
    var box = document.querySelector('.demo-notice');
    if (!box) {
      box = document.createElement('div');
      box.className = 'demo-notice';
      box.setAttribute('role', 'status');
      document.body.appendChild(box);
    }
    box.textContent = text;
    clearTimeout(box.timer);
    box.timer = setTimeout(function () { box.remove(); }, 4500);
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    event.preventDefault();
    if (form.id === 'suchForm') {
      var q = form.querySelector('[name="q"]');
      var sort = form.querySelector('[name="sort"]');
      if (q && q.value.trim() !== '') {
        hinweis('Die Suche läuft in der Vollversion über PHP. In der Demo funktionieren Vorschläge beim Tippen und die Sortierung.');
        return;
      }
      if (sort && window.DEMO_SORT && window.DEMO_SORT[sort.value]) {
        window.location.href = window.DEMO_SORT[sort.value];
        return;
      }
    }
    hinweis('In der statischen Demo wird nichts gespeichert. In der Vollversion wird diese Aktion ausgeführt.');
  });

  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-demo-missing]');
    if (link) {
      event.preventDefault();
      hinweis('Diese Seite gibt es nur in der Vollversion (Anmeldung und Datenbank nötig).');
    }
  });

  // Vorschlaege beim Tippen: in der Vollversion per AJAX, hier aus filme.json
  var input = document.getElementById('suchInput');
  var box = document.getElementById('suggestBox');
  if (!input || !box) { return; }
  var filme = null;
  input.addEventListener('input', function () {
    var text = input.value.trim().toLowerCase();
    if (text.length < 2) { box.style.display = 'none'; return; }
    var zeigen = function () {
      box.innerHTML = '';
      filme.filter(function (f) {
        return f.title.toLowerCase().indexOf(text) !== -1 || f.year.indexOf(text) !== -1;
      }).slice(0, 3).forEach(function (f) {
        var a = document.createElement('a');
        a.className = 'suggest-item';
        a.textContent = f.title;
        a.href = f.page;
        box.appendChild(a);
      });
      box.style.display = box.children.length ? 'block' : 'none';
    };
    if (filme) { zeigen(); return; }
    fetch('filme.json').then(function (r) { return r.json(); }).then(function (daten) { filme = daten; zeigen(); });
  });
})();
"""


class App:
    """Kleiner HTTP-Client mit Cookies, damit Login und CSRF-Token wie im Browser funktionieren."""

    def __init__(self, base: str):
        self.base = base.rstrip('/') + '/'
        self.reset()

    def reset(self) -> None:
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
            NoRedirect())

    def get(self, path: str) -> tuple[int, str, str]:
        return self._send(urllib.request.Request(self.base + path))

    def post(self, path: str, **data) -> tuple[int, str, str]:
        _, body, _ = self.get(path)
        token = re.search(r'name="csrf_token" value="([a-f0-9]+)"', body)
        if not token:
            raise RuntimeError(f'Kein CSRF-Token auf {path}')
        data['csrf_token'] = token[1]
        request = urllib.request.Request(self.base + path, urllib.parse.urlencode(data).encode())
        return self._send(request)

    def _send(self, request) -> tuple[int, str, str]:
        try:
            with self.opener.open(request, timeout=60) as response:
                return response.status, response.read().decode('utf-8'), response.headers.get_content_type()
        except urllib.error.HTTPError as error:
            return error.code, error.read().decode('utf-8', 'replace'), error.headers.get_content_type()


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def demo_daten_anlegen(app: App) -> None:
    """Optional: Filme ueber den echten OMDb-Import anlegen, dann Demo-Bewertungen vergeben."""
    ids_datei = ROOT / 'scripts' / 'demo_films.txt'
    imdb_ids = [z.strip() for z in ids_datei.read_text(encoding='utf-8').splitlines()
                if re.fullmatch(r'tt\d{5,10}', z.strip())]

    konten = ['demo_anna', 'demo_mehmet', 'demo_julia']
    for nummer, alias in enumerate(konten):
        passwort = 'Demo-Passwort-' + hashlib.sha256(alias.encode()).hexdigest()[:12]
        app.reset()
        app.post('register.php', alias=alias, email=alias + '@beispiel.example', passwort=passwort)
        status, _, _ = app.post('login.php', alias=alias, passwort=passwort)
        if status != 302:
            raise RuntimeError(f'Login fuer {alias} fehlgeschlagen')

        if nummer == 0 and os.environ.get('OMDB_API_KEY'):
            for imdb_id in imdb_ids:
                _, body, _ = app.post('omdb_import.php', treffer_imdbid=imdb_id,
                                      treffer_aktion='save', response_type='json')
                ok = 'gespeichert' in body
                print(f'OMDb-Import {imdb_id}: {"ok" if ok else "nicht gespeichert"}')

        _, body, _ = app.get('index.php?seite=1')
        seiten = max([1] + [int(s) for s in re.findall(r'\?seite=(\d+)', body)])
        film_ids = set()
        for seite in range(1, seiten + 1):
            film_ids.update(re.findall(r'film\.php\?id=(\d+)', app.get(f'index.php?seite={seite}')[1]))

        for film_id in sorted(film_ids, key=int):
            # Gleichbleibende "zufaellige" Bewertung von 2 bis 5, damit jeder Build gleich aussieht.
            wert = 2 + int(hashlib.sha256(f'{alias}-{film_id}'.encode()).hexdigest(), 16) % 4
            app.post(f'film.php?id={film_id}', action='add_rating', rating=str(wert))
        if nummer == 0 and film_ids:
            app.post(f'film.php?id={min(film_ids, key=int)}', action='add_comment',
                     kommentar='Beispielkommentar aus der Demo.')
    app.reset()


def seiten_name(url: str) -> str:
    """index.php?seite=2&sort=title_asc -> filme--seite-2--sort-title_asc.html"""
    teile = urllib.parse.urlsplit(url)
    datei = teile.path.rsplit('/', 1)[-1] or 'start.php'
    stamm = {'start.php': 'index', 'index.php': 'filme'}.get(datei, datei.removesuffix('.php'))
    query = sorted((k, v) for k, v in urllib.parse.parse_qsl(teile.query)
                   if not (k == 'seite' and v == '1') and not (k == 'sort' and v == 'id_desc'))
    for key, value in query:
        stamm += f'--{key}-{value}'
    return re.sub(r'[^A-Za-z0-9_-]+', '_', stamm) + '.html'


def links_umschreiben(seite_url: str, text: str, base: str) -> tuple[str, list[str]]:
    gefunden: list[str] = []

    def ersetzen(match: re.Match) -> str:
        attr, roh = match.group(1), html.unescape(match.group(2))
        if roh.startswith(('http://', 'https://', '//', '#', 'mailto:', 'data:')) or roh == '':
            return match.group(0)
        absolut = urllib.parse.urljoin(seite_url, roh)
        if not absolut.startswith(base):
            return match.group(0)
        pfad = urllib.parse.urlsplit(absolut).path[len(urllib.parse.urlsplit(base).path):]
        if pfad.endswith('.php') or pfad == '':
            gefunden.append(absolut)
            ziel = seiten_name(absolut)
        else:
            ziel = pfad
        return f'{attr}="{html.escape(ziel, quote=True)}"'

    return LINK.sub(ersetzen, text), gefunden


def crawlen(app: App) -> dict[str, str]:
    start = [app.base + 'start.php', app.base + 'index.php'] + [
        app.base + f'index.php?sort={s}' for s in SORTIERUNGEN]
    seiten: dict[str, str] = {}
    gesehen: set[str] = set()
    warteschlange = deque(start)
    while warteschlange:
        url = warteschlange.popleft()
        datei = urllib.parse.urlsplit(url).path.rsplit('/', 1)[-1]
        if url in gesehen or datei in NICHT_IN_DEMO or '/admin/' in url:
            continue
        gesehen.add(url)
        status, text, typ = app.get(url[len(app.base):])
        if status != 200 or typ != 'text/html':
            print(f'Uebersprungen: {url} (Status {status})')
            continue
        neu, links = links_umschreiben(url, text, app.base)
        seiten[seiten_name(url)] = neu
        warteschlange.extend(l for l in links if l not in gesehen)
    return seiten


def schreiben(seiten: dict[str, str], ausgabe: Path) -> None:
    if ausgabe.exists():
        shutil.rmtree(ausgabe)
    ausgabe.mkdir(parents=True)
    for ordner in ('css', 'bilder'):
        shutil.copytree(ROOT / ordner, ausgabe / ordner)
    (ausgabe / 'js').mkdir()
    (ausgabe / 'css' / 'demo.css').write_text(DEMO_CSS, encoding='utf-8')
    (ausgabe / 'js' / 'demo.js').write_text(DEMO_JS, encoding='utf-8')
    (ausgabe / '.nojekyll').write_text('', encoding='utf-8')

    filme = []
    for name, text in seiten.items():
        if name.startswith('film--id-'):
            titel = re.search(r'<h1 class="titel">\s*(.*?)\s*</h1>', text, re.S)
            jahr = re.search(r'<strong>Year:</strong>\s*([^<]*)', text)
            filme.append({'title': html.unescape(titel[1]) if titel else '', 'year': (jahr[1].strip() if jahr else ''),
                          'page': name})
    (ausgabe / 'filme.json').write_text(json.dumps(filme, ensure_ascii=False), encoding='utf-8')

    vorhanden = set(seiten)
    sortierung = {s: seiten_name(f'index.php?sort={s}') for s in SORTIERUNGEN}
    for name, text in seiten.items():
        # Links auf Seiten, die es in der Demo nicht gibt, zeigen einen Hinweis.
        text = re.sub(r'href="([A-Za-z0-9_-]+\.html)(#[^"]*)?"',
                      lambda m: m.group(0) if m.group(1) in vorhanden else 'href="#" data-demo-missing', text)
        text = text.replace('<script src="js/suggest.js"></script>', '')
        text = text.replace('</head>', '<link rel="stylesheet" href="css/demo.css">\n'
                            f'<script>window.DEMO_SORT = {json.dumps(sortierung)};</script>\n'
                            '<script src="js/demo.js" defer></script>\n</head>', 1)
        text = re.sub(r'(<body[^>]*>)', lambda m: m.group(1) + '\n' + BANNER, text, count=1)
        (ausgabe / name).write_text(text, encoding='utf-8')

    print(f'Demo erzeugt: {len(seiten)} Seiten, {len(filme)} Filme in {ausgabe}')


def pruefen(ausgabe: Path) -> None:
    """Bricht ab, wenn eine Seite auf eine fehlende Datei verweist oder PHP-Fehler enthaelt."""
    fehler = []
    for datei in ausgabe.glob('*.html'):
        text = datei.read_text(encoding='utf-8')
        if 'Die Anfrage konnte nicht verarbeitet werden' in text or 'SQLSTATE' in text:
            fehler.append(f'{datei.name}: Datenbankfehler im HTML')
        for ziel in re.findall(r'(?:href|src)="([^":#?]+\.(?:html|css|js|png|svg))"', text):
            if not (ausgabe / ziel).exists():
                fehler.append(f'{datei.name}: fehlt {ziel}')
    if not (ausgabe / 'index.html').exists():
        fehler.append('index.html fehlt')
    if fehler:
        print('\n'.join(fehler))
        sys.exit(1)


def main() -> int:
    parser = argparse.ArgumentParser(description='Statische CineScope-Demo erzeugen')
    parser.add_argument('--base', default='http://127.0.0.1:8000/')
    parser.add_argument('--out', type=Path, default=ROOT / 'demo')
    parser.add_argument('--ohne-demodaten', action='store_true', help='keine Demo-Konten anlegen')
    args = parser.parse_args()

    app = App(args.base)
    if not args.ohne_demodaten:
        demo_daten_anlegen(app)
    seiten = crawlen(app)
    schreiben(seiten, args.out.resolve())
    pruefen(args.out.resolve())
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
