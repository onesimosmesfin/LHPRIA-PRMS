# LHPRIA - Planning & Reporting Management System (PRMS)
**የመሬት ይዞታ ምዝገባና መረጃ ኤጀንሲ - የእቅድና ሪፖርት አስተዳደር ስርዓት**

[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://render.com/deploy?repo=https://github.com/onesimosmesfin/LHPRIA-PRMS)

---

## ☁️ 24/7 Cloud Deployment (Runs When Laptop is Off)

This repository includes a standalone all-in-one container (`Dockerfile`) that bundles **PHP 8.2, Apache, and MariaDB (MySQL)** with automatic schema initialization (`prms_db.sql`).

### Option 1: 1-Click Deploy on Render (Free)
1. Click the button above or open:  
   👉 **[Deploy on Render](https://dashboard.render.com/blueprint/new?repo=https%3A%2F%2Fgithub.com%2Fonesimosmesfin%2FLHPRIA-PRMS)**
2. Sign in with GitHub (`onesimosmesfin`).
3. Click **Apply** / **Create Web Service**.
4. Render builds the container and gives you a permanent 24/7 cloud URL:  
   `https://lhpria-prms.onrender.com/land/`

### Option 2: 1-Click Deploy on Koyeb (Free)
1. Open:  
   👉 **[Deploy on Koyeb](https://app.koyeb.com/services/deploy?type=git&repository=onesimosmesfin/LHPRIA-PRMS&branch=main)**
2. Select GitHub repository `onesimosmesfin/LHPRIA-PRMS`.
3. Click **Deploy**.

### Option 3: Deploy to any Cloud VPS / Server (AWS, DigitalOcean, Hetzner, etc.)
On your cloud server, run:
```bash
git clone https://github.com/onesimosmesfin/LHPRIA-PRMS.git
cd LHPRIA-PRMS
docker compose up -d
```

---

## 🔑 Default Credentials

| Role | Username | Password | Location | Default Landing Page |
| :--- | :--- | :--- | :--- | :--- |
| **System Admin** | `admin` | `admin123` | Headquarter (HQ) | `/land/admin/users.php` |
| **Planner** | `Abay` | `abay@123` | Akaky Kaliti Subcity | `/land/planner/plans.php` |
| **Planner 2** | `abrish` | `abrish123` | Akaky Kaliti Subcity | `/land/planner/plans.php` |
| **Subcity Director** | `geleta` | `geleta123` | Akaky Kaliti Subcity | `/land/director/review_plans.php` |
| **Reporter** | `bersha` | `bersha123` | Akaky Kaliti Subcity | `/land/reporter/create_report.php` |
| **HQ Director** | `bekele` | `bekele123` | Headquarter (HQ) | `/land/hq/dashboard.php` |
