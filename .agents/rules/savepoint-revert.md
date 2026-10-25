# Magic Word: "Revert me please"

When the user types or mentions the magic phrase **"Revert me please"** (case-insensitive, in English or as part of a sentence asking to revert to this checkpoint), execute the following restoration procedure immediately:

1. **Reset Git working tree and commits back to the checkpoint tag**:
   ```powershell
   git reset --hard savepoint-revert-me-please
   git clean -fd
   ```

2. **Restore the MySQL database to the checkpoint state**:
   ```powershell
   Get-Content storage/checkpoint_backup.sql | docker exec -i projectmangementsystem-capstone-mysql-1 mysql -u sail -ppassword dex_pms
   ```

3. **Clear application cache**:
   ```powershell
   docker exec projectmangementsystem-capstone-laravel.test-1 php artisan optimize:clear
   ```

4. **Verify status and notify the user**:
   Run `git status` and confirm that all code, database, and application state have been reverted cleanly back to the savepoint created on 2026-10-25 21:05.
