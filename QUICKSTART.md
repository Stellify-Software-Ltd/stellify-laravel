# Quick Start Guide

## For Package Users

### 1. Install Package
```bash
cd your-laravel-project
composer require stellify/laravel
```

### 2. Create Export Database

Create a new database where your Laravel project will be exported:

**MySQL:**
```sql
CREATE DATABASE stellify_export CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**PostgreSQL:**
```sql
CREATE DATABASE stellify_export;
```

**SQLite:**
```bash
touch database/stellify.sqlite
```

### 3. Configure Database Connection

Add to `config/database.php`:
```php
'stellify' => [
    'driver' => env('STELLIFY_DB_DRIVER', 'mysql'),
    'host' => env('STELLIFY_DB_HOST', '127.0.0.1'),
    'database' => env('STELLIFY_DB_DATABASE', 'stellify_export'),
    'username' => env('STELLIFY_DB_USERNAME', 'root'),
    'password' => env('STELLIFY_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
],
```

Add to `.env`:
```env
STELLIFY_DB_DRIVER=mysql
STELLIFY_DB_HOST=127.0.0.1
STELLIFY_DB_DATABASE=stellify_export
STELLIFY_DB_USERNAME=root
STELLIFY_DB_PASSWORD=
```

### 4. Run Migrations

Publish the Stellify migrations:
```bash
php artisan vendor:publish --tag=stellify-migrations
```

Run migrations (they automatically use the Stellify database):
```bash
php artisan migrate
```

### 5. Run Export
```bash
php artisan stellify:export
```

This will parse your Laravel project and export it to your database.

### 6. Connect Stellify to Your Database

1. Log into [Stellify.io](https://stellify.io)
2. Go to Project Settings
3. Enter your database credentials:
   - Host: Same as STELLIFY_DB_HOST
   - Database: Same as STELLIFY_DB_DATABASE
   - Username: Same as STELLIFY_DB_USERNAME
   - Password: Same as STELLIFY_DB_PASSWORD
4. Stellify will connect to your database and load your project

That's it! Your project is now in Stellify.

## For Package Development

### 1. Clone Repository
```bash
git clone https://github.com/stellify/laravel.git stellify-laravel
cd stellify-laravel
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Link to Test Laravel Project
```bash
cd /path/to/test-laravel-project
composer require stellify/laravel --dev
# or for local development:
# In composer.json add:
{
    "repositories": [
        {
            "type": "path",
            "url": "../stellify-laravel"
        }
    ]
}
composer require stellify/laravel @dev
```

### 4. Test the Command
```bash
php artisan stellify:export --only=routes
```

## Command Examples

### Export Everything
```bash
php artisan stellify:export
```

### Export Only Routes
```bash
php artisan stellify:export --only=routes
```

### Export Controllers and Models
```bash
php artisan stellify:export --only=controllers,models
```

### Export Specific Directory
```bash
php artisan stellify:export --path=app/Services
```

### Exclude Tests and Vendor
```bash
php artisan stellify:export --exclude=tests,vendor
```

### Use Different Database Connection
```bash
php artisan stellify:export --connection=my_stellify_db
```

## Troubleshooting

### "Connection refused"
- Check your `.env` STELLIFY_DB_* settings
- Make sure the MySQL server is running: `sudo systemctl status mysql`
- Verify you can connect: `mysql -h 127.0.0.1 -u root -p stellify_export`
- Test connection in Laravel: `php artisan tinker` then `DB::connection('stellify')->getPdo()`

### "Class not found"
- Run `composer dump-autoload`
- Check that the service provider is registered in `config/app.php` (should be auto-discovered)

### "Memory limit exceeded"
- Increase PHP memory: `php -d memory_limit=512M artisan stellify:export`
- Or update `php.ini`: `memory_limit = 512M`

### "Parse error"
- Check which file failed in the console output
- Fix syntax errors in that file
- Re-run the export

## What Gets Exported

✅ **Directories**: App structure (controllers, models, services, etc.)  
✅ **Files**: All PHP classes with namespaces and metadata  
✅ **Methods**: Functions and class methods with parameters  
✅ **Statements**: Code statements (assignments, returns, conditionals, loops)  
✅ **Clauses**: Atomic code elements (variables, operators, method calls)  
✅ **Routes**: All HTTP routes with middleware and controllers  
✅ **Config**: Configuration files as key-value pairs  

## Next Steps

After export:
1. Configure Stellify platform with your database credentials
2. Your project structure will be visible in Stellify
3. Start collaborative development
4. Make changes in Stellify
5. Changes are stored in your database

**Important Notes:**
- **Your data stays in your database** - Stellify reads from it, you own it
- **Database agnostic** - Works with MySQL, PostgreSQL, SQLite, SQL Server
- You can use a local database or a hosted database (AWS RDS, DigitalOcean, etc.)
- For production/team use, we recommend a hosted database that all team members and Stellify can access
- For local development/testing, a local database works great

## Support

- Documentation: https://docs.stellify.io
- Issues: https://github.com/stellify/laravel/issues
- Email: support@stellify.io
