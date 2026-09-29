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


/* =========================================================
   المرحلة
========================================================= */

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


/* =========================================================
   المنهج
========================================================= */

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


/* =========================================================
   المحافظة
========================================================= */

$governorateName = '';

if (!empty($student['governorate_id'])) {

    $stmt = $pdo->prepare("
        SELECT name
        FROM governorates
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        (int)$student['governorate_id']
    ]);

    $governorateName = $stmt->fetchColumn() ?: '';
}


/* =========================================================
   المواد الدراسية
========================================================= */

$subjects = [];

try {

    if (!empty($student['curriculum_id'])) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                curriculum_id,
                active
            FROM subjects
            WHERE active = 1
              AND curriculum_id = ?
            ORDER BY id ASC
        ");

        $stmt->execute([
            (int)$student['curriculum_id']
        ]);

        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($subjects)) {

        $stmt = $pdo->query("
            SELECT
                id,
                name,
                curriculum_id,
                active
            FROM subjects
            WHERE active = 1
            ORDER BY id ASC
        ");

        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {

    $subjects = [];
}


$subjectsCount = count($subjects);


/* =========================================================
   أساتذة المرحلة
========================================================= */

$teachers = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            t.id,
            t.name,
            t.image,
            t.subject_id,
            t.stage_id,
            t.curriculum_id,
            s.name AS subject_name
        FROM teachers t

        LEFT JOIN subjects s
            ON s.id = t.subject_id

        WHERE t.active = 1
          AND t.stage_id = ?

        ORDER BY t.id DESC

        LIMIT 10
    ");

    $stmt->execute([
        (int)$student['stage_id']
    ]);

    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $teachers = [];
}


/* =========================================================
   آخر الدروس
========================================================= */

$lessons = [];

try {

    $stmt = $pdo->query("
        SELECT
            cl.id,
            cl.title,
            cl.lesson_number,
            cl.description,
            cl.duration,

            c.title AS course_title,

            t.name AS teacher_name,
            t.image AS teacher_image,

            s.name AS subject_name

        FROM course_lessons cl

        INNER JOIN courses c
            ON c.id = cl.course_id

        INNER JOIN teachers t
            ON t.id = c.teacher_id

        LEFT JOIN subjects s
            ON s.id = c.subject_id

        WHERE cl.active = 1
          AND c.active = 1
          AND t.active = 1

        ORDER BY cl.id DESC

        LIMIT 5
    ");

    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $lessons = [];
}


/* =========================================================
   الإشعارات غير المقروءة
========================================================= */

$unreadNotifications = 0;

try {

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM student_notifications
        WHERE student_id = ?
          AND is_read = 0
    ");

    $stmt->execute([$studentId]);

    $unreadNotifications = (int)$stmt->fetchColumn();

} catch (PDOException $e) {

    $unreadNotifications = 0;
}


/* =========================================================
   صورة الطالب / الحرف الأول
========================================================= */

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


/* =========================================================
   أيقونات المواد
========================================================= */

$subjectIcons = [
    '📘',
    '📐',
    '🧪',
    '🔬',
    '🌍',
    '📖',
    '💻',
    '🧮',
    '⚗️',
    '📝'
];


$pdo = db();

/* =========================================================
   الإعلانات / البنرات من قاعدة البيانات
========================================================= */

$banners = [];

try {
    $stmt = $pdo->query("
        SELECT
            id,
            title,
            description,
            image,
            link,
            sort_order
        FROM banners
        WHERE active = 1
        ORDER BY sort_order ASC, id DESC
    ");

    $dbBanners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dbBanners as $banner) {
        $banners[] = [
            'id'    => (int)$banner['id'],
            'image' => '../uploads/banners/' . ltrim($banner['image'], '/'),
            'title' => $banner['title'] ?? '',
            'text'  => $banner['description'] ?? '',
            'link'  => $banner['link'] ?? ''
        ];
    }

} catch (PDOException $e) {
    $banners = [];
}
?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta name="theme-color" content="#345c49">

<meta name="apple-mobile-web-app-capable" content="yes">

<meta name="apple-mobile-web-app-title" content="أكاديمي">

<link rel="manifest" href="/manifest.webmanifest">

<link rel="icon" href="/assets/pwa-icon.svg" type="image/svg+xml">

<link rel="apple-touch-icon" href="/assets/pwa-icon-192.png">

<script src="/assets/pwa.js" defer></script>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
>

<title>منصة أكاديمي | الرئيسية</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {

    --primary: #345c49;

    --light: #e8e8e8;

    --green: #9bac78;

    --cream: #ede7d2;

    --white: #ffffff;

    --text: #24362d;

    --muted: #718078;

    --border: rgba(52,92,73,.12);

}


/* =========================================================
   BODY
========================================================= */

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


button {

    font-family: inherit;

}


/* =========================================================
   PAGE
========================================================= */

.page {

    width: 100%;

    max-width: 760px;

    margin: auto;

    padding: 14px;

}


/* =========================================================
   HEADER
========================================================= */

.top-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    margin-bottom: 16px;

}


.student-box {

    display: flex;

    align-items: center;

    gap: 10px;

    min-width: 0;

}


.student-avatar {

    width: 52px;

    height: 52px;

    border-radius: 18px;

    background: var(--primary);

    color: #fff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 21px;

    font-weight: bold;

    flex-shrink: 0;

    box-shadow:
        0 7px 20px rgba(52,92,73,.18);

}


.student-info {

    min-width: 0;

}


.welcome {

    font-size: 11px;

    color: var(--muted);

    margin-bottom: 3px;

}


.student-name {

    font-size: 16px;

    font-weight: bold;

    color: var(--primary);

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

    max-width: 190px;

}


.student-stage {

    font-size: 11px;

    color: var(--muted);

    margin-top: 3px;

}


.header-actions {

    display: flex;

    align-items: center;

    gap: 7px;

}


.header-btn {

    width: 42px;

    height: 42px;

    border-radius: 15px;

    border: 1px solid var(--border);

    background: rgba(255,255,255,.72);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;

    cursor: pointer;

    transition: .2s;

    position: relative;

}


.header-btn:hover {

    background: #fff;

    transform: translateY(-2px);

}


.notification-count {

    position: absolute;

    min-width: 17px;

    height: 17px;

    padding: 0 4px;

    border-radius: 20px;

    background: var(--green);

    color: #fff;

    font-size: 9px;

    font-weight: bold;

    display: flex;

    align-items: center;

    justify-content: center;

    top: 3px;

    right: 3px;

    border: 2px solid #fff;

}


/* =========================================================
   SEARCH
========================================================= */

.search-box {

    display: none;

    margin-bottom: 14px;

}


.search-box.active {

    display: block;

}


.search-input {

    width: 100%;

    height: 47px;

    border: 1px solid var(--border);

    border-radius: 17px;

    background: rgba(255,255,255,.85);

    padding: 0 16px;

    outline: none;

    color: var(--text);

    font-size: 13px;

}


.search-input:focus {

    border-color: var(--green);

}


/* =========================================================
   AD SLIDER
========================================================= */

.banner-slider {

    width: 100%;

    position: relative;

    margin-bottom: 18px;

}


.banner-window {

    width: 100%;

    overflow: hidden;

    border-radius: 25px;

    box-shadow:
        0 12px 28px rgba(52,92,73,.14);

}


.banner-track {

    display: flex;

    direction: ltr;

    transition:
        transform .55s cubic-bezier(.22,.61,.36,1);

    will-change: transform;

}


.banner-slide {

    min-width: 100%;

    height: 190px;

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            var(--primary),
            var(--green)
        );

}


