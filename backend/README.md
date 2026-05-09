# Login System Setup — Step by Step

## Prerequisites
- XAMPP running (Apache + MySQL)
- workforce_db created with schema.sql already imported
- PHP added to PATH (C:\xampp\php)
- Composer installed

---

## Step 1 — Create Laravel Project

Open PowerShell and run:
```powershell
cd C:\xampp\htdocs
composer create-project laravel/laravel workforce-api
cd workforce-api
```

---

## Step 2 — Install JWT Package

```powershell
composer require firebase/php-jwt
```

---

## Step 3 — Copy These Files Into Your Project

```
.env                                        → .env  (replace existing)
config/jwt.php                              → config/jwt.php
routes/api.php                              → routes/api.php
app/Models/Admin.php                        → app/Models/Admin.php
app/Models/Site.php                         → app/Models/Site.php
app/Models/AuditLog.php                     → app/Models/AuditLog.php
app/Http/Controllers/AuthController.php    → app/Http/Controllers/AuthController.php
app/Http/Middleware/JwtAuth.php             → app/Http/Middleware/JwtAuth.php
database/seeders/AdminSeeder.php            → database/seeders/AdminSeeder.php
```

---

## Step 4 — Generate App Key

```powershell
php artisan key:generate
```

---

## Step 5 — Register JWT Middleware

Open `bootstrap/app.php` and find `->withMiddleware(...)`, update it to:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'jwt' => \App\Http\Middleware\JwtAuth::class,
    ]);
})
```

---

## Step 6 — Seed Admin Users

```powershell
php artisan db:seed --class=AdminSeeder
```

---

## Step 7 — Start the Server

```powershell
php artisan serve
```

API is now running at: **http://localhost:8000**

---

## Testing the Login API

### Login
```
POST http://localhost:8000/api/auth/login
Content-Type: application/json

{
    "username": "admin",
    "password": "password"
}
```

**Success Response:**
```json
{
    "success": true,
    "message": "Login successful.",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_in": 3600,
    "admin": {
        "admin_id": 1,
        "username": "admin",
        "email": "admin@workforce.com",
        "permission_level": "superadmin",
        "site": {
            "site_id": 1,
            "site_name": "Main Branch"
        }
    }
}
```

### Get Current Admin (requires token)
```
GET http://localhost:8000/api/auth/me
Authorization: Bearer YOUR_TOKEN_HERE
```

### Logout (requires token)
```
POST http://localhost:8000/api/auth/logout
Authorization: Bearer YOUR_TOKEN_HERE
```

### Change Password (requires token)
```
POST http://localhost:8000/api/auth/change-password
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json

{
    "current_password": "password",
    "new_password": "newpassword123",
    "new_password_confirmation": "newpassword123"
}
```

---

## Default Credentials

| Username | Password | Level      |
|----------|----------|------------|
| admin    | password | superadmin |
| manager1 | password | manager    |
| manager2 | password | manager    |

---

## Using the Token in Other Requests

After login, copy the token and add it to every protected request:
```
Authorization: Bearer eyJ0eXAiOiJKV1Qi...
```

Token expires after **60 minutes**. Login again to get a new one.
