# Deployment and rollback

## Before deploying

1. Rehearse on a copy of the database with synthetic/anonymized data. Run the
   integrity and HTTP suites against a separate disposable test server.
2. Take an encrypted database backup and verify restoration in an isolated server.
   Preserve the application release and environment configuration used with it.
3. Stop new requests and approvals using your web server's maintenance routing.
   Drain running PHP workers so no old approval code executes during cutover.
4. Run `php scripts/migrate.php` with a separate migration identity. A fresh
   database instead needs `php includes/db-init.php`.
5. Deploy the complete release, restart PHP workers/opcache, and invalidate old
   sessions. The new session lifetime policy also expires legacy sessions without
   activity timestamps.
6. Verify sign-in, pending queues, request submission, approval/rejection, balances,
   and notification delivery/read state in staging. Resume traffic after checks.

The migration adds columns, tables and indexes; MySQL DDL may lock large tables.
Measure migration duration against representative data before scheduling cutover.
Historical deleted records and missing reviewer information cannot be recovered by
this migration. Review existing negative balances/unknown transaction types before
launch. Approval rejects unsupported legacy transaction types.

## Runtime configuration

- HTTPS with `session.cookie_secure=1`; set `APP_SESSION_SECURE=1` when TLS is
  terminated at a trusted proxy. Never derive trust from arbitrary forwarded headers.
- `display_errors=Off`, `log_errors=On`, and private, rotated error logs.
- UTC database sessions and synchronized clocks. The sample PDO config sets UTC.
- Web server denies access to `.git`, `.env`, `config/`, `database/`, `includes/`,
  `scripts/`, `tests/`, and documentation. Route only actual public PHP pages, API
  endpoints and static assets; disable directory listings. Never serve backup files.
- Dedicated runtime database identity without DDL privileges. Grant audit_events
  SELECT/INSERT only, and deny deletion of financial records. Use a separate
  restricted migration identity. Do not run the site with test/root credentials.
- This release keeps existing foreign-key cascades for compatibility; preventing
  direct administrative deletion requires database grants and operational controls.
  The application no longer exposes deletion endpoints.
- Shared server-side sessions for multiple PHP workers/nodes. PHP session files
  belong outside the web root. Use a protected session store and rotate keys/secrets
  according to your hosting platform.
- Limit request sizes and timeouts at the web server. Back up data on a schedule;
  monitor API errors, approval failures, slow queries and queue age.
- Login limiting currently allows 10 attempts per normalized username and 50 per
  source IP in 15 minutes; recipient verification allows 100 lookups per user in
  15 minutes. Tune with your abuse and shared-network traffic patterns. Periodically
  purge expired `login_limits` rows through a maintenance job.

CSRF tokens rotate on login. Financial requests need a 32–64 character lowercase
hex request key. API clients must reuse the same key and details after an uncertain
response; never automatically retry a changed financial request. A duplicate
approval returns 409 and cannot post again. A timeout/deadlock may require a retry
with the same request key after checking history.

## Rollback

Before any new requests or postings are accepted, switch the application back and
restore the verified pre-cutover database snapshot if required. Once this release
has accepted real transactions, **do not restore an old snapshot over them**.
Maintain the new database, pause approvals, investigate and forward-fix, or deploy
a compatible patched release. The earlier application's unsafe approval/deletion
paths are not a suitable rollback target once traffic has resumed. Migration DDL
is not transactionally reversible in MySQL.

## Release gates still open

MFA and role separation; password recovery and email verification; monitoring and
health endpoints; reconciliation and exports; independent security/accessibility
review; load and multi-browser testing; backup/restore rehearsal; real payment
provider and operational/legal requirements if handling customer funds. None are
implied complete by passing the application regression suite.
