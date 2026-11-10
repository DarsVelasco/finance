
---

# JCLF Finance Tracker

A PHP + MySQL web application for tracking church finances — income, expenses, and donations — with role-based access and PDF/CSV reporting.

Built for **Jesus Christ Leads Fellowship (JCLF)**.

![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white)

---

## Features

- 💰 Track income and expenses by Sunday date
- ❤️ Separate donations module (with donor tracking)
- 📊 PDF and CSV reports with filters
- 👥 Admin / Viewer roles
- 📥 CSV import with preview
- 🔒 CSRF protection, password hashing, password-confirmed edits/deletes

---

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.4+
- Apache / Nginx (XAMPP or WAMP recommended)

---

## Installation

```bash
git clone https://github.com/your-username/jclf-finance-tracker.git
cd jclf-finance-tracker
```

1. **Create the database:**
   ```sql
   CREATE DATABASE finance_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import the schema:**
   ```bash
   mysql -u root -p finance_tracker < finance_tracker.sql
   ```

3. **Edit `connection.php`** with your MySQL credentials.

4. **Add `logo.png`** to the project root (square image recommended).

5. **Install [FPDF](http://www.fpdf.org/)** into a `fpdf/` folder:
   ```
   jclf-finance-tracker/fpdf/fpdf.php
   ```

6. **Create the uploads folder:**
   ```bash
   mkdir uploads
   chmod -R 755 uploads
   ```

7. Visit `http://localhost/jclf-finance-tracker/`

---

## Project Structure

```
├── connection.php          # DB connection
├── auth.php                # Auth, CSRF, session helpers
├── index.php               # Dashboard
├── income.php / expense.php
├── donations.php           # Donation dashboard
├── summary.php             # Period summaries
├── import.php              # CSV import
├── report_selector.php     # Report generator
├── generate_pdf.php / generate_csv.php
├── login.php / register.php / logout.php
├── profile.php / manage_users.php
├── header.php / footer.php
├── style.css / script.js
├── finance_tracker.sql     # Schema + seed data
└── fpdf/                   # PDF library (download separately)
```

---

## User Roles

| Feature | Admin | Viewer |
|---------|:-----:|:------:|
| View data | ✅ | ✅ |
| Generate reports | ✅ | ✅ |
| Add / edit / delete | ✅ | ❌ |
| Import CSV | ✅ | ❌ |
| Manage users | ✅ | ❌ |

Edits and deletions require **password confirmation**.

---

## Security

- `password_hash()` / `password_verify()` for passwords
- CSRF tokens on all forms
- Prepared statements everywhere
- `htmlspecialchars()` on all output
- HttpOnly + SameSite=Lax session cookies
- Foreign keys with `ON DELETE CASCADE`

**Production tips:**
- Enable HTTPS (`'secure' => true` in `auth.php`)
- Change the default admin password
- Disable error display in `connection.php`
- Schedule regular `mysqldump` backups

---

## Reports

Both modules (main finance and donations) support PDF and CSV exports with filters for:

- Month / Year
- Category
- Optional sections: summary, cash flow, monthly, weekly, top expenses, full ledger

---

## License

Private — for Jesus Christ Leads Fellowship. All rights reserved.

---

**"Whatever you do, work at it with all your heart, as working for the Lord."**
— Colossians 3:23
