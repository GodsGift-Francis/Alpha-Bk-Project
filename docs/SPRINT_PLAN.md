# AlphaBank production upgrade sprints

Core contract: multiple accounts, user-submitted deposits/withdrawals/transfers,
manual administrator approval, and balance changes only on approval. No automatic
payment execution, currency conversion, or external banking integration is introduced.

## Verification recorded for this delivery
- 29 financial integrity checks passed, including concurrent duplicate approvals and overspending.
- 55 HTTP/browser checks passed, including authorization, CSRF, notification isolation,
  confirmation flows, safe rendering and layout widths 320/375/768/1280px in Chromium.
- PHP and JavaScript syntax checks passed. CI workflow added; remote CI has not run here.
- Existing local development database upgraded; no production deployment performed.

## Sprint 1 — financial integrity and security (implemented)
- Exact decimal input, strict action/account validation, atomic approvals with row locks.
- Revalidate source funds and active source/recipient at approval; duplicate request protection.
- CSRF on all mutations, safe DOM rendering, authorization before output, safer sessions/errors.
- Preserve rejected requests and posted history; record actor/time/reason in audit events.
- CLI-only versioned migrations, consistent fresh-install schema, integration regression tests.
Exit: concurrent/repeated approvals cannot post twice, overdrafts are blocked, rejected
requests remain visible, unauthorized mutations fail, and schema installs/upgrades repeatably.

## Sprint 2 — responsive experience (implemented)
- Responsive navigation, account grids, readable mobile forms/tables, consistent components.
- Keyboard-accessible dialogs, focus states, accessible status/error feedback.
- Pending-approval explanations, disabled duplicate submissions, explicit loading/error states.
Exit: core workflows usable at phone/tablet/desktop widths and by keyboard.

## Sprint 3 — daily operations (core implemented; extensions queued)
- Search/filter/paginated history with transaction reference and review details.
- Per-user database notification read state; latest 20 announcements displayed. Full notification archive remains queued.
- Admin queue details and rejection reasons; clearer operational terminology.
- Queued: saved beneficiaries, printable receipts/statements, full notification archive, support requests.
Exit: users and admins can trace a request from submission through decision.

## Sprint 4 — release operations (foundation implemented; release gates open)
- Reproducible environment config, CI checks, staging deployment and rollback documentation.
- Before public launch: credential/rate-limit policy, password recovery and email verification,
  MFA/admin permission separation, backup/restore rehearsal, monitoring and alerting,
  reconciliation, security review, load/accessibility/browser testing.
- Decide underlying currency policy before any real-funds deployment. Existing currency
  preference changes the display symbol only; it is not foreign exchange.
Exit: staging acceptance, restore rehearsal, monitoring verified and operational owner sign-off.

Do not describe this checkout as ready for real customer funds. External settlement,
regulatory obligations and production infrastructure require separate validation.
