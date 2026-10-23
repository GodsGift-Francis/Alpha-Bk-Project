# AlphaBank handover — October 23, 2026

The previous April 2026 overview is superseded by [README.md](README.md) and the
[upgrade sprint plan](docs/SPRINT_PLAN.md). See [deployment notes](docs/DEPLOYMENT.md)
for the required schema migration and release gates.

Current delivery: transaction locking, exact decimal validation, duplicate request
protection, retained rejections and audit events, CSRF/session/input hardening,
responsive UI, native confirmation dialogs, paginated searchable history/admin
queues, safe client editing, database notification read state, tests and CI.

Preserve the core contract: user requests, administrator review, then atomic
balance changes. No external settlement or automatic approvals were introduced.
Currency preferences remain symbol-only. Database migration must precede serving
this version. Use CLI initialization for fresh databases; there is no web installer.

Validation: 29 financial integrity checks and 55 HTTP/browser checks passed locally.
Production release gates remain open in the sprint plan; no production deployment
was performed.
