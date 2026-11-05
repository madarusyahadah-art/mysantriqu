<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/academic-documents-helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

$user = require_academic_user();
$documentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($documentId === false || $documentId === null || $documentId < 1) {
    json_response(['success' => false, 'error' => 'ID dokumen tidak valid.'], 400);
}

try {
    $connection = db_connection();
    $statement = $connection->prepare(
        'SELECT id, document_type, student_id, storage_name
         FROM academic_documents
         WHERE id = ?
         LIMIT 1'
    );
    $statement->bind_param('i', $documentId);
    $statement->execute();
    $document = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$document) {
        json_response(['success' => false, 'error' => 'Dokumen tidak ditemukan.'], 404);
    }

    $role = strtolower((string) $user['role']);
    if ($role !== 'admin') {
        if ($document['document_type'] === 'khs') {
            if (!in_array($role, academic_student_roles(), true)) {
                json_response(['success' => false, 'error' => 'KHS hanya tersedia untuk admin dan santri pemilik akun.'], 403);
            }
            $studentId = filter_var($user['santri_id'] ?? null, FILTER_VALIDATE_INT);
            if ($studentId === false || $studentId === null || $studentId < 1) {
                json_response(['success' => false, 'error' => 'Akun Anda belum dihubungkan dengan data santri. Hubungi admin.'], 403);
            }
            if ((int) $document['student_id'] !== $studentId) {
                json_response(['success' => false, 'error' => 'Anda tidak memiliki akses ke KHS tersebut.'], 403);
            }
        } elseif (!in_array($document['document_type'], ['skripsi', 'jurnal'], true)) {
            json_response(['success' => false, 'error' => 'Anda tidak memiliki akses ke dokumen tersebut.'], 403);
        }
    }

    if (!preg_match('/^[a-f0-9]{64}\.pdf$/', (string) $document['storage_name'])) {
        error_log('Academic document has an invalid private storage name, document id: ' . $documentId);
        json_response(['success' => false, 'error' => 'File dokumen tidak dapat dibuka. Hubungi admin server.'], 500);
    }
    $filePath = academic_document_storage_dir() . DIRECTORY_SEPARATOR . $document['storage_name'];
    if (!is_file($filePath) || !is_readable($filePath)) {
        error_log('Academic document file is missing or unreadable, document id: ' . $documentId);
        json_response(['success' => false, 'error' => 'File PDF tidak ditemukan di penyimpanan. Hubungi admin.'], 404);
    }

    header('Content-Type: application/pdf');
    header('Content-Length: ' . (string) filesize($filePath));
    header('Content-Disposition: inline; filename="dokumen-akademik.pdf"');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    readfile($filePath);
    exit;
} catch (mysqli_sql_exception $error) {
    error_log('Academic document file lookup failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Dokumen gagal dibuka. Terapkan migrasi database, lalu coba lagi.'], 500);
} catch (RuntimeException $error) {
    error_log('Academic document file storage unavailable: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Penyimpanan dokumen belum tersedia. Periksa konfigurasi server.'], 503);
}
