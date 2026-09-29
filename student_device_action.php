<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

session_start();

require_once __DIR__ . '/../config/config.php';

$pdo = db();

/* =========================================================
   حماية الأدمن
========================================================= */

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

/* =========================================================
   رقم الطالب
========================================================= */

$studentId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


/* =========================================================
   التحقق من رقم الطالب
========================================================= */

if ($studentId <= 0) {
    header('Location: students.php');
    exit;
}


/* =========================================================
   إلغاء ربط الجهاز
========================================================= */

try {

    $stmt = $pdo->prepare("
        UPDATE students
        SET
            device_token = NULL,
            device_bound_at = NULL
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $studentId
    ]);


    /* =====================================================
       العودة إلى صفحة الطلاب
    ===================================================== */

    header(
        'Location: students.php?success=device_reset'
    );

    exit;


} catch (PDOException $e) {

    /* =====================================================
       في حالة حدوث خطأ
    ===================================================== */

    header(
        'Location: students.php?success=device_error'
    );

    exit;
}

