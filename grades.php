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
        name
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

/*
|--------------------------------------------------------------------------
| جلب درجات الطالب
|--------------------------------------------------------------------------
|
| نستخدم فقط الأعمدة الموجودة في جدول grades:
|
| id
| student_id
| subject_id
| teacher_id
| title
| score
| max_score
| exam_date
| notes
| active
| created_at
|
|--------------------------------------------------------------------------
*/

$grades = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            g.id,
            g.title,
            g.score,
            g.max_score,
            g.exam_date,
            g.notes,

            sub.name AS subject_name,

            t.name AS teacher_name

        FROM grades g

        LEFT JOIN subjects sub
            ON sub.id = g.subject_id

        LEFT JOIN teachers t
            ON t.id = g.teacher_id

        WHERE g.student_id = ?
          AND g.active = 1

        ORDER BY
            g.exam_date DESC,
            g.id DESC
    ");

    $stmt->execute([
        $studentId
    ]);

    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    /*
     * في حالة وجود مشكلة بقاعدة البيانات
     * لا نخلي الصفحة تسقط بـ Error 500
     */

    $grades = [];
}

/*
|--------------------------------------------------------------------------
| حساب الإحصائيات
|--------------------------------------------------------------------------
*/

$totalGrades = count($grades);

$totalScore = 0;
$totalMaxScore = 0;

foreach ($grades as $grade) {

    $totalScore += (float)$grade['score'];
    $totalMaxScore += (float)$grade['max_score'];
}

$overallPercentage = 0;

if ($totalMaxScore > 0) {

    $overallPercentage =
        round(
            ($totalScore / $totalMaxScore) * 100,
            1
        );
}

/*
|--------------------------------------------------------------------------
| تحديد لون النتيجة
|--------------------------------------------------------------------------
*/

function gradeClass($percentage)
{
    if ($percentage >= 80) {
        return 'excellent';
    }

    if ($percentage >= 60) {
        return 'good';
    }

    if ($percentage >= 50) {
        return 'average';
    }

    return 'low';
}

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
>

<title>
    درجاتي | منصة أكاديمي
</title>

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

    --green: #9bac78;

    --cream: #ede7d2;

    --light: #e8e8e8;

    --white: #ffffff;

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

    padding-bottom: 100px;
}

a {
    text-decoration: none;
    color: inherit;
}

.page {

    width: 100%;

    max-width: 760px;

    margin: auto;

    padding: 14px;
}


/* =========================================================
   HEADER
========================================================= */

.page-header {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 18px;
}

.back-btn {

    width: 45px;

    height: 45px;

    border-radius: 15px;

    background:
        rgba(255,255,255,.75);

    border: 1px solid var(--border);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;

    color: var(--primary);
}

.header-text {

    flex: 1;
}

.header-title {

    font-size: 21px;

    font-weight: bold;

    color: var(--primary);
}

.header-subtitle {

    font-size: 11px;

    color: var(--muted);

    margin-top: 4px;
}


/* =========================================================
   SUMMARY
========================================================= */

.summary-card {

    background: var(--primary);

    color: #fff;

    border-radius: 25px;

    padding: 20px;

    margin-bottom: 20px;

    position: relative;

    overflow: hidden;

    box-shadow:
        0 12px 30px rgba(52,92,73,.18);
}

.summary-card::before {

    content: "";

    position: absolute;

    width: 150px;

    height: 150px;

    border-radius: 50%;

    background: rgba(255,255,255,.06);

    left: -55px;

    top: -70px;
}

.summary-card::after {

    content: "";

    position: absolute;

    width: 110px;

    height: 110px;

    border-radius: 50%;

    background: rgba(155,172,120,.16);

    right: -35px;

    bottom: -50px;
}

.summary-content {

    position: relative;

    z-index: 2;
}

.summary-title {

    font-size: 14px;

    opacity: .75;
}

.summary-name {

    font-size: 20px;

    font-weight: bold;

    margin-top: 5px;
}

