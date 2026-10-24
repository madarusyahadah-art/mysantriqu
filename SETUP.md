# MySantriQu deployment notes

## Google OAuth

The Google client ID and secret are loaded from server environment variables first. For a local XAMPP installation, they can instead be kept in `C:\xampp\mysantriqu-oauth.php`, outside `htdocs`:

```php
<?php
return [
    'client_id' => 'your-google-client-id.apps.googleusercontent.com',
    'client_secret' => 'your-google-client-secret',
];
```

Do not commit client secrets or put them in a file below the web root. Restart Apache after changing server environment variables. The Google OAuth authorized redirect URI must match `https://your-host/api/login.php` (or the local development URL).

## First admin account

There is intentionally no default admin password in the database schema. Create the first admin from a local command prompt after importing the database schema and configuring the database connection:

```powershell
$env:MYSANTRIQU_ADMIN_EMAIL = "admin@example.org"
$env:MYSANTRIQU_ADMIN_NAME = "Application Admin"
$env:MYSANTRIQU_ADMIN_PASSWORD = Read-Host "Choose an admin password"
& C:\xampp\php\php.exe C:\xampp\htdocs\MySantriQu\scripts\bootstrap-admin.php
Remove-Item Env:\MYSANTRIQU_ADMIN_EMAIL, Env:\MYSANTRIQU_ADMIN_NAME, Env:\MYSANTRIQU_ADMIN_PASSWORD
```

Use a unique password of 12-72 characters. The command only runs in PHP CLI and refuses to run once an admin exists. Never commit the password or put it in a command-line argument.

New accounts intentionally wait for an admin to assign a role; self-service role selection is disabled.

## Database migrations

For an existing database, apply the SQL migrations in order:

1. `mysantriqu_profile_migration.sql`
2. `mysantriqu_academic_calendar_migration.sql`
3. `mysantriqu_roles_migration.sql` if the role migration has not already been applied

The full `mysantriqu_xampp.sql` schema includes the profile, role, and academic-calendar tables for new installations.
