# BMS College of Commerce & Management (BMSCCM)
## College Event Management System — BCA Final Year Project

A complete, secure, and production-ready web platform built for **BMS College of Commerce and Management (VV Puram, Bengaluru)**.

---

## 🛠️ Technology Stack
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5 (CDN)
- **Backend**: PHP (mysqli with prepared statements only)
- **Database**: MySQL (InnoDB, Foreign Keys, Unique Constraints)
- **Server**: XAMPP / Apache / PHP CLI Built-in Server

---

## 🚀 Running the Project

### Option A: Running via PHP Built-in Server (Instant on macOS)
1. Ensure MySQL service is active:
   `brew services start mysql` (or start MySQL in XAMPP)
2. Import `database.sql`:
   `mysql -u root < database.sql`
3. Run setup script:
   `php setup_admin.php`
4. Start the server:
   `php -S localhost:8000`
5. Open browser at `http://localhost:8000/`

### Option B: Running with XAMPP
1. Copy `bmsccm` to `/Applications/XAMPP/xamppfiles/htdocs/`
2. Start Apache and MySQL in XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin/`, create database `bmsccm_events` and import `database.sql`.
4. Open `http://localhost/bmsccm/setup_admin.php` to initialize admin.
5. Visit `http://localhost/bmsccm/index.php`.

---

## 🔑 Default Credentials
- **Admin**: `admin@bmsccm.edu.in` / `Admin@123`
- **Demo Student**: `1BM21MC001` / `Student@123`
