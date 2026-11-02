# Hostinger VPS Production Deployment & Performance Optimization Guide

This guide ensures maximum throughput and minimum latency for **Dex PMS** on a Hostinger Linux VPS (Ubuntu/Debian).

---

## 1. Why VPS Will Be Drastically Faster Than Localhost
* **Native Filesystem**: Unlike Docker Desktop on Windows (which incurs cross-OS 9P/Hyper-V file-mounting latency), your code on a Hostinger Linux VPS resides on native ext4/NVMe SSD storage.
* **Production PHP OPcache**: PHP pre-compiles scripts into RAM byte-code without re-validating timestamps on every web request.

---

## 2. Server Requirements
* **OS**: Ubuntu 22.04 LTS or 24.04 LTS
* **Web Server**: Nginx (recommended) + PHP 8.2-FPM
* **Database**: MySQL 8.0+
* **In-Memory Store**: Redis (for session handling, caching, and task queues)

---

## 3. Post-Deployment Optimization Commands
Whenever deploying a new release or updating the codebase on your VPS, run this sequence:

```bash
# 1. Pull latest code & install production dependencies without dev tools
git pull origin main
composer install --no-dev --optimize-autoloader

# 2. Run pending migrations safely
php artisan migrate --force

# 3. Cache framework metadata (Crucial for sub-100ms response times)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 4. Build minified frontend assets
npm ci
npm run build

# 5. Restart PHP-FPM to load new OPcache state
sudo systemctl reload php8.2-fpm
```

---

## 4. Production PHP OPcache Configuration
Edit `/etc/php/8.2/fpm/conf.d/10-opcache.ini` on your VPS:

```ini
[opcache]
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```
> *Setting `opcache.validate_timestamps=0` stops PHP from checking the disk on every request. Whenever you deploy code, simply run `sudo systemctl reload php8.2-fpm`.*

---

## 5. Nginx Static Asset Caching & Gzip Compression
In your Nginx site configuration (`/etc/nginx/sites-available/dex-pms`):

```nginx
# Enable Gzip
gzip on;
gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript image/svg+xml;

# Aggressive browser caching for Vite build assets
location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg)$ {
    expires 1y;
    add_header Cache-Control "public, no-transform, immutable";
    access_log off;
}
```

---

## 6. Safe MySQL Buffer Tuning (Hostinger VPS)
In `/etc/mysql/mysql.conf.d/mysqld.cnf`:
```ini
[mysqld]
# Allocate ~50-60% of available RAM if dedicated to DB, or 256M-512M on a 2GB/4GB shared VPS:
innodb_buffer_pool_size = 512M
innodb_log_file_size = 64M
innodb_flush_log_at_trx_commit = 2
```
