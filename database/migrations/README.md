# Stellify Database Migrations

These migrations create the required tables for exporting Laravel projects to Stellify format.

## Tables Created

1. **directories** - Project directory structure
2. **files** - PHP class files with metadata
3. **methods** - Functions and class methods
4. **statements** - Code statements (assignments, returns, etc.)
5. **clauses** - Atomic code elements (variables, operators, calls)
6. **routes** - Laravel HTTP routes
7. **elements** - HTML elements from Blade templates
8. **settings** - Configuration files

## Usage

To publish these migrations to your Laravel project:

```bash
php artisan vendor:publish --tag=stellify-migrations
```

Then run migrations:

```bash
php artisan migrate
```

The migrations automatically use the `stellify` database connection defined in your `config/database.php`, so you don't need to specify `--database=stellify`.

## Database Support

These migrations use Laravel's Schema Builder and work with:
- MySQL 8.0+
- PostgreSQL 10+
- SQLite 3.8.8+
- SQL Server 2017+

## Notes

- The `user_id` and `project_id` fields are intentionally nullable since the parser sets them to NULL during export
- UUIDs are generated during parsing to link related records
- JSON columns store the full data structure for each element
- Soft deletes are supported via `deleted_at` timestamp
