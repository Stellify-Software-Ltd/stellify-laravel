# Stellify Laravel Parser - Architecture Overview

## Package Structure

```
stellify-laravel/
├── src/
│   ├── Commands/
│   │   └── ExportCommand.php          # Main Artisan command
│   ├── Parser/
│   │   ├── AstVisitor.php             # Traverses PHP AST nodes
│   │   ├── PhpFileParser.php          # Parses PHP files into AST
│   │   ├── RouteParser.php            # Extracts Laravel routes
│   │   ├── ConfigParser.php           # Parses config files
│   │   └── DirectoryParser.php        # Maps directory structure
│   └── StellifyServiceProvider.php    # Registers with Laravel
├── composer.json
├── README.md
├── LICENSE
└── .gitignore
```

## Component Responsibilities

### ExportCommand
The main orchestrator that:
- Validates database connection
- Determines what to export based on flags
- Coordinates all parsers
- Performs bulk inserts to database
- Provides progress feedback

**Key Methods:**
- `handle()` - Main execution flow
- `validateConnection()` - Checks Stellify DB access
- `exportFiles()`, `exportMethods()`, etc. - Database insertion

### PhpFileParser
Converts PHP files into Stellify's JSON format:
- Uses nikic/php-parser to generate AST
- Extracts file metadata (namespace, class name, extends, implements)
- Delegates to AstVisitor for method/statement parsing
- Can parse single files or entire directories

**Key Methods:**
- `parseFile($filePath)` - Parse single PHP file
- `parseDirectory($directory, $exclude)` - Parse all PHP files in directory
- `extractFileMetadata($ast, $filePath)` - Extract class information

### AstVisitor
Traverses the Abstract Syntax Tree and identifies:
- Functions and class methods
- Assignments
- Return statements
- Conditionals (if/else)
- Loops (for, foreach, while)
- Method calls (instance and static)

**Key Methods:**
- `enterNode($node)` - Called for each AST node
- `processFunction()` - Handles function declarations
- `processClassMethod()` - Handles class methods
- `processAssignment()` - Handles variable assignments
- `processReturn()` - Handles return statements
- `processValue()` - Converts values to JSON format
- `getResults()` - Returns parsed data

**Data Flow:**
```
PHP Code → Parser → AST → Visitor → JSON Structure → Database
```

### RouteParser
Extracts Laravel route definitions:
- Scans all registered routes via `Route::getRoutes()`
- Extracts controller and method
- Identifies middleware (web/api/auth)
- Determines route type and visibility

**Key Methods:**
- `parseRoutes()` - Gets all Laravel routes
- `parseRoute($route)` - Converts single route to database format

### ConfigParser
Converts config files to Stellify settings:
- Reads PHP config files
- Flattens nested arrays using dot notation
- Stores as JSON in settings table

**Key Methods:**
- `parseConfigFiles($configPath)` - Parse all config files
- `flattenConfig($config, $prefix)` - Converts nested arrays

### DirectoryParser
Maps the Laravel directory structure:
- Scans specified paths recursively
- Identifies directory types (controllers, models, etc.)
- Generates UUIDs for each directory

**Key Methods:**
- `parseDirectories($basePath, $includePaths)` - Main entry point
- `scanDirectory()` - Recursive directory scanner
- `getDirectoryType($path)` - Determines Laravel directory type

## Database Mapping

### files table
```json
{
  "uuid": "generated-uuid",
  "namespace": "App\\Http\\Controllers",
  "name": "UserController",
  "type": "class",
  "public": true,
  "data": {
    "path": "/path/to/file",
    "extends": "Controller",
    "implements": []
  }
}
```

### methods table
```json
{
  "uuid": "generated-uuid",
  "type": "method",
  "name": "index",
  "data": {
    "uuid": "same-uuid",
    "name": "index",
    "type": "method",
    "visibility": "public",
    "static": false,
    "params": [],
    "data": ["statement-uuid-1", "statement-uuid-2"]
  }
}
```

### statements table
```json
{
  "uuid": "generated-uuid",
  "type": "return",
  "data": {
    "uuid": "same-uuid",
    "type": "return",
    "data": ["clause-uuid-1", "clause-uuid-2"]
  }
}
```

### clauses table
```json
{
  "uuid": "generated-uuid",
  "type": "variable",
  "name": "users",
  "data": {
    "uuid": "same-uuid",
    "type": "variable",
    "name": "users"
  }
}
```

## Command Usage Flow

```
User runs: php artisan stellify:export --only=controllers

1. ExportCommand::handle() starts
2. Validates Stellify database connection
3. Determines paths to scan (app/Http/Controllers)
4. DirectoryParser scans and exports directory structure
5. PhpFileParser parses each PHP file:
   a. Creates AST using nikic/php-parser
   b. AstVisitor traverses AST
   c. Generates UUIDs for all elements
   d. Returns structured JSON
6. ExportCommand bulk inserts:
   - files table
   - methods table
   - statements table
   - clauses table
7. Completion message displayed
```

## Key Features

### UUID Generation
Every element gets a unique UUID:
- Ensures uniqueness across the entire system
- Enables relationships between tables
- Generated via `Str::uuid()->toString()`

### Relationship Tracking
Elements are linked via UUIDs stored in JSON:
- Methods contain array of statement UUIDs in `data`
- Statements contain array of clause UUIDs in `data`
- Clauses can reference other clauses (method calls, static calls)

### Bulk Insertion
All database operations use bulk inserts for performance:
- Collects all parsed data first
- Single insert per table
- Adds timestamps automatically

### Error Handling
Gracefully handles parsing errors:
- Continues parsing other files if one fails
- Outputs error messages to console
- Returns non-zero exit code on connection failure

## Extension Points

### Adding New Parsers
To parse additional Laravel components:

1. Create new parser class in `src/Parser/`
2. Implement parsing logic
3. Add to ExportCommand options
4. Add database export method

Example for parsing Jobs:
```php
class JobParser {
    public function parseJobs(string $path): array {
        // Parse job classes
        return $jobs;
    }
}
```

### Supporting New Node Types
To handle additional PHP syntax:

1. Add new condition in `AstVisitor::enterNode()`
2. Create processing method
3. Update data structure as needed

Example for traits:
```php
elseif ($node instanceof Node\Stmt\TraitUse) {
    $this->processTraitUse($node);
}
```

## Testing Strategy

Recommended test coverage:

1. **Unit Tests**
   - Each parser class independently
   - UUID generation
   - Data structure validation

2. **Integration Tests**
   - Full export on sample Laravel project
   - Database insertion verification
   - Command flag combinations

3. **Edge Cases**
   - Syntax errors in PHP files
   - Missing directories
   - Database connection failures
   - Large projects (memory limits)

## Performance Considerations

### Memory Usage
- Parses files one at a time
- Collects results before bulk insert
- Large projects may need increased PHP memory limit

### Database Performance
- Uses bulk inserts (not individual INSERTs)
- No indexes created during import
- Consider adding indexes after import for better Stellify performance

### Optimization Opportunities
1. Chunk large bulk inserts (1000 records at a time)
2. Use database transactions
3. Parallel processing for independent directories
4. Cache parsed results between runs (for incremental updates)

## Future Enhancements

Potential additions:
- **Incremental Updates**: Only parse changed files
- **Watch Mode**: Continuously sync changes
- **Blade Parsing**: Extract HTML elements from Blade templates
- **Migration Parsing**: Parse database migrations
- **Test Parsing**: Include test files
- **Dependency Graph**: Map class dependencies
- **Code Metrics**: Lines of code, complexity, etc.
