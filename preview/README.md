# Isolated cloud test preview

This serves the original PHP application and approval logic with a new synthetic
MySQL database, a separate session directory, and a preview access-code gate.
It is a development instance, not a production deployment.

## Run

Start your configured development MySQL service first, then:

```sh
PHP_BIN=php python3 preview/start.py --port 8001 --https --state-directory /tmp/alphabank-preview-instance
```

Use a new state directory on each run. The configured MySQL identity must be able
to create a database. The script creates `alphabank_preview_<random>`, copies the
app to the private state directory, and makes the copied config select that new
database. It never overwrites the configured application's database. Credentials
are saved to `<state-directory>/credentials.json` with owner-only permissions;
never commit or upload that file. `--https` sets secure preview/session cookies
and is required for an externally reachable HTTPS gateway. Omit it only for
loopback HTTP testing.

Synthetic account usernames: `preview_user`, `preview_admin`, `preview_recipient`.
They share the newly generated test account password in the private credentials
file. The independent access code must be entered before the bank login appears.
The customer starts with a 1,500.00 synthetic deposit, a pending Savings account,
and pending transfer/withdrawal requests. The recipient is `ACC-1000000002`.

## Browser connectivity is an environment requirement

The listener is deliberately bound to `127.0.0.1:8001`. A desktop browser's
localhost cannot reach a remote cloud machine. Connect an approved HTTPS preview
or port-forward gateway to this listener and open **the gateway URL** in Codex's
built-in browser. Do not expose the repository with an unrestricted static server
or copy the original application's database to a public host.

This session has no inbound preview/port-forward management tool. Its enforced
network policy permits package-download hosts but no external tunnel endpoints.
An HTTPS probe to `api.trycloudflare.com` through the configured proxy returned
403 on October 23, 2026. An environment owner must configure an approved preview
endpoint or approve the required tunnel connectivity through environment settings.
Changing application files, opening another localhost tab, or binding to every
network interface cannot create that connectivity.

No public URL has been created. Do not claim the remote preview is accessible
until a gateway URL is returned and verified from the browser.

## Verification and cleanup

`PHP_BIN=php python3 tests/preview_http.py` creates a disposable preview, verifies
the gate and synthetic customer access, then drops only its own preview database.
The main regression suite remains unchanged.

Stopping the preview process stops the PHP listener. Manually remove a persistent
preview only after stopping it: use DBA tooling to drop the exact randomly named
preview database recorded in its credentials file, then delete its private state
directory. Do not run a wildcard database deletion. The original application and
database are not cleanup targets.
