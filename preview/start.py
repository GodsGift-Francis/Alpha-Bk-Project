#!/usr/bin/env python3
"""Create an isolated preview copy and run it on loopback.
Requires the configured development MySQL server to be running.
"""
import argparse
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess

ROOT = Path(__file__).resolve().parent.parent
parser = argparse.ArgumentParser()
parser.add_argument('--port', type=int, default=8001)
parser.add_argument('--https', action='store_true', help='Use secure cookies behind an HTTPS preview gateway')
parser.add_argument('--state-directory', required=True, help='Private state directory outside the source checkout')
args = parser.parse_args()
state = Path(args.state_directory).resolve()
if state == ROOT or ROOT in state.parents: parser.error('Keep preview state outside the source checkout.')
if state.exists(): parser.error('Use a new, empty state directory for each preview.')
state.mkdir(mode=0o700, parents=True)
php = os.environ.get('PHP_BIN', 'php')
credentials = {'access_code': secrets.token_urlsafe(32), 'account_password': secrets.token_urlsafe(24), 'database': 'alphabank_preview_' + secrets.token_hex(6)}
credentials_file = state / 'credentials.json'
credentials_file.write_text(json.dumps(credentials))
credentials_file.chmod(0o600)
env = dict(os.environ, PREVIEW_ACCESS_CODE=credentials['access_code'], PREVIEW_ACCOUNT_PASSWORD=credentials['account_password'], PREVIEW_HTTPS='1' if args.https else '0', APP_SESSION_SECURE='1' if args.https else '0')
subprocess.run([php, str(ROOT / 'preview/seed.php'), credentials['database']], env=env, check=True)
app = state / 'app'
shutil.copytree(ROOT, app, ignore=shutil.ignore_patterns('.git', '.env', 'config', 'tests', 'docs', '.github', '__pycache__'))
(app / 'config').mkdir()
source_config = str(ROOT / 'config/database.php').replace('\\', '\\\\').replace("'", "\\'")
(app / 'config/database.php').write_text("<?php\n$db = require '" + source_config + "';\n$db->exec(\"USE `" + credentials['database'] + "`\");\n$db->exec(\"SET time_zone = '+00:00'\");\nreturn $db;\n")
(state / 'sessions').mkdir(mode=0o700)
print(f'Isolated preview: http://127.0.0.1:{args.port}/login.php', flush=True)
print(f'Access credentials saved privately to {credentials_file}', flush=True)
print('Synthetic accounts: preview_admin, preview_user, preview_recipient', flush=True)
print('Only connect an approved HTTPS preview gateway to this loopback port.', flush=True)
# Replace the supervisor so stopping the preview also stops its PHP listener.
os.execvpe(php, [php, '-d', 'display_errors=0', '-d', 'session.save_path=' + str(state / 'sessions'), '-S', f'127.0.0.1:{args.port}', '-t', str(app), str(app / 'preview/router.php')], env)
