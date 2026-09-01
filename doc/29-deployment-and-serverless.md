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

## 5. Deployment Commands

```bash
# Push directly to GitHub to trigger Vercel CI/CD
git push origin main

# Or deploy manually via Vercel CLI
vercel --prod
```
