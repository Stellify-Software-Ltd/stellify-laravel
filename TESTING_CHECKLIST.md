# Pre-Flight Testing Checklist

## ✅ Features Implemented

### PHP/Laravel Parsing
- [x] Parse PHP files with nikic/php-parser
- [x] Extract file metadata (namespace, class name, extends, implements)
- [x] Parse methods with parameters
- [x] Parse statements (assignments, returns, if/foreach/while)
- [x] Parse clauses (variables, operators, values, function calls)
- [x] Handle static calls (User::all())
- [x] Handle method calls ($user->save())
- [x] Handle function calls (view(), route(), config()) ✅ FIXED
- [x] Use predefined UUIDs for core Laravel files ✅ ADDED
- [x] Flat JSON structure for all records ✅ CORRECTED

### Blade Template Parsing
- [x] Parse Blade files into HTML elements
- [x] Create statements for Blade directives (@if, @foreach, @while)
- [x] Create clauses for expressions ({{ $var }}, conditions)
- [x] Use predefined UUIDs for Blade directives
- [x] Link elements to statements via `statement` field
- [x] Proper element types (s-layout, s-directive, s-input)
- [x] Root elements have `name` field set to view name ✅ ADDED
- [x] No 'view' field anywhere ✅ REMOVED

### Routes Parsing
- [x] Parse web.php and api.php routes
- [x] Extract route methods (GET, POST, etc.)
- [x] Extract route URIs
- [x] Link to controllers and methods
- [x] Handle route parameters

### Database Export
- [x] Export to `directories` table
- [x] Export to `files` table
- [x] Export to `methods` table
- [x] Export to `statements` table
- [x] Export to `clauses` table
- [x] Export to `elements` table (Blade)
- [x] Export to `routes` table
- [x] All records have `user_id` and `project_id` as NULL

### Configuration
- [x] Database connection configuration
- [x] Command options (--connection, --only, --exclude, --path)
- [x] Selective export (controllers, models, routes, views, config)

## 📋 Test Plan

### 1. Installation Test
```bash
# In a Laravel project
composer require stellify/laravel --dev
php artisan stellify:export --help
```

**Expected:** Command shows help with all options

---

### 2. Basic PHP Export Test
```bash
php artisan stellify:export --only=controllers
```

**Expected:**
- Creates records in `files` table
- Creates records in `methods` table
- Creates records in `statements` table
- Creates records in `clauses` table
- All records have NULL user_id and project_id
- Controllers extend proper UUID (6aaf556a-f2c1-43d0-a833-7db187868ada)

**Check database:**
```sql
SELECT uuid, namespace, name, type FROM files WHERE type = 'controller' LIMIT 5;
SELECT uuid, name, type FROM methods LIMIT 5;
SELECT uuid, type FROM statements LIMIT 10;
SELECT uuid, type, name FROM clauses LIMIT 10;
```

---

### 3. Function Call Test

**Test file:** Create a simple controller with helper functions

```php
<?php
namespace App\Http\Controllers;

class TestController extends Controller
{
    public function index()
    {
        $data = config('app.name');
        return view('welcome', ['title' => $data]);
    }
}
```

**Export:**
```bash
php artisan stellify:export --only=controllers
```

**Check:** Look for `function_call` type clauses
```sql
SELECT * FROM clauses WHERE data LIKE '%function_call%' LIMIT 5;
```

**Expected:** Should find clauses for `config()` and `view()` function calls

---

### 4. Core Files UUID Test

**Test file:** Check that Model extends uses predefined UUID

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    //
}
```

**Export:**
```bash
php artisan stellify:export --only=models
```

**Check:**
```sql
SELECT uuid, name, data FROM files WHERE name = 'User';
```

**Expected:** 
- User has new UUID
- data JSON contains: `"extends_uuid": "d0fe7b2c-63e8-466d-8e1b-6ca0062a45c7"`

---

### 5. Blade Export Test

**Test file:** `resources/views/test.blade.php`
```blade
<div class="container">
    <h1>{{ $title }}</h1>
    @if($user->isAdmin())
        <p>Admin Panel</p>
    @endif
    <input type="text" name="search">
