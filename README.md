# VocalID - Voice Biometric Attendance System

## Features
- Student login
- Attendance tracking
- Voice authentication

## How to run
1. Install XAMPP
2. Import database
3. Run localhost/vocalid

## Technologies
- PHP
- MySQL
- HTML/CSS/JS
```
htdocs/vocalid/
│
├── index.php               ← Landing/splash page (PHP: redirects if logged in)
├── faculty-login.php       ← Faculty login with session + password_verify()
├── student-login.php       ← Student registration, saves to DB
├── dashboard.php           ← Protected faculty dashboard (live DB stats)
├── class.php               ← Class attendance page with voice recognition
├── voice.php               ← Student voice enrollment page
├── reports.php             ← Reports with live DB data + CSV export
├── logout.php              ← Destroys PHP session, redirects to index
│
├── db.php                  ← Database connection (mysqli)
├── mark_attendance.php     ← API: marks attendance via fetch() POST
├── reset_attendance.php    ← API: resets today's attendance for a class
│
├── vocalid_database.sql    ← Run this in phpMyAdmin to set up DB
│
├── css/
│   └── style.css           ← Original design preserved (unchanged)
│
└── js/
    └── vocalid.js          ← Lightweight UI helper (toast, formatting)
```

---

## ⚙️ Step-by-Step XAMPP Setup

### Step 1 — Install XAMPP
Download from: https://www.apachefriends.org/
Install and launch the XAMPP Control Panel.
Start **Apache** and **MySQL** modules.

### Step 2 — Copy Project Files
Copy the entire `vocalid/` folder into:
```
C:\xampp\htdocs\vocalid\        (Windows)
/Applications/XAMPP/htdocs/vocalid/   (macOS)
```

### Step 3 — Set Up the Database
1. Open your browser and go to: **http://localhost/phpmyadmin**
2. Click **"New"** in the left sidebar
3. Type `vocalid` as the database name → Click **Create**
4. Click the **SQL** tab at the top
5. Open the file `vocalid_database.sql` in a text editor
6. **Copy all the SQL** and paste it into the phpMyAdmin SQL box
7. Click **Go** to run it

This will create:
- `users` table (faculty accounts)
- `students` table (enrolled students with voice profiles)
- `attendance` table (all attendance records)
- Demo faculty accounts and 13 sample students

### Step 4 — Configure Database Connection (if needed)
Open `db.php` and update if your XAMPP MySQL has a different password:
```php
define('DB_HOST', 'localhost');   // Usually stays as localhost
define('DB_USER', 'root');        // XAMPP default
define('DB_PASS', '');            // XAMPP default: empty password
define('DB_NAME', 'vocalid');     // Must match database name
```

### Step 5 — Run the Application
Open your browser and visit:
```
http://localhost/vocalid/
```

---

## 🔑 Demo Login Credentials

| Faculty ID      | Password   | Role    |
|-----------------|------------|---------|
| FAC-2024-001    | password   | faculty |
| FAC-2024-002    | password   | faculty |
| admin           | password   | admin   |

> **Note:** The password hash in the SQL file is for the string `password`.
> To create your own accounts with custom passwords:
> 1. Open phpMyAdmin → vocalid → users table
> 2. Or run in SQL tab:
>    ```sql
>    INSERT INTO users (username, password, role)
>    VALUES ('FAC-2024-003', '$2y$10$YOUR_HASH_HERE', 'faculty');
>    ```
> 3. Generate hash with: `echo password_hash('yourpassword', PASSWORD_BCRYPT);`

---

## 🔄 How Each Feature Works

### Faculty Login (`faculty-login.php`)
1. Faculty enters ID + password
2. PHP queries `users` table: `SELECT * FROM users WHERE username = ?`
3. `password_verify($input, $stored_hash)` checks the password
4. On success: `$_SESSION['faculty_logged_in'] = true` is set
5. Redirects to `dashboard.php`

### Session Protection (`dashboard.php`, `class.php`, `reports.php`)
Every protected page starts with:
```php
session_start();
if (!isset($_SESSION['faculty_logged_in']) || $_SESSION['faculty_logged_in'] !== true) {
    header('Location: faculty-login.php');
    exit;
}
```
This prevents direct URL access by unauthenticated users.

### Student Registration (`student-login.php`)
1. Student fills form → PHP validates server-side
2. Checks if student already exists (by roll number or email)
3. If new: `INSERT INTO students (name, email, roll_no, department, class_id, enrolled) VALUES (...)`
4. Student info saved to `$_SESSION['student_name']`, `$_SESSION['student_roll']`, etc.
5. Redirects to `voice.php`

### Voice Enrollment → Attendance (`voice.php` + `mark_attendance.php`)
1. `voice.php` reads student name/roll from PHP session
2. Student clicks record → simulated 3-second recording per sample
3. After 4 samples: JavaScript sends a `fetch()` POST request:
   ```javascript
   fetch('mark_attendance.php', {
     method: 'POST',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify({ student_name, roll_no, confidence })
   })
   ```
