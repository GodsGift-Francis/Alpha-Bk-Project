# AlphaBank

A PHP/MySQL account-management application with administrator-reviewed deposits,
withdrawals and transfers. Requests remain pending until an administrator reviews
them. Posting a transaction changes balances atomically and retains its history.

## Requirements and setup

- PHP 8.2+ on a 64-bit runtime, PDO MySQL, sessions and password hashing.
- MySQL 8+ or MariaDB 10.6+, InnoDB and an utf8mb4 database.
- JavaScript enabled; a current browser with native dialog support.

Copy `config/database.example.php` to the ignored `config/database.php`. Supply
`DB_DSN`, `DB_USER`, and `DB_PASSWORD` through your deployment environment. DSN
example: `mysql:host=127.0.0.1;dbname=alphabank;charset=utf8mb4`. Never commit actual
credentials. Keep the database connection timezone at UTC, as the example does.

For a new database, run `php includes/db-init.php`. For an existing installation,
back up first and run `php scripts/migrate.php` before serving the new code.
Migrations are additive, recorded, serialized and restartable. They do not replay
historic financial postings or delete existing data. Retained older transfers
have no retroactively reconstructed linkage or review actor.

Create the first administrator using a controlled database provisioning process:
register the intended account, independently verify its owner, and set only that
user's `is_admin` to 1 through your DBA tooling. Registration never grants admin
access. Do not expose a public admin setup endpoint.

Development only: `php -S 127.0.0.1:8000 -t .`. Production must use a supported web
server and PHP-FPM with HTTPS; the built-in PHP server is not a production server.
See [deployment notes](docs/DEPLOYMENT.md).

## Current behavior

- Checking, Savings and Business account requests; manual approval/rejection.
- Exact decimal request amounts; row-locked financial approval and funds checks.
- Idempotent transaction retries using a client-generated request key.
- Approved transfer debit/credit and the linked incoming record commit together.
- Rejected requests retain the reviewer, time and reason.
- Posted timestamps and financial records cannot be deleted/edited through the app.
- Responsive account dashboard, confirmation dialogs and paginated/filterable history.
- Paginated admin queues, client search, safe client-name editing and audit events.
- Targeted/general announcements with per-user database read state (latest 20 shown).
- Session expiration, secure cookie options, CSRF validation, login/lookup limits.

Pending requests do not reserve funds. Approval always rechecks the current
balance, so an unaffordable request stays pending for a later decision. Currency
preferences change the display symbol only, not the underlying denomination or
exchange value. Deposits are requests; the app does not collect or settle funds
through an external payment provider.

## Verification

Configure a local **test** MySQL server with permission to create/drop databases.
The following suites create databases named `alphabank_test_*` and remove them on
completion. Never use privileged production credentials for tests.

```sh
php tests/integrity.php
python3 tests/http_browser.py
```

Optional browser suite requires Python Playwright and Chromium:

```sh
PHP_BIN=php CHROMIUM_BIN=/usr/bin/chromium python3 tests/http_browser.py --browser
```

`PHP_BIN` may point to a PHP wrapper with the required extensions. If no
`CHROMIUM_BIN` or system Chromium is available, install Playwright's Chromium.
CI runs syntax, migration, concurrency and HTTP checks on a disposable MySQL 8.4
service. The browser suite is an additional local check.

The [sprint plan](docs/SPRINT_PLAN.md) records shipped scope and remaining work.
This application still needs the release gates described there before real funds
or public production deployment.

## Cloud browser testing

See [isolated preview setup](preview/README.md) for an access-protected synthetic
test instance. A remote workspace needs an approved HTTPS gateway/port forward;
opening desktop localhost does not reach its cloud server.
