# AgriConnect ERP Core

Agricultural Management System for Bongabong Municipal Agriculture Office

## Project Overview

AgriConnect ERP is a comprehensive web-based Enterprise Resource Planning system designed specifically for agricultural management at the municipal level. The system provides centralized management of six key agricultural modules:

1. **FFRMS** - Farmer & Farm Registration Management System
2. **APMS** - Agricultural Production & Monitoring System  
3. **AISMS** - Agricultural Inventory & Supply Management System
4. **AASMS** - Agricultural Assistance & Subsidy Management System
5. **AEAMS** - Agricultural Equipment & Asset Management System
6. **ABPMS** - Agricultural Budget & Procurement Management System

## Technology Stack

- **Backend**: Laravel 13
- **Frontend**: Laravel Blade, Tailwind CSS 4, Alpine.js
- **Database**: MySQL
- **Asset Management**: Vite
- **Authentication**: Laravel Session-based Authentication
- **Development**: Windows/Laragon, Composer, Node.js/npm

## Project Structure

```
agriconnect-erp/
├── app/
│   ├── Http/Controllers/ERP/        # ERP Core Controllers
│   ├── Modules/                     # Six Agricultural Modules
│   ├── Services/                    # Business Logic Services
│   ├── Contracts/                   # Interfaces & Contracts
│   └── Providers/                   # Service Providers
├── config/
│   └── agriconnect.php              # Application Configuration
├── database/
│   ├── migrations/                  # Database Migrations
│   ├── seeders/                     # Database Seeders
│   └── factories/                   # Model Factories
├── resources/
│   ├── views/
│   │   ├── layouts/                 # Main Layouts
│   │   ├── components/              # Reusable Components
│   │   ├── auth/                    # Authentication Views
│   │   ├── dashboard/               # Dashboard Views
│   │   ├── modules/                 # Module-specific Views
│   │   └── [erp-sections]/          # ERP Administration Views
│   ├── css/
│   └── js/
└── routes/
    ├── web.php                      # Main Web Routes
    └── modules/                     # Module Route Files
```

## Installation & Setup

### Prerequisites

1. PHP 8.3 or compatible version
2. Composer
3. Node.js & npm
4. MySQL Database
5. Laragon (recommended for Windows)

### Installation Steps

1. **Clone/Extract the project**
   ```bash
   cd c:\Agriconnect
   ```

2. **Install PHP dependencies**
   ```bash
   cd agriconnect-erp
   composer install
   ```

3. **Install JavaScript dependencies**
   ```bash
   npm install
   ```

4. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   
   Edit `.env` file with your database credentials:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=agriconnect_erp
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

5. **Create database**
   ```bash
   mysql -u root -p -e "CREATE DATABASE agriconnect_erp;"
   ```

6. **Run migrations**
   ```bash
   php artisan migrate
   ```

7. **Build frontend assets**
   ```bash
   npm run build
   ```

8. **Start development server**
   ```bash
   php artisan serve
   ```

9. **Access the application**
   - Open browser to: `http://localhost:8000`
   - Use a locally provisioned account and its private password. The repository does not contain default credentials.

## Module Development Guidelines

### For Module Developers

Each agricultural module is developed independently within the `app/Modules/` directory:

```
app/Modules/MODULE_NAME/
├── Http/
│   └── Controllers/
│       └── ModuleDashboardController.php
├── Models/
├── Services/
├── Requests/
├── Policies/
└── Providers/
```

### Module Registration

Modules are registered in `config/agriconnect.php` under the `modules` array. Each module should have:

```php
'module_key' => [
    'name' => 'Module Display Name',
    'developer' => 'Developer Name',
    'description' => 'Module description',
    'route_prefix' => 'modules/module_key',
    'enabled' => true,
]
```

### Adding New Module Routes

Create a route file in `routes/modules/module_key.php` and register it in `RouteServiceProvider`.

## Color Scheme & Design

The application uses an agriculture-inspired color palette:

- **Deep Forest Green**: `#163D2A` - Primary brand color
- **Emerald Green**: `#237A52` - Accent and interactive elements
- **Agricultural Gold**: `#D5AB50` - Highlights and warnings
- **Light Sage**: `#EAF2E8` - Backgrounds and cards
- **Off White**: `#F8FAF7` - Main background
- **Dark Slate**: `#26352D` - Text and headings

## User Roles & Permissions

The system supports five user roles:

1. **Super Administrator** - Full system access
2. **MAO Administrator** - Administrative functions
3. **Municipal Agriculturist** - Technical agricultural functions
4. **Module Personnel** - Module-specific access
5. **Authorized Viewer/Auditor** - Read-only access

## Development Commands

### Common Development Tasks

```bash
# Run migrations
php artisan migrate

# Run seeders only against an isolated local database after setting private values
# DEMO_STAFF_PASSWORD and DEMO_PASSWORD in the untracked .env file.
php artisan db:seed

# Start development server
php artisan serve --port=8080

# Build assets for production
npm run build

# Watch assets for development
npm run dev

# Run tests
php artisan test

# Generate model with migration, factory, and controller
php artisan make:model ModelName -mfc
```

### Creating New Components

```bash
# Create new Blade component
php artisan make:component Components/ComponentName
```

## Security Considerations

- Input validation on all forms
- CSRF protection enabled
- Seeders require private environment-supplied passwords and never contain shared credentials
- Secure password hashing
- Role-based access control
- Module-specific authorization
- Activity logging for administrative actions
- Secure session handling

## Deployment

1. Ensure production environment variables are set
2. Run `composer install --no-dev`
3. Run `npm run build`
4. Set up database and run migrations
5. Configure web server (Nginx/Apache)
6. Set up SSL certificates
7. Configure queue workers (if using queues)
8. Set up scheduled tasks (cron jobs)

## Contributing

1. Fork the repository
2. Create feature branch
3. Commit changes
4. Push to branch
5. Create Pull Request

## License

Proprietary - Developed for Bongabong Municipal Agriculture Office

## Support

For technical support, contact the development team or refer to the module-specific documentation.

---
**Version**: 1.0.0  
**Last Updated**: October 2026  
**Developed by**: AgriConnect Development Team