4. `mark_attendance.php` receives JSON, validates, checks for duplicates,
   then runs:
   ```sql
   INSERT INTO attendance (student_name, roll_no, class_id, date, time, status, confidence)
   VALUES (?, ?, ?, ?, ?, 'present', ?)
   ```
5. Returns JSON `{ success: true, message: "..." }`

### Voice Recognition in Class (`class.php`)
1. Faculty clicks mic button → Web Speech API starts
2. Spoken name is matched against student table rows in the DOM
3. On match: `fetch('mark_attendance.php', ...)` marks the student present
4. Table row updates dynamically (no page reload)

### Reports (`reports.php`)
- All data fetched live from MySQL with `SELECT` queries
- Filter by class or date using URL parameters (`?class=class1&date=2024-11-25`)
- Export to CSV via `?export=csv` — PHP sets headers and outputs CSV directly

### Logout (`logout.php`)
```php
$_SESSION = [];              // Clear all session data
session_destroy();           // Delete session from server
header('Location: index.php'); // Redirect to home
```

---

## 🗄️ Database Tables Reference

### `users` table
| Column     | Type         | Description                        |
|------------|--------------|------------------------------------|
| id         | INT PK AUTO  | Auto-increment ID                  |
| username   | VARCHAR(100) | Faculty ID (e.g. FAC-2024-001)    |
| password   | VARCHAR(255) | bcrypt hash via password_hash()    |
| role       | ENUM         | 'faculty' or 'admin'              |
| created_at | TIMESTAMP    | Auto-set on insert                 |

### `students` table
| Column     | Type         | Description                        |
|------------|--------------|------------------------------------|
| id         | INT PK AUTO  | Auto-increment ID                  |
| name       | VARCHAR(150) | Student full name                  |
| email      | VARCHAR(150) | Student email (unique)             |
| roll_no    | VARCHAR(50)  | Roll number (unique)               |
| department | VARCHAR(10)  | Department code (CS, IT, etc.)    |
| class_id   | VARCHAR(20)  | class1 or class2                   |
| enrolled   | TINYINT      | 1 = voice profile created          |
| created_at | TIMESTAMP    | Auto-set on insert                 |

### `attendance` table
| Column       | Type         | Description                      |
|--------------|--------------|----------------------------------|
| id           | INT PK AUTO  | Auto-increment ID                |
| student_name | VARCHAR(150) | Student name at time of marking  |
| roll_no      | VARCHAR(50)  | Student roll number              |
| class_id     | VARCHAR(20)  | class1 or class2                 |
| date         | DATE         | Date of attendance (YYYY-MM-DD)  |
| time         | TIME         | Time marked (HH:MM:SS)           |
| status       | ENUM         | 'present' or 'absent'            |
| confidence   | INT          | Voice match confidence (0-100)   |
| marked_at    | TIMESTAMP    | Auto-set on insert               |

---

## 🔧 Useful SQL Queries

```sql
-- View all attendance records
SELECT * FROM attendance ORDER BY date DESC, time DESC;

-- Today's attendance for CS101
SELECT * FROM attendance WHERE class_id = 'class1' AND date = CURDATE();

-- Student attendance percentage
SELECT student_name,
       COUNT(*) as total_sessions,
       SUM(status='present') as present,
       ROUND(SUM(status='present') / COUNT(*) * 100, 1) as pct
FROM attendance
GROUP BY student_name
ORDER BY pct DESC;

-- Reset all attendance (for testing)
DELETE FROM attendance;

-- Add a new faculty account (password = 'newpass123')
INSERT INTO users (username, password, role)
VALUES ('FAC-2024-010', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty');
```

---

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| "Database connection failed" | Check Apache + MySQL are running in XAMPP; verify db.php credentials |
| Login fails with correct password | Re-run the SQL file; the hash must match the `password` string |
| Voice recognition not working | Use Google Chrome (Safari/Firefox have limited Speech API support) |
| Page shows blank / PHP errors | Enable error display: add `ini_set('display_errors', 1);` at top of any PHP file |
| Can't access http://localhost/vocalid | Make sure project is inside `htdocs/vocalid/` folder |
| Duplicate attendance entries | `mark_attendance.php` already prevents duplicates per student per day per class |

---

## 🔐 Security Notes

- All user inputs are sanitized with `htmlspecialchars()` and `trim()`
- All database queries use **prepared statements** (`$stmt->bind_param(...)`) to prevent SQL injection
- Passwords stored as **bcrypt hashes** via `password_hash()` — never plain text
- All protected pages check `$_SESSION['faculty_logged_in']` at the top
- Student session data is validated server-side before use
