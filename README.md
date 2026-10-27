# Internship Tracking System

A role-based web application for managing student internships: placements,
attendance (including QR codes), documents, evaluations and reports.

Built with **PHP 8**, **MySQL/MariaDB**, **HTML/CSS/JavaScript** and a light
touch of vanilla JS. The interface uses an orange, black and white theme
(`#F97316`, `#111111`, `#FFFFFF`). The database is the single source of
truth for every record.

---

## 1. Technology stack

| Layer    | Technology                                                                    |
| -------- | ----------------------------------------------------------------------------- |
| Frontend | HTML5, CSS3, JavaScript (vanilla), responsive                                 |
| Backend  | PHP 8 (PDO, prepared statements)                                              |
| Database | MySQL / MariaDB (`internship_tracking_system`)                                |
| Server   | Apache (XAMPP)                                                                |
| QR codes | `qrcodejs` (display) + `BarcodeDetector` (scan), with a manual-token fallback |

---

## 2. Requirements

- Windows with **XAMPP** (Apache + PHP 8+ + MySQL/MariaDB).
- A modern browser. Chrome or Edge is recommended for camera QR scanning.
- Internet access is optional (only used for the web font and the QR
  rendering library). Attendance always has a manual fallback.

---

## 2b. Interface and audience

- The system is intended for one programme only: **BS Information
  Technology, 4th Year**. Registration, student profiles and the
  coordinator's student records therefore do not ask for a course or year
  level - both are set automatically.
- The interface uses an orange, black and white theme with a **light / dark
  mode toggle** in the sidebar (the choice is remembered in the browser).

## 3. Project structure

```
Internship-Tracking-System/
├── config/
│   ├── config.php              # App + database settings, url() helper
│   └── database.php            # PDO connection
├── includes/
│   ├── init.php                # Session + security headers bootstrap
│   ├── functions.php           # Shared helpers
│   ├── auth.php                # Login, guards, registration, password reset
│   ├── notifications.php       # Shared notifications component
│   ├── header.php              # App shell (sidebar/topbar)
│   ├── footer.php
│   ├── auth_header.php         # Public-page chrome
│   └── auth_footer.php
├── assets/
│   ├── css/style.css
│   ├── js/app.js               # Theme toggle, live search, confirmations, QR scan
│   ├── js/qrcode.min.js        # QR generation (vendored, works offline)
│   └── images/logo.png
├── student/                    # Student Intern pages
├── coordinator/                # Internship Coordinator pages
├── supervisor/                 # Company Supervisor pages
├── uploads/                    # Uploaded documents (execution blocked)
├── docs/                       # EERD diagram (EERD.png, eerd.html, eerd.mmd)
├── database.sql                # Schema + sample data
├── index.php                   # Entry point (routes to login/dashboard)
├── login.php  register.php  logout.php
├── forgot_password.php  reset_password.php
├── dashboard.php               # Sends each role to their dashboard
├── avatar.php                  # Authenticated profile-photo endpoint
├── download.php                # Authorised document download
├── install.php                 # One-time first-coordinator setup
└── README.md
```

---

## 4. Setup with XAMPP

1. **Install XAMPP** and open the XAMPP Control Panel. Start **Apache** and
   **MySQL**.

2. **Place the project**. Either keep it where it is, or copy the folder into
   `C:\xampp\htdocs\internship_tracking_system`. The application detects its
   own URL path, so both `http://localhost/internship_tracking_system/` and
   another folder name work.

3. **Import the database**.
   - Open `http://localhost/phpmyadmin`.
   - Choose **Import**, select `database.sql`, then **Import**.
   - This creates the `internship_tracking_system` database and sample data.
   - It can also be run from the command line:
     `C:\xampp\mysql\bin\mysql.exe -u root < database.sql`
   - The script **drops and recreates** the application tables, so import it
     only into a development database.

4. **Configure the connection** (only if your setup differs from XAMPP's
   defaults). The defaults are host `127.0.0.1`, port `3306`, database
   `internship_tracking_system`, user `root`, empty password. To override,
   set environment variables before starting Apache, or edit
   `config/config.php`:
   - `ITS_DB_HOST`, `ITS_DB_PORT`, `ITS_DB_NAME`, `ITS_DB_USER`,
     `ITS_DB_PASSWORD`

5. **Open the app** at `http://localhost/internship_tracking_system/`
   (adjust the folder name if you used a different one). Do **not** open the
   PHP files with a `file:///` URL - PHP must run through Apache.

6. **Sign in** with a sample account (below), or delete `install.php` after
   using it.

---

## 5. Demo accounts

All sample accounts use the password **`Intern@123`**.

| Role                   | Email / username |
| ---------------------- | ---------------- |
| Internship Coordinator | `coordinator`    |
| Student Intern         | `student01`      |
| Student Intern         | `student02`      |
| Company Supervisor     | `supervisor`     |

