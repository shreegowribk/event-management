# 🎟️ Evenza — Campus Event Management System

A comprehensive, responsive web application for college campuses to streamline event discovery, student registrations, capacity tracking, and digital E-Ticket distribution with live verification.

---

## 🌟 Key Features

- **Public Event Showcase**: Browse upcoming, ongoing, and past college events with category filtering (Technical, Cultural, Sports, Workshops, Seminars).
- **Student Portal**: Secure student registration, login, profile management, and history of registered events.
- **Seat Capacity & Real-Time Tracking**: Automatic seat tallying with waitlists and sold-out flags to prevent overbooking.
- **Digital E-Tickets**: Instant downloadable/printable ticket cards featuring custom QR verification codes and registration IDs.
- **Administrator Dashboard**: Full management suite to create/modify events, monitor registrations, view attendee rosters, and download reports.
- **Responsive Cinema & Modern UI**: Built with Bootstrap 5 and customized modern styling.

---

## 🛠️ Technology Stack

- **Backend**: PHP (strictly **`mysqli` with prepared statements** for SQL injection protection)
- **Database**: MySQL (`evenza_db`)
- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Bootstrap 5
- **Server**: Compatible with XAMPP (Apache + MySQL), MAMP, WAMP, or PHP built-in development server

---

## 📂 Repository Structure

```text
event-management/
├── index.html           # Public Event Showcase & Registration Landing
├── admin/               # Administrative Console & Live QR Gate Scanner
│   └── index.html
├── student/             # Student Portal for Pass Tracking & Self-Service
│   └── index.html
├── js/                  # Real-Time Cloud Engine & Persistence
│   └── firebase-db.js
├── css/                 # Modern Global Design Tokens & Styles
│   └── style.css
├── vercel.json          # Deployment routing & clean URL rewrites
└── evenza/              # Canonical Full-Stack PHP & MySQL Release
    ├── admin/           # PHP Administrative portal & moderation tools
    ├── config/          # MySQL database connection (db.php)
    ├── css/             # PHP edition stylesheets
    ├── includes/        # Shared templates, headers & footers
    ├── dashboard.php    # Student user dashboard
    ├── database.sql     # MySQL schema
    ├── event_register.php # Registration workflow
    ├── index.php        # PHP landing catalog
    ├── login.php        # PHP user authentication
    ├── profile.php      # User account settings
    ├── ticket.php       # E-Ticket generator with QR verification
    └── README.md        # Evenza PHP documentation
```

---

## 🚀 Quick Setup & Installation

### 1. Database Setup
1. Open **phpMyAdmin** (e.g., `http://localhost/phpmyadmin`) or your MySQL client.
2. Create a database named `evenza_db` (or import directly):
   ```sql
   CREATE DATABASE evenza_db;
   ```
3. Import `database.sql` located inside `evenza/database.sql`.

### 2. Configure Database Credentials
Verify or update the connection settings in `evenza/config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'evenza_db');
```

### 3. Launch the Application
- **Option A: Using XAMPP / MAMP**
  Copy the folder into your server web root (e.g., `/Applications/XAMPP/xamppfiles/htdocs/` or `C:\xampp\htdocs\`) and access:
  ```text
  http://localhost/evenza
  ```

- **Option B: Using PHP Built-in Server**
  ```bash
  cd evenza
  php -S localhost:8000
  ```
  Open `http://localhost:8000` in your web browser.

---

## 🔐 Administrative Access
Run `setup_admin.php` on first run to configure the super-administrator account or inspect `database.sql` for pre-seeded administrator credentials.

---

## 📄 License
This project is licensed under the MIT License.
