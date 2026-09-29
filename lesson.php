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
$lesson_id  = (int)($_GET['id'] ?? $_GET['lesson_id'] ?? 0);

if ($lesson_id <= 0) {
    die("رقم المحاضرة غير موجود في الرابط.");
}

/* =========================
   جلب المحاضرة
========================= */
$stmt = $pdo->prepare("
    SELECT
        cl.id,
        cl.course_id,
        cl.title,
        cl.lesson_number,
        cl.youtube_url,
        cl.description,
        cl.duration,
        c.title AS course_title
    FROM course_lessons cl
    INNER JOIN courses c ON c.id = cl.course_id
    WHERE cl.id = ?
      AND cl.active = 1
      AND c.active = 1
    LIMIT 1
");

$stmt->execute([$lesson_id]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    die("المحاضرة غير موجودة أو غير متاحة.");
}

$course_id = (int)$lesson['course_id'];

/* =========================
   التحقق من اشتراك الطالب
========================= */
$stmt = $pdo->prepare("
    SELECT
        id,
        status,
        starts_at,
        expires_at
    FROM subscriptions
    WHERE student_id = ?
      AND course_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([$student_id, $course_id]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subscription) {
    header("Location: my_courses.php");
    exit;
}

/* =========================
   التحقق من حالة الاشتراك
========================= */
$now = time();

$starts_at  = !empty($subscription['starts_at'])
    ? strtotime($subscription['starts_at'])
    : 0;

$expires_at = !empty($subscription['expires_at'])
    ? strtotime($subscription['expires_at'])
    : 0;

if (
    $subscription['status'] !== 'active' ||
    ($starts_at && $now < $starts_at) ||
    ($expires_at && $now > $expires_at)
) {
    die("اشتراكك في هذه الدورة غير فعال حالياً.");
}

/* =========================
   استخراج معرف فيديو يوتيوب
========================= */
function getYoutubeId($url)
{
    $url = trim($url);

    if (!$url) {
        return '';
    }

    $patterns = [
        '~youtube\.com/watch\?v=([^&]+)~i',
        '~youtu\.be/([^?&]+)~i',
        '~youtube\.com/embed/([^?&]+)~i',
        '~youtube\.com/shorts/([^?&]+)~i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return preg_replace('/[^a-zA-Z0-9_-]/', '', $matches[1]);
        }
    }

    return '';
}

$youtube_id = getYoutubeId($lesson['youtube_url']);

if (!$youtube_id) {
    die("رابط الفيديو غير صالح.");
}

$embed_url =
    "https://www.youtube.com/embed/" .
    $youtube_id .
    "?rel=0&modestbranding=1&playsinline=1";

/* =========================
   جلب محاضرات الدورة
========================= */
$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        lesson_number
    FROM course_lessons
    WHERE course_id = ?
      AND active = 1
    ORDER BY lesson_number ASC, id ASC
");

$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   تحديد السابقة والتالية
========================= */
$current_index = -1;

foreach ($lessons as $index => $item) {
    if ((int)$item['id'] === $lesson_id) {
        $current_index = $index;
        break;
    }
}

$previous_lesson = null;
$next_lesson = null;

if ($current_index > 0) {
    $previous_lesson = $lessons[$current_index - 1];
}

if (
    $current_index >= 0 &&
    $current_index < count($lessons) - 1
) {
    $next_lesson = $lessons[$current_index + 1];
}

/* =========================
   دالة الهروب
========================= */
function lesson_e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
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
        <?= lesson_e($lesson['title']) ?> | منصة أكاديمي
    </title>

    <style>

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: #f4f6f3;
            color: #26332c;
            font-family:
                Tahoma,
                Arial,
                sans-serif;
        }

        a {
            -webkit-tap-highlight-color: transparent;
        }

        /* =========================
           الصفحة
        ========================= */

        .page {
            min-height: 100vh;
            padding-bottom: 35px;
        }

        /* =========================
           الهيدر
        ========================= */

        .header {
            background: #345c49;
            color: #fff;
            border-radius: 0 0 28px 28px;
            padding: 18px 16px 24px;
            box-shadow:
                0 8px 25px rgba(52, 92, 73, 0.14);
        }

        .header-inner {
            max-width: 1100px;
            margin: auto;
        }

        .top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .back-btn {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #fff;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 14px;
            font-size: 22px;
            transition: .2s;
        }

        .back-btn:hover {
            background: rgba(255,255,255,.20);
        }

        .brand-area {
            flex: 1;
            text-align: right;
        }

        .brand {
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .brand-sub {
            font-size: 12px;
            opacity: .78;
        }

        /* =========================
           المحتوى
        ========================= */

        .container {
            width: min(1100px, calc(100% - 24px));
            margin: -8px auto 0;
            position: relative;
        }

        /* =========================
           معلومات الدورة
        ========================= */

        .course-info {
            background: #fff;
            border-radius: 20px;
            padding: 16px;
            margin-bottom: 15px;
            box-shadow:
                0 5px 20px rgba(40, 60, 50, .06);
        }

        .course-link {
            text-decoration: none;
            color: #345c49;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .course-title {
            font-size: 13px;
            color: #78827c;
            margin-top: 7px;
        }

        /* =========================
           عنوان المحاضرة
        ========================= */

        .lesson-heading {
            background: #fff;
            border-radius: 20px;
            padding: 18px;
            margin-bottom: 15px;
            box-shadow:
                0 5px 20px rgba(40, 60, 50, .06);
        }

        .lesson-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 11px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #ede7d2;
            color: #345c49;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge.gray {
            background: #e8e8e8;
            color: #59615c;
        }

        .lesson-heading h1 {
            margin: 0;
            color: #26332c;
            font-size: 23px;
            line-height: 1.6;
            font-weight: 800;
        }

        /* =========================
           الفيديو
        ========================= */

        .video-card {
            background: #fff;
            padding: 10px;
            border-radius: 22px;
            box-shadow:
                0 7px 25px rgba(40, 60, 50, .09);
            margin-bottom: 15px;
        }

        .video-wrapper {
            position: relative;
            width: 100%;
            padding-top: 56.25%;
            overflow: hidden;
            border-radius: 17px;
            background: #111;
        }

        .video-wrapper iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }

        /* =========================
           معلومات المحاضرة
        ========================= */

        .info-card {
            background: #fff;
            border-radius: 20px;
            padding: 19px;
            box-shadow:
                0 5px 20px rgba(40, 60, 50, .06);
            margin-bottom: 15px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 800;
            color: #345c49;
            margin-bottom: 13px;
        }

        .description {
            color: #58645d;
            line-height: 2;
            font-size: 14px;
            white-space: pre-line;
        }

        .duration-box {
            margin-top: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f4f6f3;
            border-radius: 14px;
            padding: 12px 14px;
            color: #4d5b53;
            font-size: 13px;
            font-weight: 700;
        }

        .duration-icon {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ede7d2;
            border-radius: 10px;
            font-size: 16px;
        }

        /* =========================
           التنقل
        ========================= */

        .navigation {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 15px;
        }

        .nav-btn {
            min-width: 0;
            text-decoration: none;
            background: #fff;
            border-radius: 18px;
            padding: 15px;
            color: #26332c;
            box-shadow:
                0 5px 18px rgba(40, 60, 50, .06);
            transition: .2s;
            border: 1px solid transparent;
        }

        .nav-btn:hover {
            border-color: #9bac78;
            transform: translateY(-1px);
        }

        .nav-label {
            color: #849087;
            font-size: 11px;
            margin-bottom: 7px;
            font-weight: 700;
        }

        .nav-title {
            font-size: 13px;
            line-height: 1.7;
            font-weight: 800;
            color: #345c49;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .nav-btn.next {
            text-align: left;
        }

        .nav-btn.previous {
            text-align: right;
        }

        .empty-nav {
            visibility: hidden;
        }

        /* =========================
           زر العودة
        ========================= */

        .course-back {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            text-decoration: none;
            background: #345c49;
            color: #fff;
            border-radius: 16px;
            padding: 14px;
            font-size: 14px;
            font-weight: 800;
            transition: .2s;
        }

        .course-back:hover {
            background: #294b3b;
        }

        /* =========================
           الشاشات الكبيرة
        ========================= */

        @media (min-width: 768px) {

            .header {
                padding: 20px 25px 30px;
            }

            .container {
                margin-top: -10px;
            }

            .lesson-heading {
                padding: 22px;
            }

            .lesson-heading h1 {
                font-size: 27px;
            }

            .video-card {
                padding: 14px;
            }

            .info-card {
                padding: 22px;
            }

        }

        /* =========================
           الموبايل
        ========================= */

        @media (max-width: 600px) {

            .header {
                border-radius: 0 0 24px 24px;
            }

            .brand {
                font-size: 14px;
            }

            .brand-sub {
                font-size: 11px;
            }

            .back-btn {
                width: 42px;
                height: 42px;
                border-radius: 13px;
            }

            .container {
                width: calc(100% - 18px);
            }

            .course-info {
                padding: 14px;
                border-radius: 18px;
            }

            .lesson-heading {
                padding: 16px;
                border-radius: 18px;
            }

            .lesson-heading h1 {
                font-size: 20px;
            }

            .video-card {
                padding: 7px;
                border-radius: 18px;
            }

            .video-wrapper {
                border-radius: 14px;
            }

            .info-card {
                padding: 16px;
                border-radius: 18px;
            }

            .navigation {
                grid-template-columns: 1fr;
            }

            .empty-nav {
                display: none;
            }

            .nav-btn.next,
            .nav-btn.previous {
                text-align: right;
            }

        }

    </style>

