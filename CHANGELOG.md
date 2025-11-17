# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release of Stellify Laravel parser
- PHP file parsing with AST traversal
- Route parsing from Laravel's route collection
- Config file parsing with dot notation
- Directory structure mapping
- **Blade template parsing** - Converts Blade views to HTML elements
- Artisan command with multiple export options
- Support for selective exports (--only flag)
- Path exclusion support (--exclude flag)
- Custom database connection support
- Bulk database insertion for performance
- UUID generation for all entities
- Relationship tracking via UUIDs
- Comprehensive error handling
- Progress feedback during export
- **Database-agnostic migrations** - Works with MySQL, PostgreSQL, SQLite, SQL Server
- **Migration publishing** - Use Laravel's migration system instead of raw SQL

### Supported Laravel Versions
- Laravel 10.x
- Laravel 11.x

### Supported PHP Versions
- PHP 8.1
- PHP 8.2
- PHP 8.3

### Supported Databases
- MySQL 8.0+
- PostgreSQL 10+
- SQLite 3.8.8+
- SQL Server 2017+

## [1.0.0] - TBD

Initial public release.
