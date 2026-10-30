# ZimComply AI — Infrastructure & Deployment Specification

*Document Version:* 1.0.0  
*Phase:* Phase 0 Architecture Baseline  
*Status:* Authoritative

---

## 1. Hosting Architecture & Target Platforms

ZimComply AI is designed for high-availability deployment on **Laravel Cloud** or containerized/virtualized Linux VPS infrastructure (Ubuntu 24.04 LTS on AWS, DigitalOcean, or Hetzner).

```
                            [ Cloudflare / DNS ]
                                     |
                                     v
                        [ Nginx / Caddy Web Server ]
                         (TLS 1.3, HTTP/2, Static)
                                     |
                                     v
                           [ PHP 8.2+ PHP-FPM ]
                        (OPcache, JIT, Worker Pool)
                          /          |          \
                         /           |           \
                        v            v            v
               [ PostgreSQL 16 ]  [ Redis ]   [ S3 Storage ]
                 (Primary DB)     (Queues &    (Encrypted Vault)
                                   Sessions)
```

---

## 2. Server Prerequisites & Runtime Configuration

### 2.1 Operating System & Packages
- **OS:** Ubuntu 24.04 LTS x64 (or Windows Server 2022 for dedicated enterprise on-premises).
- **PHP:** 8.2 or 8.3 with extensions:
  `bcmath`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_pgsql`, `redis`, `xml`, `zip`.
- **Database:** PostgreSQL 16 with UTF-8 encoding.
- **Cache / Queue:** Redis 7.2+.
- **Node.js:** Node.js 20 LTS & NPM 10+.

### 2.2 PHP-FPM Performance Tuning
```ini
; /etc/php/8.2/fpm/pool.d/zimcomply.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 1000
request_terminate_timeout = 60s

; OPcache settings
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
```

---

## 3. Background Processing & Queue Workers

ZimComply AI uses **native Laravel Queue workers and Scheduler**; no external workflow engines (e.g. n8n) are permitted.

### 3.1 Supervisor Queue Worker Configuration
```ini
; /etc/supervisor/conf.d/zimcomply-worker.conf
[program:zimcomply-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zimcomply/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/zimcomply/worker.log
```

### 3.2 Scheduled Task Execution (Cron)
```bash
# Crontab for www-data
* * * * * cd /var/www/zimcomply && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs execute:
- Daily compliance expiry scans (dispatching 90/60/30/14/7-day alerts).
- Asynchronous document OCR processing and verification queue cleanup.
- Weekly regulatory radar scraping of gazetted statutory notices.
- Daily database backup generation and off-site synchronization.

---

## 4. PostgreSQL Database Hardening

1. **Connection Pooling:** Use PgBouncer in transaction mode for high-concurrency environments.
2. **Encrypted Connections:** Mandate `sslmode=require` between application servers and the database.
3. **Backup Strategy:**
   - Continuous WAL (Write-Ahead Logging) archiving via `pgBackRest` or AWS RDS automated snapshots.
   - Nightly encrypted database dumps exported to isolated off-site object storage.

---

## 5. Build & Deployment Pipeline

### 5.1 Zero-Downtime Deployment Script (Envoy / GitHub Actions)
```bash
#!/bin/bash
set -e

echo "Deploying ZimComply AI..."

# 1. Enter release directory
cd /var/www/zimcomply

# 2. Pull latest verified release
git fetch origin main
git checkout main
git pull origin main

# 3. Install PHP dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Install Node dependencies and compile frontend
npm ci
npm run build

# 5. Run safe database migrations (NEVER fresh)
php artisan migrate --force

# 6. Optimize framework caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Restart queue workers and reload PHP-FPM
php artisan queue:restart
sudo systemctl reload php8.2-fpm

echo "Deployment completed successfully!"
```

---

## 6. Environment Configuration Checklist (.env)

| Key | Description | Example / Recommended Setting |
|---|---|---|
| `APP_ENV` | Application environment | `production` |
| `APP_DEBUG` | Debug mode | `false` |
| `APP_URL` | Canonical platform URL | `https://app.zimcomply.co.zw` |
| `DB_CONNECTION` | Database driver | `pgsql` |
| `DB_HOST` | PostgreSQL host | `127.0.0.1` or internal private IP |
| `DB_PORT` | PostgreSQL port | `5432` |
| `DB_DATABASE` | Database name | `zimcomply_prod` |
| `QUEUE_CONNECTION`| Queue driver | `redis` |
| `CACHE_STORE` | Cache driver | `redis` |
| `SESSION_DRIVER` | Session storage | `redis` |
| `FILESYSTEM_DISK`| File storage driver | `s3` (private bucket) |
| `AWS_BUCKET` | Document vault bucket | `zimcomply-secure-vault` |
| `GEMINI_API_KEY` | AI Assistant API key | Encrypted server secret |
