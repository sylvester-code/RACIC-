# ROYAL AMBASSADORS OF CHRIST INTERNATIONAL CHURCH (RACIC)
## Enterprise Church Management System (CMS)

A production-ready, centralized Church Management Platform developed in native PHP 8 and MySQL (PDO) specifically engineered for the leadership, administration, ministries, and membership of the **Royal Ambassadors of Christ International Church (RACIC)**.

---

## 1. System Technology Stack

| Layer | Technology Specification |
|---|---|
| **Backend Language** | PHP 8+ (Tested & verified on PHP 8.2 in XAMPP) |
| **Relational Database** | MySQL 8.x / MariaDB 10.4+ (`racic_church_management`) |
| **Database Connection** | PHP Data Objects (PDO) with prepared statements & transactions |
| **Frontend UI** | HTML5, CSS3, Bootstrap 5.3.3 |
| **Typography & Icons** | Font Awesome 6.5.1 |
| **Data Visualizations** | Chart.js 4.4.2 |
| **Interactive Calendar** | FullCalendar 6.1.11 |
| **Reports & Exporting** | Dedicated Print Engine (HTML-to-PDF / Print CSS) & Client/Server CSV/Excel Exporter |
| **Target Runtime** | XAMPP (Apache + MariaDB) / Linux LAMP / Virtual Hosts |

---

## 2. Super Administrator Master Account

The system is delivered in a pristine **clean-slate** state with **NO dummy or pre-existing records**. Only the Super Administrator account exists:

| Field | Credential |
|---|---|
| **Username** | `RACIC` |
| **Password** | `AMBASSADORS` |
| **Role** | Super Administrator (Full System Authority) |
| **Email** | `superadmin@racic.org` |

### How User Accounts & Department Portals Work:
1. The **Super Administrator** logs in using `RACIC` and `AMBASSADORS`.
2. The Super Administrator creates church ministries and departments in **Departments** (`departments/create.php`).
3. The Super Administrator goes to **User Accounts** (`admin/users.php`) to create accounts for other leaders:
   - Assigns a unique **Username**
   - Assigns the **Password** directly
   - Selects the specific **Role** (Head Pastor, Church Administrator, Department Leader, Finance Officer, or Church Worker)
   - Assigns the **Department** so that department leaders and workers immediately get access to their dedicated **Department Portal** (`departments/view.php`).
4. Other administrators and department leaders log in with their assigned username and password and access their respective modules and portals.

---

## 3. System Architecture & Folder Layout

