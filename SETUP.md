# MySantriQu deployment notes

## Google OAuth

Google OAuth uses a server-side authorization-code flow. Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` as server environment variables. For local XAMPP, they can instead be kept in `C:\xampp\mysantriqu-oauth.php`, outside `htdocs`:

```php
<?php
return [
    'client_id' => 'your-google-client-id.apps.googleusercontent.com',
    'client_secret' => 'your-google-client-secret',
    'redirect_uri' => 'http://localhost/MySantriQu/api/login.php',
];
```

Do not commit client secrets or put them in a file below the web root. Restart Apache after changing server environment variables. The redirect URI must exactly match the authorized redirect URI configured for the Google OAuth client. Google login is available only to an email that already exists in `users`; it does not create an account automatically. PHP cURL must be enabled.

## Login database

For a new database, import `mysantriqu_xampp.sql` to create the initial `users` table. For an existing database whose `users` table predates phone login, run `mysantriqu_phone_login_migration.sql` once. The existing schema must already contain the `users` table.

Email login uses the account password. Phone login accepts Indonesian numbers entered as `08...`, `62...`, or `+62...`; the stored value is normalized to `+62...`. A phone can be used to log in only after an administrator has confirmed ownership and stored a non-NULL `phone_verified_at` timestamp. SMS/WhatsApp OTP is not part of this flow.

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
3. `mysantriqu_academic_calendar_time_migration.sql`
4. `mysantriqu_roles_migration.sql` if the role migration has not already been applied
5. `mysantriqu_student_services_migration.sql`
6. `mysantriqu_academic_documents_migration.sql`

The full `mysantriqu_xampp.sql` schema includes the profile, role, academic-calendar, and student-service tables for new installations. Apply `mysantriqu_academic_calendar_migration.sql` once after importing the existing schema to enable draft publishing, event descriptions/categories, change history, and push subscriptions.

## Academic calendar and notifications

The calendar accepts `.xlsx` workbooks up to 10 MB. Use one header row with **Tanggal Mulai**, **Tanggal Selesai**, **Nama Kegiatan**, **Keterangan**, and **Kategori**; **Jam Mulai**, **Jam Selesai**, and **Akhir Semester** are optional. If supplied, start/end times must both be present. Dates should be real Excel dates or `DD/MM/YYYY`; times should be Excel time values or `HH:MM`. The admin reviews and corrects the parsed activities, saves a draft, and publishes it when ready. Each published activity can be rescheduled or cancelled only with a reason; the old/new schedule and reason remain in the change history. Event colors follow their category and can be adjusted in the import preview.

The page also keeps an existing UAS/akhir-semester flag: when a published, non-cancelled marked event has ended, opening the calendar advances each santri's active semester by one (up to semester 6). Review the flag carefully before publishing.

Web Push works for users who opt in on a supported HTTPS browser/PWA. Generate a VAPID key pair from a local PowerShell window and save the output outside `htdocs`:

```powershell
& C:\xampp\php\php.exe C:\xampp\htdocs\MySantriQu\scripts\generate-academic-calendar-vapid-keys.php |
  Set-Content -LiteralPath C:\xampp\mysantriqu-push.php -Encoding ascii
```

Edit `C:\xampp\mysantriqu-push.php` and replace the sample `mailto:admin@example.org` subject with an address monitored by the application operator. Keep this private-key file outside the web root and never commit it. The public domain must serve the app over HTTPS, and Apache/PHP must have OpenSSL and cURL enabled. Users opt in separately on each browser/device; calendar changes are also shown in the page while it is open. If push is not configured, the page still refreshes in-app updates while open.

On a non-XAMPP production host, store the generated PHP config outside the web root and set `MYSANTRIQU_PUSH_CONFIG` to its absolute path, or provide `MYSANTRIQU_VAPID_PUBLIC_KEY`, `MYSANTRIQU_VAPID_PRIVATE_KEY`, and `MYSANTRIQU_VAPID_SUBJECT` as server environment variables.

## KHS and academic PDF library

Apply `mysantriqu_academic_documents_migration.sql` before using KHS, Skripsi, or Jurnal Munazharah. PHP creates the private PDF directory at `C:\xampp\mysantriqu-private-documents` on first upload; ensure the Apache/PHP account can write there. Set `ACADEMIC_DOCUMENT_STORAGE` to a different directory outside the web root if needed.

Admins upload one KHS PDF per santri and academic period. Re-uploading that same santri/period replaces the previous KHS. Students with the `mahasantri` or `santri` role can only view KHS linked to their account; connect student accounts to a santri record in **Manajemen User**.

Admins upload Skripsi and Jurnal Munazharah PDFs with the work title, author name, author status (active student or alumni), publication year, and optional description. Logged-in `mahasantri` and `santri` accounts can read these reference libraries. Admins can remove uploaded documents. PDF uploads are limited to 20 MB and served only through authenticated endpoints; do not move the storage directory under the web root.