.banner-slide img {

    position: absolute;

    inset: 0;

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;

}


.banner-overlay {

    position: absolute;

    inset: 0;

   
}


.banner-content {

    position: absolute;

    right: 20px;

    top: 50%;

    transform: translateY(-50%);

    color: #fff;

    max-width: 62%;

    z-index: 2;

}


.banner-title {

    font-size: 19px;

    font-weight: bold;

    line-height: 1.5;

}


.banner-text {

    margin-top: 7px;

    font-size: 11px;

    line-height: 1.7;

    color: rgba(255,255,255,.82);

}


.banner-arrow {

    position: absolute;

    top: 50%;

    transform: translateY(-50%);

    width: 34px;

    height: 34px;

    border: 0;

    border-radius: 50%;

    background: rgba(255,255,255,.85);

    color: var(--primary);

    display: flex;

    align-items: center;

    justify-content: center;

    cursor: pointer;

    z-index: 5;

    font-size: 17px;

    box-shadow:
        0 5px 14px rgba(0,0,0,.12);

}


.banner-prev {

    left: 10px;

}


.banner-next {

    right: 10px;

}


.banner-dots {

    position: absolute;

    bottom: 11px;

    left: 50%;

    transform: translateX(-50%);

    display: flex;

    align-items: center;

    gap: 6px;

    z-index: 5;

}


.banner-dot {

    width: 7px;

    height: 7px;

    border-radius: 50%;

    border: 0;

    padding: 0;

    background: rgba(255,255,255,.55);

    cursor: pointer;

    transition: .25s;

}


