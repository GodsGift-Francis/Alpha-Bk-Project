"""Disposable-database HTTP and browser regression suite.
PHP_BIN=/path/to/php python3 tests/http_browser.py [--browser]
"""
import contextlib
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parent.parent
PHP = os.environ.get('PHP_BIN', 'php')
DATABASE = 'alphabank_test_' + secrets.token_hex(6)
checks = 0

def check(value, message):
    global checks
    assert value, message
    checks += 1
    print('PASS:', message, flush=True)

class Client:
    def __init__(self, base):
        self.base = base
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
        self.token = ''
    def get(self, path):
        return self.send(path)
    def send(self, path, data=None):
        payload = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = self.opener.open(self.base + path, payload, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        body = response.read().decode()
        return response.status, body, response.headers
    def csrf(self):
        _, body, _ = self.get('/login.php')
        self.token = re.search(r'name="csrf-token" content="([^"]+)"', body)[1]
    def post(self, path, data=None, csrf=True):
        values = dict(data or {})
        if csrf: values['csrf_token'] = self.token
        status, body, _ = self.send(path, values)
        return status, json.loads(body)
    def login(self, username, password, administrator=False):
        self.csrf()
        status, data = self.post('/api/admin-login.php' if administrator else '/api/login.php', {'username': username, 'password': password})
        assert status == 200, data
        _, body, _ = self.get('/admin.php' if data['is_admin'] else '/dashboard.php')
        self.token = re.search(r'name="csrf-token" content="([^"]+)"', body)[1]

with tempfile.TemporaryDirectory(prefix='alphabank-tests-') as temporary:
    folder = Path(temporary)
    server = None
    try:
        setup = subprocess.check_output([PHP, str(ROOT / 'tests/fixtures.php'), 'create', DATABASE], text=True)
        fixture = json.loads(setup.strip().splitlines()[-1])
        app = folder / 'app'
        shutil.copytree(ROOT, app, ignore=shutil.ignore_patterns('.git', 'config', 'tests', 'docs', '.github'))
        (app / 'config').mkdir()
        source = str(ROOT / 'config/database.php').replace("'", "\\'")
        (app / 'config/database.php').write_text("<?php\n$db = require '" + source + "';\n$db->exec(\"USE `" + DATABASE + "`\");\nreturn $db;\n")
        (folder / 'sessions').mkdir()
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        log = open(folder / 'server.log', 'w+')
        server = subprocess.Popen([PHP, '-d', 'display_errors=0', '-d', 'session.save_path=' + str(folder / 'sessions'), '-S', f'127.0.0.1:{port}', '-t', str(app)], stdout=log, stderr=log)
        base = f'http://127.0.0.1:{port}'
        for _ in range(50):
            try:
                urllib.request.urlopen(base + '/login.php', timeout=1).close()
                break
            except (OSError, urllib.error.URLError): time.sleep(.1)
        guest, sender, admin, recipient = (Client(base) for _ in range(4))
        status, _, _ = guest.get('/api/accounts.php')
        check(status == 401, 'unauthenticated API denied with JSON status')
        check(guest.get('/includes/db-init.php')[0] == 404 and guest.get('/update-tx-status.php')[0] == 404, 'web schema modification scripts disabled')
        guest.csrf()
        check('admin-login.php' not in guest.get('/login.php')[1], 'customer login has no admin discovery link')
        check('Customer sign in' not in guest.get('/admin')[1], 'direct admin entry has no customer cross-link')
        check(guest.post('/api/register.php', {}, csrf=False)[0] == 403, 'registration requires CSRF')
        check(guest.post('/api/register.php', {'username': 'invalid spaces', 'email': 'bad', 'full_name': 'Test', 'password': 'short'})[0] == 422, 'registration validates inputs')
        check(guest.post('/api/admin-login.php', {'username': fixture['sender'], 'password': fixture['password']})[0] == 401, 'customer denied administrator sign in')
        check(guest.post('/api/login.php', {'username': fixture['admin'], 'password': fixture['password']})[0] == 403, 'administrator must use administrator sign in')
        check(guest.post('/api/admin-login.php', {}, csrf=False)[0] == 403, 'administrator sign in requires CSRF')
        sender.login(fixture['sender'], fixture['password'])
        admin.login(fixture['admin'], fixture['password'], administrator=True)
        recipient.login(fixture['recipient'], fixture['password'])
        check(sender.post('/api/admin-tx.php', {'tx_id': 1, 'action': 'APPROVE'})[0] == 403, 'user cannot approve transactions')
        for endpoint, data in [('/api/admin-tx.php', {'tx_id': 1, 'action': 'APPROVE'}), ('/api/admin-accounts.php', {'account_id': 3, 'action': 'APPROVE'}), ('/api/post-announcement.php', {'title': 'Test', 'content': 'Body'})]:
            check(admin.post(endpoint, data, csrf=False)[0] == 403, endpoint + ' enforces CSRF')
        check(admin.post('/api/admin-tx.php', {'tx_id': 1, 'action': 'APPROVE'})[0] == 200, 'admin can approve a pending deposit')
        check(admin.post('/api/admin-tx.php', {'tx_id': 1, 'action': 'APPROVE'})[0] == 409, 'repeated approval rejected at HTTP boundary')
        check(admin.post('/api/admin-tx.php', {'tx_id': 2, 'action': 'REJECT', 'reason': 'Not authorized by client'})[0] == 200, 'admin can reject with a reason')
        check(admin.post('/api/admin-accounts.php', {'account_id': 3, 'action': 'REJECT', 'reason': 'Please provide verification'})[0] == 200, 'account rejection retained')
        accounts = json.loads(sender.get('/api/accounts.php')[1])['accounts']
        check(accounts[0]['balance'] == '105.00' and accounts[1]['status'] == 'REJECTED', 'approved balances and rejected accounts visible')
        request = {'action': 'TRANSFER', 'account_number': 'ACC-1000000001', 'recipient_account': 'ACC-1000000002', 'amount': '2.10', 'description': '<img src=x onerror=alert(1)>', 'request_key': secrets.token_hex(16)}
        status, result = sender.post('/api/transactions.php', request)
        repeat_status, repeat = sender.post('/api/transactions.php', request)
        check(status == 201 and repeat_status == 200 and result['id'] == repeat['id'], 'HTTP retries deduplicated')
        bad = {**request, 'amount': '1e2', 'request_key': secrets.token_hex(16)}
        check(sender.post('/api/transactions.php', bad)[0] == 422, 'nondecimal amounts rejected at HTTP boundary')
        bad = {**request, 'account_number': 'ACC-1000000002', 'request_key': secrets.token_hex(16)}
        check(sender.post('/api/transactions.php', bad)[0] == 409, 'another user account cannot submit a request')
        history = json.loads(sender.get('/api/history.php')[1])
        check(len(history['history']) == 20 and history['pagination']['total'] == 25, 'history pagination bounds payload')
        rows = json.loads(sender.get('/api/history.php?status=REJECTED&q=Pending')[1])['history']
        check(len(rows) == 1 and rows[0]['review_reason'] == 'Not authorized by client', 'history filters expose retained rejection reason')
        check(json.loads(sender.get('/api/history.php?account=ACC-1000000002')[1])['history'] == [], 'history cannot leak another account')
        check(sender.get('/api/history.php?from=2026-99-99')[0] == 422, 'invalid history date rejected')
        check('APPROVED' in sender.get('/invoice.php?id=1')[1], 'approved transaction invoice available to owner')
        check('REJECTED' in sender.get('/invoice.php?id=2')[1], 'rejection and reason retained on invoice')
        check(recipient.get('/invoice.php?id=1')[0] == 404, 'other customers cannot read transaction invoice')
        check('APPROVED' in admin.get('/invoice.php?id=1')[1], 'admin can inspect transaction invoice')
        check('PENDING' in sender.get('/invoice.php?id=' + str(result['id']))[1], 'pending transaction has invoice before posting')
        notifications = json.loads(sender.get('/api/announcements.php')[1])
        check(all(n['id'] != 2 for n in notifications['announcements']) and notifications['unread_count'] == 4, 'private announcements isolated; decision notifications delivered')
        check(sender.post('/api/notification-read.php', {'last_read_id': 2})[0] == 422, 'cannot acknowledge private notification of another user')
        check(sender.post('/api/notification-read.php', {'last_read_id': max(n['id'] for n in notifications['announcements'])})[0] == 200, 'notification acknowledgment persists')
        sender2 = Client(base); sender2.login(fixture['sender'], fixture['password'])
        check(json.loads(sender2.get('/api/announcements.php')[1])['unread_count'] == 0, 'notification read state synchronized across sessions')
        check(admin.post('/api/post-announcement.php', {'title': 'New general notice', 'content': 'A new update'})[0] == 201, 'admin announcement posting works')
        check(json.loads(sender.get('/api/announcements.php')[1])['unread_count'] == 1, 'new notifications remain unread after mark-all-read')
        check(admin.post('/api/admin-manage.php', {'type': 'transaction', 'id': 1})[0] == 409, 'financial record deletion disabled')
        check(admin.post('/api/admin-edit.php', {'type': 'transaction', 'field': 'timestamp', 'id': 1, 'value': '2000-01-01'})[0] == 409, 'original transaction timestamps immutable')
        check(sender.get('/logout.php')[0] == 405, 'logout requires POST')
        # Fresh sessions avoid impacting browser checks below.
        status, _, _ = sender2.send('/logout.php', {'csrf_token': sender2.token})
        check(status == 200 and sender2.get('/api/accounts.php')[0] == 401, 'logout clears authenticated session')
        if '--browser' in sys.argv:
            from playwright.sync_api import sync_playwright
            with sync_playwright() as playwright:
                browser = playwright.chromium.launch(headless=True, executable_path=os.environ.get('CHROMIUM_BIN') or shutil.which('chromium'), args=['--no-sandbox'])
                context = browser.new_context(viewport={'width': 1280, 'height': 900})
                page = context.new_page()
                errors = []
                page.on('pageerror', lambda error: errors.append(str(error)))
                page.goto(base + '/login.php')
                page.fill('#username', fixture['sender']); page.fill('#password', fixture['password'])
                page.click('button[type=submit]'); page.wait_for_url('**/dashboard.php')
                page.locator('#dashboard-content .account-card').first.wait_for()
                page.locator('#history-content table').wait_for()
                check(page.locator('#history-content tbody tr').count() == 20, 'browser renders paginated history')
                previews = os.environ.get('SCREENSHOT_DIR')
                if previews:
                    Path(previews).mkdir(parents=True, exist_ok=True)
                    page.screenshot(path=str(Path(previews) / 'dashboard-desktop.png'), full_page=True)
                page.get_by_role('button', name='Open New Account').click()
                check(page.locator('#create-account-modal').evaluate('(dialog) => dialog.open'), 'account dialog opens accessibly')
                page.keyboard.press('Escape')
                check(not page.locator('#create-account-modal').evaluate('(dialog) => dialog.open'), 'account dialog closes with Escape')
                page.goto(base + '/transfer.php'); page.fill('#recipient_account', 'ACC-1000000002'); page.fill('#amount', '1.25')
                page.get_by_role('button', name='Submit Transfer Request').click()
                page.locator('dialog[open]').wait_for()
                check('1.25' in page.locator('dialog[open]').inner_text(), 'transfer review shows exact amount')
                page.get_by_role('button', name='Go back').click()
                check(page.locator('#amount').input_value() == '1.25', 'canceling review preserves form values')
                page.get_by_role('button', name='Submit Transfer Request').click()
                page.get_by_role('button', name='Submit request', exact=True).click()
                page.wait_for_url('**/dashboard.php')
                check(True, 'browser submits transfer request through confirmation dialog')
                for width in [320, 375, 768, 1280, 1920, 2560]:
                    page.set_viewport_size({'width': width, 'height': 900})
                    page.goto(base + '/dashboard.php')
                    page.locator('#history-content table').wait_for()
                    check(page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'), f'dashboard has no horizontal page overflow at {width}px')
                    check(page.locator('.dashboard-header .btn, .filter-bar > .btn').evaluate_all('(elements) => elements.every(el => el.getBoundingClientRect().height <= 80 && el.getBoundingClientRect().width >= 75)'), f'action buttons remain readable at {width}px')
                page.set_viewport_size({'width': 375, 'height': 900})
                check(page.locator('#history-content tbody tr').evaluate_all('(rows) => rows.every(row => row.getBoundingClientRect().height < 450)'), 'mobile history cards remain bounded and readable')
                if previews:
                    page.screenshot(path=str(Path(previews) / 'dashboard-mobile.png'), full_page=False)
                    page.locator('#history-content').evaluate('(el) => window.scrollTo(0, el.offsetTop - 100)')
                    page.screenshot(path=str(Path(previews) / 'history-mobile.png'), full_page=False)
                    page.evaluate('window.scrollTo(0, 0)')
                page.get_by_role('button', name='Menu', exact=True).click()
                check(page.locator('#primary-nav').is_visible(), 'mobile menu opens')
                page.keyboard.press('Escape')
                check(not page.locator('#primary-nav').is_visible(), 'mobile menu closes with Escape')
                admin_context = browser.new_context(viewport={'width': 1280, 'height': 900})
                admin_page = admin_context.new_page(); admin_page.on('pageerror', lambda error: errors.append(str(error)))
                admin_page.goto(base + '/admin-login.php'); admin_page.fill('#admin-username', fixture['admin']); admin_page.fill('#admin-password', fixture['password']); admin_page.click('button[type=submit]'); admin_page.wait_for_url('**/admin.php')
                if previews: admin_page.screenshot(path=str(Path(previews) / 'admin-desktop.png'), full_page=True)
                check(admin_page.locator('#clients img').count() == 0 and admin_page.evaluate('window.injected !== true'), 'malicious client name is rendered as text')
                admin_page.locator('[data-edit-client="2"]').click()
                check('<img' in admin_page.locator('#edit-value').input_value() and admin_page.locator('#admin-edit-modal img').count() == 0, 'edit dialog uses safe value assignment')
                admin_page.keyboard.press('Escape')
                admin_page.locator('[data-review-kind="transaction"][data-action="APPROVE"]').first.click()
                check(admin_page.locator('#review-modal').evaluate('(dialog) => dialog.open'), 'admin approval confirmation opens')
                admin_page.keyboard.press('Escape')
                for width in [320, 375, 768, 1280, 1920, 2560]:
                    admin_page.set_viewport_size({'width': width, 'height': 900})
                    check(admin_page.evaluate('document.documentElement.scrollWidth <= window.innerWidth'), f'admin page has no horizontal page overflow at {width}px')
                check(not errors, 'no browser JavaScript errors: ' + repr(errors))
                browser.close()
        print(f'HTTP/browser checks passed: {checks}')
    except Exception:
        if 'log' in locals():
            log.flush()
            print((folder / 'server.log').read_text()[-5000:], file=sys.stderr)
        raise
    finally:
        if server:
            server.terminate()
            with contextlib.suppress(subprocess.TimeoutExpired): server.wait(timeout=5)
        subprocess.run([PHP, str(ROOT / 'tests/fixtures.php'), 'drop', DATABASE], check=True)
