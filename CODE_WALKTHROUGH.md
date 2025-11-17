# BladeParser Code Walkthrough

## Test Input

```blade
<!-- resources/views/dashboard.blade.php -->
<div class="container">
    <h1>{{ $title }}</h1>
    @if($user->isAdmin())
        <p>Admin Panel</p>
    @endif
    <input type="text" name="search">
</div>
```

## Execution Flow

### 1. `parseBladeFiles()` Entry Point

```php
$result = $bladeParser->parseBladeFiles('/path/to/resources/views');
```

**Initializes:**
- `$this->elements = []`
- `$this->statements = []`
- `$this->clauses = []`

**Finds:** `dashboard.blade.php`

**Calls:** `parseBladeFile('/path/to/dashboard.blade.php', '/path/to/resources/views')`

---

### 2. `parseBladeFile()` 

**Gets view name:** `'dashboard'` (not used anymore)

**Calls:** `extractBladeDirectives($content, 'dashboard')`

---

### 3. `extractBladeDirectives()` - First Pass

**Finds `{{ $title }}`:**
```php
preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/', ...)
// Matches: {{ $title }}
// $matches[1] = '$title'
```

**Calls:** `createOutputStatement('$title', 'dashboard', false)`

**Returns:** `'stmt-abc123'`

**Replaces with:** `<blade-output data-statement="stmt-abc123"></blade-output>`

---

**Finds `@if($user->isAdmin())`:**
```php
preg_replace_callback('/@if\s*\((.*?)\)/', ...)
// Matches: @if($user->isAdmin())
// $matches[1] = '$user->isAdmin()'
```

**Calls:** `createBladeStatement('@if', '$user->isAdmin()', 'dashboard')`

**Returns:** `'stmt-def456'`

**Replaces with:** `<blade-if data-statement="stmt-def456">`

---

**Finds `@endif`:**
```php
preg_replace('/@endif/', '</blade-if>')
```

**Replaces with:** `</blade-if>`

---

**After `extractBladeDirectives()` returns:**

```html
<div class="container">
    <h1><blade-output data-statement="stmt-abc123"></blade-output></h1>
    <blade-if data-statement="stmt-def456">
        <p>Admin Panel</p>
    </blade-if>
    <input type="text" name="search">
</div>
```

---

### 4. `createOutputStatement('$title', 'dashboard', false)`

**Generates UUIDs:**
- `$statementUuid = 'stmt-abc123'`
- `$expressionUuid = 'expr-xyz789'`

**Gets predefined UUIDs:**
- `$startUuid = '2f0c88fe-1c1d-48e9-9704-d6ae707525d7'` (placeholder_start)
- `$endUuid = 'c71acac4-8524-452a-bd80-de08f97ee54b'` (placeholder_end)

**Adds to `$this->clauses`:**
```php
[
    'uuid' => 'expr-xyz789',
    'type' => 'expression',
    'name' => '$title'
]
```

**Adds to `$this->statements`:**
```php
[
    'uuid' => 'stmt-abc123',
    'type' => 'blade_output',
    'name' => '{{ }}',
    'data' => [
        '2f0c88fe-1c1d-48e9-9704-d6ae707525d7',  // placeholder_start
        'expr-xyz789',                            // $title expression
        'c71acac4-8524-452a-bd80-de08f97ee54b'   // placeholder_end
    ]
]
```

**Returns:** `'stmt-abc123'`

---

### 5. `createBladeStatement('@if', '$user->isAdmin()', 'dashboard')`

**Generates UUIDs:**
- `$statementUuid = 'stmt-def456'`
- `$conditionUuid = 'cond-qrs123'`

**Gets predefined UUID:**
- `$directiveClauseUuid = '4b19462a-3fc3-47dd-bd83-9a3635e4d20b'` (from `$this->bladeTypes['@if']`)

**Checks:** `isset($this->bladeTypes['@if'])` → **TRUE**