</div>
```

**Export:**
```bash
php artisan stellify:export --only=views
```

**Check:**
```sql
SELECT uuid, tag, type FROM elements WHERE tag = 'div' LIMIT 1;
SELECT uuid, type, name FROM statements WHERE type LIKE 'blade%' LIMIT 5;
SELECT uuid, type, name FROM clauses WHERE type = 'expression' LIMIT 5;
```

**Expected:**
- div element with type = 's-layout'
- input element with type = 's-input'
- blade-if element with type = 's-directive'
- blade-output elements linked to statements
- Statements with Blade directive UUIDs (4b19462a-...)
- Root div has `name` field = 'test'

---

### 6. Routes Export Test

**Test file:** `routes/web.php`
```php
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
```

**Export:**
```bash
php artisan stellify:export --only=routes
```

**Check:**
```sql
SELECT * FROM routes LIMIT 5;
```

**Expected:** Route records with method, uri, controller references

---

### 7. Full Export Test
```bash
php artisan stellify:export
```

**Expected:**
- All directories exported
- All PHP files exported
- All Blade templates exported
- All routes exported
- No errors in console

---

## 🐛 Known Potential Issues

### Issue 1: Large Projects
**Symptom:** Memory errors or timeouts on large projects  
**Solution:** Use `--only` flag to export in batches

### Issue 2: Complex Blade Syntax
**Symptom:** Some advanced Blade features might not parse correctly  
**Solution:** Check elements table for malformed data

### Issue 3: Namespace Resolution
**Symptom:** Some `extends_uuid` or `implements_uuids` might be NULL  
**Solution:** Check if core file UUID mapping is complete

### Issue 4: Database Connection
**Symptom:** "Connection refused" errors  
**Solution:** Check .env database credentials

---

## 🔍 Debugging Commands

**Check what was exported:**
```sql
-- Count records
SELECT 'files' as table_name, COUNT(*) as count FROM files WHERE project_id IS NULL
UNION ALL
SELECT 'methods', COUNT(*) FROM methods WHERE project_id IS NULL
UNION ALL
SELECT 'statements', COUNT(*) FROM statements WHERE project_id IS NULL
UNION ALL
SELECT 'clauses', COUNT(*) FROM clauses WHERE project_id IS NULL
UNION ALL
SELECT 'elements', COUNT(*) FROM elements WHERE project_id IS NULL
UNION ALL
SELECT 'routes', COUNT(*) FROM routes WHERE project_id IS NULL;
```

**Check for errors:**
```sql
-- Look for incomplete data
SELECT * FROM files WHERE data IS NULL OR data = '' OR data = '{}' LIMIT 10;
SELECT * FROM methods WHERE data IS NULL OR data = '' OR data = '{}' LIMIT 10;
```

**Check relationships:**
```sql
-- Files extending Controller
SELECT f.name, f.data->>'extends' as extends, f.data->>'extends_uuid' as extends_uuid 
FROM files f 
WHERE f.data->>'extends' IS NOT NULL 
LIMIT 10;
```

---

## ✅ Success Criteria

The export is successful if:

1. ✅ All tables have records
2. ✅ No NULL data columns (except legitimate cases)
3. ✅ Core Laravel files use predefined UUIDs
4. ✅ Function calls are properly parsed (type = 'function_call')
5. ✅ Blade elements link to statements
6. ✅ Element types are correct (s-layout, s-directive, s-input)
7. ✅ Root Blade elements have view name
8. ✅ No console errors during export

---

## 🚀 Ready to Test!

Start with a **small test project** first, then move to your real application.

**Recommended order:**
1. Test on fresh Laravel installation
2. Test with simple controller
3. Test with Blade templates
4. Test on your actual project

Good luck! 🎉
