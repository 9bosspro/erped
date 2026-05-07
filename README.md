# Laravel 13 ERP System (Headless)

This project is a **Headless ERP (Enterprise Resource Planning)** system built with **Laravel 13**. It follows a **Microservices-like architecture** where the frontend and backend are decoupled. The backend provides a RESTful API and uses Laravel Modules to organize features.

## Key Features

- **Headless Architecture**: Backend provides API, frontend consumes it (Inertia.js is used for initial rendering/demo purposes).
- **Laravel Modules**: Features are organized into independent modules (e.g., Demo, Inventory, Finance, HR).
- **Laravel Permission**: Comprehensive role-based access control (RBAC) using the Spatie Laravel Permission package.
- **Master-Slave Architecture**: Supports integration with a Master Server for licensing and data sync (via `engine/slave` package).
- **Modern Authentication**: Laravel Fortify for robust authentication flows.
- **TypeScript Frontend**: React frontend with TypeScript for type safety.

## Prerequisites

- PHP 8.- or higher
- Composer
- Node.js and NPM
- MySQL 8.0 or higher

## Installation

1.  **Clone the repository:**
    ```bash
    git clone <repository-url>
    cd erped
    ```

2.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

3.  **Configure Environment:**
    Copy the `.env.example` file to `.env` and configure your database credentials.
    ```bash
    cp .env.example .env
    ```
    *Edit `.env` with your `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, etc.*

4.  **Generate App Key:**
    ```bash
    php artisan key:generate
    ```

5.  **Run Migrations:**
    This will create the necessary database tables, including the permission tables.
    ```bash
    php artisan migrate
    ```

6.  **Seed Initial Data (Admin User):**
    Create an admin user with the 'super_admin' role.
    ```bash
    php artisan db:seed --class=AdminUserSeeder
    ```

7.  **Install Frontend Dependencies:**
    ```bash
    cd public/erped-frontend
    npm install
    ```

8.  **Run Frontend Build:**
    ```bash
    npm run dev
    ```

9.  **Run Development Server:**
    ```bash
    php artisan serve
    ```
    *The application will be accessible at `http://localhost:8000`.*
    *The frontend will be available at `http://localhost:8000/erped` (if inertia is configured to route there, or check `public/erped` directly).*)

## Directory Structure

- **`app/Modules/`**: Contains the core business logic organized into modules.
    - `Demo/`: Example module showing module structure.
    - `Inventory/`: (Placeholder) For inventory management.
    - `Finance/`: (Placeholder) For financial accounting.
- **`public/erped-frontend/`**: React + TypeScript frontend application.
- **`config/`**: Configuration files.
    - `permission.php`: Permission system configuration.
    - `modules.php`: Module configuration.
    - `slave.php`: Master-Slave integration settings.
- **`routes/`**: API and frontend route definitions.
- **`engine/slave/`**: Custom package for Master Server integration.

## Modules

Modules are self-contained Laravel packages that can be enabled or disabled. You can create new modules by running:

```bash
php artisan module:create <ModuleName>
```

## Access Control

The application uses Spatie Laravel Permission:
- **Roles**: Super Admin, Admin, Staff, etc.
- **Permissions**: Fine-grained permissions for specific actions.

### Default Login
- **Email**: [EMAIL_ADDRESS]`
- **Password**: `admin1234`
