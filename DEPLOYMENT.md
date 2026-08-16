# Deployment Guide

## Prerequisites

- PHP 8.2+ with extensions: bcmath, ctype, curl, dom, fileinfo, json, mbstring, openssl, pdo, tokenizer, xml, zip, gd
- MySQL 8.0+ or MariaDB 10.6+
- Composer 2.x
- Node.js 20+ + npm
- Queue worker (Supervisor on VPS, or Laravel Forge worker)
- Writable `storage/` and `bootstrap/cache/` directories

---

## First-time setup

```bash
# 1. Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 2. Configure environment
cp .env.example .env
php artisan key:generate
# Edit .env — fill in all required values

# 3. Private storage disk (add to config/filesystems.php):
# 'private' => [
#     'driver'     => 'local',
#     'root'       => storage_path('app/private'),
#     'visibility' => 'private',
# ],

# 4. Database
php artisan migrate --force
php artisan db:seed --force    # Permissions, roles, admin user

# 5. Storage link
php artisan storage:link

# 6. Create super admin (if not seeded)
php artisan admin:create-super

# 7. Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## Queue Worker (Supervisor)

```ini
[program:marketplace-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/marketplace/artisan queue:work \
    --queue=critical,notifications,sms,emails,default \
    --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/logs/worker.log
stopwaitsecs=3600
```

---

## Scheduler (Crontab)

```
* * * * * cd /path/to/marketplace && php artisan schedule:run >> /dev/null 2>&1
```

---

## Scheduled Tasks Summary

| Command | Frequency | Purpose |
|---------|-----------|---------|
| `offers:expire` | Hourly | Expire stale 8h offers |
| `orders:auto-complete` | Every 15 min | 72h buyer protection auto-complete |
| `earnings:release` | Every 15 min | Release 8h earning lock |
| `promotions:expire` | Every 5 min | Expire listing promotions |
| `promotions:warn-expiring` | Hourly | 24h expiry warning |
| Notification prune | Daily 03:00 | Remove read notifications >90 days |

---

## Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /path/to/marketplace/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    # Block private storage access
    location /app/private {
        deny all;
        return 403;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security headers (complement app-level headers)
    add_header X-XSS-Protection "1; mode=block";
}

# HTTP → HTTPS redirect
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;
}
```

---

## Post-deployment checklist

```bash
# Verify
php artisan about
php artisan queue:monitor default
curl -f https://yourdomain.com/up && echo "Health OK"
curl -I https://yourdomain.com/robots.txt
```

---

## Rollback

```bash
php artisan down          # Maintenance mode (shows 503 page)
git checkout <prev-tag>
composer install --no-dev --optimize-autoloader
php artisan migrate:rollback
php artisan up
```
