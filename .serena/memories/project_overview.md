# FutScore Project Overview

## Purpose
FutScore is a management system for football or futsal scoring and player management.

## Tech Stack
- **Backend**: PHP (using both mysqli and PDO)
- **Database**: MySQL
- **Testing**: PHPUnit
- **Dependencies**: Composer

## Project Structure
- `admin/`: Contains administrative scripts and forms for managing perangkat (staff/officials), players, teams, etc.
- `includes/`: Shared configuration (`config.php`), database connection (`db.php`), and helper functions (`functions.php`).
- `api/`: Likely contains API endpoints.
- `migrations/`: Database migration scripts.
- `tests/`: Integration and unit tests.
- `uploads/`: Directory for uploaded files like photos and certificates.

## Database Connection
The project uses `includes/db.php` for a mysqli-based wrapper and often uses PDO in some scripts (like `admin/perangkat_create.php`). Database credentials are defined in `includes/config.php` or `admin/config/database.php`.

## Coding Style
- Standard PHP, often mixing logic and HTML in some admin files.
- Modern admin pages use 'Plus Jakarta Sans' font and a CSS-based sidebar.
- Uses CSRF protection in forms.
