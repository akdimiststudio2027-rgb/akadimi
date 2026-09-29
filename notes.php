<?php

session_start();

require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
        require_once __DIR__ . '/student_auth_check.php';

}
$pdo = db();

$studentId = (int) $_SESSION['student_id'];


/* =========================================================
   فحص ربط الجهاز
========================================================= */

$deviceToken = $_COOKIE['akadimi_device_token'] ?? '';

if ($deviceToken === '') {

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: login.php?device_unlinked=1');
    exit;
}


$stmt = $pdo->prepare("
    SELECT device_token
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$studentId]);

$currentDeviceToken = $stmt->fetchColumn();


if (
    empty($currentDeviceToken) ||
    !hash_equals(
        (string) $currentDeviceToken,
        (string) $deviceToken
    )
) {

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    setcookie(
        'akadimi_device_token',
        '',
        time() - 3600,
        '/',
        '',
        false,
        true
    );

    header('Location: login.php?device_unlinked=1');
    exit;
}


/* =========================================================
   بيانات الطالب
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone,
        governorate_id,
        stage_id,
        curriculum_id,
        active
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$studentId]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (isset($student['active']) && (int)$student['active'] !== 1) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$pdo = db();

$studentId = (int) $_SESSION['student_id'];


/* =========================================================
   بيانات الطالب
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone,
        governorate_id,
        stage_id,
        curriculum_id,
        active
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$studentId]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (isset($student['active']) && (int)$student['active'] !== 1) {
    session_destroy();
    header('Location: login.php');
    exit;
}


$student_id = (int) $_SESSION['student_id'];


/* =====================================================
   إنشاء جدول المفكرة إذا لم يكن موجوداً
===================================================== */

try {

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

} catch (PDOException $e) {
}


/* =====================================================
   إضافة ملاحظة
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {

        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($title !== '' && $content !== '') {

            try {

                $stmt = $pdo->prepare("
                    INSERT INTO student_notes
                    (
                        student_id,
                        title,
                        content
                    )
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $student_id,
                    $title,
                    $content
                ]);

            } catch (PDOException $e) {
            }
        }

        header('Location: notes.php');
        exit;
    }


    if ($action === 'delete') {

        $note_id = (int)($_POST['note_id'] ?? 0);

        if ($note_id > 0) {

            try {

                $stmt = $pdo->prepare("
                    DELETE FROM student_notes
                    WHERE id = ?
                      AND student_id = ?
                ");

                $stmt->execute([
                    $note_id,
                    $student_id
                ]);

            } catch (PDOException $e) {
            }
        }

        header('Location: notes.php');
        exit;
    }
}


/* =====================================================
   جلب الملاحظات
===================================================== */

$notes = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            content,
            created_at
        FROM student_notes
        WHERE student_id = ?
        ORDER BY id DESC
    ");

    $stmt->execute([
        $student_id
    ]);

    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $notes = [];
}

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>مفكرتي | منصة أكاديمي</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
}

body {

    font-family:
        Tahoma,
        Arial,
        sans-serif;

    background: #f5f8fc;

    color: #172033;

    min-height: 100vh;

    padding-bottom: 90px;
}

.container {

    width: 100%;

    max-width: 760px;

    margin: auto;

    padding: 18px 16px;
}

.header {

    display: flex;

    align-items: center;

    gap: 14px;

    margin-bottom: 20px;
}

.back-btn {

    width: 45px;

    height: 45px;

    border-radius: 14px;

    background: white;

    border: 1px solid #e5eaf0;

    display: flex;

    align-items: center;

    justify-content: center;

    text-decoration: none;

    color: #345c49;

    font-size: 21px;
}

.header-title {

    font-size: 22px;

    font-weight: bold;
}

.header-subtitle {

    color: #8a98ab;

    font-size: 12px;

    margin-top: 5px;
}


/* =====================================================
   إضافة ملاحظة
===================================================== */

.add-card {

    background: white;

    border: 1px solid #e5eaf0;

    border-radius: 24px;

    padding: 18px;

    margin-bottom: 20px;

    box-shadow:
        0 8px 25px rgba(0,0,0,.04);
}

.add-title {

    font-size: 16px;

    font-weight: bold;

    color: #345c49;

    margin-bottom: 15px;
}

input,
textarea {

    width: 100%;

    border: 1px solid #dfe5ec;

    border-radius: 14px;

    padding: 12px 14px;

    font-family: inherit;

    font-size: 13px;

    outline: none;

    background: #fafbfd;

    margin-bottom: 10px;
}

input {

    height: 46px;
}

textarea {

    min-height: 110px;

    resize: vertical;
}

input:focus,
textarea:focus {

    border-color: #9bac78;

    background: white;
}

.add-btn {

    width: 100%;

    height: 48px;

    border: 0;

    border-radius: 14px;

    background: #345c49;

    color: white;

    font-family: inherit;

    font-weight: bold;

    cursor: pointer;
}


