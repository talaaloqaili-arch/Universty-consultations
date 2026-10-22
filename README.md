# University Advisor Appointment System

A web-based system where students book consultations with academic advisors. Advisors manage their own time slots and approve or reject requests, and admins manage users and monitor statistics.

## Features

### Student
- Register (name, email, university ID, department, password), log in, log out
- Browse available time slots by date, advisor, or department, with remaining capacity
- Book a slot with a consultation topic and an optional note (status starts as **Pending**)
- "My Appointments" page showing date, time, advisor, and status (Pending / Confirmed / Rejected / Cancelled)
- Cancel own appointments before the slot starts

### Advisor
- View requests for their own slots only
- Confirm, reject, or cancel appointments
- Add slots (date, start, end, capacity) with overlap prevention
- Delete slots that have no Pending or Confirmed appointments

### Admin
- Dashboard statistics: total, pending, and confirmed appointments, students, advisors
- Appointment status chart (Chart.js)
- Manage appointments: search by name or email, filter by status, confirm / reject / cancel
- Manage users: activate or deactivate accounts (deactivated users cannot log in)
- Create advisor accounts

## Business Rules
- Past time slots cannot be booked
- A slot cannot exceed its capacity
- A student cannot book the same slot more than once
- Cancelled and rejected appointments do not count toward capacity
- Slots with Pending or Confirmed appointments cannot be deleted
- Only advisors (for their own slots) and admins can confirm or reject
- Students can view and cancel only their own appointments
- Deactivated accounts cannot log in

## Technologies
- **Backend:** PHP 8 (PDO, prepared statements, sessions)
- **Database:** MySQL / MariaDB (managed with phpMyAdmin)
- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5
- **Charts:** Chart.js
- **Server:** Apache (XAMPP)
- **Version control:** Git / GitHub

## Security
- Passwords hashed with `password_hash()` / verified with `password_verify()`
- Session-based authentication with `session_regenerate_id()` on login
- Server-side role-based access control (student / advisor / admin)
- CSRF tokens on all POST forms
- Prepared statements (SQL injection protection) and output escaping (XSS protection)
- Server-side validation with clear error messages
- Row locking (`SELECT ... FOR UPDATE`) inside a transaction to enforce capacity

## Project Structure
```
advisor-system/
├── config/db.php
├── includes/ (auth.php, header.php, footer.php)
├── sql/schema.sql
├── seed.php
├── index.php               # available slots
├── register.php, login.php, logout.php
├── reserve.php, my-appointments.php, cancel.php
├── advisor/ (index.php, actions.php)
├── admin/ (index.php, appointments.php, users.php, actions.php)
├── assets/js/app.js
├── tests/
└── README.md
```

## Installation

1. **Install XAMPP** and start **Apache** and **MySQL**.
2. **Copy the project** into `C:\xampp\htdocs\advisor-system`
   (or clone it: `git clone <your-repo-url> advisor-system`).
3. **Create the database:** open `http://localhost/phpmyadmin`, create a database
   (e.g. `university_consultations`), select it, open the **SQL** tab, and run `sql/schema.sql`.
4. **Configure the connection:** edit `config/db.php` and set `dbname` to your database name
   (plus the username/password if you changed the defaults).
5. **Seed demo accounts:** open `http://localhost/advisor-system/seed.php` once, then delete or protect the file.
6. **(Optional) Demo slots:** run the sample slots `INSERT` query in phpMyAdmin.
7. **Open the app:** `http://localhost/advisor-system/`

## Demo Accounts

| Role | Email | Password |
|---|---|---|
| Admin | admin@demo.com | ***! |
| Advisor | advisor@demo.com | ***! |
| Student | student@demo.com | ***! |

> For demonstration only. Change these passwords in any real deployment.

## Testing Checklist

| Test | Expected result |
|---|---|
| Valid registration and login | Success, redirected by role |
| Invalid email / short password / invalid university ID | Rejected with an error message |
| Student opens admin or advisor pages | 403 Forbidden |
| Advisor opens admin pages | 403 Forbidden |
| Student cancels another student's appointment | 404 Not found |
| Booking a past slot | Rejected |
| Duplicate booking | Rejected |
| Booking a full slot | Rejected |
| Status change by advisor/admin | Shown correctly on the student's page |
| Deactivated user logs in | Rejected |
| Deleting a slot with active appointments | Rejected |
| Advisor modifies another advisor's slot/appointment | Rejected |
| Advisor adds an overlapping slot | Rejected |

## Demo Workflow
1. Register a student, log in, and browse slots.
2. Book a slot (Pending), then try to book it again (error) and a past slot (error).
3. Log in as advisor, then confirm the request.
4. Return to the student account to see **Confirmed**, then cancel it.
5. Log in as admin: view statistics and chart, search/filter appointments.
6. Add a slot, try deleting a slot with a booking (rejected), then delete an empty slot.
7. Deactivate a user and show that they cannot log in.
8. Show the tables and data in phpMyAdmin.

## Git Workflow
Meaningful commits were used throughout, for example:
`feat: database schema`, `feat: auth`, `feat: role-based access control`,
`feat: slots listing`, `feat: create appointment with business rules`,
`feat: advisor dashboard`, `feat: admin dashboard with Chart.js`, `test: business rules`, `docs: README`.