</head>

<body>

<div class="page">

    <!-- =========================
         الهيدر
    ========================== -->
    <header class="header">

        <div class="header-inner">

            <div class="top-row">

                <div class="brand-area">

                    <div class="brand">
                        🎓 منصة أكاديمي التعليمية
                    </div>

                    <div class="brand-sub">
                        مشاهدة المحاضرة
                    </div>

                </div>

                <a
                    href="course.php?id=<?= $course_id ?>"
                    class="back-btn"
                    aria-label="العودة للدورة"
                >
                    ←
                </a>

            </div>

        </div>

    </header>


    <!-- =========================
         المحتوى
    ========================== -->
    <main class="container">


        <!-- اسم الدورة -->
        <div class="course-info">

            <a
                href="course.php?id=<?= $course_id ?>"
                class="course-link"
            >
                📚 العودة إلى الدورة
            </a>

            <div class="course-title">
                <?= lesson_e($lesson['course_title']) ?>
            </div>

        </div>


        <!-- عنوان المحاضرة -->
        <section class="lesson-heading">

            <div class="lesson-meta">

                <span class="badge">
                    🎬 المحاضرة
                    <?= (int)$lesson['lesson_number'] ?>
                </span>

                <?php if (!empty($lesson['duration'])): ?>

                    <span class="badge gray">
                        ⏱ <?= lesson_e($lesson['duration']) ?>
                    </span>

                <?php endif; ?>

            </div>

            <h1>
                <?= lesson_e($lesson['title']) ?>
            </h1>

        </section>


        <!-- =========================
             الفيديو
        ========================== -->
        <section class="video-card">

            <div class="video-wrapper">

                <iframe
                    src="<?= lesson_e($embed_url) ?>"
                    title="<?= lesson_e($lesson['title']) ?>"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen>
                </iframe>

            </div>

        </section>


        <!-- =========================
             معلومات المحاضرة
        ========================== -->
        <?php if (
            !empty($lesson['description']) ||
            !empty($lesson['duration'])
        ): ?>

            <section class="info-card">

                <div class="section-title">
                    📖 تفاصيل المحاضرة
                </div>

                <?php if (!empty($lesson['description'])): ?>

                    <div class="description">
                        <?= lesson_e($lesson['description']) ?>
                    </div>

                <?php endif; ?>


                <?php if (!empty($lesson['duration'])): ?>

                    <div class="duration-box">

                        <div class="duration-icon">
                            ⏱
                        </div>

                        <div>
                            مدة المحاضرة:
                            <?= lesson_e($lesson['duration']) ?>
                        </div>

                    </div>

                <?php endif; ?>

            </section>

        <?php endif; ?>


        <!-- =========================
             السابقة / التالية
        ========================== -->
        <?php if ($previous_lesson || $next_lesson): ?>

            <div class="navigation">

                <?php if ($previous_lesson): ?>

                    <a
                        href="lesson.php?id=<?= (int)$previous_lesson['id'] ?>"
                        class="nav-btn previous"
                    >

                        <div class="nav-label">
                            ← المحاضرة السابقة
                        </div>

                        <div class="nav-title">
                            <?= lesson_e($previous_lesson['title']) ?>
                        </div>

                    </a>

                <?php else: ?>

                    <div class="nav-btn empty-nav"></div>

                <?php endif; ?>


                <?php if ($next_lesson): ?>

                    <a
                        href="lesson.php?id=<?= (int)$next_lesson['id'] ?>"
                        class="nav-btn next"
                    >

                        <div class="nav-label">
                            المحاضرة التالية →
                        </div>

                        <div class="nav-title">
                            <?= lesson_e($next_lesson['title']) ?>
                        </div>

                    </a>

                <?php else: ?>

                    <div class="nav-btn empty-nav"></div>

                <?php endif; ?>

            </div>

        <?php endif; ?>


        <!-- العودة للدورة -->
        <a
            href="course.php?id=<?= $course_id ?>"
            class="course-back"
        >
            ← العودة إلى قائمة المحاضرات
        </a>


    </main>

</div>

</body>
</html>