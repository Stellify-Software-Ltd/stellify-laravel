# Blade Template Parsing

## Overview

The package now includes a **BladeParser** that converts Laravel Blade templates into:
1. **HTML elements** - The DOM structure
2. **Statements** - Blade directives like `@if`, `@foreach`, `{{ }}`
3. **Clauses** - Expressions and conditions within directives

This preserves the Blade syntax as actual code that can be edited in Stellify, not just stripped away.

## How It Works

### 1. Extract Blade Directives
Finds all Blade directives in the template:
- `@if(condition)`, `@foreach(...)`, `@while(...)`
- `{{ $variable }}`, `{!! $html !!}`
- Creates statements and clauses for each

### 2. Convert Directives to Special Elements
Replaces Blade directives with placeholder elements:
```blade
@if($user->isAdmin())
    <div>Admin Panel</div>
@endif
```

Becomes:
```html
<blade-if data-statement="uuid-123">
    <div>Admin Panel</div>
</blade-if>
```

### 3. Parse HTML Structure
Uses DOMDocument to parse the HTML and create element records, preserving the link to statements.

### 4. Export Everything
- Elements go to `elements` table
- Statements go to `statements` table  
- Clauses go to `clauses` table

## Example

**Input Blade** (`resources/views/dashboard.blade.php`):
```blade
<div class="container">
    <h1>{{ $title }}</h1>
    @if($user->isAdmin())
        <p>Welcome Admin!</p>
    @endif
    @foreach($posts as $post)
        <div>{{ $post->title }}</div>
    @endforeach
</div>
```

**Generated Data:**

### Clauses Table
```php
[
    ['uuid' => '2f0c88fe-...', 'type' => 'placeholder_start', 'name' => 'placeholder_start'],  // Predefined
    ['uuid' => 'c71acac4-...', 'type' => 'placeholder_end', 'name' => 'placeholder_end'],      // Predefined
    ['uuid' => '4b19462a-...', 'type' => 'directive', 'name' => '@if'],                       // Predefined
    ['uuid' => 'new-uuid-1', 'type' => 'expression', 'name' => '$user->isAdmin()'],
    ['uuid' => 'new-uuid-2', 'type' => 'expression', 'name' => '$title'],
    ['uuid' => '13a0ed23-...', 'type' => 'directive', 'name' => '@foreach'],                  // Predefined
    ['uuid' => 'new-uuid-3', 'type' => 'expression', 'name' => '$posts as $post'],
    ['uuid' => 'new-uuid-4', 'type' => 'expression', 'name' => '$post->title'],
]
```

### Statements Table
```php
[
    [
        'uuid' => 'stmt-1',
        'type' => 'blade_output',
        'name' => '{{ }}',
        'data' => ['2f0c88fe-...', 'new-uuid-2', 'c71acac4-...'],  // [start, expression, end]
        'view' => 'dashboard'
    ],
    [
        'uuid' => 'stmt-2',
        'type' => 'blade_directive',
        'name' => '@if',
        'data' => ['4b19462a-...', 'new-uuid-1'],  // [directive, condition]
        'view' => 'dashboard'
    ],
    [
        'uuid' => 'stmt-3',
        'type' => 'blade_directive',
        'name' => '@foreach',
        'data' => ['13a0ed23-...', 'new-uuid-3'],  // [directive, expression]
        'view' => 'dashboard'
    ],
    [
        'uuid' => 'stmt-4',
        'type' => 'blade_output',
        'name' => '{{ }}',
        'data' => ['2f0c88fe-...', 'new-uuid-4', 'c71acac4-...'],
        'view' => 'dashboard'
    ]
]
```

### Elements Table
```php
[
    [
        'uuid' => 'elem-1',
        'tag' => 'div',
        'attributes' => ['class' => 'container'],
        'data' => ['elem-2', 'elem-3', 'elem-4'],
        'view' => 'dashboard'
    ],
    [
        'uuid' => 'elem-2',
        'tag' => 'h1',
        'parent' => 'elem-1',
        'data' => ['elem-output-1'],
        'view' => 'dashboard'
    ],
    [
        'uuid' => 'elem-output-1',
        'tag' => 'blade-output',
        'parent' => 'elem-2',
        'statement' => 'stmt-1',  // Links to statement!
        'view' => 'dashboard'
    ],
    [
        'uuid' => 'elem-3',
        'tag' => 'blade-if',
        'parent' => 'elem-1',
        'statement' => 'stmt-2',  // Links to @if statement!
        'data' => ['elem-5'],
        'view' => 'dashboard'
    ],
    [
        'uuid' => 'elem-5',
        'tag' => 'p',
        'parent' => 'elem-3',
        'text' => 'Welcome Admin!',
        'view' => 'dashboard'
    ],
    // ... more elements for @foreach
]
```

