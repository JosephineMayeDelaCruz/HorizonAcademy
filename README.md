# Horizon Academy Web System

A role-based web system for Horizon Academy that digitizes enrollment queuing, clearance processing, grade viewing, and document requests.

**Tech Stack:** PHP (vanilla) · MySQL · Apache (XAMPP)

---

## Local Setup

### Requirements
- XAMPP (Apache + MySQL)
- Git

### Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/JosephineMayeDelaCruz/horizon_academy-web.git
   cd horizon_academy-web
   ```

2. **Start XAMPP** — enable Apache and MySQL in the XAMPP Control Panel.

3. **Create the database**
   - Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
   - Create a new database named `horizon_academy`
   - Import `schema.sql` first, then `migration.sql`

4. **Configure the database connection**
   - Open `config/db.php` and confirm credentials match your XAMPP setup
   - Default XAMPP credentials: host `localhost`, user `root`, password *(empty)*

5. **Seed the passwords**
   - `seed_passwords.php` is not tracked in git for security reasons
   - Create the file manually in the project root with this content:
   ```php
   <?php
   require 'config/db.php';
   $users = $pdo->query("SELECT id, password FROM users")->fetchAll();
   $count = 0;
   foreach ($users as $u) {
       if (str_starts_with($u['password'], '$2y$')) continue;
       $hashed = password_hash($u['password'], PASSWORD_DEFAULT);
       $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $u['id']]);
       $count++;
   }
   echo "$count password(s) hashed. Delete this file after use.";
   ```
   - Visit `http://localhost/horizon_academy-web/seed_passwords.php` once
   - **Delete the file after running it**

6. **Open the app**
   - Go to [http://localhost/horizon_academy-web](http://localhost/horizon_academy-web)

---

## Default Accounts

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Faculty | `rfernandez` | `fac123` |
| Student | `2403938` | `stu123` |
| Registrar | `registrar` | `reg123` |
| Cashier | `cashier` | `cash123` |
| Department | `dept_ccs` | `dept123` |

> ⚠️ Change all default passwords before any public deployment.

---

## Modules

- **Student Portal** — grades, attendance, clearance status, document requests, enrollment queue
- **Registrar** — student management, clearance approval, document request processing, reports
- **Cashier** — payment processing, document request fulfillment
- **Department** — clearance management per department
- **Admin** — full system management (users, courses, reports)
- **Faculty** — attendance and grade encoding

---

## Security Notes

- All database queries use PDO prepared statements (SQL injection protection)
- Brute force lockout and rate limiting implemented in `auth/login.php`
- `schema.sql`, `migration.sql`, and `seed_passwords.php` are excluded from version control via `.gitignore`
- `config/db.php` contains credentials — do not commit production passwords

---

## Group 4 · CCS110 Information Management 1

---

## Git Branches

| Branch | Contents |
|---|---|
| `main` | Core: config, auth, login (`index.php`), partials, assets, migrations, deploy files |
| `feature/student-portal` | `student/` module |
| `feature/registrar-department` | `registrar/` and `department/` modules |
| `feature/cashier-faculty` | `cashier/` and `faculty/` modules |
| `feature/admin` | `admin/` module |

## Deployment (Railway)

Set these variables on the web service (reference the MySQL service): `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`. `config/db.php` reads them automatically and falls back to XAMPP defaults locally. Delete `fix_db.php` after its first run.
