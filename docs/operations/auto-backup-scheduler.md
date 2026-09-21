# Automatic backup scheduler rollout

After reviewing and deploying this change, run the CLI entry point hourly using a dedicated account with access to the application database and `tmp/auto_backups`.

Windows Task Scheduler program: `F:\xampp\php\php.exe`

Arguments: `F:\xampp\htdocs\collection\scripts\run_auto_backup.php`

Start in: `F:\xampp\htdocs\collection`

On Linux, use an hourly cron entry with the equivalent PHP and project paths. Direct standard output and standard error to a protected log outside the web root. Verify the first due run by checking its exit code, the admin page's last run timestamps, and a complete backup manifest. Keep `tmp/auto_backups/.htaccess` protected and restrict operating system permissions.

The service uses one MySQL advisory lock per database for both manual and automatic jobs. All runners must connect to the same database for this lock to coordinate them. No scheduler is installed by this change.
