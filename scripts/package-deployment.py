#!/usr/bin/env python3
"""Build a credential-free PHP deployment with a separate public document root."""
from pathlib import Path
import argparse, tempfile, shutil, zipfile
parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True)
parser.add_argument('--build-dir', required=True)
args = parser.parse_args()
source = Path(__file__).resolve().parents[1]
build = Path(args.build_dir).resolve()
if build.exists():
    raise SystemExit('Choose a new empty build directory; existing files are never removed.')
app = build/'alphabank'/'app'; public = build/'alphabank'/'public'; private = build/'alphabank'/'private'
for folder in (app, public, private/'sessions'):
    folder.mkdir(parents=True, exist_ok=True)
for folder in ['api','assets','database','includes','views','scripts','admin']:
    for path in (source/folder).rglob('*'):
        if path.is_file() and path.suffix.lower() in {'.php','.css','.js','.svg','.jpg','.jpeg','.png','.webp'}:
            target = app/path.relative_to(source);target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(path,target)
for path in source.glob('*.php'):
    shutil.copy2(path,app/path.name)
(app/'config').mkdir()
shutil.copy2(source/'config/database.example.php',app/'config/database.example.php')
(app/'config/database.php').write_text("<?php\nrequire_once dirname(__DIR__,2).'/private/bootstrap.php';\nreturn require __DIR__.'/database.example.php';\n",encoding='utf-8')
(private/'config.example.php').write_text("""<?php
// Copy to config.php and replace database values privately. Never commit config.php.
return [
    'DB_DSN' => 'mysql:host=mysql-ACCOUNT.alwaysdata.net;dbname=ACCOUNT_alphabank;charset=utf8mb4',
    'DB_USER' => 'ACCOUNT_DATABASE_USER',
    'DB_PASSWORD' => 'SET_PRIVATE_DATABASE_PASSWORD',
    'APP_SESSION_SECURE' => '1',
    'APP_DEMO' => '1',
];
""",encoding='utf-8')
(private/'bootstrap.php').write_text("""<?php
(static function (): void {
    $file = __DIR__.'/config.php';
    if (!is_file($file)) { http_response_code(503); exit('Deployment setup is not complete.'); }
    $config = require $file;
    foreach (['DB_DSN','DB_USER','DB_PASSWORD','APP_SESSION_SECURE','APP_DEMO'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key])) throw new RuntimeException('Incomplete private configuration.');
        putenv($key.'='.$config[$key]);
    }
    if (session_status() === PHP_SESSION_NONE) ini_set('session.save_path',__DIR__.'/sessions');
})();
""",encoding='utf-8')
(private/'.htaccess').write_text('Require all denied\n',encoding='utf-8')
(app/'.htaccess').write_text('Require all denied\n',encoding='utf-8')
(public/'.htaccess').write_text('Options -Indexes\nDirectoryIndex index.php\n',encoding='utf-8')
(public/'.user.ini').write_text('display_errors=Off\nlog_errors=On\nsession.cookie_secure=1\nsession.cookie_httponly=1\nsession.cookie_samesite=Lax\nsession.use_strict_mode=1\n',encoding='utf-8')
shutil.copytree(source/'assets',public/'assets',ignore=shutil.ignore_patterns('*.log','__pycache__'))
shutil.copy2(source/'logo.jpg',public/'logo.jpg')
pages=['index','login','register','logout','dashboard','settings','deposit','withdraw','transfer','invoice','admin','admin-login']
for page in pages:
    (public/(page+'.php')).write_text("<?php\nrequire_once dirname(__DIR__).'/private/bootstrap.php';\nrequire dirname(__DIR__).'/app/"+page+".php';\n",encoding='utf-8')
for folder in ['api','admin']:
    (public/folder).mkdir(exist_ok=True)
    for path in (source/folder).glob('*.php'):
        (public/folder/path.name).write_text("<?php\nrequire_once dirname(__DIR__,2).'/private/bootstrap.php';\nrequire dirname(__DIR__,2).'/app/"+folder+'/'+path.name+"';\n",encoding='utf-8')
if (source/'docs/FREE_LAUNCH.md').exists():
    shutil.copy2(source/'docs/FREE_LAUNCH.md',build/'START-HERE.md')
output=Path(args.output).resolve();output.parent.mkdir(parents=True,exist_ok=True)
with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED) as archive:
    for path in sorted(build.rglob('*')):
        archive.write(path,path.relative_to(build))
print(str(output))
print('Packaged public entry points, private config template and CLI migrations. No database records, passwords or local reset links included.')