## Predefined Clause UUIDs

The parser uses hardcoded UUIDs for common Blade directives (from your ClauseController):

```php
'@if' => '4b19462a-3fc3-47dd-bd83-9a3635e4d20b',
'@foreach' => '13a0ed23-5db6-411c-8efc-37313ae8b3b3',
'@while' => 'ec038718-675a-4bd8-aa9d-47ff22be44c4',
'placeholder_start' => '2f0c88fe-1c1d-48e9-9704-d6ae707525d7',
'placeholder_end' => 'c71acac4-8524-452a-bd80-de08f97ee54b',
// ... etc
```

This ensures Stellify recognizes these as system clauses.

## Usage

### Export All Views
```bash
php artisan stellify:export
```

### Export Only Views
```bash
php artisan stellify:export --only=views
```

### Export Views with Other Components
```bash
php artisan stellify:export --only=controllers,routes,views
```

## Blade Directives Supported

The parser creates statements and clauses for these Blade directives:

**Control Structures:**
- `@if`, `@elseif`, `@else`, `@endif`
- `@foreach`, `@endforeach`
- `@for`, `@endfor`  
- `@while`, `@endwhile`
- `@unless`, `@endunless`

**Conditionals:**
- `@isset`, `@endisset`
- `@empty`, `@endempty`
- `@auth`, `@endauth`
- `@guest`, `@endguest`
- `@production`, `@endproduction`

**Output:**
- `{{ $variable }}` - Escaped output
- `{!! $html !!}` - Raw output

## What Gets Preserved

✅ **HTML structure** - All tags and nesting  
✅ **Attributes** - Classes, IDs, data attributes  
✅ **Text content** - Static text between tags  
✅ **Blade directives** - As statements with clauses  
✅ **Conditions & expressions** - Preserved in clauses  
✅ **View names** - Which Blade file it came from  

## Comparison with Stellify's htmlToElement

The BladeParser uses the same approach as your existing `htmlToElement` method:

### Similarities
- Uses DOMDocument for parsing
- Generates UUIDs for each element
- Tracks parent-child relationships
- Stores attributes and text content
- Same JSON structure

### Differences
- **Input**: Reads from `.blade.php` files instead of request
- **Blade stripping**: Removes Blade directives first
- **Batch processing**: Processes all views at once
- **View metadata**: Adds view name to each element

## Integration with Stellify Platform

When user links database to Stellify:

1. **Elements, statements, and clauses exist** in database from export
2. **Stellify populates** `user_id` and `project_id` for all records
3. **View names** help link elements to routes
4. **Statements can be edited** - Change Blade conditions in Stellify's code editor
5. **Elements can be edited** - Visual editing of HTML structure
6. **Blade directives are live code** - Not stripped, but preserved as editable statements

## Advantages Over Stripping

**Old approach (stripping):**
- ❌ Blade syntax lost forever
- ❌ Can't edit conditions
- ❌ Just static HTML

**New approach (statements + clauses):**
- ✅ Blade syntax preserved as code
- ✅ Conditions are editable
- ✅ Full round-trip support
- ✅ Elements linked to their directives

## Limitations

### Current Limitations
- Dynamic Blade content is replaced with placeholders
- Component tags (`<x-component>`) are kept as-is but may not render correctly
- Blade directives inside attributes aren't handled
- PHP code in Blade is removed

### Potential Future Improvements
- Parse Blade components separately
- Handle `@include` by actually including the file
- Preserve Blade variable names as metadata
- Support for Blade slots and stacks

## File Structure

```
src/Parser/BladeParser.php
├── parseBladeFiles()          # Main entry point
├── parseBladeFile()           # Parse single file
├── stripBladeDirectives()     # Remove Blade syntax
├── sanitiseHtml()             # Clean HTML
├── getElementsFromDom()       # Recursively extract elements (same as htmlToElement)
├── getViewName()              # Convert path to view name
└── getBladeFiles()            # Find all .blade.php files
```

## Benefits

✅ **Complete export** - Not just PHP, but UI too  
✅ **Visual editing** - Users can edit views in Stellify  
✅ **Structure preservation** - DOM hierarchy maintained  
✅ **Familiar approach** - Uses same logic as your existing code  
✅ **Metadata rich** - Includes view names for context  

## Testing

Test the Blade parser on a Laravel project:

```bash
# Create a test view
echo '<div class="test"><h1>Hello</h1></div>' > resources/views/test.blade.php

# Export views
php artisan stellify:export --only=views

# Check database
SELECT * FROM elements;
```

You should see elements with:
- Tag names (div, h1)
- Attributes (class="test")
- Parent-child relationships
- View name (test)

---

This completes the export package - now it handles **PHP code, routes, config, AND views**! 🎉
