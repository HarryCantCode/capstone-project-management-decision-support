#!/usr/bin/env bash
# ==============================================================================
# Dex PMS - Export Database Snapshot to database/seed_data.sql
# ==============================================================================
# Usage:
#   chmod +x export-db.sh
#   ./export-db.sh
# ==============================================================================

set -e

echo "Exporting current MySQL database to database/seed_data.sql..."
docker compose exec -T mysql mysqldump -u sail -ppassword --no-tablespaces dex_pms > database/seed_data.sql

echo "Database snapshot exported successfully to database/seed_data.sql!"
echo "You can now commit and push this file to GitHub so other PCs will have your latest data."
