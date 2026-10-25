# AlphaBank design research and implementation

Research date: 25 October 2026. Scope: public website, customer banking workspace,
administrator operations workspace and shared transactional screens.

## Sources reviewed

- [Monzo current accounts](https://monzo.com/current-account) — account families, concise product explanations, clear enrollment actions and security information.
- [Wise homepage](https://wise.com/) and [transfer flow guidance](https://wise.com/help/articles/2596987/how-can-i-send-money-from-my-wise-account) — action-oriented information architecture and an explicit recipient/amount/review sequence.
- [Revolut homepage](https://www.revolut.com/) — oversized editorial headings, a focused main action and distinct product sections.
- [N26 homepage](https://n26.com/en-eu) — simple product grouping, account presentation and a mobile-first product narrative.
- [Chase online banking](https://www.chase.com/digital/online-banking) — central account activity, transfer review, reference numbers and printable details.
- [Capital One online banking](https://www.capitalone.com/bank/online-banking/) — task-based feature discovery and practical frequently asked questions.

These are observations of published public pages and help material, not access to
private customer dashboards. The application layout below is an original design
inferred from these patterns. Product availability, financial offers, customer
counts and regulatory claims from those institutions are not transferred to AlphaBank.

## Findings translated into design decisions

### 1. Show the next useful action

The reviewed sites give prominent placement to opening an account, signing in or
moving money. AlphaBank now gives its homepage one primary enrollment action and
its customer workspace direct deposit, withdrawal and transfer shortcuts. Actions
use the existing routes and request flows, with no new financial capability implied.

### 2. Build confidence through hierarchy and specificity

Clear typography, consistent navigation and concrete explanations make an
interface easier to assess. The new identity uses deep forest green, pale sage,
warm off-white, Manrope headings and DM Sans body text. A small geometric wordmark
and native SVG icons replace mixed visual treatments. The homepage illustration
is explicitly illustrative; it does not claim that a payment card has been issued.

### 3. Separate acquisition from daily banking

The public website explains accounts and the existing review process. Its product
sections describe Checking, Savings and Business without inventing interest rates,
fees, insurance coverage or card services. The public FAQ answers balance timing,
invoice status and device access. Customer and administrator sign-in stay separate,
and public navigation continues to omit the admin entry.

### 4. Put real account data first

The customer view keeps the first-name greeting, actual balances, masked account
numbers and the existing show/hide controls. Account cards have a strong visual
hierarchy and distinct active/pending presentation. Status is retained on invoices;
no approved transaction badge has been reintroduced in customer history. No sample
balance or fabricated analytics is shown in a real customer's workspace.

### 5. Make operations feel like an operational tool

The administrator receives a dedicated sidebar with links to reviews, account
requests, clients and records. Existing counts and total balances remain database
values. Review queues, reasons, confirmation dialogs, client search, invoices and
audit events keep their existing behavior. The new section navigation reduces the
need to scroll through an undifferentiated page.

### 6. Keep critical details near the decision

Deposit, withdrawal and transfer pages retain the approval explanation and account
selection. Confirmation dialogs, idempotent submissions and server-side checks are
unchanged. Invoices retain current status, timestamps and review details. The
redesign changes presentation rather than the meaning of a pending or posted request.

## Responsive and accessibility approach

At desktop sizes, a persistent sidebar anchors the workspaces and a bounded
content area prevents extremely long lines. On compact screens the sidebar becomes
a collapsible navigation region, shortcuts become compact tiles and transaction
history remains a sequence of labeled cards. Dense operational tables scroll
inside bounded regions instead of forcing the whole page sideways. Forms use
native labels, comfortable control sizes and visible keyboard focus. Native dialogs,
reduced-motion handling and print styling remain supported. Fonts have system
fallbacks if the external font provider is unavailable.

## Validation and limits

The HTTP regression suite checks roles, CSRF, money posting, retry behavior,
account review, notification isolation, invoice ownership and logout. Visual browser
review covers the landing, authentication, customer dashboard, money forms,
preferences, invoices and administrator workspace across representative widths.
This is a design and usability pass; it does not add licensing, deposit protection,
external payment settlement, identity verification, MFA, card issuing, bill pay,
interest or investment products. Those would require separate product and backend
work, not cosmetic controls.
