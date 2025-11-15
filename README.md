# Stellify Laravel Parser

Parse Laravel projects into Stellify's database-driven development format.

## Installation

Install the package via Composer:

```bash
composer require stellify/laravel
```

The service provider will be automatically discovered by Laravel.

## Configuration

### Step 1: Create a Database for Export

Create a new database where your Laravel project will be exported. This can be:
- A local MySQL database on your machine
- A PostgreSQL database
- A hosted database (AWS RDS, DigitalOcean, etc.)
- Any database supported by Laravel

```sql
# MySQL
CREATE DATABASE stellify_export CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# PostgreSQL
CREATE DATABASE stellify_export;
```

### Step 2: Add Database Connection

Add the export database connection to `config/database.php`:

```php
'connections' => [
    // ... existing connections
    
    'stellify' => [
        'driver' => env('STELLIFY_DB_DRIVER', 'mysql'),
        'host' => env('STELLIFY_DB_HOST', '127.0.0.1'),
        'port' => env('STELLIFY_DB_PORT', '3306'),
        'database' => env('STELLIFY_DB_DATABASE', 'stellify_export'),
        'username' => env('STELLIFY_DB_USERNAME', 'root'),
        'password' => env('STELLIFY_DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

Then add to your `.env`:

```env
STELLIFY_DB_DRIVER=mysql
STELLIFY_DB_HOST=127.0.0.1
STELLIFY_DB_DATABASE=stellify_export
STELLIFY_DB_USERNAME=root
STELLIFY_DB_PASSWORD=
```

### Step 3: Publish and Run Migrations

Publish the Stellify migrations:

```bash
php artisan vendor:publish --tag=stellify-migrations
```

Then run the migrations (they automatically use the `stellify` connection):

```bash
php artisan migrate
```

This will create all the required tables in your export database.

## Usage

### Complete Workflow

1. **Export your Laravel project** to your database:
   ```bash
   php artisan stellify:export
   ```

2. **Configure Stellify platform** to connect to your database:
   - Log into Stellify.io
   - Go to Project Settings
   - Enter your database credentials (the same ones from your `.env`)
   - Stellify will now read your project from your database

3. **Start developing** in Stellify's collaborative IDE!

### Basic Export

Export your entire Laravel project:

```bash
php artisan stellify:export
```

### Export Specific Components

Export only routes:

```bash
php artisan stellify:export --only=routes
```

Export only controllers and models:

```bash
php artisan stellify:export --only=controllers,models
```

Available types:
- `routes` - Laravel routes
- `controllers` - Controller classes
- `models` - Eloquent models
- `config` - Configuration files

### Export Specific Paths

Export only files from a specific directory:

```bash
php artisan stellify:export --path=app/Services
```

### Exclude Paths

Exclude specific directories:

```bash
php artisan stellify:export --exclude=tests,vendor
```

### Custom Database Connection

Use a different database connection:

```bash
php artisan stellify:export --connection=my_stellify_connection
```

## What Gets Exported

The parser exports your Laravel project into the following Stellify database tables:

### Directories
All directory structures are mapped, including:
- Controllers
- Models
- Middleware
- Services
- Custom app directories

### Files
PHP class files with metadata:
- Namespace
- Class name
- Type (class, interface, trait)
- Extends/implements relationships

### Methods
All class methods and functions with:
- Method name
- Visibility (public, protected, private)
- Parameters
- Method body structure

### Statements
Individual code statements:
- Assignments
- Returns
- Conditionals (if/else)
- Loops (for, foreach, while)

### Clauses
Atomic code elements:
- Variables
- Operators
- Method calls
- Static calls
- Literals (strings, numbers)

### Routes
All Laravel routes with:
- HTTP method
- Path
- Controller and method
- Middleware
- Named routes
- Route parameters

### Settings
Configuration files converted to key-value pairs:
- All files from `config/` directory
- Nested arrays flattened with dot notation
- e.g., `database.connections.mysql` → value

## Example Workflow

1. Install the package in your Laravel project
2. Configure your Stellify database connection
3. Run the export command
4. Open your project in Stellify IDE
5. Start collaborative development!

## How It Works

The parser uses PHP-Parser (nikic/php-parser) to convert PHP code into an Abstract Syntax Tree (AST), then transforms that into Stellify's JSON-based format and stores it in **your own database**.

**Architecture:**
1. You run `php artisan stellify:export` in your Laravel project
2. The package parses your code and exports it to your MySQL database
3. You configure Stellify platform with your database credentials
4. Stellify reads from your database to display and edit your project

**This approach means:**
- ✅ You own your data (it's in your database)
- ✅ Works with local or hosted databases
- ✅ Perfect for self-hosted scenarios
- ✅ No data sent to Stellify servers during export

This enables:
- ✅ Real-time collaborative editing
- ✅ Automatic refactoring across your entire application
- ✅ AI-powered code optimization
- ✅ No local development environment needed

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- Any Laravel-supported database (MySQL, PostgreSQL, SQLite, SQL Server)
- Database credentials to configure Stellify platform

## Troubleshooting

### Database Connection Failed

Make sure your `.env` file has the correct database credentials for your export database. Test the connection:

```bash
php artisan tinker
> DB::connection('stellify')->getPdo()
```

If you see "could not find driver", you may need to install the MySQL PDO extension:
```bash
# Ubuntu/Debian
sudo apt-get install php-mysql

# macOS with Homebrew
brew install php@8.1  # or your PHP version
```

### Parse Errors

Some files may fail to parse if they contain syntax errors. The command will continue parsing other files and output which files failed.

### Memory Issues

For very large projects, you may need to increase PHP's memory limit:

```bash
php -d memory_limit=512M artisan stellify:export
```

## Support

For issues, questions, or feature requests, please visit:
- [Stellify.io](https://stellify.io)
- [GitHub Issues](https://github.com/stellify/laravel-parser)

## License

MIT License - see LICENSE file for details
