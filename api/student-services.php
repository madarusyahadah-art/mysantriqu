<?php
require_once __DIR__ . '/config.php';

$user = require_role(['admin', 'mahasantri']);
$isAdmin = $user['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($isAdmin) {
        if (($_GET['admin'] ?? '') !== '1') {
            json_response(["success" => false, "error" => "Mode pengelolaan admin tidak valid"], 400);
        }

        $studentsStmt = $conn->prepare("SELECT id, nama, no_ktm, angkatan, semester_aktif FROM santri ORDER BY nama ASC");
        if (!$studentsStmt || !$studentsStmt->execute()) {
            if ($studentsStmt) $studentsStmt->close();
            json_response(["success" => false, "error" => "Daftar Mahasantri gagal dimuat"], 500);
        }
        $students = $studentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $studentsStmt->close();

        $santriId = isset($_GET['santri_id']) ? filter_var($_GET['santri_id'], FILTER_VALIDATE_INT) : null;
        if ($santriId === null) {
            json_response(["success" => true, "students" => $students]);
        }
        if ($santriId === false || $santriId < 1) {
            json_response(["success" => false, "error" => "ID Mahasantri tidak valid"], 400);
        }

        $studentStmt = $conn->prepare("SELECT id FROM santri WHERE id = ?");
        $studentStmt->bind_param("i", $santriId);
        if (!$studentStmt->execute()) {
            $studentStmt->close();
            json_response(["success" => false, "error" => "Data Mahasantri gagal diperiksa"], 500);
        }
        $studentExists = $studentStmt->get_result()->num_rows > 0;
        $studentStmt->close();
        if (!$studentExists) {
            json_response(["success" => false, "error" => "Data Mahasantri tidak ditemukan"], 404);
        }

        $thesisStmt = $conn->prepare("
            SELECT id, santri_id, title, original_filename, status, admin_note, submitted_at, reviewed_at
            FROM student_thesis_submissions WHERE santri_id = ? ORDER BY submitted_at DESC
        ");
        $thesisStmt->bind_param("i", $santriId);
        if (!$thesisStmt->execute()) {
            $thesisStmt->close();
            json_response(["success" => false, "error" => "Daftar pengumpulan skripsi gagal dimuat"], 500);
        }
        $thesis = $thesisStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $thesisStmt->close();

        $khsStmt = $conn->prepare("
            SELECT id, santri_id, semester, original_filename, uploaded_at
            FROM student_khs_documents WHERE santri_id = ? ORDER BY semester DESC
        ");
        $khsStmt->bind_param("i", $santriId);
        if (!$khsStmt->execute()) {
            $khsStmt->close();
            json_response(["success" => false, "error" => "Daftar KHS gagal dimuat"], 500);
        }
        $khs = $khsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $khsStmt->close();
        json_response(["success" => true, "students" => $students, "thesis" => $thesis, "khs" => $khs]);
    }

    if (empty($user['santri_id'])) {
        json_response(["success" => false, "error" => "Profil Mahasantri belum dihubungkan oleh admin"], 403);
    }
    $santriId = (int) $user['santri_id'];

    $journalStmt = $conn->prepare("
        SELECT id, entry_date, title, content, created_at
        FROM student_journal_entries WHERE santri_id = ? ORDER BY entry_date DESC, id DESC
    ");
    $journalStmt->bind_param("i", $santriId);
    if (!$journalStmt->execute()) {
        $journalStmt->close();
        json_response(["success" => false, "error" => "Jurnal gagal dimuat"], 500);
    }
    $journals = $journalStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $journalStmt->close();

    $thesisStmt = $conn->prepare("
        SELECT id, title, original_filename, status, admin_note, submitted_at, reviewed_at
        FROM student_thesis_submissions WHERE santri_id = ? ORDER BY submitted_at DESC
    ");
    $thesisStmt->bind_param("i", $santriId);
    if (!$thesisStmt->execute()) {
        $thesisStmt->close();
        json_response(["success" => false, "error" => "Pengumpulan skripsi gagal dimuat"], 500);
    }
    $thesis = $thesisStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $thesisStmt->close();

    $khsStmt = $conn->prepare("
        SELECT id, semester, original_filename, uploaded_at
        FROM student_khs_documents WHERE santri_id = ? ORDER BY semester DESC
    ");
    $khsStmt->bind_param("i", $santriId);
    if (!$khsStmt->execute()) {
        $khsStmt->close();
        json_response(["success" => false, "error" => "KHS gagal dimuat"], 500);
    }
    $khs = $khsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $khsStmt->close();
    json_response(["success" => true, "journals" => $journals, "thesis" => $thesis, "khs" => $khs]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data tidak valid"], 400);
}

if ($isAdmin) {
    if (($data['action'] ?? '') !== 'review-thesis') {
        json_response(["success" => false, "error" => "Aksi admin tidak valid"], 400);
    }
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    $status = $data['status'] ?? '';
    $note = $data['admin_note'] ?? '';
    if ($id === false || $id < 1 || !in_array($status, ['diterima', 'perlu_revisi'], true) ||
        !is_string($note) || mb_strlen($note) > 5000) {
        json_response(["success" => false, "error" => "Status atau catatan pemeriksaan tidak valid"], 400);
    }

    $stmt = $conn->prepare("
        UPDATE student_thesis_submissions
        SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->bind_param("ssii", $status, $note, $user['id'], $id);
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(["success" => false, "error" => "Status pengumpulan gagal diperbarui"], 500);
    }
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    if ($affectedRows === 0) {
        $existsStmt = $conn->prepare("SELECT id FROM student_thesis_submissions WHERE id = ?");
        $existsStmt->bind_param("i", $id);
        $existsStmt->execute();
        $exists = $existsStmt->get_result()->num_rows > 0;
        $existsStmt->close();
        if (!$exists) json_response(["success" => false, "error" => "Pengumpulan skripsi tidak ditemukan"], 404);
    }
    json_response(["success" => true]);
}

if (empty($user['santri_id'])) {
    json_response(["success" => false, "error" => "Profil Mahasantri belum dihubungkan oleh admin"], 403);
}
if (($data['action'] ?? '') !== 'create-journal') {
    json_response(["success" => false, "error" => "Aksi Mahasantri tidak valid"], 400);
}

$date = $data['entry_date'] ?? '';
$title = trim((string) ($data['title'] ?? ''));
$content = trim((string) ($data['content'] ?? ''));
$parsedDate = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date ||
    $title === '' || mb_strlen($title) > 255 || $content === '' || mb_strlen($content) > 10000) {
    json_response(["success" => false, "error" => "Tanggal, judul, atau isi jurnal tidak valid"], 400);
}

$stmt = $conn->prepare("INSERT INTO student_journal_entries (santri_id, entry_date, title, content) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $user['santri_id'], $date, $title, $content);
if (!$stmt->execute()) {
    $stmt->close();
    json_response(["success" => false, "error" => "Jurnal gagal disimpan"], 500);
}
$id = $stmt->insert_id;
$stmt->close();
json_response(["success" => true, "id" => $id]);
?>