.banner-dot.active {

    width: 21px;

    border-radius: 10px;

    background: #fff;

}


/* =========================================================
   WELCOME
========================================================= */

.welcome-card {

    background: var(--primary);

    color: #fff;

    border-radius: 24px;

    padding: 19px;

    position: relative;

    overflow: hidden;

    margin-bottom: 20px;

    box-shadow:
        0 11px 28px rgba(52,92,73,.16);

}


.welcome-card::before {

    content: "";

    position: absolute;

    width: 150px;

    height: 150px;

    border-radius: 50%;

    background: rgba(255,255,255,.06);

    left: -45px;

    top: -65px;

}


.welcome-card::after {

    content: "";

    position: absolute;

    width: 100px;

    height: 100px;

    border-radius: 50%;

    background: rgba(155,172,120,.18);

    right: -35px;

    bottom: -45px;

}


.welcome-title {

    font-size: 19px;

    font-weight: bold;

    position: relative;

    z-index: 1;

}


.welcome-text {

    font-size: 12px;

    margin-top: 6px;

    color: rgba(255,255,255,.78);

    position: relative;

    z-index: 1;

    line-height: 1.8;

}


.curriculum-badge {

    display: inline-flex;

    margin-top: 11px;

    padding: 7px 11px;

    border-radius: 12px;

    background: rgba(237,231,210,.13);

    color: var(--cream);

    font-size: 10px;

    position: relative;

    z-index: 1;

}


/* =========================================================
   SECTION HEAD
========================================================= */

.section-head {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin: 23px 2px 11px;

}


.section-title {

    font-size: 16px;

    font-weight: bold;

    color: var(--primary);

}


.section-link {

    font-size: 11px;

    color: var(--green);

    font-weight: bold;

}


/* =========================================================
   SHORTCUTS
========================================================= */

.shortcut-slider {

    display: flex;

    gap: 11px;

    overflow-x: auto;

    padding: 3px 1px 8px;

    scrollbar-width: none;

}


.shortcut-slider::-webkit-scrollbar {

    display: none;

}


.shortcut-card {

    min-width: 132px;

    height: 120px;

    border-radius: 21px;

    padding: 14px;

    position: relative;

    overflow: hidden;

    display: flex;

    flex-direction: column;

    justify-content: space-between;

    flex-shrink: 0;

    border: 1px solid rgba(52,92,73,.08);

    box-shadow:
        0 7px 20px rgba(52,92,73,.07);

    transition: .2s;

}


.shortcut-card:hover {

    transform: translateY(-3px);

}


.shortcut-card:nth-child(1) {

    background: var(--primary);

    color: #fff;

}


.shortcut-card:nth-child(2) {

    background: var(--cream);

}


.shortcut-card:nth-child(3) {

    background: #dce4d1;

}


.shortcut-card:nth-child(4) {

    background: var(--light);

}


.shortcut-card:nth-child(5) {

    background: #d6e0d8;

}


.shortcut-icon {

    width: 39px;

    height: 39px;

    border-radius: 13px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: rgba(255,255,255,.48);

    font-size: 20px;

}


.shortcut-card:nth-child(1)
.shortcut-icon {

    background: rgba(255,255,255,.12);

}


.shortcut-title {

    font-size: 13px;

    font-weight: bold;

}


.shortcut-small {

    font-size: 9px;

    opacity: .65;

    margin-top: 3px;

}


/* =========================================================
   SUBJECTS
========================================================= */

.subjects-slider {

    display: flex;

    gap: 11px;

    overflow-x: auto;

    scrollbar-width: none;

    padding: 3px 1px 8px;

}


.subjects-slider::-webkit-scrollbar {

    display: none;

}


.subject-card {

    min-width: 103px;

    background: rgba(255,255,255,.80);

    border: 1px solid var(--border);

    border-radius: 19px;

    padding: 12px 9px;

    text-align: center;

    flex-shrink: 0;

    box-shadow:
        0 6px 18px rgba(52,92,73,.05);

    transition: .2s;

}


.subject-card:hover {

    transform: translateY(-3px);

}


.subject-icon {

    width: 52px;

    height: 52px;

    border-radius: 16px;

    margin: auto;

    background: var(--cream);

    color: var(--primary);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 22px;

}


.subject-name {

    font-size: 11px;

    font-weight: bold;

    margin-top: 8px;

    color: var(--text);

    line-height: 1.5;

}


/* =========================================================
   TEACHERS
========================================================= */

.teachers-slider {

    display: flex;

    gap: 12px;

    overflow-x: auto;

    scrollbar-width: none;

    padding: 3px 1px 8px;

}


