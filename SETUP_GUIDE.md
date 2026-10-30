# AgriConnect ERP - Setup Guide

## Current Status
The Laravel project has been successfully created with:
- ✅ Laravel 12 installation
- ✅ All directory structure created
- ✅ Views, controllers, and services
- ✅ Database migrations and seeders ready
- ✅ Six agricultural modules configured
- ✅ AgriConnect theme and design

## Database Setup Required

### Error: Database Connection Failed
The application is trying to connect to MySQL but the database doesn't exist yet.

### Solution 1: Use Laragon (Recommended for Windows)
1. Open Laragon
2. Start MySQL service
3. Open Laragon terminal
4. Run: `mysql -u root`
5. Create database: `CREATE DATABASE agriconnect_erp;`
6. Exit: `exit`

### Solution 2: Manual MySQL Setup
1. Start MySQL service
2. Open command prompt/powershell
3. Connect to MySQL: `mysql -u root -p`
4. Enter password (default is empty)
5. Create database: `CREATE DATABASE agriconnect_erp;`
6. Exit: `exit`

### Solution 3: Use SQLite (Simpler)
Update `.env` file:
```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
SESSION_DRIVER=file
```

Then create SQLite database:
```bash
cd agriconnect-erp
# Create database file
New-Item -Path database/database.sqlite -ItemType File -Force
```

## Complete Setup Steps

### Step 1: Configure Database
Choose one option above and update `.env` file accordingly.

### Step 2: Run Migrations
```bash
cd agriconnect-erp
php artisan migrate
```

### Step 3: Seed Database
```bash
# Set DEMO_STAFF_PASSWORD and DEMO_PASSWORD privately in the untracked .env first.
php artisan db:seed
```

### Step 4: Start Development Server
```bash
php artisan serve --port=8080
```

### Step 5: Access Application
Open browser to: `http://localhost:8080`

## Account Credentials
No default password is stored in the repository. Seeders require private
`DEMO_STAFF_PASSWORD` and `DEMO_PASSWORD` values from the untracked `.env`.
For an existing installation, use the authorized account setup workflow rather
than guessing or publishing credentials.

## Project Structure
```
agriconnect-erp/
├── app/Modules/           # Six agricultural modules
├── resources/views/       # All Blade templates
├── database/migrations/   # Database schema
├── database/seeders/      # Initial data
└── routes/web.php        # Application routes
```

## For Module Developers
Each developer works in their module directory:
- `app/Modules/FFRMS/` - Lei (Farmer Registration)
- `app/Modules/APMS/` - Roselle (Production Monitoring)
- `app/Modules/AISMS/` - Christine (Inventory)
- `app/Modules/AASMS/` - Flor (Assistance)
- `app/Modules/AEAMS/` - Jaymark (Equipment)
- `app/Modules/ABPMS/` - Rainier (Budget)

## Troubleshooting

### Error: "Unknown database 'agriconnect_erp'"
Solution: Create the database as shown above.

### Error: "SQLSTATE[HY000] [2002] No connection"
Solution: Start MySQL service.

### Error: Session issues
Solution: Change `SESSION_DRIVER=database` to `SESSION_DRIVER=file` in `.env`

## Quick Fix for Immediate Testing
To test the application immediately without MySQL:

1. Update `.env`:
```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
SESSION_DRIVER=file
```

2. Create SQLite file:
```bash
cd agriconnect-erp
New-Item -Path database/database.sqlite -ItemType File -Force
```

3. Skip migrations for now by modifying `routes/web.php` to show simple welcome page.

## Support
- Check Laravel logs: `storage/logs/laravel.log`
- Run status check: `php artisan agriconnect:status`
- View current setup: Visit `/setup-test` in browser

---
**AgriConnect ERP Core v1.0.0**
**Bongabong Municipal Agriculture Office**
**October 2026**
