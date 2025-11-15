# Quick Start Guide

## For Package Users

### 1. Install Package
```bash
cd your-laravel-project
composer require stellify/laravel
```

### 2. Configure Database
Add to `config/database.php`:
```php
'stellify' => [
    'driver' => 'mysql',
    'host' => env('STELLIFY_DB_HOST'),
    'database' => env('STELLIFY_DB_DATABASE'),
    'username' => env('STELLIFY_DB_USERNAME'),
    'password' => env('STELLIFY_DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
],
```

Add to `.env`:
```env
STELLIFY_DB_HOST=your-stellify-host
STELLIFY_DB_DATABASE=your-database
STELLIFY_DB_USERNAME=your-username
STELLIFY_DB_PASSWORD=your-password
```

### 3. Run Export
```bash
php artisan stellify:export
```

That's it! Your project is now in Stellify's database.

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
- Ensure the database is accessible from your machine
- Test connection: `php artisan tinker` then `DB::connection('stellify')->getPdo()`

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
1. Open Stellify IDE
2. Your project structure will be visible
3. Start collaborative development
4. Make changes in Stellify
5. Changes sync back to your Laravel project

## Support

- Documentation: https://docs.stellify.io
- Issues: https://github.com/stellify/laravel/issues
- Email: support@stellify.io
