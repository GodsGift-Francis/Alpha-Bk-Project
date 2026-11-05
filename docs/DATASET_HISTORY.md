# Dataset history

Admin center → Dataset history provides a description field, JSON upload or random generation, account selection, date range and count, then an explicit preview/save step.

Generated records are kept in `synthetic_history_batches`, never in the transaction ledger. A batch must reconcile to the account balance at save time. The account row is locked during save to detect changes since preview. Each account allows one batch; remove it before replacing. Removal is audited and does not delete genuine transactions.

Customers access their own batches through Dashboard → Dataset history. Descriptions are visible there. History is paginated, with a small “Synthetic data” note below the table, and linked documents and JSON exports retain the same distinction. Existing activity remains in the ordinary history. Imported closing balances are snapshots, not live balances. Generated opening/running balances may be negative; the preview explains this.

JSON upload: up to 2 MB and 1,000 chronological entries, USD, dates from 2001 to today, integer cents, unique references and IDs. Every running balance is checked. Missing row-level synthetic flags are added by the importer. Descriptions do not execute instructions or scripts.

Deploy migration `004_synthetic_history.php` using the existing CLI migration runner before enabling the pages. Deploy `admin-history.php` and `dataset-history.php` public wrappers along with the updated application. The packaging script includes these wrappers. Never reinitialize the live database. No customer dataset is committed to Git.

Checks: `php tests/synthetic_history.php [dataset.json]` and the standard `tests/http_browser.py` disposable-database suite.