.teachers-slider::-webkit-scrollbar {

    display: none;

}


.teacher-card {

    min-width: 172px;

    background: rgba(255,255,255,.84);

    border: 1px solid var(--border);

    border-radius: 21px;

    overflow: hidden;

    flex-shrink: 0;

    box-shadow:
        0 7px 20px rgba(52,92,73,.06);

}


.teacher-photo {

    width: 100%;

    height: 130px;

    background: var(--cream);

    display: flex;

    align-items: center;

    justify-content: center;

    overflow: hidden;

}


.teacher-photo img {

    width: 100%;

    height: 100%;

    object-fit: cover;

}


.teacher-placeholder {

    width: 60px;

    height: 60px;

    border-radius: 19px;

    background: var(--primary);

    color: #fff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 24px;

    font-weight: bold;

}


.teacher-info {

    padding: 11px;

}


.teacher-name {

    font-size: 13px;

    font-weight: bold;

    color: var(--primary);

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

}


.teacher-subject {

    font-size: 10px;

    color: var(--muted);

    margin-top: 5px;

}


/* =========================================================
   LESSONS
========================================================= */

.lessons-slider {

    display: flex;

    gap: 12px;

    overflow-x: auto;

    scrollbar-width: none;

    padding: 3px 1px 8px;

}


.lessons-slider::-webkit-scrollbar {

    display: none;

}


.lesson-card {

    min-width: 220px;

    background: rgba(255,255,255,.84);

    border: 1px solid var(--border);

    border-radius: 20px;

    overflow: hidden;

    flex-shrink: 0;

    box-shadow:
        0 7px 20px rgba(52,92,73,.06);

}


.lesson-thumb {

    width: 100%;

    height: 120px;

    background:
        linear-gradient(
            135deg,
            var(--primary),
            var(--green)
        );

    position: relative;

    overflow: hidden;

}


.lesson-thumb img {

    width: 100%;

    height: 100%;

    object-fit: cover;

}


.lesson-play {

    position: absolute;

    top: 50%;

    left: 50%;

    transform:
        translate(-50%, -50%);

    width: 47px;

    height: 47px;

    border-radius: 50%;

    background: rgba(255,255,255,.93);

    color: var(--primary);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 19px;

}


.lesson-info {

    padding: 11px;

}


.lesson-title {

    font-size: 12px;

    font-weight: bold;

    line-height: 1.6;

    color: var(--text);

}


.lesson-meta {

    margin-top: 6px;

    font-size: 9px;

    color: var(--muted);

}


/* =========================================================
   EMPTY
========================================================= */

.empty-box {

    background: rgba(255,255,255,.68);

    border: 1px dashed rgba(52,92,73,.20);

    border-radius: 19px;

    padding: 23px 14px;

    text-align: center;

    color: var(--muted);

    font-size: 12px;

    width: 100%;

}


.empty-icon {

    font-size: 27px;

    margin-bottom: 7px;

}


/* =========================================================
   EXAMS
========================================================= */

.exam-card {

    background: var(--primary);

    border-radius: 22px;

    padding: 16px;

    color: #fff;

    margin-top: 7px;

    position: relative;

    overflow: hidden;

}


.exam-card::after {

    content: "";

    position: absolute;

    width: 130px;

    height: 130px;

    border-radius: 50%;

    background: rgba(155,172,120,.14);

    left: -45px;

    bottom: -60px;

}


.exam-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    position: relative;

    z-index: 1;

}


.exam-icon {

    width: 43px;

    height: 43px;

    border-radius: 14px;

    background: rgba(255,255,255,.12);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;

}


.exam-title {

    font-weight: bold;

    font-size: 13px;

}


.exam-text {

    margin-top: 11px;

    font-size: 11px;

    color: rgba(255,255,255,.72);

    line-height: 1.7;

    position: relative;

    z-index: 1;

}


/* =========================================================
   SUBSCRIPTION
========================================================= */

.subscription-info {

    margin-top: 19px;

    background: var(--cream);

    border-radius: 21px;

    padding: 16px;

    border: 1px solid rgba(52,92,73,.08);

}


.subscription-info-title {

    color: var(--primary);

    font-size: 14px;

    font-weight: bold;

}


.subscription-info-text {

    color: var(--muted);

    font-size: 10px;

    line-height: 1.8;

    margin-top: 6px;

}


/* =========================================================
   BOTTOM NAV
========================================================= */

.bottom-nav-wrap {

    position: fixed;

    bottom: 11px;

    left: 0;

    right: 0;

    z-index: 100;

    padding: 0 11px;

}


