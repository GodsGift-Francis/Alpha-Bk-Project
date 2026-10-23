"""Verify the isolated preview gate against a disposable synthetic database."""
import contextlib
from http.cookies import SimpleCookie
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
ROOT = Path(__file__).resolve().parent.parent
PHP = os.environ.get('PHP_BIN', 'php')
checks = 0

def check(value, message):
    global checks
    assert value, message
    checks += 1
    print('PASS:', message, flush=True)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl): return None

with tempfile.TemporaryDirectory(prefix='alphabank-preview-check-') as temporary:
    state = Path(temporary) / 'instance'
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]
    log = open(Path(temporary) / 'server.log', 'w+')
    process = subprocess.Popen(['python3', str(ROOT / 'preview/start.py'), '--port', str(port), '--https', '--state-directory', str(state)], env=dict(os.environ, PHP_BIN=PHP), stdout=log, stderr=log)
    credentials = None
    base = f'http://127.0.0.1:{port}'
    opener = urllib.request.build_opener(NoRedirect)
    cookies = {}
    def request(path, data=None):
        headers = {'Cookie': '; '.join(f'{key}={value}' for key, value in cookies.items())}
        payload = urllib.parse.urlencode(data).encode() if data is not None else None
        try: response = opener.open(urllib.request.Request(base + path, data=payload, headers=headers), timeout=5)
        except urllib.error.HTTPError as error: response = error
        for value in response.headers.get_all('Set-Cookie', []):
            parsed = SimpleCookie(); parsed.load(value)
            cookies.update({key: item.value for key, item in parsed.items()})
        return response.status, response.read().decode(), response.headers
    try:
        for _ in range(100):
            if process.poll() is not None: raise RuntimeError('Preview startup failed: ' + Path(log.name).read_text())
            try:
                status, body, headers = request('/login.php')
                if status == 401: break
            except OSError: pass
            time.sleep(.1)
        else: raise RuntimeError('Preview startup timed out')
        credentials = json.loads((state / 'credentials.json').read_text())
        check(status == 401 and 'Preview access code' in body, 'unauthenticated preview is gated')
        check(request('/api/accounts.php')[0] == 401, 'API is gated before app authentication')
        check(request('/login.php', {'preview_access_code': 'wrong'})[0] == 401, 'incorrect access code rejected')
        status, _, headers = request('/login.php', {'preview_access_code': credentials['access_code']})
        cookie = headers.get('Set-Cookie', '')
        check(status == 303 and 'secure' in cookie.lower() and 'httponly' in cookie.lower() and 'samesite=strict' in cookie.lower(), 'access gate sets signed secure HTTP-only cookie')
        for path in ['/config/database.php', '/includes/auth.php', '/preview/seed.php', '/.git/config', '/tests/fixtures.php', '/assets/../config/database.php']:
            check(request(path)[0] == 404, 'internal path blocked: ' + path)
        status, body, _ = request('/login.php')
        token = re.search(r'name="csrf-token" content="([^"]+)"', body)[1]
        check(status == 200, 'authorized preview serves original login page')
        status, body, headers = request('/api/login.php', {'csrf_token': token, 'username': 'preview_user', 'password': credentials['account_password']})
        check(status == 200 and json.loads(body).get('success'), 'synthetic customer can sign in')
        status, body, _ = request('/api/accounts.php')
        accounts = json.loads(body)['accounts']
        check(status == 200 and accounts[0]['balance'] == '1500.00' and len(accounts) == 2, 'synthetic balances and account states available')
        check(request('/admin.php')[0] == 403, 'preview does not bypass banking authorization')
        cookies['alphabank_preview'] = '0.' + '0' * 64
        check(request('/dashboard.php')[0] == 401, 'tampered gate cookie rejected')
        print(f'Preview checks passed: {checks}')
    finally:
        process.terminate()
        with contextlib.suppress(subprocess.TimeoutExpired): process.wait(timeout=5)
        if credentials is None and (state / 'credentials.json').exists(): credentials = json.loads((state / 'credentials.json').read_text())
        if credentials:
            database = credentials['database']
            assert re.fullmatch('alphabank_preview_[a-f0-9]{12}', database)
            subprocess.run([PHP, '-r', "$db = require $argv[1]; $db->exec('DROP DATABASE IF EXISTS `' . $argv[2] . '`');", str(ROOT / 'config/database.php'), database], check=True)
