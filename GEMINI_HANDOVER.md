# AlphaBank Project Handover State
**Last Updated:** April 1, 2026

## 📌 Project Overview
AlphaBank is a modern, MySQL-powered digital banking platform featuring a professional UI, multi-account management, and a robust Admin Command Center with manual oversight for all financial movements.

## ✅ Completed Milestones
1. **Core Infrastructure:** Migrated from SQLite to MySQL. Built a one-click `install.php` wizard.
2. **Security:** Implemented CSRF protection, secure password hashing, and real-time database-to-session synchronization.
3. **User Features:**
   - Multi-account creation (Checking, Savings, Business).
   - Dynamic currency settings ($, €, £, ₦, ¥).
   - Universal funds transfer (Internal & to other users).
   - Modernized Notification Bell with pulsing alerts and targeted/general announcements.
4. **Admin Features:**
   - Account Approval/Rejection queue.
   - Transaction Approval/Rejection queue (Money is only moved after approval).
   - Administrative Editing: Can update client names and transaction timestamps via UI.
   - Data Management: Can delete users, accounts, and transactions.
   - Communication Hub: Can send broadcast or user-specific private announcements.

## 🛠️ Current Architecture
- **Backend:** PHP 8+ with PDO MySQL.
- **Frontend:** Vanilla CSS (Modern Desktop Layout), Vanilla JS with a modular `api-client.js` and `notifications.js`.
- **Database:** `users`, `accounts`, `transactions`, `announcements`.

## 🚦 Navigation & Access
- **Admin Panel:** `admin.php` (Visible only to users with `is_admin = 1`).
- **User Dashboard:** `dashboard.php` (Hidden from Admins).
- **Redirection:** Fixed all 404 path issues; the app is sub-directory friendly.

## 🚀 Future Roadmap / Pending Tasks
- **Mobile Responsiveness:** A mobile-first overhaul was started but reverted to maintain desktop stability. This is the next logical UI step.
- **Email Notifications:** Integrating an SMTP server for transaction alerts.
- **Search/Filters:** Adding search bars to the Admin Audit tables.

---
*This file is generated to ensure continuity between AI sessions.*
