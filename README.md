# QuickLead Manager (Full Stack)

This repository contains the legacy `frontend` UI, a modern Vue 3 frontend port `frontendvue`, and the PHP backend `quicklead-backend`. This README shows how to set up, run, and deploy both projects locally on Windows (PowerShell). It also provides recommended commands, scripts, and quick troubleshooting steps.

---

## Table of contents
- Overview
- Directory structure
- Prerequisites
- Backend: QuickLead API (PHP)
  - Database configuration
  - Starting local backend (XAMPP / Apache / PHP built-in)
  - Database quick-start (SQL schema)
- Frontend: `frontendvue` (Vue 3 + Vite)
  - Development
  - Build, Preview
  - Useful scripts and configuration
- Safe deployment notes (how to deploy without taking the site down)
- Troubleshooting
- Contributing

---

## Overview
QuickLead Manager is a lightweight CRM for collecting & tracking incoming leads. The new frontend resides in `frontendvue` (Vue 3 + Vite). The backend is a PHP API (non-framework) served from `quicklead-backend/api`.

This README covers both parts and how they work together.

---

## Directory structure (important parts)
- `frontend/` - Legacy HTML/CSS/JS frontend (preserved for reference)
- `frontendvue/` - New Vue + Vite frontend application
  - `src/` — Vue components, views, services, assets
  - `scripts/` — (Optional) deployment scripts, build helpers
  - `dist/` — generated artifact after `npm run build` (shouldn't be committed)
- `quicklead-backend/` - PHP backend
  - `config/database.php` — DB connection configuration
  - `api/` — REST-like PHP endpoints: `auth.php`, `register.php`, `leads.php`, `projects.php`, `users.php`, etc.

---

## Prerequisites
- Windows (PowerShell recommended)
- Node.js (recommended LTS >= 20)
- PHP >= 7.4 or 8.x and Apache or XAMPP for local dev
- MySQL / MariaDB (or use XAMPP bundled DB)
- Composer (if needed for backend dependencies)

Optional tools:
- Postman or curl for API testing
- 7-Zip for backup/restore when deploying

---

## Backend: QuickLead API (PHP)
The backend is a small PHP-based API that expects a database connection. The DB setup is managed in `quicklead-backend/config/database.php`.

### Database config
Open `quicklead-backend/config/database.php` and update the connection settings (host, db_name, username, password) for your local MySQL instance.

Example (already configured in the repository):
```php
// quicklead-backend/config/database.php
class Database {
    private $host = "localhost";
    private $db_name = "quicklead_db";
    private $username = "root";
    private $password = "";
    public $conn;
    // ...
}
```

### Create the database & tables (SQL quick-start)
If you do not have an existing schema you can run the following minimal SQL (example) to create users, projects and leads tables.

> NOTE: This is a minimal example for development. Check /api scripts in `quicklead-backend` for the realistic schema used in production.

```sql
CREATE DATABASE IF NOT EXISTS quicklead_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quicklead_db;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) UNIQUE NOT NULL,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  password VARCHAR(255) DEFAULT NULL,
  role ENUM('admin','operator','client') NOT NULL DEFAULT 'client',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  api_key VARCHAR(255) DEFAULT NULL,
  lead_count INT DEFAULT 0
);

CREATE TABLE leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(64),
  email VARCHAR(255),
  project_id INT DEFAULT NULL,
  status ENUM('new','in_progress','success','failed') DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
);

-- Add a demo user
INSERT INTO users (username, name, email, role) VALUES ('admin', 'Admin', 'admin@qlm.local', 'admin');
INSERT INTO users (username, name, email, role) VALUES ('operator', 'Operator', 'operator@qlm.local', 'operator');
INSERT INTO users (username, name, email, role) VALUES ('client', 'Client', 'client@qlm.local', 'client');
```

### Running the backend locally (XAMPP / Apache)
- Move `quicklead-backend` into your webserver root (if using XAMPP: `C:\xampp\htdocs\quicklead-backend`).
  - Using this repo layout the folder is already at `D:\htdocs\quicklead-backend`.
- Configure a vhost or an alias to serve `quicklead-backend/api` at a URL like `http://localhost/quicklead-backend/api`.
- Visit `http://localhost/quicklead-backend/api/auth.php` (or the endpoints) to test the API.

Alternative: PHP built-in server for quick dev (not recommended for production):
```powershell
Set-Location -LiteralPath 'D:\htdocs\quicklead-backend\api'
php -S localhost:8000
# then call http://localhost:8000/auth.php etc. from the frontend's API base
```

Ensure `config/database.php` contains the correct credentials for the connection.

---

## Frontend: `frontendvue` (Vue 3 + Vite)

### Install dependencies (Windows / PowerShell)
```powershell
Set-Location -LiteralPath 'D:\htdocs\frontendvue'
npm ci
```

### Run development server (Vite)
```powershell
npm run dev
# Open http://localhost:5173 in a browser
```

### Build for production
```powershell
npm run build
# Build artifacts are generated to frontendvue/dist/
```

### Preview production build
```powershell
npm run preview
# Preview server exposes the built app for QA
```

### Key files
- `src/services/ApiService.js` — central API wrapper. The `baseUrl` points to the backend API (default: `http://localhost/quicklead-backend/api`). Update this if backend path differs.
- `src/views/` — main views: `Login.vue`, `AdminLayout.vue`, `ClientLayout.vue`, `Dashboard.vue`, `Projects.vue`, `Users.vue`, `Leads.vue`, plus others.
- `src/assets/quicklead.css` — full imported stylesheet based on the legacy theme.

### Demo login
The app supports demo login buttons (Admin / Operator / Client) from the login screen. Use these for local testing without backend authentication.

### Notes about the ported app vs legacy
- The Vue port mirrors the original UI and logic but some DOM behavior is now implemented via Vue (e.g., router-based navigation, reactive state). If you see missing functionality compare `frontend/scripts/*.js` to `frontendvue` views and the `ApiService` methods.
- Common missing behaviors to watch for: direct DOM event handlers replaced with Vue methods, confirm prompts replaced with modal components, and global CSS variable differences.

---

## Safe deploy (recommended)
> Warning: Never deploy directly to a live webroot without a backup and rollback plan.

A safe deploy approach for `frontendvue` typically follows: build artifacts to `dist/`, backup the existing `D:\htdocs\frontend` folder, copy the `dist` into `D:\htdocs\frontend` (or an atomic swap), run a quick health check, and keep the backup for quick rollback.

Example PowerShell workflow (manual):
```powershell
# From inside frontendvue
npm run build
Set-Location -LiteralPath 'D:\htdocs\frontendvue'
# 1) create zip backup of existing site
Compress-Archive -Path 'D:\htdocs\frontend\*' -DestinationPath 'D:\htdocs\frontend-backup-$(Get-Date -Format yyyyMMddHHmmss).zip'
# 2) copy build to site (atomic swap recommended)
# We copy the dist files into the target webroot
Copy-Item -Path 'dist\*' -Destination 'D:\htdocs\frontend' -Recurse -Force
# 3) validate site served by web server
Start-Sleep -Seconds 2
Invoke-WebRequest -Uri 'http://localhost/frontend' -UseBasicParsing | Select-Object StatusCode
```
If something goes wrong, re-extract the zip backup and overwrite the site directory.

Alternatively, you can add a `scripts/deploy.ps1` to automate these steps (see `frontendvue/scripts/deploy.ps1` pattern earlier).

---

## Backend API endpoints (examples)
The frontend expects these endpoints at `http://localhost/quicklead-backend/api` (update `ApiService.baseUrl` to match):
- `auth.php` — POST { username, password }
- `register.php` — POST { username, name, email, password }
- `leads.php` — GET, POST, PUT, DELETE
- `projects.php` — GET, POST, PUT, DELETE
- `users.php` — GET, POST, PUT, DELETE

Use `curl` or Postman for testing:
```powershell
curl -X GET "http://localhost/quicklead-backend/api/projects.php"
```

---

## Troubleshooting & common gotchas
- If `api` returns CORS errors, set proper CORS headers in the PHP response or serve both frontend and backend on the same host & port (reverse proxy or Apache vhost). For development, modify `ApiService.baseUrl` to match backend host/port.
- If `frontendvue` cannot fetch API, check that `quicklead-backend/api` is accessible in the browser.
- If you encounter `Unclosed block` PostCSS errors, ensure your CSS is valid (no stray braces) and Vite is running with the correct `postcss` settings.
- If the login/demo buttons behave differently between `frontend` and `frontendvue`, recheck the ported methods in `Login.vue` and `ApiService`.
- If the animated gradient does not appear full-screen, ensure `body` styles are set correctly:
  ```css
  html, body, #app { height: 100%; width: 100%; }
  body { background: linear-gradient(...); background-size: 400% 400%; animation: gradient 15s ease infinite; background-attachment: fixed; }
  ```

---

## Contributing & Development flow
- Use `npm ci` to install dependencies on each machine to ensure reproducible builds.
- When adding new endpoints to the backend, update the `ApiService` and create tests to confirm behavior.
- Keep `dist/` and `node_modules/` excluded from source control (check `.gitignore`).
- For UI changes, prefer modifying `src/assets/quicklead.css` and `src/views/*.vue` rather than the legacy `frontend` markup.

---

## Local test checklist
- [ ] Run PHP backend and confirm API endpoints are accessible.
- [ ] Run `npm ci` inside `frontendvue`, start `npm run dev`, verify login page works.
- [ ] Test demo-login flows (Admin/Operator/Client) and verify route changes.
- [ ] Build `npm run build` and validate `dist` contents render on the web server.

---

If you'd like, I can also:
- Add a `deploy.ps1` file that implements safe, atomic deploy + backup + rollback.
- Add a `docker-compose` setup to run backend & DB for easier development.
- Add SQL migration scripts to the backend for reproducible schema.

If you want me to proceed with any of those, tell me which ones and I'll implement them next.  

---

Maintainers:
- `frontendvue` port by: (project contributors)
- Backend by: (project contributors)

Thank you — let's make the port behave exactly like the legacy UI. If you want, I can now compare specific broken button behavior and fix it step by step (eg demo login, API calls, modal behavior, CSS differences).