# ==============================================================================
# Dex PMS - Automated Local Docker Setup for Windows (PowerShell)
# ==============================================================================
# Usage:
#   Open PowerShell, navigate to the project directory, and run:
#   powershell -ExecutionPolicy Bypass -File .\setup.ps1
#
# Prerequisites on the target PC:
#   - Git (https://git-scm.com/)
#   - Docker Desktop (https://www.docker.com/products/docker-desktop/) running
# ==============================================================================

$ErrorActionPreference = "Continue"

Write-Host ""
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "  Dex PMS - Automated Local Docker Setup (Windows)" -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Verify Docker is running
Write-Host "[1/6] Checking Docker status..." -ForegroundColor Yellow
$dockerInfo = docker info 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Docker Desktop is not running or not found." -ForegroundColor Red
    Write-Host "Please start Docker Desktop and run this script again." -ForegroundColor Red
    exit 1
}
Write-Host "  -> Docker is active and running." -ForegroundColor Green

# 2. Environment file (.env)
Write-Host "[2/6] Configuring environment file (.env)..." -ForegroundColor Yellow
if (-not (Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
        Write-Host "  -> .env created from .env.example" -ForegroundColor Green
    } else {
        Write-Host "ERROR: .env.example not found!" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "  -> .env already exists. Preserving current configuration." -ForegroundColor Green
}

# 3. Bootstrap Composer dependencies if vendor/ is missing
Write-Host "[3/6] Checking PHP and Composer dependencies..." -ForegroundColor Yellow
if (-not (Test-Path "vendor/autoload.php")) {
    Write-Host "  -> vendor directory is missing." -ForegroundColor Yellow
    Write-Host "  -> Bootstrapping Composer packages via temporary Docker container..." -ForegroundColor Cyan
    Write-Host "     (This downloads dependencies and creates vendor folder - please wait a moment)" -ForegroundColor Gray
    
    docker run --rm -v "${PWD}:/var/www/html" -w /var/www/html laravelsail/php82-composer:latest composer install --ignore-platform-reqs
    if ($LASTEXITCODE -ne 0) {
        Write-Host "ERROR: Composer installation failed inside Docker container." -ForegroundColor Red
        exit 1
    }
    Write-Host "  -> Composer dependencies installed successfully." -ForegroundColor Green
} else {
    Write-Host "  -> Composer dependencies already installed." -ForegroundColor Green
}

# 4. Start Docker Compose containers
Write-Host "[4/6] Starting Docker containers (Laravel, MySQL, Redis, phpMyAdmin)..." -ForegroundColor Yellow
docker compose up -d
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Failed to start containers via docker compose up." -ForegroundColor Red
    exit 1
}
Write-Host "  -> Docker containers started in background." -ForegroundColor Green

# 5. Wait for MySQL to be healthy
Write-Host "  -> Waiting for MySQL database to initialize..." -ForegroundColor Cyan
$maxAttempts = 30
$attempt = 0
$mysqlReady = $false

while ($attempt -lt $maxAttempts) {
    $attempt++
    $health = (docker compose ps mysql --format "{{.Health}}").Trim()
    if ($health -eq "healthy") {
        $mysqlReady = $true
        break
    }
    Start-Sleep -Seconds 2
}

if ($mysqlReady) {
    Write-Host "  -> MySQL is healthy and ready to accept connections." -ForegroundColor Green
} else {
    Write-Host "  -> Warning: MySQL health check timed out; proceeding with artisan commands..." -ForegroundColor Yellow
}

# 6. Generate application key and populate database
Write-Host "[5/6] Ensuring application key and database setup..." -ForegroundColor Yellow
$envContent = Get-Content .env -Raw
if ($envContent -notmatch "APP_KEY=base64:") {
    Write-Host "  -> Generating application key..." -ForegroundColor Cyan
    docker compose exec -T laravel.test php artisan key:generate --force
} else {
    Write-Host "  -> Application key is already set." -ForegroundColor Green
}

if (Test-Path "database/seed_data.sql") {
    Write-Host "  -> Found full database snapshot (database/seed_data.sql)." -ForegroundColor Cyan
    Write-Host "  -> Restoring existing projects, personnel, accounts, and history..." -ForegroundColor Cyan
    Get-Content database/seed_data.sql | docker compose exec -T mysql mysql -u sail -ppassword dex_pms
    docker compose exec -T laravel.test php artisan migrate --force
    Write-Host "  -> Database restored successfully with full data!" -ForegroundColor Green
} else {
    Write-Host "  -> Running fresh database migrations and demo seeders..." -ForegroundColor Cyan
    docker compose exec -T laravel.test php artisan migrate --seed --force
    Write-Host "  -> Database migrated and demo data seeded successfully." -ForegroundColor Green
}

# 7. Frontend assets & Autoloader optimization
Write-Host "[6/6] Installing Node dependencies and compiling frontend assets..." -ForegroundColor Yellow
docker compose exec -T laravel.test npm install
docker compose exec -T laravel.test npm run build
docker compose exec -T laravel.test composer dump-autoload -o
Write-Host "  -> Frontend assets compiled and Composer autoloader optimized successfully." -ForegroundColor Green

# Summary banner
Write-Host ""
Write-Host "================================================================" -ForegroundColor Green
Write-Host "   Dex PMS is ready! Local environment setup is complete.       " -ForegroundColor Green
Write-Host "================================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Access URLs:" -ForegroundColor Cyan
Write-Host "  - Web Application: http://localhost" -ForegroundColor White
Write-Host "  - phpMyAdmin:      http://localhost:8081" -ForegroundColor White
Write-Host "                     (Server: mysql, Username: sail, Password: password)" -ForegroundColor Gray
Write-Host ""
Write-Host "Pre-seeded Demo Accounts (Passwords are active for testing):" -ForegroundColor Cyan
Write-Host "  - Admin:    admin@dex-pms.local     | DexAdmin2026!" -ForegroundColor White
Write-Host "  - Manager:  manager@dex-pms.local   | DexManager2026!" -ForegroundColor White
Write-Host "  - Staff:    inventory@dex-pms.local | DexInventory2026!" -ForegroundColor White
Write-Host ""
Write-Host "Useful Everyday Commands:" -ForegroundColor Cyan
Write-Host "  - Stop system:    docker compose down" -ForegroundColor White
Write-Host "  - Start system:   docker compose up -d" -ForegroundColor White
Write-Host "  - Run tests:      docker compose exec -T laravel.test ./vendor/bin/pest" -ForegroundColor White
Write-Host "  - Run asset dev:  docker compose exec laravel.test npm run dev" -ForegroundColor White
Write-Host ""