```text
c:\xampp\htdocs\RACIC/
│
├── index.php                         # Gateway router (redirects to dashboard or login)
├── login.php                         # Secure login accepting Username or Email
├── logout.php                        # Secure session destruction and audit logging
├── dashboard.php                     # Executive Leadership Dashboard with live KPI charts
│
├── config/
│   ├── database.php                  # PDO singleton connection with error handling
│   └── app.php                       # Base URL autodetect, church metadata, CSRF helpers
│
├── includes/
│   ├── header.php                    # Topbar, notifications drawer, responsive layout
│   ├── sidebar.php                   # Collapsible, role-permission-aware navigation
│   ├── footer.php                    # Chart.js, FullCalendar, bootstrap scripts
│   ├── auth.php                      # Role check, session security, audit logger
│   └── functions.php                 # Sanitization, upload handlers, formatters, alerts
│
├── activities/
│   ├── index.php                     # Activities monitoring center with status pills
│   ├── create.php                    # Activity scheduler with budget, target & workers
│   ├── edit.php                      # Modify schedule, status, and department
│   ├── view.php                      # Detailed activity view & pastoral evaluation report
│   ├── report.php                    # Post-event report form (attendance, spend, highlights)
│   └── delete.php                    # Secure record deletion with CSRF protection
│
├── members/
│   ├── index.php                     # Member directory with search, filter, CSV export
│   ├── create.php                    # Registration form with photo upload & baptism status
│   ├── edit.php                      # Update personal, contact, and spiritual records
│   ├── view.php                      # Profile with attendance and giving ledger
│   └── delete.php                    # Remove member record and attached media
│
├── visitors/
│   ├── index.php                     # Visitor retention tracker & follow-up status
│   ├── create.php                    # Visitor intake form with prayer requests & inviter
│   ├── view.php                      # Visitor profile & historical follow-up timeline
│   ├── follow-up.php                 # Log call, visit, WhatsApp message & next action date
│   ├── convert.php                   # 1-Click convert visitor into full church member
│   └── delete.php                    # Remove visitor record
│
├── departments/
│   ├── index.php                     # Department registry & budget utilization cards
│   ├── create.php                    # Establish new ministry & designate leader
│   ├── edit.php                      # Edit department details & meeting schedule
│   └── view.php                      # Dedicated Department Management Portal (Members, Activities, Expenses)
│
├── attendance/
│   ├── index.php                     # Service attendance overview with demographic breakdown
│   ├── mark.php                      # Record headcount (Men, Women, Children) & roll call
│   ├── services.php                  # Service registry (Sunday, Midweek, All-night, etc.)
│   └── reports.php                   # Attendance analytics filtered by date, department, member
│
├── finance/
│   ├── index.php                     # Financial dashboard (Inflow vs Outflow Chart, summaries)
│   ├── income.php                    # Income & Tithe log with receipt attachment
│   ├── expenses.php                  # Expenditure disbursements with pastoral approval flow
│   ├── budgets.php                   # Departmental budget allocation & burn rates
│   └── reports.php                   # Official Financial Statements & audit signatures
│
├── calendar/
│   ├── index.php                     # Interactive FullCalendar view with event modals
│   └── events_feed.php               # JSON event sync provider
│
├── announcements/
│   └── index.php                     # Targeted bulletins (Entire church, Workers, Depts)
│
├── media/
│   └── index.php                     # Photo gallery, event flyers, church documents
│
├── reports/
│   └── index.php                     # Centralized PDF/Excel reports generator
│
├── admin/
│   ├── users.php                     # User account creation, role assignment, password reset
│   ├── roles.php                     # Permissions matrix across the 6 roles
│   ├── audit.php                     # Security audit trail of all system actions
│   └── settings.php                  # System diagnostics and SQL backup exporter
│
├── assets/
│   ├── css/racic-theme.css           # Custom theme stylesheet (Royal Blue & Gold palette)
│   └── js/racic-main.js              # Sidebar toggle, CSV export, confirm dialogs
│
├── uploads/                          # Secure upload directories
│   ├── members/
│   ├── receipts/
│   ├── activities/
│   └── media/
│
└── database/
    └── racic_church_management.sql   # Complete normalized schema with Super Admin account
```

---

## 4. How to Access the System Locally (XAMPP)

1. Start **Apache** and **MySQL** in your XAMPP Control Panel.
2. Open your web browser and navigate to:
   ```text
   http://localhost/RACIC/
   ```
3. Sign in using the Super Admin credentials:
   - **Username**: `RACIC`
   - **Password**: `AMBASSADORS`

---

## 5. Vercel Serverless Deployment

When deploying to Vercel:
1. Connect your GitHub repository (`https://github.com/sylvester-code/RACIC-.git`) to Vercel.
2. In **Vercel Dashboard &rarr; Project Settings &rarr; Environment Variables**, add your cloud MySQL connection string:
   - `DATABASE_URL` (e.g. from TiDB Cloud, Aiven, or Railway)
   - Or add `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.
3. **Automated Migration**: You do not need to manually run SQL scripts! The system detects an empty cloud database on first connect and automatically creates all 23 tables, roles, permissions, and the initial Super Admin account (`RACIC` / `AMBASSADORS`).
4. Click **Redeploy** on Vercel and your live system is ready.