.bottom-nav {

    max-width: 560px;

    margin: auto;

    min-height: 70px;

    border-radius: 24px;

    background:
        rgba(255,255,255,.80);

    border: 1px solid rgba(52,92,73,.13);

    box-shadow:
        0 13px 34px rgba(52,92,73,.15);

    backdrop-filter: blur(18px);

    -webkit-backdrop-filter: blur(18px);

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    align-items: center;

    padding: 6px;

}


.nav-item {

    height: 57px;

    border-radius: 18px;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    gap: 4px;

    color: var(--muted);

    font-size: 9px;

    transition: .2s;

    position: relative;

}


.nav-icon {

    font-size: 18px;

    line-height: 1;

}


.nav-item.active {

    background: var(--primary);

    color: #fff;

    box-shadow:
        0 7px 17px rgba(52,92,73,.20);

}


.nav-item:hover {

    color: var(--primary);

}


.nav-item.active:hover {

    color: #fff;

}


.nav-badge {

    position: absolute;

    top: 7px;

    right: calc(50% - 18px);

    min-width: 16px;

    height: 16px;

    padding: 0 4px;

    border-radius: 20px;

    background: var(--green);

    color: #fff;

    font-size: 8px;

    display: flex;

    align-items: center;

    justify-content: center;

    border: 2px solid #fff;

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 420px) {

    .page {

        padding: 12px;

    }


    .student-name {

        max-width: 145px;

    }


    .banner-slide {

        height: 175px;

    }


    .banner-content {

        right: 17px;

        max-width: 66%;

    }


    .banner-title {

        font-size: 17px;

    }


    .banner-text {

        font-size: 10px;

    }


    .shortcut-card {

        min-width: 124px;

        height: 116px;

    }


    .teacher-card {

        min-width: 160px;

    }


    .lesson-card {

        min-width: 208px;

    }

}


/* =========================================================
   VERY SMALL
========================================================= */

