# Deployment & Operations

Read this before touching server setup, environment config, or anything
related to going live. This system deploys to a **single on-premise server
inside Dex's office LAN — no public internet exposure.**

## Target environment

- Ubuntu Server LTS (22.04/24.04), 4+ cores, 16GB RAM, SSD storage, UPS
  for power protection.
- Nginx (reverse proxy + static assets) → PHP-FPM 8.2 → Laravel app →
  MySQL 8 (same box is fine at this scale).
- Laravel queue worker managed by `systemd`; scheduler via cron.
- Server assigned a static local IP or internal hostname (e.g.
  `dex-pms.local`) via the office router.
- Router firewall blocks all inbound WAN traffic to this server — LAN
  access only.
- HTTPS via an internal CA or self-signed cert trusted on office machines
  (see `security.md`) — plaintext credentials on an office network are
  still sniffable, don't skip this because it's "just LAN."

## Do not add for this scale

Load balancers, CDN, multi-region failover, container orchestration,
managed cloud database. None of these solve a problem this deployment has.
If a future requirement (e.g. remote access for off-site managers) changes
that calculus, the correct next step is a VPN (e.g. WireGuard) into the
existing LAN — not exposing the app directly to the internet.

## Deployment flow

```
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm run build
php artisan config:cache && php artisan route:cache
# restart php-fpm
```

Zero-downtime deploy tooling is unnecessary complexity here — a short
restart during off-hours is acceptable for a 10–50-user office tool.

## Backups

- Nightly cron job: `mysqldump` + file storage directory, encrypted.
- Copy to an external drive and/or an offsite encrypted cloud location —
  **at least one copy must leave the office premises.** An on-premise-only
  backup defeats its own purpose in a fire, flood, or theft scenario.
- Test the restore procedure monthly, not just assume it works.

## Monitoring

- Laravel's log files with rotation enabled.
- A basic cron script hitting a health-check endpoint, alerting (even just
  email) if the app is unreachable. Nothing more elaborate is needed at
  this scale.

## Environment separation

- Local dev: XAMPP or Laravel Sail.
- Production: the on-premise server described above.
- Separate `.env` per environment. Production `.env` values are never
  committed, never shared outside the people who need server access, and
  never reused as "convenient" defaults in local dev.
