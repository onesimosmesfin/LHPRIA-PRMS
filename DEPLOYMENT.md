# LHPRIA - Planning & Reporting Management System (PRMS)
**የመሬት ይዞታ ምዝገባና መረጃ ኤጀንሲ - የእቅድና ሪፖርት አስተዳደር ስርዓት**

---

## 1. System Architecture & Features

The **LHPRIA PRMS** application is an enterprise PHP/MySQL platform designed for municipal administration across **11 Addis Ababa Subcities** and the **Central Headquarter (HQ)**.

### Hierarchy & Supported Subcities
- **Headquarter (HQ)**
- **11 Subcities**: Addis Ketema (AK), Akaky Kaliti (AKT), Arada (ARD), Bole (BOL), Gulele (GUL), Kirkos (KIR), Kolfe Keranio (KK), Lemi Kura (LMK), Lideta (LID), Nefas Silk-Lafto (NSL), Yeka (YEK).

### Role-Based Access Control (RBAC)
1. **System Administrator (`admin`)**:
   - Access: `/land/admin/users.php`
   - Responsibilities: Account creation, role and location assignments, user password resets, account activation/deactivation.
2. **Planner (`planner`)**:
   - Access: `/land/planner/plans.php`
   - Responsibilities: Formulate quarterly and annual plans, define KPI targets, unit of measures, planned revenues; submit plans for director approval.
3. **Subcity Director (`subcity_director`)**:
   - Access: `/land/director/review_plans.php` & `/land/director/subcity_reports.php`
   - Responsibilities: Review subcity plans, approve or rollback with remarks; inspect submitted execution reports and forward verified reports to HQ.
4. **Reporter (`reporter`)**:
   - Access: `/land/reporter/create_report.php` & `/land/reporter/submit_report.php`
   - Responsibilities: Enter progress and actual results against approved plans, record revenue collected, report on challenges and actions taken.
5. **HQ Director (`hq_director`)**:
   - Access: `/land/hq/dashboard.php`
   - Responsibilities: Executive overview, city-wide scorecards, 11 subcity performance rankings, revenue target vs. actual collection metrics, aggregate report reviews.

---

## 2. Active Deployment Links

### 🌐 Live Public Access (HTTPS):
- **Primary Public Link**: [https://oluth-196-191-60-145.free.pinggy.net/land/](https://oluth-196-191-60-145.free.pinggy.net/land/)
- **Mirror Public Link**: [https://oluth-196-191-60-145.free.pinggy.net/land/](https://oluth-196-191-60-145.free.pinggy.net/land/)

### 🖥️ Local Access:
- **Local URL**: [http://localhost/land/](http://localhost/land/)
- **Direct Login**: [http://localhost/land/login.php](http://localhost/land/login.php)

---

## 3. Pre-Configured Demo Credentials

| Role | Full Name | Username | Password | Default Subcity/Location |
| :--- | :--- | :--- | :--- | :--- |
| **System Admin** | System Administrator | `admin` | `admin123` | Headquarter (HQ) |
| **Planner** | Abay Mulisa | `Abay` | `abay@123` | Akaky Kaliti |
| **Planner 2** | Abraham Mekonen | `abrish` | `abrish123` | Akaky Kaliti |
| **Subcity Director** | Geleta Tola | `geleta` | `geleta123` | Akaky Kaliti |
| **Reporter** | Bersha | `bersha` | `bersha123` | Akaky Kaliti |
| **HQ Director** | Bekele Gudisa | `bekele` | `bekele123` | Headquarter (HQ) |

---

## 4. Improvements & Fixes Applied

1. **Database Schema Initialization & Fixes**:
   - Created and imported `prms_db` into MySQL.
   - Added missing `hq_remarks` and `updated_at` columns to `reports` table to prevent SQL exceptions when HQ directors submit remarks.
2. **Dynamic Configuration (`config/db.php`)**:
   - Added environment variable support (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`).
   - Improved `base_url()` to dynamically adapt whether hosted in `/land/` or domain root `/`.
3. **Root Entry Point (`index.php`)**:
   - Added clean routing that automatically redirects guests to `login.php` and authenticated users to their designated role dashboards.
4. **Asset Linking Fix**:
   - Fixed typo in `includes/header.php` referencing `bootstrap.min.php` to direct CDN `bootstrap.min.css`.
5. **Turnkey Deployment Files**:
   - Added `run_locally.bat` for instant local execution.
   - Added `share_publicly.bat` for encrypted public tunneling.
   - Added `Dockerfile` and `docker-compose.yml` for multi-platform cloud containerization.