**Does NOT add @if clause** (already exists with predefined UUID)

**Adds to `$this->clauses`:**
```php
[
    'uuid' => 'cond-qrs123',
    'type' => 'expression',
    'name' => '$user->isAdmin()'
]
```

**Adds to `$this->statements`:**
```php
[
    'uuid' => 'stmt-def456',
    'type' => 'blade_directive',
    'name' => '@if',
    'data' => [
        '4b19462a-3fc3-47dd-bd83-9a3635e4d20b',  // @if directive (predefined)
        'cond-qrs123'                             // condition expression
    ]
]
```

**Returns:** `'stmt-def456'`

---

### 6. `parseHtmlStructure()` 

**Wraps HTML:**
```html
<!DOCTYPE html><html><body>
<div class="container">
    <h1><blade-output data-statement="stmt-abc123"></blade-output></h1>
    <blade-if data-statement="stmt-def456">
        <p>Admin Panel</p>
    </blade-if>
    <input type="text" name="search">
</div>
</body></html>
```

**Loads with DOMDocument**

**Calls:** `extractElementsFromDom($dom, null, 'dashboard')`

---

### 7. `extractElementsFromDom()` - Recursive Traversal

#### First Element: `<div class="container">`

**Generates:** `$uuid = 'elem-001'`

**Tag:** `'div'`

**Type determination:**
- Not `blade-*` → not s-directive
- Not `input/textarea/select` → not s-input
- **Type:** `'s-layout'`

**Has attributes:** `class="container"`

**Element created:**
```php
[
    'uuid' => 'elem-001',
    'tag' => 'div',
    'type' => 's-layout',
    'attributes' => ['class' => 'container'],
    'data' => []  // will be populated with children
]
```

**Stores:** `$this->elements['elem-001'] = ...`

**Has children:** YES → **Recursively calls** `extractElementsFromDom()` with `$parentUuid = 'elem-001'`

---

#### Second Element: `<h1>`

**Generates:** `$uuid = 'elem-002'`

**Tag:** `'h1'`

**Type:** `'s-layout'`

**Parent:** `'elem-001'`

**Element created:**
```php
[
    'uuid' => 'elem-002',
    'tag' => 'h1',
    'type' => 's-layout',
    'parent' => 'elem-001',
    'data' => []
]
```

**Has children:** YES (blade-output) → **Recurse**

---

#### Third Element: `<blade-output data-statement="stmt-abc123">`

**Generates:** `$uuid = 'elem-003'`

**Tag:** `'blade-output'`

**Starts with `blade-`:** YES

**Type:** `'s-directive'`

**Gets statement UUID:** `'stmt-abc123'` from `data-statement` attribute

**Element created:**
```php
[
    'uuid' => 'elem-003',
    'tag' => 'blade-output',
    'type' => 's-directive',
    'parent' => 'elem-002',
    'statement' => 'stmt-abc123',  // ← LINKED!
    'data' => []
]
```

---

#### Fourth Element: `<blade-if data-statement="stmt-def456">`

**Generates:** `$uuid = 'elem-004'`

**Tag:** `'blade-if'`

**Type:** `'s-directive'`

**Gets statement UUID:** `'stmt-def456'`

**Element created:**
```php
[
    'uuid' => 'elem-004',
    'tag' => 'blade-if',
    'type' => 's-directive',
    'parent' => 'elem-001',
    'statement' => 'stmt-def456',  // ← LINKED!
    'data' => []
]
```

**Has children:** YES (p tag) → **Recurse**

---

#### Fifth Element: `<p>`

**Generates:** `$uuid = 'elem-005'`

**Tag:** `'p'`

**Type:** `'s-layout'`

**Has text:** `'Admin Panel'`

**Element created:**
```php
[
    'uuid' => 'elem-005',
    'tag' => 'p',
    'type' => 's-layout',
    'parent' => 'elem-004',
    'text' => 'Admin Panel',
    'data' => []
]
```

---

