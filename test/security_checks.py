"""HTTP regression checks using an isolated PHP server; no database writes.

Run: python test/security_checks.py
"""
import http.client
import os
from pathlib import Path
import re
import secrets
import socket
import subprocess
import time
import tempfile
import unittest
from urllib.parse import urlencode

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BINARY', r'C:\xampp\php\php.exe')


class SecurityChecks(unittest.TestCase):
    @classmethod
    def server_database(cls):
        return 'cinescope_nonexistent_security_check'

    @classmethod
    def setUpClass(cls):
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            cls.port = sock.getsockname()[1]
        cls.password = secrets.token_urlsafe(24)
        result = subprocess.run(
            [PHP, '-r', 'echo password_hash($argv[1], PASSWORD_DEFAULT);', cls.password],
            capture_output=True, text=True, check=True,
        )
        env = dict(os.environ, ADMIN_USERNAME='security-test',
                   ADMIN_PASSWORD_HASH=result.stdout,
                   DB_NAME=cls.server_database(), OMDB_API_KEY='')
        if os.environ.get('RUN_LIVE_OMDB') == '1':
            env.pop('OMDB_API_KEY', None)
        cls.exports = tempfile.TemporaryDirectory(prefix='cinescope-exports-')
        env['EXPORT_DIR'] = cls.exports.name
        cls.sessions = tempfile.TemporaryDirectory(prefix='cinescope-security-')
        cls.server = subprocess.Popen(
            [PHP, '-d', 'display_errors=0', '-d', 'session.save_path=' + cls.sessions.name,
             '-S', f'127.0.0.1:{cls.port}', '-t', str(ROOT)],
            env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        )
        for _ in range(50):
            try:
                with socket.create_connection(('127.0.0.1', cls.port), timeout=.2):
                    return
            except OSError:
                time.sleep(.1)
        cls.server.terminate()
        raise RuntimeError('PHP test server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=5)
        cls.sessions.cleanup()
        cls.exports.cleanup()

    def setUp(self):
        self.cookie = ''

    def request(self, path, data=None):
        conn = http.client.HTTPConnection('127.0.0.1', self.port, timeout=10)
        headers = {'Cookie': self.cookie}
        if data is not None:
            headers['Content-Type'] = 'application/x-www-form-urlencoded'
        conn.request('POST' if data is not None else 'GET', path,
                     urlencode(data) if data is not None else None, headers)
        response = conn.getresponse()
        body = response.read().decode('utf-8')
        cookie = response.getheader('Set-Cookie')
        if cookie:
            self.cookie = cookie.split(';')[0]
        status, location = response.status, response.getheader('Location')
        conn.close()
        return status, body, cookie, location

    def token(self, path='/login.php'):
        status, body, cookie, _ = self.request(path)
        self.assertEqual(status, 200)
        return re.search(r'name="csrf_token" value="([a-f0-9]+)"', body)[1]

    def test_post_requires_session_bound_token(self):
        token = self.token()
        for path in ('/login.php', '/register.php', '/index.php', '/start.php',
                     '/film.php?id=1', '/contact.php', '/omdb_import.php',
                     '/omdb_datei_import.php', '/admin/index.php'):
            for data in ({}, {'csrf_token': 'wrong'}, {'csrf_token[]': token}):
                with self.subTest(path=path, data=data):
                    self.assertEqual(self.request(path, data)[0], 403)
        self.cookie = ''
        self.assertEqual(self.request('/login.php', {'csrf_token': token})[0], 403)

    def test_valid_token_and_cookie_attributes(self):
        _, _, cookie, _ = self.request('/login.php')
        self.assertIn('HttpOnly', cookie)
        self.assertIn('SameSite=Lax', cookie)
        token = self.token()
        status, body, _, _ = self.request('/login.php', {'csrf_token': token})
        self.assertEqual(status, 200)
        self.assertIn('Bitte Alias und Passwort eingeben.', body)

    def test_admin_login_rotates_session_and_logout_requires_post(self):
        token = self.token('/admin/index.php')
        data = {'csrf_token': token, 'action': 'login', 'name': 'admin', 'passwort': 'admin'}
        self.assertEqual(self.request('/admin/index.php', data)[0], 200)
        before = self.cookie
        data.update(name='security-test', passwort=self.password)
        self.assertEqual(self.request('/admin/index.php', data)[0], 302)
        self.assertNotEqual(self.cookie, before)
        self.assertIn('Willkommen im Admin-Bereich', self.request('/admin/index.php?logout=1')[1])
        self.assertEqual(self.request('/admin/index.php', {
            'csrf_token': token, 'action': 'admin_logout'})[0], 302)
        self.assertNotIn('Willkommen im Admin-Bereich', self.request('/admin/index.php')[1])

    def test_logout_works_without_database_and_errors_are_generic(self):
        token = self.token()
        for path in ('/start.php', '/index.php'):
            self.assertEqual(self.request(path, {'csrf_token': token, 'action': 'user_logout'})[0], 302)
        body = self.request('/index.php')[1]
        self.assertNotIn('SQLSTATE', body)
        self.assertNotIn('cinescope_nonexistent_security_check', body)


if __name__ == '__main__':
    unittest.main(verbosity=2)
