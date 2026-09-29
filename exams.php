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


/*
|--------------------------------------------------------------------------
| دالة حماية النصوص
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/*
|--------------------------------------------------------------------------
| بيانات الطالب
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
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

/*
|--------------------------------------------------------------------------
| اسم المرحلة
|--------------------------------------------------------------------------
*/

$stageName = '';

if (!empty($student['stage_id'])) {

    $stmt = $pdo->prepare("
        SELECT name
        FROM stages
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        (int)$student['stage_id']
    ]);

    $stageName = $stmt->fetchColumn() ?: '';
}

/*
|--------------------------------------------------------------------------
| اسم المنهج
|--------------------------------------------------------------------------
*/

$curriculumName = '';

if (!empty($student['curriculum_id'])) {

    $stmt = $pdo->prepare("
        SELECT name
        FROM curricula
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        (int)$student['curriculum_id']
    ]);

    $curriculumName = $stmt->fetchColumn() ?: '';
}

/*
|--------------------------------------------------------------------------
| جلب الامتحانات
|
| نعتمد فقط على:
| exams.id
| exams.course_id
| exams.title
| exams.total_marks
|
| ولا نستخدم questions_json في هذه الصفحة.
|--------------------------------------------------------------------------
*/

$exams = [];

try {

    $stmt = $pdo->query("
        SELECT
            e.id,
            e.course_id,
            e.title,
            e.total_marks,

            c.title AS course_title,

            t.name AS teacher_name,

            sub.name AS subject_name

        FROM exams e

        LEFT JOIN courses c
            ON c.id = e.course_id

        LEFT JOIN teachers t
            ON t.id = c.teacher_id

        LEFT JOIN subjects sub
            ON sub.id = c.subject_id

        ORDER BY e.id DESC
    ");

    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $exams = [];
}

/*
|--------------------------------------------------------------------------
| اسم الطالب والحرف الأول
|--------------------------------------------------------------------------
*/

$studentName = trim($student['name'] ?? '');

$studentInitial = '';

if ($studentName !== '') {

    $studentInitial = mb_substr(
        $studentName,
        0,
        1,
        'UTF-8'
    );
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

<title>
    الاختبارات | منصة أكاديمي
</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {

    --primary: #345c49;
    --green: #9bac78;
    --cream: #ede7d2;
    --light: #e8e8e8;
    --white: #fff;
    --text: #24362d;
    --muted: #718078;
    --border: rgba(52,92,73,.12);

}

body {

    font-family:
        Tahoma,
        Arial,
        sans-serif;

    background:
        linear-gradient(
            180deg,
            #ede7d2 0%,
            #e8e8e8 38%,
            #f4f4f2 100%
        );

    color: var(--text);

    min-height: 100vh;

    padding-bottom: 105px;

}

a {

    text-decoration: none;

    color: inherit;

}

.page {

    width: 100%;

    max-width: 760px;

    margin: auto;

    padding: 15px;

}

/* =========================================================
   HEADER
========================================================= */

.top-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    margin-bottom: 20px;

}

.student-box {

    display: flex;

    align-items: center;

    gap: 11px;

    min-width: 0;

}

.student-avatar {

    width: 54px;

    height: 54px;

    border-radius: 18px;

    background: var(--primary);

    color: #fff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 22px;

    font-weight: bold;

    flex-shrink: 0;

    box-shadow:
        0 7px 20px rgba(52,92,73,.20);

}

.student-info {

    min-width: 0;

}

.welcome {

    font-size: 12px;

    color: var(--muted);

    margin-bottom: 3px;

}

.student-name {

    font-size: 17px;

    font-weight: bold;

    color: var(--primary);

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

    max-width: 200px;

}

.student-stage {

    font-size: 12px;

    color: var(--muted);

    margin-top: 4px;

}

.back-btn {

    width: 43px;

    height: 43px;

    border-radius: 15px;

    background: rgba(255,255,255,.72);

    border: 1px solid var(--border);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 19px;

}

/* =========================================================
   TITLE
========================================================= */

.page-title {

    background: var(--primary);

    color: #fff;

    border-radius: 25px;

    padding: 22px;

    margin-bottom: 20px;

    position: relative;

    overflow: hidden;

    box-shadow:
        0 12px 30px rgba(52,92,73,.18);

}

.page-title::after {

    content: "";

    position: absolute;

    width: 130px;

    height: 130px;

    border-radius: 50%;

    background:
        rgba(155,172,120,.15);

    left: -45px;

    bottom: -55px;

}

.page-title h1 {

    font-size: 21px;

    position: relative;

    z-index: 1;

}

.page-title p {

    font-size: 12px;

    margin-top: 7px;

    color: rgba(255,255,255,.75);

    line-height: 1.7;

    position: relative;

    z-index: 1;

}

.curriculum {

    display: inline-flex;

    margin-top: 12px;

    background: rgba(237,231,210,.14);

    color: var(--cream);

    padding: 7px 11px;

    border-radius: 11px;

    font-size: 11px;

    position: relative;

    z-index: 1;

}

/* =========================================================
   SECTION
========================================================= */

.section-head {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin: 24px 2px 12px;

}

.section-title {

    font-size: 17px;

    font-weight: bold;

    color: var(--primary);

}

/* =========================================================
   EXAMS
========================================================= */

.exam-list {

    display: flex;

    flex-direction: column;

    gap: 13px;

}

.exam-card {

    background:
        rgba(255,255,255,.84);

    border:
        1px solid var(--border);

    border-radius: 21px;

    padding: 16px;

    box-shadow:
        0 8px 22px rgba(52,92,73,.07);

    transition: .2s;

}

.exam-card:hover {

    transform: translateY(-2px);

}

.exam-top {

    display: flex;

    align-items: center;

    gap: 13px;

}

.exam-icon {

    width: 52px;

    height: 52px;

    border-radius: 17px;

    background: var(--cream);

    color: var(--primary);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;

    flex-shrink: 0;

}

.exam-content {

    min-width: 0;

    flex: 1;

}

.exam-title {

    font-size: 15px;

    font-weight: bold;

    color: var(--primary);

    line-height: 1.6;

}

.exam-course {

    font-size: 11px;

    color: var(--muted);

    margin-top: 4px;

}

.exam-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 7px;

    margin-top: 14px;

}

