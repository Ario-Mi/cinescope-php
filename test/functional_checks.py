"""Real HTTP/MySQL tests in a disposable database. Requires local MySQL.

Run: python test/functional_checks.py
Only the random cinescope_test_<hex> database is created and removed.
"""
import json
import os
from pathlib import Path
import re
import secrets
import unittest
import csv
import xml.etree.ElementTree as ET

from security_checks import SecurityChecks, PHP, ROOT
import subprocess
from mysql_sandbox import MySQLSandbox


class FunctionalChecks(SecurityChecks):
    @classmethod
    def fixture(cls, action):
        result = subprocess.run([PHP, str(ROOT / 'test/db_fixture.php'), action, cls.database],
                                capture_output=True, text=True)
        if result.returncode:
            raise RuntimeError('Database fixture failed: ' + result.stderr)
        return result.stdout

    @classmethod
    def setUpClass(cls):
        cls.enterClassContext(MySQLSandbox())
        cls.database = 'cinescope_test_' + secrets.token_hex(8)
        cls.fixture('create')
        cls.addClassCleanup(cls.fixture, 'drop')
        super().setUpClass()

    @classmethod
    def server_database(cls):
        return cls.database

    def snapshot(self):
        return json.loads(self.fixture('snapshot'))

    def test_start_poster_navigation(self):
        status, body, _, _ = self.request('/start.php')
        self.assertEqual(status, 200)
        links = re.findall(r'<a href="(film.php\?id=\d+)" class="start-film-link">', body)
        self.assertEqual(len(links), 3)
        for link in links:
            response = self.request('/' + link)
            self.assertEqual(response[0], 200)
            self.assertIn('class="poster"', response[1])
        self.assertEqual(self.request('/bilder/placeholder.svg')[0], 200)

    def test_registration_validation(self):
        count = len(self.snapshot()['users'])
        for data in [dict(alias='a' * 51, email='test@example.invalid', passwort='ValidPassword123'),
                     dict(alias='short-password', email='test@example.invalid', passwort='123'),
                     {'alias[]': 'invalid-array', 'email': 'test@example.invalid', 'passwort': 'ValidPassword123'}]:
            token = self.token('/register.php')
            status, body, _, _ = self.request('/register.php', dict(data, csrf_token=token))
            self.assertEqual(status, 200)
            self.assertIn('class="fehler"', body)
        self.assertEqual(len(self.snapshot()['users']), count)

    def post(self, path, **data):
        token = self.token(path)
        response = self.request(path, dict(data, csrf_token=token))
        self.assertIn(response[0], (200, 302))
        self.assertNotIn('Die Anfrage konnte nicht verarbeitet werden', response[1])
        return response

    def test_user_journey(self):
        baseline = self.snapshot()
        alias = 'functional_' + secrets.token_hex(4)
        password = ' Test password with spaces! '
        email = alias + '@example.invalid'
        before = self.cookie
        _, body, _, _ = self.post('/register.php', alias=alias, email=email, passwort=password)
        self.assertIn('Registrierung erfolgreich', body)
        users = self.snapshot()['users']
        user = next(row for row in users if row['alias'] == alias)
        self.assertNotEqual(user['passwort'], password)
        _, body, _, _ = self.post('/register.php', alias=alias, email=email, passwort=password)
        self.assertIn('schon vergeben', body)
        self.assertEqual(len(self.snapshot()['users']), len(users))
        # A stored hash must not authenticate as a password.
        self.assertEqual(self.post('/login.php', alias=alias, passwort=user['passwort'])[0], 200)
        token = self.token('/login.php')
        before = self.cookie
        self.assertEqual(self.request('/login.php', dict(csrf_token=token, alias=alias, passwort=password))[0], 302)
        self.assertNotEqual(self.cookie, before)
        self.assertIn(alias, self.request('/index.php')[1])

        # Search filters, AJAX and pagination use the schema's sample records.
        for field, query in [('title', 'Matrix'), ('year', '1999'), ('actor', 'Keanu'),
                             ('director', 'Wachowski'), ('genre', 'Action')]:
            with self.subTest(field=field):
                path = '/index.php?such_feld=' + field + '&q=' + query
                self.assertIn('The Matrix', self.request(path)[1])
                suggestions = json.loads(self.request('/search_suggest.php?such_feld=' + field + '&q=' + query)[1])
                self.assertTrue(any(row['title'] == 'The Matrix' for row in suggestions))
        first = self.request('/index.php')[1]
        second = self.request('/index.php?seite=2')[1]
        self.assertIn('?seite=2', first)
        self.assertNotEqual(re.findall(r'class="film-titel">\s*<a[^>]*>(.*?)</a>', first, re.S),
                            re.findall(r'class="film-titel">\s*<a[^>]*>(.*?)</a>', second, re.S))

        film_id = next(row['id'] for row in baseline['films'] if row['imdbID'] == 'tt0133093')
        path = '/film.php?id=' + str(film_id)
        self.post(path, action='save_film')
        self.post(path, action='save_film')
        saved = [row for row in self.snapshot()['saved_films'] if row['users_id'] == user['id']]
        self.assertEqual(len(saved), 1)
        comment = '<script>alert("test")</script> Functional comment'
        self.post(path, action='add_comment', kommentar=comment)
        body = self.request(path)[1]
        self.assertIn('&lt;script&gt;', body)
        self.assertNotIn(comment, body)
        self.post('/contact.php', betreff='Functional test', nachricht='Disposable test message')
        snapshot = self.snapshot()
        self.assertTrue(any(row['users_id'] == user['id'] for row in snapshot['comments']))
        self.assertTrue(any(row['users_id'] == user['id'] for row in snapshot['messages']))

        for kind in ('json', 'xml'):
            _, body, _, _ = self.post('/omdb_datei_import.php', datei_name='beispiel_film.' + kind, response_type=kind)
            self.assertIn('erfolgreich gelesen', body)

        self.post('/start.php', action='user_logout')
        self.assertNotIn('name="action" value="add_comment"', self.request(path)[1])
        before_count = len(self.snapshot()['comments'])
        token = self.token('/login.php')
        self.assertEqual(self.request(path, dict(csrf_token=token, action='add_comment',
                                                kommentar='Unauthorized comment'))[0], 200)
        self.assertEqual(len(self.snapshot()['comments']), before_count)

        # Temporary admin is supplied only through the isolated server environment.
        self.assertEqual(self.post('/admin/index.php', action='login', name='security-test', passwort=self.password)[0], 302)
        snapshot = self.snapshot()
        for table, action, field in [('comments', 'delete_comment', 'comment_id'),
                                     ('messages', 'delete_message', 'message_id')]:
            row = next(row for row in snapshot[table] if row['users_id'] == user['id'])
            self.post('/admin/index.php', action=action, **{field: row['id']})
            self.assertFalse(any(item['id'] == row['id'] for item in self.snapshot()[table]))
        self.post('/admin/index.php', action='delete_user', user_id=user['id'])
        self.assertFalse(any(row['id'] == user['id'] for row in self.snapshot()['users']))
        self.assertFalse(any(row['users_id'] == user['id'] for row in self.snapshot()['saved_films']))
        # Deletion response statistics must reflect the updated database.
        body = self.request('/admin/index.php')[1]
        counts = self.snapshot()
        for label, table in [('Benutzer', 'users'), ('Filme', 'films'), ('Kommentare', 'comments'),
                             ('Nachrichten', 'messages')]:
            match = re.search(r'<td>' + label + r'</td>\s*<td>(\d+)</td>', body)
            self.assertEqual(int(match[1]), len(counts[table]))
        for kind in ('json', 'csv', 'xml'):
            self.post('/admin/index.php', action='export_' + kind)
        directory = Path(self.exports.name)
        self.assertEqual(len(json.loads((directory / 'films_export.json').read_text(encoding='utf-8'))), len(counts['films']))
        with (directory / 'films_export.csv').open(encoding='utf-8', newline='') as stream:
            self.assertEqual(len(list(csv.DictReader(stream, delimiter=';'))), len(counts['films']))
        self.assertEqual(len(ET.parse(directory / 'films_export.xml').getroot()), len(counts['films']))

    @unittest.skipUnless(os.environ.get('RUN_LIVE_OMDB') == '1', 'Live API check is opt-in')
    def test_live_omdb(self):
        alias = 'api_' + secrets.token_hex(4)
        password = secrets.token_urlsafe(20)
        self.post('/register.php', alias=alias, email=alias + '@example.invalid', passwort=password)
        self.post('/login.php', alias=alias, passwort=password)
        for kind in ('json', 'xml'):
            _, body, _, _ = self.post('/omdb_import.php', imdbID='tt0133093', response_type=kind)
            self.assertIn('OMDb Daten erfolgreich geholt.', body)
        # A film not present in the seed data exercises transactional import.
        _, body, _, _ = self.post('/omdb_import.php', treffer_imdbid='tt0108052', treffer_aktion='save', response_type='json')
        self.assertIn('Film wurde in Datenbank gespeichert.', body)
        imported = [row for row in self.snapshot()['films'] if row['imdbID'] == 'tt0108052']
        self.assertEqual(len(imported), 1)


if __name__ == '__main__':
    unittest.main(defaultTest='FunctionalChecks', verbosity=2)
