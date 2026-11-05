<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/academic-documents-helpers.php';

$user = require_academic_user();
$role = strtolower((string) $user['role']);
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$type = validate_academic_document_type($_GET['type'] ?? $_POST['type'] ?? '');

if ($method === 'GET') {
    try {
        $connection = db_connection();
        if ($type === 'khs' && $role !== 'admin') {
            $studentId = filter_var($user['santri_id'] ?? null, FILTER_VALIDATE_INT);
            if ($studentId === false || $studentId === null || $studentId < 1) {
                json_response(['success' => false, 'error' => 'Akun Anda belum dihubungkan dengan data santri. Hubungi admin.'], 403);
            }
            $statement = $connection->prepare(
                'SELECT d.id, d.document_type, d.title, d.academic_period, d.original_file_name,
                        d.file_size, d.created_at, s.nama AS student_name
                 FROM academic_documents d
                 INNER JOIN santri s ON s.id = d.student_id
                 WHERE d.document_type = ? AND d.student_id = ?
                 ORDER BY d.academic_period DESC, d.created_at DESC, d.id DESC'
            );
            $statement->bind_param('si', $type, $studentId);
        } elseif ($type === 'khs') {
            $statement = $connection->prepare(
                'SELECT d.id, d.document_type, d.title, d.academic_period, d.original_file_name,
                        d.file_size, d.created_at, s.nama AS student_name, d.student_id
                 FROM academic_documents d
                 INNER JOIN santri s ON s.id = d.student_id
                 WHERE d.document_type = ?
                 ORDER BY s.nama ASC, d.academic_period DESC, d.created_at DESC'
            );
            $statement->bind_param('s', $type);
        } else {
            $statement = $connection->prepare(
                'SELECT id, document_type, title, author_name, author_status, publication_year,
                        description, original_file_name, file_size, created_at
                 FROM academic_documents
                 WHERE document_type = ?
                 ORDER BY publication_year DESC, title ASC, id DESC'
            );
            $statement->bind_param('s', $type);
        }
        $statement->execute();
        $documents = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();
        foreach ($documents as &$document) {
            $document['id'] = (int) $document['id'];
            $document['file_size'] = (int) $document['file_size'];
            if (isset($document['student_id'])) {
                $document['student_id'] = (int) $document['student_id'];
            }
            if (isset($document['publication_year'])) {
                $document['publication_year'] = $document['publication_year'] === null
                    ? null
                    : (int) $document['publication_year'];
            }
        }
        unset($document);
        $response = ['success' => true, 'documents' => $documents];
        if ($type === 'khs' && $role === 'admin') {
            $studentResult = $connection->query('SELECT id, nama, angkatan FROM santri ORDER BY nama ASC, id ASC');
            $response['santri'] = $studentResult->fetch_all(MYSQLI_ASSOC);
            foreach ($response['santri'] as &$student) {
                $student['id'] = (int) $student['id'];
            }
            unset($student);
        }
        json_response($response);
    } catch (mysqli_sql_exception $error) {
        error_log('Academic document list failed: ' . $error->getMessage());
        json_response(['success' => false, 'error' => 'Daftar dokumen akademik gagal dimuat. Terapkan migrasi database, lalu coba lagi.'], 500);
    } catch (RuntimeException $error) {
        error_log('Academic document database unavailable: ' . $error->getMessage());
        json_response(['success' => false, 'error' => 'Database belum tersedia. Pastikan MySQL aktif.'], 503);
    }
}