.exam-badge {

    background: #f0f3ed;

    color: var(--muted);

    padding: 7px 10px;

    border-radius: 10px;

    font-size: 10px;

}

.exam-badge strong {

    color: var(--primary);

}

.exam-teacher {

    margin-top: 10px;

    font-size: 11px;

    color: var(--muted);

}

.start-btn {

    display: block;

    margin-top: 14px;

    background: var(--primary);

    color: #fff;

    text-align: center;

    padding: 11px;

    border-radius: 13px;

    font-size: 12px;

    font-weight: bold;

}

/* =========================================================
   EMPTY
========================================================= */

.empty-box {

    background:
        rgba(255,255,255,.70);

    border:
        1px dashed rgba(52,92,73,.20);

    border-radius: 21px;

    padding: 35px 18px;

    text-align: center;

    color: var(--muted);

}

.empty-icon {

    font-size: 40px;

    margin-bottom: 10px;

}

.empty-title {

    color: var(--primary);

    font-weight: bold;

    font-size: 15px;

    margin-bottom: 6px;

}

.empty-text {

    font-size: 12px;

    line-height: 1.8;

}

/* =========================================================
   BOTTOM NAV
========================================================= */

.bottom-nav-wrap {

    position: fixed;

    bottom: 12px;

    left: 0;

    right: 0;

    z-index: 100;

    padding: 0 12px;

}

.bottom-nav {

    max-width: 560px;

    margin: auto;

    min-height: 72px;

    border-radius: 25px;

    background:
        rgba(255,255,255,.80);

    border:
        1px solid rgba(52,92,73,.13);

    box-shadow:
        0 14px 35px rgba(52,92,73,.16);

    backdrop-filter: blur(18px);

    -webkit-backdrop-filter: blur(18px);

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    align-items: center;

    padding: 7px;

}

.nav-item {

    height: 58px;

    border-radius: 19px;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    gap: 4px;

    color: var(--muted);

    font-size: 10px;

}

.nav-icon {

    font-size: 19px;

}

.nav-item.active {

    background: var(--primary);

    color: #fff;

}

