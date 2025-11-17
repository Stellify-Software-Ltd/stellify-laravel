# Core Laravel File UUID Support

## Overview

The export tool now recognizes and uses your predefined UUIDs for core Laravel framework files. This ensures that when exporting a Laravel project, framework dependencies (like `Model`, `Controller`, `Migration`, etc.) use the same UUIDs as your existing Stellify system.

## How It Works

### 1. Predefined UUIDs

The `PhpFileParser` now contains a mapping of core Laravel classes to their UUIDs:

```php
$this->coreFiles = [
    'Illuminate\Database\Eloquent\Model' => 'd0fe7b2c-63e8-466d-8e1b-6ca0062a45c7',
    'App\Http\Controllers\Controller' => '6aaf556a-f2c1-43d0-a833-7db187868ada',
    'Illuminate\Database\Migrations\Migration' => '797cce30-8b01-44e7-a0b2-c26a641a17e0',
    // ... etc
];
```

### 2. File Parsing

When parsing a file like:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    // ...
}
```

The parser:

1. **Tracks use statements**: Maps `Model` alias to `Illuminate\Database\Eloquent\Model`
2. **Resolves full class names**: Converts `Model` to `Illuminate\Database\Eloquent\Model`
3. **Checks predefined UUIDs**: Looks up the UUID for `Illuminate\Database\Eloquent\Model`
4. **Uses predefined UUID**: Instead of generating a new UUID, uses `d0fe7b2c-63e8-466d-8e1b-6ca0062a45c7`

### 3. Result

**File record in database:**
```json
{
    "uuid": "generated-new-uuid",
    "namespace": "App\\Models",
    "name": "User",
    "type": "class",
    "public": true,
    "path": "/path/to/User.php",
    "extends": "Model",
    "extends_uuid": "d0fe7b2c-63e8-466d-8e1b-6ca0062a45c7",
    "implements": [],
    "implements_uuids": []
}
```

Note: This entire JSON object is stored in the `data` column. The `uuid`, `namespace`, `name`, `type`, and `public` fields are also stored as individual columns for indexing.

## Supported Framework Files

### Models
- `Illuminate\Database\Eloquent\Model` → `d0fe7b2c-63e8-466d-8e1b-6ca0062a45c7`

### Controllers
- `App\Http\Controllers\Controller` → `6aaf556a-f2c1-43d0-a833-7db187868ada`

### Migrations
- `Illuminate\Database\Migrations\Migration` → `797cce30-8b01-44e7-a0b2-c26a641a17e0`
- `Illuminate\Support\Facades\Schema` → `08e4dfa8-b927-450f-8104-616b586d8696`
- `Illuminate\Database\Schema\Blueprint` → `93c290ea-c225-4cd7-9884-7de50dbaf2e3`

### Factories
- `Illuminate\Database\Eloquent\Factories\Factory` → `75c7673e-3ece-441a-8405-ca7802b98090`

### Tests
- `Tests\TestCase` → `da0dd8c8-8255-473c-9ad1-662116e0ed5e`

### Middleware
- `Illuminate\Auth\Middleware\Authenticate` → `2520e62a-dccf-42fb-a78c-56471b4dabac`
- `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` → `b8f5653b-fc8b-4163-b731-343d2b5d6950`
- `Illuminate\Routing\Middleware\SubstituteBindings` → `8636dac7-7459-41ef-b812-f148e1b8c976`

### Rules
- `Illuminate\Contracts\Validation\Rule` → `773c0655-240b-4a03-b727-5e4d3ea07944`

### Events
- `Illuminate\Foundation\Events\Dispatchable` → `fa2584fd-0c8f-47fa-b5de-f9196b56fff4`
- `Illuminate\Broadcasting\InteractsWithSockets` → `b681be9a-c26e-403d-a7bc-84548203dfad`
- `Illuminate\Queue\SerializesModels` → `62a60b43-6460-4335-9722-ed57047e214d`
- `Illuminate\Broadcasting\PrivateChannel` → `e4e01384-6ad3-4425-963f-c4430c6d5ae3`
- `Illuminate\Broadcasting\PresenceChannel` → `0f7ad11f-7a62-4435-b6fa-7b3deb9b9645`
- `Illuminate\Contracts\Broadcasting\ShouldBroadcast` → `b3796872-3b8c-491f-a3ca-f7e245d1291d`

### Jobs
- `Illuminate\Bus\Queueable` → `3fcf7072-9f9a-4cd4-b2f7-eebe4e1b3fb6`
- `Illuminate\Contracts\Queue\ShouldQueue` → `af19260c-4f37-404f-985f-ca73ee70977e`
- `Illuminate\Queue\InteractsWithQueue` → `9f25210d-f321-4e4c-878a-4351906c3531`

### Requests
- `Illuminate\Foundation\Http\FormRequest` → `6edb0af2-39ec-4b87-9bf4-29fd44fff1a1`

### Notifications
- `Illuminate\Notifications\Notification` → `3620e6d9-68cd-47d3-9a54-aa6d40d730a6`
- `Illuminate\Notifications\Messages\MailMessage` → `b47ae7b6-7ac5-46a5-9b0a-fe8b319242e2`

### Mail
- `Illuminate\Mail\Mailable` → `9e6ffaa7-68e1-4afd-ba9c-7daabefa3467`
- `Illuminate\Mail\Mailables\Content` → `004fb22b-f352-4125-8621-ac550156e892`
- `Illuminate\Mail\Mailables\Envelope` → `8c0701f1-6891-4976-90ad-ed5ff23e475e`

## Benefits

✅ **Consistency**: Exported projects use the same framework file UUIDs as Stellify  
✅ **No duplicates**: Framework files won't be duplicated in the database  
✅ **Proper relationships**: `extends_uuid` and `implements_uuids` link correctly to framework files  
✅ **Seamless integration**: Exported projects integrate immediately with Stellify's framework library  

## Example Export

### Input: UserController.php

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return view('users.index', ['users' => $users]);
    }
}
```

### Output: Files Table

**Data column contains:**
```json
{
    "uuid": "new-uuid-for-usercontroller",
    "namespace": "App\\Http\\Controllers",
    "name": "UserController",
    "type": "class",
    "public": true,
    "path": "/app/Http/Controllers/UserController.php",
    "extends": "Controller",
    "extends_uuid": "6aaf556a-f2c1-43d0-a833-7db187868ada",
    "implements": [],
    "implements_uuids": []
}
```

The `extends_uuid` field points to your existing `Controller` file in Stellify! 🎉

## Adding New Core Files

To add more core Laravel files, simply add them to the `$this->coreFiles` array in `PhpFileParser.php`:

```php
$this->coreFiles = [
    // ... existing entries
    'Your\New\CoreClass' => 'your-predefined-uuid-here',
];
```
