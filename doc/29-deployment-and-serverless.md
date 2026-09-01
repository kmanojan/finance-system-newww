# 29. Deployment & Serverless Architecture (Vercel + Supabase)

## Overview

The Apptimus Finance System is deployed on **Vercel Serverless Functions** (PHP 8.3 via `vercel-php@0.7.3`) connected to a hosted **Supabase PostgreSQL** database.

---

## 1. Key Architectural Components

```
                +----------------------------+
                |    User Web Browser / PWA   |
                +--------------+-------------+
                               |
                               v
                +----------------------------+
                |     Vercel Edge / CDN      |
                |    (Singapore sin1 Region) |
                +--------------+-------------+
                               |
              +----------------+----------------+
              | (Static Files)                  | (Dynamic Routes)
              v                                 v
   +-----------------------+        +-----------------------+
   | /public/build/*       |        | /api/index.php        |
   | /styles.css           |        | (Serverless Bridge)   |
   | /script.js            |        +-----------+-----------+
   | /favicon.ico          |                    |
   +-----------------------+                    v
                                    +-----------------------+
                                    | Laravel App Container |
                                    | ($app->useStoragePath |
                                    |  '/tmp/storage')      |
                                    +-----------+-----------+
                                                |
                                                v
                                    +-----------------------+
                                    |  Supabase PostgreSQL  |
                                    |  (Singapore Region)   |
                                    |  Port 5432 / 6543     |
                                    +-----------------------+
```

---

## 2. Serverless File System Considerations

### Read-Only Root Filesystem
In Vercel Serverless environments:
- The project root (`/var/task/user/`) is **read-only**.
- All writable operations MUST be directed to the **`/tmp`** directory.

### Solutions Implemented:
1. **Dynamic Storage Path (`bootstrap/app.php`)**:
   ```php
   if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || getenv('VERCEL')) {
       $app->useStoragePath('/tmp/storage');
   }
   ```
2. **Directory Initialization (`api/index.php`)**:
   The serverless bridge guarantees all essential directories exist before executing requests:
   - `/tmp/storage/app/private`
   - `/tmp/storage/app/public`
   - `/tmp/storage/framework/cache/data`
   - `/tmp/storage/framework/sessions`
   - `/tmp/storage/framework/views`
   - `/tmp/storage/logs`
   - `/tmp/uploads/templates`

3. **Blade Template Cache**:
   `VIEW_COMPILED_PATH=/tmp/storage/framework/views` is configured in `vercel.json` and `api/index.php`.

4. **Dynamic File Uploads (`DocumentTemplateController.php` & `routes/web.php`)**:
   Uploaded template images and documents are saved into `/tmp/uploads/` and served via a dynamic streaming route:
   ```php
   Route::get('/uploads/{path}', function ($path) {
       $tmpFile = '/tmp/uploads/' . $path;
       $publicFile = public_path('uploads/' . $path);
       $filePath = file_exists($tmpFile) ? $tmpFile : (file_exists($publicFile) ? $publicFile : null);
       if (!$filePath) abort(404);
       return response()->file($filePath);
   })->where('path', '.*');
   ```

---

## 3. Database & Network Optimization

### Low Latency Region Colocation
- **Vercel Region**: Singapore (`sin1`) specified in `vercel.json`: `"regions": ["sin1"]`.
- **Supabase Region**: Singapore (`ap-southeast-1`).
- **Result**: Query latency dropped from ~250ms (cross-ocean) to **~5–15ms**.

### Connection Pooling
For serverless scaling:
- **Direct Connection**: `db.<project-ref>.supabase.co:5432`
- **Supavisor Transaction Pooler**: `aws-0-<region>.pooler.supabase.com:6543` (handles thousands of concurrent serverless instances).

---

## 4. `vercel.json` Configuration Reference

```json
{
    "version": 2,
    "framework": null,
    "buildCommand": "npm run build",
    "outputDirectory": "dist",
    "regions": ["sin1"],
    "functions": {
        "api/index.php": {
            "runtime": "vercel-php@0.7.3"
        }
    },
    "routes": [
        { "src": "/build/(.*)", "dest": "/public/build/$1" },
        { "src": "/favicon.ico", "dest": "/public/favicon.ico" },
        { "src": "/robots.txt", "dest": "/public/robots.txt" },
        { "src": "/styles.css", "dest": "/public/styles.css" },
        { "src": "/script.js", "dest": "/public/script.js" },
        { "src": "/uploads/(.*)", "dest": "/public/uploads/$1" },
        { "src": "/(.*\\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|pdf))", "dest": "/public/$1" },
        { "src": "/(.*)", "dest": "/api/index.php" }
    ],
    "env": {
        "DB_CONNECTION": "pgsql",
        "SESSION_DRIVER": "cookie",
        "PHP_VERSION": "8.3",
        "LOG_CHANNEL": "stderr",
        "APP_ENV": "production",
        "APP_DEBUG": "true",
        "APP_KEY": "base64:...",
        "VIEW_COMPILED_PATH": "/tmp/storage/framework/views"
    }
}
```

---

## 5. Deployment Commands & Workflow

### 5.1 Vercel Deployment Workflow (Zero-Command Deployments)

When deploying to **Vercel Serverless**, you **do NOT need to run `php artisan optimize` or `php artisan config:cache`**:

1. **Why `optimize` / `config:cache` is skipped on Vercel**:
   - Vercel functions execute in isolated, stateless containers where the application root is **read-only** (except `/tmp`).
   - Vercel injects environment variables dynamically into `$_ENV` and `$_SERVER` at runtime. Running `config:cache` during build can freeze stale environment variables or cause runtime path resolution issues.
   - Blade templates are automatically compiled and stored in `/tmp/storage/framework/views`.

2. **Automatic Schema Migrations**:
   - [`AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) contains an automatic migration check that detects missing columns (such as `loan_code`, `maturity_date`, `deleted_at`) and triggers `migrate --force` automatically on the first request after deployment.

3. **Standard Deployment**:
   ```bash
   git add .
   git commit -m "Deploy new features"
   git push origin main
   ```
   Vercel CI/CD automatically detects the push, compiles assets via `npm run build`, and publishes the serverless deployment.

---

## 6. Traditional VPS / Dedicated Server Deployments (Comparison)

If deploying to a traditional server (Ubuntu / Forge / EC2 / Docker with PHP-FPM and Nginx):

```bash
# 1. Put app into maintenance mode
php artisan down

# 2. Pull latest code & dependencies
git pull origin main
composer install --no-dev --optimize-autoloader

# 3. Run database migrations
php artisan migrate --force

# 4. Clear stale caches and rebuild optimization files
php artisan optimize:clear
php artisan optimize

# 5. Bring app back online
php artisan up
```

### Command Reference:

| Command | Vercel Serverless | Traditional VPS | Description |
| :--- | :---: | :---: | :--- |
| `git push origin main` | **Required** | Optional | Triggers automated deployment. |
| `php artisan migrate --force` | Auto-handled (or run locally) | **Required** | Applies pending database schema migrations. |
| `php artisan optimize` | **Do NOT run** | **Recommended** | Caches configuration, routes, and compiled classes. |
| `php artisan optimize:clear` | Auto-handled in `/tmp` | **Recommended** | Clears all caches before rebuilding. |
| `php artisan view:clear` | Auto-handled in `/tmp` | As needed | Clears compiled Blade templates. |