/* =====================================================
   الملاحظات
===================================================== */

.section-title {

    font-size: 17px;

    font-weight: bold;

    color: #345c49;

    margin-bottom: 12px;
}

.note {

    background: white;

    border: 1px solid #e5eaf0;

    border-radius: 20px;

    padding: 16px;

    margin-bottom: 12px;

    box-shadow:
        0 6px 20px rgba(0,0,0,.035);
}

.note-head {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 9px;
}

.note-title {

    font-size: 15px;

    font-weight: bold;

    color: #345c49;
}

.note-date {

    color: #9aa6b5;

    font-size: 9px;
}

.note-content {

    color: #526070;

    font-size: 12px;

    line-height: 1.9;

    white-space: pre-wrap;
}

.delete-btn {

    margin-top: 12px;

    border: 0;

    background: #fff1f1;

    color: #a33b3b;

    border-radius: 11px;

    padding: 8px 12px;

    font-family: inherit;

    font-size: 10px;

    cursor: pointer;
}

.empty {

    background: white;

    border: 1px dashed #d7dfe8;

    border-radius: 20px;

    padding: 40px 20px;

    text-align: center;

    color: #8a98ab;

    font-size: 13px;
}

.empty-icon {

    font-size: 35px;

    margin-bottom: 10px;
}


/* =====================================================
   الشريط السفلي
===================================================== */

.bottom-nav {

    position: fixed;

    left: 0;

    right: 0;

    bottom: 0;

    height: 72px;

    background: rgba(255,255,255,.97);

    border-top: 1px solid #e5e9ee;

    display: flex;

    justify-content: center;

    z-index: 1000;
}

.bottom-inner {

    width: 100%;

    max-width: 600px;

    display: grid;

    grid-template-columns: repeat(5, 1fr);
}

.bottom-item {

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    gap: 4px;

    text-decoration: none;

    color: #8a98ab;

    font-size: 9px;
}

.bottom-item.active {

    color: #345c49;
}

.bottom-icon {

    font-size: 19px;
}

</style>

</head>

<body>

<div class="container">


    <header class="header">

        <a
            href="index.php"
            class="back-btn"
        >
            ←
        </a>

        <div>

            <div class="header-title">
                مفكرتي
            </div>

            <div class="header-subtitle">
                سجّل ملاحظاتك الدراسية المهمة
            </div>

        </div>

    </header>


    <section class="add-card">

        <div class="add-title">
            ✏️ إضافة ملاحظة جديدة
        </div>

        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="add"
            >

            <input
                type="text"
                name="title"
                placeholder="عنوان الملاحظة"
                required
            >

            <textarea
                name="content"
                placeholder="اكتب ملاحظتك هنا..."
                required
            ></textarea>

            <button
                type="submit"
                class="add-btn"
            >
                ➕ حفظ الملاحظة
            </button>

        </form>

    </section>


    <div class="section-title">
        ملاحظاتي
    </div>


    <?php if (empty($notes)): ?>

        <div class="empty">

            <div class="empty-icon">
                📝
            </div>

            لا توجد ملاحظات حتى الآن.

        </div>

    <?php else: ?>


        <?php foreach ($notes as $note): ?>

            <div class="note">

                <div class="note-head">

                    <div class="note-title">

                        <?= e($note['title']) ?>

                    </div>

                    <div class="note-date">

                        <?= e(
                            date(
                                'Y/m/d',
                                strtotime($note['created_at'])
                            )
                        ) ?>

                    </div>

                </div>


                <div class="note-content">

                    <?= e($note['content']) ?>

                </div>


                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="delete"
                    >

                    <input
                        type="hidden"
                        name="note_id"
                        value="<?= (int)$note['id'] ?>"
                    >

                    <button
                        type="submit"
                        class="delete-btn"
                        onclick="return confirm('هل تريد حذف هذه الملاحظة؟');"
                    >
                        🗑 حذف الملاحظة
                    </button>

                </form>

            </div>

        <?php endforeach; ?>


    <?php endif; ?>


</div>


<nav class="bottom-nav">

    <div class="bottom-inner">

        <a href="index.php" class="bottom-item">

            <div class="bottom-icon">🏠</div>

            الرئيسية

        </a>


        <a href="notes.php" class="bottom-item active">

            <div class="bottom-icon">📝</div>

            مفكرتي

        </a>


        <a href="settings.php" class="bottom-item">

            <div class="bottom-icon">⚙️</div>

            الإعدادات

        </a>


        <a href="support.php" class="bottom-item">

            <div class="bottom-icon">💬</div>

            الدعم الفني

        </a>


        <a href="profile.php" class="bottom-item">

            <div class="bottom-icon">👤</div>

            حسابي

        </a>

    </div>

</nav>

</body>

</html>