.summary-stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 10px;

    margin-top: 18px;
}

.summary-stat {

    background:
        rgba(255,255,255,.10);

    border-radius: 15px;

    padding: 12px 8px;

    text-align: center;
}

.summary-number {

    font-size: 20px;

    font-weight: bold;
}

.summary-label {

    font-size: 10px;

    opacity: .7;

    margin-top: 4px;
}


/* =========================================================
   SECTION
========================================================= */

.section-head {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin: 22px 2px 12px;
}

.section-title {

    font-size: 17px;

    font-weight: bold;

    color: var(--primary);
}


/* =========================================================
   GRADE CARD
========================================================= */

.grade-card {

    background:
        rgba(255,255,255,.84);

    border:
        1px solid var(--border);

    border-radius: 22px;

    padding: 16px;

    margin-bottom: 12px;

    box-shadow:
        0 8px 22px rgba(52,92,73,.07);
}

.grade-top {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 12px;
}

.grade-info {

    min-width: 0;

    flex: 1;
}

.grade-title {

    font-size: 15px;

    font-weight: bold;

    color: var(--primary);

    line-height: 1.6;
}

.grade-subject {

    font-size: 11px;

    color: var(--muted);

    margin-top: 5px;
}

.grade-score {

    min-width: 75px;

    text-align: center;

    border-radius: 15px;

    padding: 9px 7px;

    background: var(--cream);

    color: var(--primary);

    font-weight: bold;
}

.score-number {

    font-size: 18px;
}

.score-max {

    font-size: 10px;

    opacity: .65;
}


/* =========================================================
   PERCENTAGE
========================================================= */

.grade-progress {

    margin-top: 14px;
}

.progress-top {

    display: flex;

    justify-content: space-between;

    font-size: 10px;

    color: var(--muted);

    margin-bottom: 6px;
}

.progress-bar {

    height: 8px;

    background: #e8ece8;

    border-radius: 20px;

    overflow: hidden;
}

.progress-fill {

    height: 100%;

    border-radius: 20px;

    background:
        linear-gradient(
            90deg,
            var(--primary),
            var(--green)
        );
}


/* =========================================================
   META
========================================================= */

.grade-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 8px;

    margin-top: 13px;
}

.meta-item {

    background: #f3f5f1;

    color: var(--muted);

    border-radius: 9px;

    padding: 6px 9px;

    font-size: 10px;
}

