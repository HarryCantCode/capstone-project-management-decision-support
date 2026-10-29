#!/usr/bin/env bash
# ==============================================================================
# Dex PMS — Automated Local Docker Setup for Linux / macOS / WSL2
# ==============================================================================
# Usage:
#   chmod +x setup.sh
#   ./setup.sh
#
# Prerequisites on the target PC:
#   - Git
#   - Docker & Docker Compose running
# ==============================================================================

set -e

echo ""
echo "=================================================="
echo "  Dex PMS — Automated Local Docker Setup (Bash)   "
echo "=================================================="
echo ""

# 1. Verify Docker is running
echo "[1/6] Checking Docker status..."
if ! docker info > /dev/null 2>&1; then
    echo "ERROR: Docker is not running or not found in PATH."
    echo "Please start Docker / Docker Desktop and run this script again."
    exit 1
fi
echo "  -> Docker is active and running."

# 2. Environment file (.env)
echo "[2/6] Configuring environment file (.env)..."
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo "  -> .env created from .env.example"
    else
        echo "ERROR: .env.example not found!"
        exit 1
    fi
else
    echo "  -> .env already exists. Preserving current configuration."
fi

# 3. Bootstrap Composer dependencies if vendor/ is missing
echo "[3/6] Checking PHP & Composer dependencies..."
if [ ! -f "vendor/autoload.php" ]; then
    echo "  -> vendor/ directory is missing."
    echo "  -> Bootstrapping Composer packages via temporary Docker container..."
    echo "     (This downloads dependencies and creates vendor/ — please wait a moment)"
    
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        laravelsail/php82-composer:latest \
        composer install --ignore-platform-reqs
    echo "  -> Composer dependencies installed successfully."
else
    echo "  -> Composer dependencies already installed."
fi

# 4. Start Docker Compose containers
echo "[4/6] Starting Docker containers (Laravel, MySQL, Redis, phpMyAdmin)..."
docker compose up -d
echo "  -> Docker containers started in background."

# 5. Wait for MySQL to be healthy
echo "  -> Waiting for MySQL database to become ready..."
MAX_ATTEMPTS=30
ATTEMPT=0
MYSQL_READY=0

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    ATTEMPT=$((ATTEMPT + 1))
    if docker compose exec -T mysql mysqladmin ping -ppassword 2>&1 | grep -q "mysqld is alive"; then
        MYSQL_READY=1
        break
    fi
    sleep 2
done

if [ $MYSQL_READY -eq 1 ]; then
    echo "  -> MySQL is healthy and ready to accept connections."
else
    echo "  -> Warning: MySQL took longer than expected; proceeding with commands..."
fi

# 6. Generate application key and populate database
echo "[5/6] Ensuring application key & database setup..."
if ! grep -q "APP_KEY=base64:" .env; then
    echo "  -> Generating application key..."
    docker compose exec -T laravel.test php artisan key:generate --force
else
    echo "  -> Application key is already set."
fi

if [ -f "database/seed_data.sql" ]; then
    echo "  -> Found full database snapshot (database/seed_data.sql)."
    echo "  -> Restoring existing projects, personnel, accounts, and history..."
    docker compose exec -T mysql mysql -u sail -ppassword dex_pms < database/seed_data.sql
    docker compose exec -T laravel.test php artisan migrate --force
    echo "  -> Database restored successfully with full data!"
else
    echo "  -> Running fresh database migrations and demo seeders..."
    docker compose exec -T laravel.test php artisan migrate --seed --force
    echo "  -> Database migrated and demo data seeded successfully."
fi

# 7. Frontend assets & Autoloader optimization
echo "[6/6] Installing Node dependencies and compiling frontend assets..."
docker compose exec -T laravel.test npm install
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test composer dump-autoload -o
echo "  -> Frontend assets compiled and Composer autoloader optimized successfully."

# Summary banner
echo ""
echo "================================================================"
echo "   Dex PMS is ready! Local environment setup is complete.       "
echo "================================================================"
echo ""
echo "Access URLs:"
echo "  - Web Application: http://localhost"
echo "  - phpMyAdmin:      http://localhost:8081"
echo "                     (Server: mysql, Username: sail, Password: password)"
echo ""
echo "Pre-seeded Demo Accounts (Passwords are all active for testing):"
echo "  - Admin:    admin@dex-pms.local     | DexAdmin2026!"
echo "  - Manager:  manager@dex-pms.local   | DexManager2026!"
echo "  - Staff:    inventory@dex-pms.local | DexInventory2026!"
echo ""
echo "Useful Everyday Commands:"
echo "  - Stop system:    docker compose down"
echo "  - Start system:   docker compose up -d"
echo "  - Run tests:      docker compose exec -T laravel.test ./vendor/bin/pest"
echo "  - Run asset dev:  docker compose exec laravel.test npm run dev"
echo ""
