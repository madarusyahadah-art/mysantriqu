<?php
declare(strict_types=1);

function academic_student_roles(): array
{
    return ['mahasantri', 'santri'];
}

function academic_document_storage_dir(): string
{
    $configured = getenv('ACADEMIC_DOCUMENT_STORAGE');
    return $configured !== false && $configured !== ''
        ? $configured
        : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'mysantriqu-private-documents';
}

function ensure_academic_document_storage(): string
{
    $directory = academic_document_storage_dir();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Academic document storage could not be created.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('Academic document storage is not writable.');
    }
    return $directory;
}

function require_academic_user(): array
{
    $user = current_user();
    if ($user === null) {
        json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
    }

    $role = strtolower((string) ($user['role'] ?? ''));
    if (!in_array($role, ['admin', 'kajur', 'musyrif', 'supervisor', "mas'ul", ...academic_student_roles()], true)) {
        json_response(['success' => false, 'error' => 'Akun ini tidak memiliki akses ke modul akademik.'], 403);
    }
    return $user;
}

function require_academic_admin(array $user): void
{
    if (strtolower((string) ($user['role'] ?? '')) !== 'admin') {
        json_response(['success' => false, 'error' => 'Hanya admin yang dapat mengelola dokumen akademik.'], 403);
    }
}

function validate_academic_document_type(mixed $value): string
{
    $type = is_string($value) ? $value : '';
    if (!in_array($type, ['khs', 'skripsi', 'jurnal'], true)) {
        json_response(['success' => false, 'error' => 'Jenis dokumen tidak valid.'], 400);
    }
    return $type;
}

function sanitize_academic_file_name(string $fileName): string
{
    $baseName = basename(str_replace('\\', '/', $fileName));
    $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $baseName);
    $safeName = is_string($safeName) ? trim($safeName, " .\t\n\r\0\x0B") : '';
    if ($safeName === '') {
        return 'dokumen.pdf';
    }
    return substr($safeName, 0, 255);
}