if ($method === 'POST') {
    require_academic_admin($user);
    $name = trim((string) ($_POST['title'] ?? ''));
    $author = trim((string) ($_POST['author_name'] ?? ''));
    $authorStatus = trim((string) ($_POST['author_status'] ?? ''));
    $period = trim((string) ($_POST['academic_period'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $studentId = null;
    $publicationYear = null;

    if ($name === '' || strlen($name) > 255) {
        json_response(['success' => false, 'error' => 'Judul dokumen wajib diisi dan maksimal 255 karakter.'], 400);
    }
    if ($type === 'khs') {
        $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
        if ($studentId === false || $studentId === null || $studentId < 1
            || $period === '' || strlen($period) > 50) {
            json_response(['success' => false, 'error' => 'Pilih santri dan isi periode KHS dengan benar.'], 400);
        }
    } else {
        $publicationYear = filter_var($_POST['publication_year'] ?? null, FILTER_VALIDATE_INT);
        if ($author === '' || strlen($author) > 255
            || !in_array($authorStatus, ['aktif', 'alumni'], true)
            || $publicationYear === false || $publicationYear === null
            || $publicationYear < 1900 || $publicationYear > (int) date('Y') + 1
            || strlen($description) > 5000) {
            json_response(['success' => false, 'error' => 'Nama dan status penulis, tahun terbit, dan deskripsi harus valid.'], 400);
        }
    }

    $upload = $_FILES['pdf'] ?? null;
    if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_response(['success' => false, 'error' => 'Pilih file PDF yang valid untuk diunggah.'], 400);
    }
    $maxFileSize = 20 * 1024 * 1024;
    if ((int) ($upload['size'] ?? 0) < 1 || (int) $upload['size'] > $maxFileSize) {
        json_response(['success' => false, 'error' => 'Ukuran PDF maksimal 20 MB.'], 413);
    }
    if (!is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        json_response(['success' => false, 'error' => 'File unggahan tidak valid.'], 400);
    }

    $temporaryPath = (string) $upload['tmp_name'];
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($temporaryPath);
    $fileHandle = fopen($temporaryPath, 'rb');
    $signature = $fileHandle === false ? false : fread($fileHandle, 5);
    if ($fileHandle !== false) {
        fclose($fileHandle);
    }
    if ($mimeType !== 'application/pdf' || $signature !== '%PDF-') {
        json_response(['success' => false, 'error' => 'File harus berupa PDF yang valid.'], 415);
    }

    try {
        $storageDirectory = ensure_academic_document_storage();
        $storageName = bin2hex(random_bytes(32)) . '.pdf';
        $destination = $storageDirectory . DIRECTORY_SEPARATOR . $storageName;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('Uploaded PDF could not be moved into private storage.');
        }
        chmod($destination, 0640);

        try {
            $connection = db_connection();
            $fileName = sanitize_academic_file_name((string) ($upload['name'] ?? 'dokumen.pdf'));
            if ($type === 'khs') {
                $studentCheck = $connection->prepare('SELECT id FROM santri WHERE id = ?');
                $studentCheck->bind_param('i', $studentId);
                $studentCheck->execute();
                $studentExists = $studentCheck->get_result()->num_rows > 0;
                $studentCheck->close();
                if (!$studentExists) {
                    unlink($destination);
                    json_response(['success' => false, 'error' => 'Data santri yang dipilih tidak ditemukan.'], 404);
                }

                $existingFile = null;
                $existingCheck = $connection->prepare(
                    'SELECT storage_name FROM academic_documents
                     WHERE document_type = ? AND student_id = ? AND academic_period = ?'
                );
                $existingCheck->bind_param('sis', $type, $studentId, $period);
                $existingCheck->execute();
                $existingRow = $existingCheck->get_result()->fetch_assoc();
                $existingCheck->close();
                if ($existingRow) {
                    $existingFile = $existingRow['storage_name'];
                    $replace = $connection->prepare(
                        'UPDATE academic_documents
                         SET title = ?, original_file_name = ?, storage_name = ?, file_size = ?,
                             uploaded_by = ?, created_at = CURRENT_TIMESTAMP
                         WHERE document_type = ? AND student_id = ? AND academic_period = ?'
                    );
                    $size = (int) $upload['size'];
                    $uploaderId = (int) $user['id'];
                    $replace->bind_param('sssiisis', $name, $fileName, $storageName, $size, $uploaderId, $type, $studentId, $period);
                    $replace->execute();
                    $replace->close();
                } else {
                    $insert = $connection->prepare(
                        'INSERT INTO academic_documents
                         (document_type, student_id, title, academic_period, original_file_name,
                          storage_name, file_size, uploaded_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $size = (int) $upload['size'];
                    $uploaderId = (int) $user['id'];
                    $insert->bind_param('sissssii', $type, $studentId, $name, $period, $fileName, $storageName, $size, $uploaderId);
                    $insert->execute();
                    $insert->close();
                }
                if ($existingFile !== null) {
                    $oldPath = $storageDirectory . DIRECTORY_SEPARATOR . $existingFile;
                    if (is_file($oldPath) && !unlink($oldPath)) {
                        error_log('Unable to remove replaced academic document file: ' . $oldPath);
                    }
                }
            } else {
                $insert = $connection->prepare(
                    'INSERT INTO academic_documents
                     (document_type, title, author_name, author_status, publication_year, description,
                      original_file_name, storage_name, file_size, uploaded_by)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $size = (int) $upload['size'];
                $uploaderId = (int) $user['id'];
                $insert->bind_param(
                    'ssssisssii',
                    $type,
                    $name,
                    $author,
                    $authorStatus,
                    $publicationYear,
                    $description,
                    $fileName,
                    $storageName,
                    $size,
                    $uploaderId
                );
                $insert->execute();
                $insert->close();
            }
            json_response(['success' => true]);
        } catch (Throwable $error) {
            if (is_file($destination) && !unlink($destination)) {
                error_log('Unable to remove unregistered academic document file: ' . $destination);
            }
            throw $error;
        }
    } catch (mysqli_sql_exception $error) {
        error_log('Academic document upload failed: ' . $error->getMessage());
        json_response(['success' => false, 'error' => 'Dokumen gagal disimpan. Pastikan migrasi database sudah diterapkan.'], 500);
    } catch (RuntimeException $error) {
        error_log('Academic document storage unavailable: ' . $error->getMessage());
        json_response(['success' => false, 'error' => 'Penyimpanan dokumen belum tersedia. Periksa konfigurasi dan izin folder server.'], 503);
    }
}

if ($method === 'DELETE') {
    require_academic_admin($user);
    $data = json_decode(file_get_contents('php://input'), true);
    $id = is_array($data) ? filter_var($data['id'] ?? null, FILTER_VALIDATE_INT) : false;
    if ($id === false || $id === null || $id < 1) {
        json_response(['success' => false, 'error' => 'ID dokumen tidak valid.'], 400);
    }
    try {
        $connection = db_connection();
        $lookup = $connection->prepare('SELECT storage_name FROM academic_documents WHERE id = ? AND document_type = ?');
        $lookup->bind_param('is', $id, $type);
        $lookup->execute();
        $document = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if (!$document) {
            json_response(['success' => false, 'error' => 'Dokumen tidak ditemukan.'], 404);
        }

        $delete = $connection->prepare('DELETE FROM academic_documents WHERE id = ? AND document_type = ?');
        $delete->bind_param('is', $id, $type);
        $delete->execute();
        $deleted = $delete->affected_rows;
        $delete->close();
        if ($deleted !== 1) {
            json_response(['success' => false, 'error' => 'Dokumen tidak dapat dihapus. Muat ulang halaman dan coba lagi.'], 409);
        }

        $filePath = academic_document_storage_dir() . DIRECTORY_SEPARATOR . $document['storage_name'];
        if (is_file($filePath) && !unlink($filePath)) {
            error_log('Unable to remove deleted academic document file: ' . $filePath);
            json_response(['success' => false, 'error' => 'Data dokumen telah dihapus, tetapi file tertinggal di penyimpanan privat. Hubungi admin server.'], 500);
        }
        json_response(['success' => true]);
    } catch (mysqli_sql_exception $error) {
        error_log('Academic document delete failed: ' . $error->getMessage());
        json_response(['success' => false, 'error' => 'Dokumen gagal dihapus. Silakan coba lagi.'], 500);
    } catch (RuntimeException $error) {
        error_log('Academic document storage unavailable: ' . $error->getMessage());
        json_response(['success' => false, 'error' => 'Penyimpanan dokumen belum tersedia. Periksa konfigurasi server.'], 503);
    }
}

header('Allow: GET, POST, DELETE');
json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
