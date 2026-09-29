<?php

session_start();

require_once __DIR__ . '/../config/config.php';

$pdo = db();

if (empty($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
        require_once __DIR__ . '/student_auth_check.php';

}

$student_id = (int) $_SESSION['student_id'];
$course_id  = (int) ($_GET['course_id'] ?? 0);

if ($course_id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        c.title,
        s.name AS subject_name,
        sub.starts_at,
        sub.expires_at
    FROM subscriptions sub

    INNER JOIN courses c
        ON c.id = sub.course_id

    INNER JOIN subjects s
        ON s.id = c.subject_id

    WHERE sub.student_id = ?
      AND sub.course_id = ?
      AND sub.status = 'active'

    ORDER BY sub.id DESC

    LIMIT 1
");

$stmt->execute([
    $student_id,
    $course_id
]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>تم الاشتراك</title>

<style>

body {
    margin: 0;
    background: #f5f8fc;
    font-family: Tahoma, Arial, sans-serif;
}

.box {
    max-width: 550px;
    margin: 70px auto;
    padding: 20px;
}

.card {
    background: white;
    border-radius: 22px;
    padding: 35px 25px;
    text-align: center;
    box-shadow: 0 10px 35px rgba(0,0,0,.07);
}

.icon {
    font-size: 65px;
    margin-bottom: 15px;
}

h1 {
    color: #078f83;
    margin-bottom: 10px;
}

.course {
    font-size: 19px;
    font-weight: bold;
    margin: 15px 0;
}

.info {
    color: #64748b;
    line-height: 2;
    font-size: 13px;
}

.btn {
    display: block;
    margin-top: 22px;
    padding: 14px;
    border-radius: 12px;
    background: #078f83;
    color: white;
    text-decoration: none;
    font-weight: bold;
}

</style>

</head>

<body>

<div class="box">

    <div class="card">

        <div class="icon">
            ✅
        </div>

        <h1>
            تم تفعيل اشتراكك
        </h1>

        <div class="course">
            <?= e($data['title']) ?>
        </div>

        <div class="info">

            📚 المادة:
            <?= e($data['subject_name']) ?>

            <br>

            📅 يبدأ الاشتراك:
            <?= e($data['starts_at']) ?>

            <br>

            ⏳ ينتهي الاشتراك:
            <?= e($data['expires_at']) ?>

        </div>

        <a
            href="lessons.php?course_id=<?= $course_id ?>"
            class="btn"
        >
            🎥 الدخول إلى المحاضرات
        </a>

        <a
            href="index.php"
            class="btn"
            style="background:#eef7f6;color:#078f83;"
        >
            🏠 الصفحة الرئيسية
        </a>

    </div>

</div>

</body>

</html>