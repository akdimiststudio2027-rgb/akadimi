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


/* =========================
   بيانات الطالب
========================= */

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

if (!$student || (int)$student['active'] !== 1) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/* =========================
   دورات الطالب
========================= */

$courses = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            sub.id AS subscription_id,
            sub.course_id,
            sub.starts_at,
            sub.expires_at,
            sub.status,

            c.title AS course_title,
            c.description AS course_description,
            c.price,
            c.duration_days,

            t.id AS teacher_id,
            t.name AS teacher_name,
            t.image AS teacher_image,

            s.id AS subject_id,
            s.name AS subject_name

        FROM subscriptions sub

        INNER JOIN courses c
            ON c.id = sub.course_id

        INNER JOIN teachers t
            ON t.id = c.teacher_id

        LEFT JOIN subjects s
            ON s.id = c.subject_id

        WHERE sub.student_id = ?

        ORDER BY sub.id DESC
    ");

    $stmt->execute([
        $studentId
    ]);

    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {

    $courses = [];

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

<title>دوراتي | منصة أكاديمي</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Tahoma, Arial, sans-serif;
    background:
        linear-gradient(
            180deg,
            #ede7d2 0%,
            #e8e8e8 45%,
            #f4f4f2 100%
        );
    color: #24362d;
    min-height: 100vh;
    padding-bottom: 95px;
}

a {
    text-decoration: none;
    color: inherit;
}

.page {
    max-width: 760px;
    margin: auto;
    padding: 15px;
}

/* HEADER */

.header {
    background: #345c49;
    color: #fff;
    border-radius: 24px;
    padding: 20px;
    margin-bottom: 18px;
    box-shadow: 0 10px 25px rgba(52,92,73,.15);
}

.header-top {
    display: flex;
    align-items: center;
    gap: 12px;
}

.back {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    background: rgba(255,255,255,.12);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.header-text {
    flex: 1;
}

.header-title {
    font-size: 20px;
    font-weight: bold;
}

.header-description {
    margin-top: 6px;
    color: rgba(255,255,255,.72);
    font-size: 12px;
}

/* COURSE */

.course-card {
    background: rgba(255,255,255,.88);
    border: 1px solid rgba(52,92,73,.1);
    border-radius: 23px;
    padding: 17px;
    margin-bottom: 15px;
    box-shadow: 0 8px 22px rgba(52,92,73,.07);
}

.course-top {
    display: flex;
    gap: 13px;
    align-items: flex-start;
}

.course-icon {
    width: 55px;
    height: 55px;
    flex-shrink: 0;
    border-radius: 17px;
    background: #ede7d2;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
}

.course-info {
    flex: 1;
}

.course-title {
    color: #345c49;
    font-size: 16px;
    font-weight: bold;
    line-height: 1.5;
}

.teacher {
    color: #77817a;
    font-size: 11px;
    margin-top: 5px;
}

.description {
    color: #737d76;
    font-size: 12px;
    line-height: 1.7;
    margin-top: 9px;
}

.details {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin-top: 13px;
}

.detail {
    background: #e8e8e8;
    color: #59655d;
    padding: 7px 9px;
    border-radius: 9px;
    font-size: 10px;
}

.status {
    margin-top: 13px;
    padding: 9px 11px;
    border-radius: 11px;
    background: #dce4d1;
    color: #345c49;
    font-size: 11px;
    font-weight: bold;
}

.open-btn {
    display: block;
    margin-top: 12px;
    background: #345c49;
    color: #fff;
    text-align: center;
    padding: 12px;
    border-radius: 13px;
    font-size: 13px;
}

/* EMPTY */

.empty {
    background: rgba(255,255,255,.84);
    border-radius: 23px;
    padding: 42px 20px;
    text-align: center;
    border: 1px dashed rgba(52,92,73,.2);
}

.empty-icon {
    font-size: 45px;
}

.empty h3 {
    margin-top: 12px;
    color: #345c49;
    font-size: 18px;
}

.empty p {
    margin-top: 8px;
    color: #768078;
    font-size: 12px;
    line-height: 1.8;
}

.choose-btn {
    display: block;
    margin: 18px auto 0;
    max-width: 280px;
    background: #345c49;
    color: #fff;
    padding: 13px;
    border-radius: 14px;
    font-size: 13px;
}

/* NAV */

.bottom-nav {
    position: fixed;
    bottom: 10px;
    right: 12px;
    left: 12px;
    max-width: 560px;
    margin: auto;
    min-height: 68px;
    background: rgba(255,255,255,.92);
    border: 1px solid rgba(52,92,73,.12);
    border-radius: 23px;
    box-shadow: 0 12px 30px rgba(52,92,73,.16);
    display: grid;
    grid-template-columns: repeat(4,1fr);
    padding: 6px;
    z-index: 100;
}

.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    border-radius: 17px;
    color: #7b8780;
    font-size: 10px;
}

