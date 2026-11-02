# AlphaBank — zero-budget development launch

Prepared 2 November 2026. This is a development preview using sample data, not a
production launch for customer funds. The package sets APP_DEMO=1 and displays
that distinction on the hosted pages. Your local users, balances, password hashes,
backups, configuration and one-time reset links are not included.

## The free hosting route

Use the **Free (€0/month)** personal-project plan at alwaysdata, with its supplied
`ACCOUNT.alwaysdata.net` address. No paid domain is needed. Do not select a Plus
trial or a paid plan. Free-plan eligibility is for personal needs; keep this
installation a personal development preview.

Official sources checked for this package:

- Free plan and included site address: https://www.alwaysdata.com/en/offers/
- Free signup: https://www.alwaysdata.com/en/register/?p=2012
- PHP: https://help.alwaysdata.com/en/docs/web-hosting/languages/php/
- MariaDB: https://help.alwaysdata.com/en/docs/web-hosting/databases/mariadb/
- SSH/SFTP: https://help.alwaysdata.com/en/docs/web-hosting/remote-access/ssh/

The current signup page may require credit/debit-card validation and states that
no fees are charged. Complete signup yourself, including your private password,
any identity checks and acceptance of provider policies. If card verification is
unavailable, stop before entering payment information and we can prepare a no-card
alternative; do not subscribe to a paid plan to get around it.

## 1. Create your hosting profile and Free account

Choose an available account name. `alphabank-preview` is a suggestion, not a
reserved name or a verified available address. Record the assigned site URL.
Enable account 2FA if offered. Do not send passwords or card details in chat.

## 2. Upload the package

Extract the ZIP locally. Connect with SFTP using the server/user details in
**Remote access → SSH/SFTP**, verifying the displayed server fingerprint. Upload
`alphabank/` to your hosting home directory. The result should be:

```
/home/ACCOUNT/alphabank/
  app/       application source and CLI migrations
  private/   private configuration, bootstrap and sessions
  public/    the only directory exposed to the website
```

Do not upload your Windows `work/` directory or export the local development DB.
Do not set the website document root to `alphabank/` or `app/`.

## 3. Create an empty MariaDB database

In **Databases → MySQL**, create a new empty database and its user. Use MariaDB
10.11 or a newer supported release. Copy the actual database host, database name,
username and password from the hosting panel; do not assume the examples match
what the provider assigns. Initial schema setup requires permission to create
and alter tables. A separate runtime identity can be restricted after setup.

## 4. Configure privately

Copy `private/config.example.php` to `private/config.php` on the server. Replace
only the database placeholders with the hosting values. Keep APP_SESSION_SECURE
and APP_DEMO set to `1`. This file stays outside the public document root.
The `private/sessions/` directory must be writable by your hosting PHP process.
Do not paste this configuration into chat or commit it to GitHub.

## 5. Initialize the empty database

From the hosting SSH terminal:

```sh
cd ~/alphabank
php app/includes/db-init.php
```

Use the same PHP 8.4 runtime for CLI and the website. The command creates tables
and records the three included migrations. For later code updates to an existing
database, take a backup first and use `php app/scripts/migrate.php` instead.

## 6. Create the first administrator privately

Run these commands yourself. The password is entered without terminal echo and
is not included in shell command history. Choose at least 16 characters.
Replace the email argument with the address you intend for the administrator.

```sh
cd ~/alphabank
read -rsp 'Choose the administrator password: ' ALPHABANK_ADMIN_PASSWORD
printf '\n'
export ALPHABANK_ADMIN_PASSWORD
php app/scripts/provision-admin.php curtis_breen you@example.com 'Curtis Breen'
unset ALPHABANK_ADMIN_PASSWORD
```

This creates a new administrator. It never resets an existing account. No default
admin credentials are shipped, and the local Curtis Breen account is not copied.

## 7. Configure the public website

In **Web → Sites**, use a PHP website with:

- Address: the free `ACCOUNT.alwaysdata.net` address assigned by the provider.
- Document root: `/home/ACCOUNT/alphabank/public`.
- PHP: 8.4, with PDO MySQL and sessions enabled.
- HTTPS: enable the provider's certificate and HTTP-to-HTTPS redirection.

This runtime uses the host's web server, not `php -S`. Leave setup private or
unannounced until the smoke checks below pass. Public pages must use HTTPS for
secure sign-in cookies to work.

## 8. Verify the actual deployment

Check homepage, registration, customer sign-in and `/admin`. Use sample customer
data. Request and review an account, deposit, withdrawal and profile change.
Confirm profile data stays unchanged until approval; test rejection and the
notification. Test light/dark settings, invoices and mobile layouts.

Verify `/private/config.php`, `/app/config/database.php`, `/.git/config`,
`/tests/` and `/scripts/` cannot be downloaded. Test a backup restoration separately.
The ZIP has no real credentials, so uploading it alone does not produce a working
application until configuration, database setup and administrator creation finish.

## What to send back so deployment can continue

Once your hosting account exists, provide the **account name, assigned site URL
and whether SSH/SFTP is enabled**. Share no passwords. A signed-in hosting-panel
tab can be used to continue configuration with you handling private credential
entry. Nothing has been publicly deployed merely by preparing this ZIP.
