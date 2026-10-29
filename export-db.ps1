# ==============================================================================
# Dex PMS - Export Database Snapshot to database/seed_data.sql
# ==============================================================================
# Usage:
#   powershell -ExecutionPolicy Bypass -File .\export-db.ps1
# ==============================================================================

Write-Host "Exporting current MySQL database to database/seed_data.sql..." -ForegroundColor Cyan

cmd /c "docker compose exec -T mysql mysqldump -u sail -ppassword --no-tablespaces dex_pms > database\seed_data.sql"

if ($LASTEXITCODE -eq 0) {
    Write-Host "Database snapshot exported successfully to database/seed_data.sql!" -ForegroundColor Green
    Write-Host "You can now commit and push this file to GitHub so other PCs will have your latest data." -ForegroundColor Yellow
} else {
    Write-Host "ERROR: Export failed. Ensure Docker containers are running." -ForegroundColor Red
}