.nav-icon {
    font-size: 19px;
}

.nav-item.active {
    background: #345c49;
    color: #fff;
}

</style>

</head>

<body>

<div class="page">

    <section class="header">

        <div class="header-top">

            <a href="index.php" class="back">
                →
            </a>

            <div class="header-text">

                <div class="header-title">
                    دوراتي
                </div>

                <div class="header-description">
                    الدورات التي اشتركت بها وتستطيع الوصول إليها
                </div>

            </div>

        </div>

    </section>


    <?php if (!empty($courses)): ?>

        <?php foreach ($courses as $course): ?>

            <div class="course-card">

                <div class="course-top">

                    <div class="course-icon">
                        🎓
                    </div>

                    <div class="course-info">

                        <div class="course-title">
                            <?= htmlspecialchars(
                                $course['course_title'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>

                        <div class="teacher">
                            👨‍🏫
                            <?= htmlspecialchars(
                                $course['teacher_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            <?php if (!empty($course['subject_name'])): ?>

                                ·

                                <?= htmlspecialchars(
                                    $course['subject_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <?php if (!empty($course['course_description'])): ?>

                    <div class="description">

                        <?= nl2br(
                            htmlspecialchars(
                                $course['course_description'],
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        ) ?>

                    </div>

                <?php endif; ?>


                <div class="details">

                    <?php if (!empty($course['duration_days'])): ?>

                        <div class="detail">
                            🗓 <?= (int)$course['duration_days'] ?> يوم
                        </div>

                    <?php endif; ?>


                    <?php if (!empty($course['starts_at'])): ?>

                        <div class="detail">
                            ▶ بدأت:
                            <?= htmlspecialchars(
                                $course['starts_at'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>

                    <?php endif; ?>

                </div>


                <div class="status">

                    <?php if ($course['status'] === 'active'): ?>

                        ✅ الاشتراك فعال

                    <?php elseif ($course['status'] === 'pending'): ?>

                        ⏳ الطلب قيد المراجعة

                    <?php else: ?>

                        ℹ️ حالة الاشتراك:
                        <?= htmlspecialchars(
                            $course['status'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    <?php endif; ?>

                </div>


                <a
                    href="course.php?id=<?= (int)$course['course_id'] ?>"
                    class="open-btn"
                >
                    فتح الدورة
                </a>

            </div>

        <?php endforeach; ?>


    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">
                🎓
            </div>

            <h3>
                لا توجد دورات في حسابك حالياً
            </h3>

            <p>
                عند اشتراكك بدورة وإتمام طلب بطاقة الاشتراك،
                ستظهر الدورة هنا تلقائياً.
            </p>

            <a
                href="subjects.php"
                class="choose-btn"
            >
                📚 اختيار مادة والاشتراك بدورة
            </a>

        </div>

    <?php endif; ?>

</div>


<nav class="bottom-nav">

    <a href="index.php" class="nav-item">

        <span class="nav-icon">🏠</span>

        الرئيسية

    </a>


    <a href="subjects.php" class="nav-item">

        <span class="nav-icon">📚</span>

        المواد

    </a>


    <a href="courses.php" class="nav-item active">

        <span class="nav-icon">🎓</span>

        دوراتي

    </a>


    <a href="profile.php" class="nav-item">

        <span class="nav-icon">👤</span>

        حسابي

    </a>

</nav>

</body>

</html>