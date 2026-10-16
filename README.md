# Internship Tracking System

A role-based web application for managing student internships: placements,
attendance (including QR codes), documents, evaluations and reports.

Built with **PHP 8**, **MySQL/MariaDB**, **HTML/CSS/JavaScript** and a light
touch of vanilla JS. The interface uses an orange, black and white theme
(`#F97316`, `#111111`, `#FFFFFF`). The database is the single source of
truth for every record.

---

## 1. Technology stack

| Layer     | Technology                                        |
|-----------|---------------------------------------------------|
| Frontend  | HTML5, CSS3, JavaScript (vanilla), responsive     |
| Backend   | PHP 8 (PDO, prepared statements)                  |
| Database  | MySQL / MariaDB (`internship_tracking_system`)    |
| Server    | Apache (XAMPP)                                    |
| QR codes  | `qrcodejs` (display) + `BarcodeDetector` (scan), with a manual-token fallback |

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
   defaults). The defaults are host `127.0.0.1`, port `3307`, database
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

| Role                  | Email / username        |
|-----------------------|-------------------------|
| Internship Coordinator| `coordinator`           |
| Student Intern        | `student01`             |
| Student Intern        | `student02`             |
| Company Supervisor    | `supervisor`            |

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
attendance (displays their personal QR for authorized personnel to scan;
manual time in/out as a fallback), evaluations, notifications, progress and
rendered hours.

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
  record to *pending* and is written to the **audit log**. Students can never
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
  on their Attendance page. **Students display the code; they do not scan it.**
- The **Instructor (Coordinator)** scans the student's QR from the Attendance
  page (camera or manual token) to record time in / time out.
- The **Company Supervisor / Authorized Personnel** scans the QR of interns
  assigned to them to record time in / time out.
- The server decides whether a scan is a time-in or a time-out: if the student
  has an open session it times out, otherwise it starts a new one. Duplicate
  time-ins are prevented.
- All validation happens on the PHP server. Camera scanning requires a secure
  context (`localhost` or HTTPS) and browser `BarcodeDetector` support; a
  manual token field is always available as a fallback.
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

| Problem | Fix |
|---|---|
| "Database connection needed" | Start the XAMPP **MySQL/MariaDB** (in this setup it runs on port **3307** because the Windows `MySQL80` service occupies 3306), import `database.sql`, and check `config/config.php`. |
| Apache won't start | Another app is using port 80/443; check the XAMPP logs. |
| Uploads fail | Check PHP `upload_max_filesize`/`post_max_size` in `C:\xampp\php\php.ini`, restart Apache, and ensure `uploads/` is writable. |
| Camera QR unavailable | Use `http://localhost/...` or HTTPS, grant camera access, or use the manual token field. |
| Blank page / server error | Check the Apache/PHP error log in the XAMPP Control Panel. |
| Testing the code | Lint with `C:\xampp\php\php.exe -l <file>`; run locally with `php -S 127.0.0.1:8899 -t <project>`. |

---

## 13. Notes for this environment

- The project lives at `D:\maamCindy\Internship-Tracking-System`.
- The XAMPP MySQL data directory had to be rebuilt during setup after its
  InnoDB system tablespace became corrupted. The previous directory was
  preserved as `C:\xampp\mysql\data.corrupt-<timestamp>` and a full copy was
  saved to `D:\maamCindy\_mysql-data-backup-<timestamp>`.
- The former single-file application and its `internship_db` database were
  backed up to `D:\maamCindy\_ITS-backup-<timestamp>` before restructuring.
