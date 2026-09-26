# Evenza — College Event Management System
## BCA Final Year Project &bull; Campus Event Portal

**Evenza** is a complete, secure web platform designed for college campuses to streamline event announcements, student registrations, capacity tracking, and digital E-Ticket distribution with live QR verification.

---

## 🛠️ Strict Technology Stack
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5 (via CDN)
- **Backend**: PHP (strictly **`mysqli` with prepared statements only** — no PDO)
- **Database**: MySQL (`evenza_db`)
- **Server**: XAMPP on macOS (Apache + MySQL) or PHP Built-in Server
- **IDE**: VS Code

---

## 🚀 Setup Instructions for macOS with XAMPP

### Step 1: Copy to XAMPP `htdocs`
Copy the `evenza` folder directly into your XAMPP web root:
```bash
cp -r "/Users/shreegowribk/event management/evenza" /Applications/XAMPP/xamppfiles/htdocs/
```
*(Or if using standard XAMPP directory: `/Applications/XAMPP/htdocs/evenza`)*

### Step 2: Start Apache & MySQL
1. Launch **XAMPP Application Manager** (`/Applications/XAMPP/manager-osx.app`).
2. In **Manage Servers**, start **Apache Web Server** and **MySQL Database**.

### Step 3: Import Database via phpMyAdmin
1. Open `http://localhost/phpmyadmin/`.
2. Click **New** and create database: `evenza_db`.
3. Select `evenza_db` &bull; click **Import** &bull; choose `database.sql` &bull; click **Go**.

### Step 4: Run the One-Time Admin Setup
Open in browser:
```
http://localhost/evenza/setup_admin.php
```
This dynamically computes real bcrypt password hashes and provisions default accounts.

---

## 🔑 Default Credentials
- **Evenza Admin**: `admin@evenza.com` / `Admin@123`  
  Portal: `http://localhost/evenza/admin/admin_login.php`
- **Demo Student**: `1EV21MC001` / `Student@123`  
  Portal: `http://localhost/evenza/login.php`