.grade-note {

    margin-top: 11px;

    padding: 10px;

    background: #faf8ef;

    border-radius: 12px;

    color: var(--muted);

    font-size: 11px;

    line-height: 1.7;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-box {

    background:
        rgba(255,255,255,.70);

    border:
        1px dashed rgba(52,92,73,.20);

    border-radius: 22px;

    padding: 40px 20px;

    text-align: center;

    color: var(--muted);
}

.empty-icon {

    font-size: 42px;

    margin-bottom: 12px;
}

.empty-title {

    color: var(--primary);

    font-size: 16px;

    font-weight: bold;

    margin-bottom: 6px;
}

.empty-text {

    font-size: 12px;

    line-height: 1.7;
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
        rgba(255,255,255,.82);

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

    line-height: 1;
}

.nav-item.active {

    background: var(--primary);

    color: #fff;

    box-shadow:
        0 7px 17px rgba(52,92,73,.20);
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 420px) {

    .page {
        padding: 12px;
    }

    .summary-stats {
        gap: 7px;
    }

    .summary-number {
        font-size: 17px;
    }

    .grade-card {
        padding: 14px;
    }

    .grade-title {
        font-size: 14px;
    }

}

</style>

</head>


<body>


<div class="page">


<!-- =====================================================
     HEADER
====================================================== -->

<header class="page-header">

    <a
        href="index.php"
        class="back-btn"
        aria-label="العودة"
    >
        ←
    </a>

    <div class="header-text">

        <div class="header-title">
            📊 درجاتي
        </div>

        <div class="header-subtitle">
            تابع نتائجك ومستواك الدراسي
        </div>

    </div>

</header>


<!-- =====================================================
     SUMMARY
====================================================== -->

<section class="summary-card">

    <div class="summary-content">

        <div class="summary-title">
            درجات الطالب
        </div>

        <div class="summary-name">
            <?= e($student['name']) ?>
        </div>


        <div class="summary-stats">


            <div class="summary-stat">

                <div class="summary-number">
                    <?= $totalGrades ?>
                </div>

                <div class="summary-label">
                    الاختبارات
                </div>

            </div>


            <div class="summary-stat">

                <div class="summary-number">
                    <?= e($overallPercentage) ?>%
                </div>

                <div class="summary-label">
                    النسبة العامة
                </div>

            </div>


            <div class="summary-stat">

                <div class="summary-number">
                    <?= e($totalScore) ?>
                </div>

                <div class="summary-label">
                    مجموع الدرجات
                </div>

            </div>


        </div>

    </div>

</section>


<!-- =====================================================
     GRADES
====================================================== -->

<div class="section-head">

    <div class="section-title">
        نتائج الاختبارات
    </div>

</div>


<?php if (!empty($grades)): ?>


    <?php foreach ($grades as $grade): ?>


        <?php

        $score =
            (float)$grade['score'];

        $maxScore =
            (float)$grade['max_score'];

        $percentage = 0;

        if ($maxScore > 0) {

            $percentage =
                round(
                    ($score / $maxScore) * 100,
                    1
                );
        }

        ?>


        <div class="grade-card">


            <div class="grade-top">


                <div class="grade-info">


                    <div class="grade-title">

                        <?= e($grade['title']) ?>

                    </div>


                    <?php if (!empty($grade['subject_name'])): ?>

                        <div class="grade-subject">

                            📚
                            <?= e($grade['subject_name']) ?>

                        </div>

                    <?php endif; ?>


                </div>


                <div class="grade-score">

                    <div class="score-number">

                        <?= e($score) ?>

                    </div>

                    <div class="score-max">

                        من <?= e($maxScore) ?>

                    </div>

                </div>


            </div>


            <!-- Progress -->

            <div class="grade-progress">


                <div class="progress-top">

                    <span>
                        نسبة النجاح
                    </span>

                    <strong>
                        <?= e($percentage) ?>%
                    </strong>

                </div>


                <div class="progress-bar">

                    <div
                        class="progress-fill"
                        style="width: <?= min(100, max(0, $percentage)) ?>%;"
                    ></div>

                </div>


            </div>


            <!-- Meta -->

            <div class="grade-meta">


                <?php if (!empty($grade['teacher_name'])): ?>

                    <div class="meta-item">

                        👨‍🏫
                        <?= e($grade['teacher_name']) ?>

                    </div>

                <?php endif; ?>


                <?php if (!empty($grade['exam_date'])): ?>

                    <div class="meta-item">

                        📅
                        <?= e($grade['exam_date']) ?>

                    </div>

                <?php endif; ?>


            </div>


            <!-- Notes -->

            <?php if (!empty($grade['notes'])): ?>

                <div class="grade-note">

                    📝
                    <?= e($grade['notes']) ?>

                </div>

            <?php endif; ?>


        </div>


    <?php endforeach; ?>


<?php else: ?>


    <div class="empty-box">

        <div class="empty-icon">
            📊
        </div>

        <div class="empty-title">
            لا توجد درجات حالياً
        </div>

        <div class="empty-text">
            عند إضافة نتيجة لك من لوحة التحكم أو من تطبيق الأستاذ
            ستظهر هنا تلقائياً.
        </div>

    </div>


<?php endif; ?>


</div>


<!-- =====================================================
     BOTTOM NAV
====================================================== -->

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
        href="settings.php"
        class="nav-item"
    >

        <div class="nav-icon">
            ⚙️
        </div>

        <div>
            الإعدادات
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
            الدعم الفني
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
```