> These are for local development only. Change the passwords, and remove or
> replace the sample accounts before deploying anywhere others can reach.

---

## 6. Creating the first coordinator securely

Public registration creates **Student Intern** accounts only. Coordinator and
supervisor accounts are always created by an authorized user.

- The sample data already includes a coordinator, so you can simply sign in.
- For a clean install, open `install.php` once. It creates the first
  coordinator **only while no coordinator exists**, then refuses further use.
  **Delete `install.php` after using it.**
- Additional coordinators are created from **Coordinator → User Accounts**.

---

## 7. Roles and modules

**Student Intern** - dashboard, profile, placement, requirement uploads,
attendance, a separate **MyQR** page for displaying their personal QR to
authorized personnel, manual time in/out as a fallback, evaluations,
notifications, progress and rendered hours.

**Internship Coordinator** - dashboard counts, student records, companies,
supervisors, placements, requirements, document review, attendance
verification and correction, evaluations with criteria management,
announcements, deadline reminders, user accounts, reports (date filters, CSV,
print).

**Company Supervisor** - assigned interns, attendance verification, scanning
an intern's QR to record attendance, evaluations, notifications, profile.

Every module is backed by real PHP/MySQL logic: **Student Profile
Management, Company/Placement Management, Requirement Tracking, QR
Attendance, Attendance/Rendered Hours, Progress Monitoring, Document
Upload/Management, Evaluation/Feedback, Notifications/Reminders, Report
Generation, User/Role Management, and Login/Registration/Logout.**

---

## 8. Attendance, hours and progress

- Students time in/out, or scan a coordinator's shared QR session, or have a
  supervisor scan their personal QR.
- Duplicate time-ins are prevented; an open session must be closed before a
  new one starts.
- Only **approved** rendered hours count toward progress. Pending and rejected
  records never count.
- Required hours are configurable **per placement**. Progress is
  `approved ÷ required`, shown as a percentage and a progress bar, capped at
  100% while the approved total is unchanged.
- Coordinators can correct a record with a reason; the correction returns the
  record to _pending_ and is written to the **audit log**. Students can never
  edit their own hours.

---

## 9. Documents

- Coordinators define requirements, deadlines, allowed types and size limits.
- Students upload PDF/JPG/JPEG/PNG files, validated by extension **and**
  detected MIME type, capped at the configured size (default 5 MB).
- Files are stored under `uploads/` with random names. An `.htaccess` rule
  blocks direct listing and execution; files are served only through
  `download.php`, which checks that the requester is the coordinator, the
  owning student, or the assigned supervisor.
- Statuses: **Pending → Submitted → Approved / Rejected**, with reviewer
  remarks (a reason is required to reject).

---

## 9b. Profile photos

Every user can upload a profile photo (JPG or PNG, max 2 MB) from their
**My Profile** page. Photos are stored under `uploads/avatars/` (non-public,
non-executable) and served through the authenticated `avatar.php` endpoint.
The sidebar and header show the photo, falling back to the user's initials
when none is set. A photo can be replaced or removed at any time.

## 10. QR attendance

There are three user types: **Instructor (Coordinator)**, **Intern Student**,
and **Company / Authorized Personnel (Supervisor)**.

- Each student has a **personal QR code** (a unique attendance token), shown
  on their separate **MyQR** page. **Students display the code; they do not scan it.**
- The MyQR page renders a **student ID card**: programme header, profile photo,
  full name, student number, programme/year, company, supervisor, placement
  validity and the QR with the student's name printed beside it.
- The card can be **downloaded as a PNG** (`StudentID-<student number>.png`) so
  the student can keep it on their phone. The PNG is composed in the browser
  from the same data, so no server-side image library is required.