@media (max-width: 420px) {

    .page {

        padding: 12px;

    }

    .student-name {

        max-width: 150px;

    }

}

</style>

</head>

<body>

<div class="page">

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="top-header">

        <div class="student-box">

            <div class="student-avatar">

                <?= e($studentInitial) ?>

            </div>

            <div class="student-info">

                <div class="welcome">
                    أهلاً بك 👋
                </div>

                <div class="student-name">
                    <?= e($student['name']) ?>
                </div>

                <div class="student-stage">

                    <?= e($stageName ?: 'الطالب') ?>

                </div>

            </div>

        </div>

        <a
            href="index.php"
            class="back-btn"
            aria-label="الرئيسية"
        >
            🏠
        </a>

    </header>


    <!-- =====================================================
         TITLE
    ====================================================== -->

    <section class="page-title">

        <h1>
            📝 الاختبارات
        </h1>

        <p>
            هنا تظهر الاختبارات المضافة من لوحة الإدارة
            والدورات المرتبطة بها.
        </p>

        <?php if ($curriculumName): ?>

            <div class="curriculum">

                📚

                <?= e($curriculumName) ?>

            </div>

        <?php endif; ?>

    </section>


    <!-- =====================================================
         EXAMS
    ====================================================== -->

    <div class="section-head">

        <div class="section-title">
            الاختبارات المتاحة
        </div>

    </div>


    <div class="exam-list">

        <?php if (!empty($exams)): ?>

            <?php foreach ($exams as $exam): ?>

                <div class="exam-card">

                    <div class="exam-top">

                        <div class="exam-icon">
                            📝
                        </div>

                        <div class="exam-content">

                            <div class="exam-title">

                                <?= e(
                                    $exam['title']
                                    ?: 'اختبار بدون عنوان'
                                ) ?>

                            </div>

                            <div class="exam-course">

                                <?= e(
                                    $exam['course_title']
                                    ?: 'اختبار المنصة'
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <div class="exam-meta">

                        <?php if (!empty($exam['subject_name'])): ?>

                            <div class="exam-badge">

                                المادة:

                                <strong>
                                    <?= e($exam['subject_name']) ?>
                                </strong>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty($exam['total_marks'])): ?>

                            <div class="exam-badge">

                                الدرجة الكاملة:

                                <strong>
                                    <?= e($exam['total_marks']) ?>
                                </strong>

                            </div>

                        <?php endif; ?>

                    </div>


                    <?php if (!empty($exam['teacher_name'])): ?>

                        <div class="exam-teacher">

                            👨‍🏫

                            الأستاذ:

                            <?= e($exam['teacher_name']) ?>

                        </div>

                    <?php endif; ?>


                    <a
                        href="exam.php?id=<?= (int)$exam['id'] ?>"
                        class="start-btn"
                    >

                        ▶ بدء الاختبار

                    </a>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty-box">

                <div class="empty-icon">
                    📝
                </div>

                <div class="empty-title">
                    لا توجد اختبارات حالياً
                </div>

                <div class="empty-text">

                    عندما تتم إضافة اختبار من لوحة الإدارة
                    سيظهر هنا تلقائياً.

                </div>

            </div>

        <?php endif; ?>

    </div>

</div>


<!-- =========================================================
     BOTTOM NAV
========================================================= -->

<div class="bottom-nav-wrap">

    <nav class="bottom-nav">

        <a
            href="index.php"
            class="nav-item"
        >

            <div class="nav-icon">
                🏠
            </div>

            <div>
                الرئيسية
            </div>

        </a>


        <a
            href="notes.php"
            class="nav-item"
        >

            <div class="nav-icon">
                📝
            </div>

            <div>
                مفكرتي
            </div>

        </a>


        <a
            href="exams.php"
            class="nav-item active"
        >

            <div class="nav-icon">
                🎯
            </div>

            <div>
                الاختبارات
            </div>

        </a>


        <a
            href="support.php"
            class="nav-item"
        >

            <div class="nav-icon">
                💬
            </div>

            <div>
                الدعم
            </div>

        </a>


        <a
            href="profile.php"
            class="nav-item"
        >

            <div class="nav-icon">
                👤
            </div>

            <div>
                حسابي
            </div>

        </a>

    </nav>

</div>

</body>

</html>