@media (max-width: 350px) {

    .student-avatar {

        width: 48px;

        height: 48px;

        border-radius: 16px;

    }


    .student-name {

        max-width: 125px;

        font-size: 14px;

    }


    .header-btn {

        width: 39px;

        height: 39px;

    }


    .banner-slide {

        height: 160px;

    }


    .banner-title {

        font-size: 15px;

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

            <?= htmlspecialchars(
                $studentInitial,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>


        <div class="student-info">


            <div class="welcome">

                أهلاً بك 👋

            </div>


            <div class="student-name">

                <?= htmlspecialchars(
                    $student['name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>


            <div class="student-stage">

                <?= htmlspecialchars(
                    $stageName ?: 'الطالب',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>


        </div>


    </div>


    <div class="header-actions">


        <button
            type="button"
            class="header-btn"
            id="searchButton"
            aria-label="البحث"
        >

            🔍

        </button>


        <a
            href="notifications.php"
            class="header-btn"
            aria-label="الإشعارات"
        >

            🔔


            <?php if ($unreadNotifications > 0): ?>

                <span class="notification-count">

                    <?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?>

                </span>

            <?php endif; ?>


        </a>


    </div>


</header>


<!-- =====================================================
     SEARCH
====================================================== -->

<div
    class="search-box"
    id="searchBox"
>

    <input
        type="text"
        class="search-input"
        id="searchInput"
        placeholder="ابحث عن أستاذ أو مادة أو درس..."
    >

</div>


<!-- =====================================================
     ADVERTISEMENT SLIDER
====================================================== -->

<section class="banner-slider">


    <div
        class="banner-window"
        id="bannerWindow"
    >


        <div
            class="banner-track"
            id="bannerTrack"
        >


            <?php foreach ($banners as $banner): ?>


                <div class="banner-slide">


                    <img
                        src="<?= htmlspecialchars(
                            $banner['image'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        alt="<?= htmlspecialchars(
                            $banner['title'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        onerror="this.style.display='none';"
                    >


                    <div class="banner-overlay"></div>


                    <div class="banner-content">


                        <div class="banner-title">

                            <?= htmlspecialchars(
                                $banner['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>


                        <div class="banner-text">

                            <?= htmlspecialchars(
                                $banner['text'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>


                    </div>


                </div>


            <?php endforeach; ?>


        </div>


        <button
            type="button"
            class="banner-arrow banner-prev"
            id="bannerPrev"
            aria-label="الإعلان السابق"
        >

            ‹

        </button>


        <button
            type="button"
            class="banner-arrow banner-next"
            id="bannerNext"
            aria-label="الإعلان التالي"
        >

            ›

        </button>


        <div
            class="banner-dots"
            id="bannerDots"
        >


            <?php foreach ($banners as $index => $banner): ?>


                <button
                    type="button"
                    class="banner-dot <?= $index === 0 ? 'active' : '' ?>"
                    data-slide="<?= $index ?>"
                    aria-label="الإعلان <?= $index + 1 ?>"
                ></button>


            <?php endforeach; ?>


        </div>


    </div>


</section>


<!-- =====================================================
     WELCOME
====================================================== -->

<section class="welcome-card">


    <div class="welcome-title">

        منصتك التعليمية بين يديك

    </div>


    <div class="welcome-text">

        تابع موادك، أساتذتك، دوراتك ودروسك من مكان واحد.

    </div>


    <?php if ($curriculumName): ?>

        <div class="curriculum-badge">

            📚

            <?= htmlspecialchars(
                $curriculumName,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


</section>


<!-- =====================================================
     SERVICES
====================================================== -->

<div class="section-head">


    <div class="section-title">

        خدماتك التعليمية

    </div>


</div>


<div class="shortcut-slider">


    <!-- موادي -->

    <a
        href="my_courses.php"
        class="shortcut-card"
    >


        <div class="shortcut-icon">

            📚

        </div>


        <div>


            <div class="shortcut-title">

                دوراتي

            </div>


            <div class="shortcut-small">

                دوراتك ومحاضراتك

            </div>


        </div>


    </a>


    <!-- جدول الامتحانات -->

    <a
        href="exams.php"
        class="shortcut-card"
    >


        <div class="shortcut-icon">

            📅

        </div>


        <div>


            <div class="shortcut-title">

                جدول الامتحانات

            </div>


            <div class="shortcut-small">

                مواعيد اختباراتك

            </div>


        </div>


    </a>


    <!-- درجاتي -->

    <a
        href="grades.php"
        class="shortcut-card"
    >


        <div class="shortcut-icon">

            📝

        </div>


        <div>


            <div class="shortcut-title">

                درجاتي

            </div>


            <div class="shortcut-small">

                تابع مستواك

            </div>


        </div>


    </a>


    <!-- الاختبارات -->

    <a
        href="tests.php"
        class="shortcut-card"
    >


        <div class="shortcut-icon">

            🎯

        </div>


        <div>


            <div class="shortcut-title">

                الاختبارات

            </div>


            <div class="shortcut-small">

                اختبر معلوماتك

            </div>


        </div>


    </a>


    <!-- المواد -->

    <a
        href="subjects.php"
        class="shortcut-card"
    >


        <div class="shortcut-icon">

            📚

        </div>


        <div>


            <div class="shortcut-title">

                المواد

            </div>


            <div class="shortcut-small">

                <?= $subjectsCount ?>

                مواد متاحة

            </div>


        </div>


    </a>


</div>


<!-- =====================================================
     SUBJECTS
====================================================== -->

<div class="section-head">


    <div class="section-title">

        مواد المرحلة

    </div>


    <a
        href="subjects.php"
        class="section-link"
    >

        عرض الكل ←

    </a>


</div>


<div class="subjects-slider">


<?php if (!empty($subjects)): ?>


    <?php foreach ($subjects as $index => $subject): ?>


        <?php

        $subjectIcon =
            $subjectIcons[
                $index % count($subjectIcons)
            ];

        ?>


        <a
            href="teachers.php?subject_id=<?= (int)$subject['id'] ?>"
            class="subject-card"
        >


            <div class="subject-icon">

                <?= $subjectIcon ?>

            </div>


            <div class="subject-name">

                <?= htmlspecialchars(
                    $subject['name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>


        </a>


    <?php endforeach; ?>


<?php else: ?>


    <div class="empty-box">


        <div class="empty-icon">

            📚

        </div>


        لا توجد مواد مضافة حالياً.


    </div>


<?php endif; ?>


</div>


<!-- =====================================================
     TEACHERS
====================================================== -->

<div class="section-head">


    <div class="section-title">

        أساتذة المنصة

    </div>


    <a
        href="teachers.php"
        class="section-link"
    >

        عرض الكل ←

    </a>


</div>


<div class="teachers-slider">


<?php if (!empty($teachers)): ?>


    <?php foreach ($teachers as $teacher): ?>


        <?php

        $teacherInitial =
            mb_substr(
                trim($teacher['name']),
                0,
                1,
                'UTF-8'
            );

        ?>


        <a
            href="teacher.php?id=<?= (int)$teacher['id'] ?>"
            class="teacher-card"
        >


            <div class="teacher-photo">


                <?php if (!empty($teacher['image'])): ?>


                    <img
                        src="../uploads/teachers/<?= htmlspecialchars(
                            $teacher['image'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        alt="<?= htmlspecialchars(
                            $teacher['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >


                <?php else: ?>


                    <div class="teacher-placeholder">

                        <?= htmlspecialchars(
                            $teacherInitial,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>


                <?php endif; ?>


            </div>


            <div class="teacher-info">


                <div class="teacher-name">

                    <?= htmlspecialchars(
                        $teacher['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <div class="teacher-subject">

                    <?= htmlspecialchars(
                        $teacher['subject_name']
                        ?: 'أستاذ في المنصة',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


            </div>


        </a>


    <?php endforeach; ?>


<?php else: ?>


    <div class="empty-box">


        <div class="empty-icon">

            👨‍🏫

        </div>


        لا توجد أساتذة مضافين لهذه المرحلة حالياً.


    </div>


<?php endif; ?>


</div>


<!-- =====================================================
     LATEST LESSONS
====================================================== -->

<div class="section-head">


    <div class="section-title">

        آخر الدروس

    </div>


    <a
        href="lessons.php"
        class="section-link"
    >

        عرض الكل ←

    </a>


</div>


<div class="lessons-slider">


<?php if (!empty($lessons)): ?>


    <?php foreach ($lessons as $lesson): ?>


        <a
            href="lesson.php?id=<?= (int)$lesson['id'] ?>"
            class="lesson-card"
        >


            <div class="lesson-thumb">


                <?php if (!empty($lesson['teacher_image'])): ?>


                    <img
                        src="../uploads/teachers/<?= htmlspecialchars(
                            $lesson['teacher_image'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        alt=""
                    >


                <?php endif; ?>


                <div class="lesson-play">

                    ▶

                </div>


            </div>


            <div class="lesson-info">


                <div class="lesson-title">

                    <?= htmlspecialchars(
                        $lesson['title'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <div class="lesson-meta">

                    <?= htmlspecialchars(
                        $lesson['teacher_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>


                    <?php if (!empty($lesson['subject_name'])): ?>


                        ·


                        <?= htmlspecialchars(
                            $lesson['subject_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>


                    <?php endif; ?>


                </div>


            </div>


        </a>


    <?php endforeach; ?>


<?php else: ?>


    <div class="empty-box">


        <div class="empty-icon">

            🎬

        </div>


        لا توجد دروس مضافة حالياً.


    </div>


<?php endif; ?>


</div>


<!-- =====================================================
     EXAMS
====================================================== -->

<div class="section-head">


    <div class="section-title">

        جدول الامتحانات

    </div>


</div>


<div class="exam-card">


    <div class="exam-top">


        <div>


            <div class="exam-title">

                جدول امتحاناتك

            </div>


        </div>


        <div class="exam-icon">

            📅

        </div>


    </div>


    <div class="exam-text">

        سيتم عرض جدول الامتحانات هنا بشكل تلقائي
        عند إضافته من لوحة الإدارة.

    </div>


</div>


<!-- =====================================================
     SUBSCRIPTION
====================================================== -->

<div class="subscription-info">


    <div class="subscription-info-title">

        💳 طلب بطاقة الاشتراك

    </div>


    <div class="subscription-info-text">

        اختر المادة ثم الأستاذ والدورة،
        وبعدها يمكنك تقديم طلب بطاقة الاشتراك
        وإكمال الطلب من داخل المنصة.

    </div>


</div>


</div>


<!-- =========================================================
     BOTTOM NAVIGATION
========================================================= -->

<div class="bottom-nav-wrap">


<nav class="bottom-nav">


    <a
        href="index.php"
        class="nav-item active"
    >


        <div class="nav-icon">

            🏠

        </div>


        <div>

            الرئيسية

        </div>


    </a>


    <a
        href="my_courses.php"
        class="nav-item"
    >


        <div class="nav-icon">

            🎓

        </div>


        <div>

            موادي

        </div>


    </a>


    <a
        href="notifications.php"
        class="nav-item"
    >


        <div class="nav-icon">

            🔔

        </div>


        <?php if ($unreadNotifications > 0): ?>

            <span class="nav-badge">

                <?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?>

            </span>

        <?php endif; ?>


        <div>

            الإشعارات

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


<script>

/* =========================================================
   SEARCH
========================================================= */

const searchButton =
    document.getElementById('searchButton');

const searchBox =
    document.getElementById('searchBox');

const searchInput =
    document.getElementById('searchInput');


if (searchButton && searchBox && searchInput) {

    searchButton.addEventListener(
        'click',
        function () {

            searchBox.classList.toggle('active');

            if (
                searchBox.classList.contains('active')
            ) {

                searchInput.focus();

            }

        }
    );


    searchInput.addEventListener(
        'input',
        function () {

            const value =
                this.value
                    .trim()
                    .toLowerCase();


            const subjectCards =
                document.querySelectorAll(
                    '.subject-card'
                );


            const teacherCards =
                document.querySelectorAll(
                    '.teacher-card'
                );


            const lessonCards =
                document.querySelectorAll(
                    '.lesson-card'
                );


            subjectCards.forEach(
                function (card) {

                    card.style.display =
                        card.innerText
                            .toLowerCase()
                            .includes(value)
                            ? ''
                            : 'none';

                }
            );


            teacherCards.forEach(
                function (card) {

                    card.style.display =
                        card.innerText
                            .toLowerCase()
                            .includes(value)
                            ? ''
                            : 'none';

                }
            );


            lessonCards.forEach(
                function (card) {

                    card.style.display =
                        card.innerText
                            .toLowerCase()
                            .includes(value)
                            ? ''
                            : 'none';

                }
            );

        }
    );

}


/* =========================================================
   BANNER SLIDER
========================================================= */

const bannerTrack =
    document.getElementById('bannerTrack');

const bannerWindow =
    document.getElementById('bannerWindow');

const bannerPrev =
    document.getElementById('bannerPrev');

const bannerNext =
    document.getElementById('bannerNext');

const bannerDots =
    document.querySelectorAll('.banner-dot');

const bannerSlides =
    document.querySelectorAll('.banner-slide');


let currentBanner = 0;

let bannerTimer = null;

let touchStartX = 0;

let touchEndX = 0;


/* تحديث السلايدر */

function updateBanner(index) {

    if (!bannerTrack || bannerSlides.length === 0) {
        return;
    }


    if (index < 0) {

        index = bannerSlides.length - 1;

    }


    if (index >= bannerSlides.length) {

        index = 0;

    }


    currentBanner = index;


    bannerTrack.style.transform =
        'translateX(-' +
        (currentBanner * 100) +
        '%)';


    bannerDots.forEach(
        function(dot, dotIndex) {

            dot.classList.toggle(
                'active',
                dotIndex === currentBanner
            );

        }
    );

}


/* الإعلان التالي */

function nextBanner() {

    updateBanner(
        currentBanner + 1
    );

}


/* الإعلان السابق */

function previousBanner() {

    updateBanner(
        currentBanner - 1
    );

}


/* التشغيل التلقائي */

function startBannerTimer() {

    stopBannerTimer();


    bannerTimer = setInterval(
        function() {

            nextBanner();

        },
        5000
    );

}


/* إيقاف التشغيل */

function stopBannerTimer() {

    if (bannerTimer) {

        clearInterval(bannerTimer);

        bannerTimer = null;

    }

}


/* الأزرار */

if (bannerNext) {

    bannerNext.addEventListener(
        'click',
        function() {

            nextBanner();

            startBannerTimer();

        }
    );

}


if (bannerPrev) {

    bannerPrev.addEventListener(
        'click',
        function() {

            previousBanner();

            startBannerTimer();

        }
    );

}


/* النقاط */

bannerDots.forEach(
    function(dot) {

        dot.addEventListener(
            'click',
            function() {

                const slide =
                    parseInt(
                        this.dataset.slide,
                        10
                    );

                updateBanner(slide);

                startBannerTimer();

            }
        );

    }
);


/* إيقاف مؤقت عند لمس السلايدر */

if (bannerWindow) {

    bannerWindow.addEventListener(
        'mouseenter',
        function() {

            stopBannerTimer();

        }
    );


    bannerWindow.addEventListener(
        'mouseleave',
        function() {

            startBannerTimer();

        }
    );


    /* Touch */

    bannerWindow.addEventListener(
        'touchstart',
        function(event) {

            touchStartX =
                event.changedTouches[0].screenX;

            stopBannerTimer();

        },
        {
            passive: true
        }
    );


    bannerWindow.addEventListener(
        'touchend',
        function(event) {

            touchEndX =
                event.changedTouches[0].screenX;


            const difference =
                touchStartX - touchEndX;


            if (Math.abs(difference) > 45) {

                if (difference > 0) {

                    nextBanner();

                } else {

                    previousBanner();

                }

            }


            startBannerTimer();

        },
        {
            passive: true
        }
    );

}


/* بدء السلايدر */

if (bannerSlides.length > 1) {

    startBannerTimer();

}

</script>


</body>

</html>