- After a successful scan, the coordinator or supervisor sees the student's
  **ID card in a popup**, together with the recorded action ("Time-in recorded
  for ..."), and can download the same card from there.
- The **Instructor (Coordinator)** scans the student's QR from the Attendance
  page (camera or manual token) to record time in / time out.
- The **Company Supervisor / Authorized Personnel** scans the QR of interns
  assigned to them to record time in / time out.
- The server decides whether a scan is a time-in or a time-out: if the student
  has an open session it times out, otherwise it starts a new one. Duplicate
  time-ins are prevented.
- All validation happens on the PHP server. Camera scanning needs a secure
  context (`localhost` or HTTPS) and camera permission. It uses the browser's
  native `BarcodeDetector` where available (Android, ChromeOS, macOS) and falls
  back to the bundled offline decoder `assets/js/jsqr.min.js` on Windows
  desktop Chrome/Edge, where `BarcodeDetector` is not implemented. A manual
  token field is always available as a fallback.
- If the camera does not open, click the camera icon in the address bar, allow
  access for `localhost`, then press **Start camera scanner** again. Pressing
  the button while it is scanning stops the camera.
- Students may still use the manual time in/out fallback if no authorized
  personnel is available, but that record still requires verification.
- A QR token identifies the account, not physical presence.

---

## 11. Security

- PDO prepared statements everywhere; passwords hashed with
  `password_hash()` and checked with `password_verify()`.
- Session id regenerated on login; cookies are HTTP-only and `SameSite=Lax`.
- CSRF tokens on all state-changing forms.
- Server-side role checks on every protected page and action.
- Output escaped with `htmlspecialchars()`.
- Uploads validated by type and size and kept non-executable.
- Database credentials kept in `config/config.php`, which Apache blocks from
  direct access (along with `includes/`, `storage/`, `database.sql`).

---

## 12. Troubleshooting

| Problem                      | Fix                                                                                                                                                                                   |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| "Database connection needed" | Start the XAMPP **MySQL/MariaDB**, import `database.sql`, and ensure its port matches `DB_PORT` in `config/config.php` (default **3306**). |
| Apache won't start           | Another app is using port 80/443; check the XAMPP logs.                                                                                                                               |
| Uploads fail                 | Check PHP `upload_max_filesize`/`post_max_size` in `C:\xampp\php\php.ini`, restart Apache, and ensure `uploads/` is writable.                                                         |
| Camera QR unavailable        | Use `http://localhost/...` or HTTPS, grant camera access, or use the manual token field.                                                                                              |
| Blank page / server error    | Check the Apache/PHP error log in the XAMPP Control Panel.                                                                                                                            |
| Testing the code             | Lint with `C:\xampp\php\php.exe -l <file>`; run locally with `php -S 127.0.0.1:8899 -t <project>`.                                                                                    |

---

## 13. Notes for this environment

- The project lives at `D:\maamCindy\Internship-Tracking-System`.
- The XAMPP MySQL data directory had to be rebuilt during setup after its
  InnoDB system tablespace became corrupted. The previous directory was
  preserved as `C:\xampp\mysql\data.corrupt-<timestamp>` and a full copy was
  saved to `D:\maamCindy\_mysql-data-backup-<timestamp>`.
- The former single-file application and its `internship_db` database were
  backed up to `D:\maamCindy\_ITS-backup-<timestamp>` before restructuring.

---

## 14. Database safety (fixes applied 2026-10-27)

The database had been corrupted twice before. Root cause: every time MySQL was
stopped, `C:\xampp\mysql_stop.bat` (and the "Stop all" button, via
`killprocess.bat`) ran `taskkill /F mysqld.exe`. A forced kill leaves Aria's
system tables (`mysql.db`, `mysql.roles_mapping`) half-written, and on the next
start the server refuses to boot with "Table is marked as crashed".

What was fixed:

1. **Graceful shutdown.** `mysql_stop.bat` now runs `mysqladmin shutdown` and
   waits for the process to exit. `killprocess.bat` does the same before it
   ever falls back to a forced kill. Original files are kept as
   `mysql_stop.bat.original-forcekill` and `killprocess.bat.original`.
2. **Corrupt system tables rebuilt.** `mysql.db` and `mysql.roles_mapping` were
   replaced with clean copies generated by `mysql_install_db.exe`; all 15
   application tables and the remaining `mysql` tables verify as `OK`.
3. **Durability settings** in `C:\xampp\mysql\bin\my.ini` (original saved as
   `my.ini.bak-*`):
   - `innodb_buffer_pool_size=128M`, `innodb_log_file_size=64M`
   - `innodb_doublewrite=1`, `innodb_file_per_table=1`
   - `aria_page_checksum=1`, `aria_recover_options=BACKUP,QUICK`
4. **Automatic backups** — `scripts/db-backup.bat` dumps every database to
   `D:\maamCindy\db-backups\auto-<timestamp>\full-backup.sql` and keeps the
   newest 30. Scheduled task **ITS Database Backup** runs it daily at 6:00 PM.
5. **Weekly integrity check** — `scripts/db-check.ps1` runs `mysqlcheck
   --all-databases`, logs to `D:\maamCindy\db-backups\integrity.log`, and exits
   non-zero when a table needs attention. Scheduled task **ITS Database
   Check** runs it Sundays at 9:00 AM.

**Rule:** never end `mysqld.exe` from Task Manager, and never close the XAMPP
window mid-write. If MySQL will not stop, wait for the 30-second timeout in
`mysql_stop.bat` instead of force-killing it.

**Restore from a backup:**

```bat
C:\xampp\mysql\bin\mysql.exe -u root < D:\maamCindy\db-backups\auto-YYYYMMDD-HHMMSS\full-backup.sql
```

**Manual backup** (before importing `database.sql` or doing anything risky):

```bat
D:\maamCindy\Internship-Tracking-System\scripts\db-backup.bat
```