#### Sixth Element: `<input type="text" name="search">`

**Generates:** `$uuid = 'elem-006'`

**Tag:** `'input'`

**Type determination:**
- Not `blade-*` → not s-directive
- IS `input` → **Type:** `'s-input'` ✅

**Has attributes:** `type="text"`, `name="search"`

**Element created:**
```php
[
    'uuid' => 'elem-006',
    'tag' => 'input',
    'type' => 's-input',  // ← CORRECT TYPE!
    'parent' => 'elem-001',
    'attributes' => [
        'type' => 'text',
        'name' => 'search'
    ],
    'data' => []
]
```

---

### 8. Child Collection (After Recursion Returns)

**For each parent element, collects child UUIDs:**

**elem-001 (div) children:**
- elem-002 (h1) - has parent = elem-001 ✅
- elem-004 (blade-if) - has parent = elem-001 ✅
- elem-006 (input) - has parent = elem-001 ✅

**Updates:**
```php
$this->elements['elem-001']['data'] = ['elem-002', 'elem-004', 'elem-006']
```

**elem-002 (h1) children:**
- elem-003 (blade-output) - has parent = elem-002 ✅

**Updates:**
```php
$this->elements['elem-002']['data'] = ['elem-003']
```

**elem-004 (blade-if) children:**
- elem-005 (p) - has parent = elem-004 ✅

**Updates:**
```php
$this->elements['elem-004']['data'] = ['elem-005']
```

---

### 9. Wrapper Removal

**Removes:** `<html>` and `<body>` elements

**Removes parent from:** `elem-001` (was pointing to body)

---

### 10. Final Return

```php
return [
    'elements' => [
        elem-001,  // div.container
        elem-002,  // h1
        elem-003,  // blade-output (linked to stmt-abc123)
        elem-004,  // blade-if (linked to stmt-def456)
        elem-005,  // p
        elem-006   // input (type: s-input)
    ],
    'statements' => [
        stmt-abc123,  // {{ $title }}
        stmt-def456   // @if($user->isAdmin())
    ],
    'clauses' => [
        expr-xyz789,  // $title
        cond-qrs123   // $user->isAdmin()
    ]
    // NOTE: No clause for @if itself - uses predefined UUID!
];
```

---

## Database Insertions

### Clauses Table
```
uuid          | type       | name
--------------+------------+-------------------
expr-xyz789   | expression | $title
cond-qrs123   | expression | $user->isAdmin()
```

### Statements Table
```
uuid        | type              | name   | data (JSON)
------------+-------------------+--------+------------------------------------------
stmt-abc123 | blade_output      | {{ }}  | ["2f0c88fe...", "expr-xyz789", "c71acac4..."]
stmt-def456 | blade_directive   | @if    | ["4b19462a...", "cond-qrs123"]
```

### Elements Table
```
uuid     | tag          | type        | statement   | parent   | data (JSON)
---------+--------------+-------------+-------------+----------+-------------------------
elem-001 | div          | s-layout    | null        | null     | ["elem-002","elem-004","elem-006"]
elem-002 | h1           | s-layout    | null        | elem-001 | ["elem-003"]
elem-003 | blade-output | s-directive | stmt-abc123 | elem-002 | []
elem-004 | blade-if     | s-directive | stmt-def456 | elem-001 | ["elem-005"]
elem-005 | p            | s-layout    | null        | elem-004 | []
elem-006 | input        | s-input     | null        | elem-001 | []
```

---

## Key Points

✅ **Predefined UUIDs used** for `@if`, `placeholder_start`, `placeholder_end`  
✅ **Element types correct**: `s-layout`, `s-directive`, `s-input`  
✅ **No 'view' field** anywhere  
✅ **Statements link to clauses** via `data` array  
✅ **Elements link to statements** via `statement` field  
✅ **Parent-child relationships** preserved in `parent` and `data` fields  

---

## Potential Issues

None identified! The logic looks solid. Ready to test! 🎯
