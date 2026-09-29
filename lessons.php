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


$studentId = (int) $_SESSION['student_id'];
$courseId  = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;

if ($courseId <= 0) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| حماية النصوص
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/*
|--------------------------------------------------------------------------
| بيانات الطالب
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.name,
        s.stage_id,
        s.curriculum_id,
        s.active,
        st.name AS stage_name,
        c.name AS curriculum_name
    FROM students s
    LEFT JOIN stages st
        ON st.id = s.stage_id
    LEFT JOIN curricula c
        ON c.id = s.curriculum_id
    WHERE s.id = ?
    LIMIT 1
");

$stmt->execute([$studentId]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student || (int)$student['active'] !== 1) {
    session_destroy();
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| بيانات الدورة
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.teacher_id,
        c.subject_id,
        c.title,
        c.description,
        c.price,
        c.duration_days,
        c.active,

        t.name AS teacher_name,
        t.image AS teacher_image,
        t.stage_id AS teacher_stage_id,
        t.curriculum_id AS teacher_curriculum_id,

        sub.name AS subject_name

    FROM courses c

    INNER JOIN teachers t
        ON t.id = c.teacher_id

    LEFT JOIN subjects sub
        ON sub.id = c.subject_id

    WHERE c.id = ?
      AND c.active = 1
      AND t.active = 1

    LIMIT 1
");

$stmt->execute([$courseId]);

$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| توافق الدورة مع الطالب
|--------------------------------------------------------------------------
*/

$stageMatch =
    (int)$course['teacher_stage_id'] ===
    (int)$student['stage_id'];

$curriculumMatch =
    empty($course['teacher_curriculum_id']) ||
    (int)$course['teacher_curriculum_id'] ===
    (int)$student['curriculum_id'];

if (!$stageMatch || !$curriculumMatch) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| التحقق من الاشتراك
|--------------------------------------------------------------------------
*/

$subscription = null;

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

$stmt->execute([
    $studentId,
    $courseId
]);

$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| الدورة المجانية
|--------------------------------------------------------------------------
*/

$isFreeCourse = ((float)$course['price'] <= 0);

/*
|--------------------------------------------------------------------------
| التحقق من الوصول
|--------------------------------------------------------------------------
*/

$hasAccess = false;
$daysRemaining = 0;

if ($isFreeCourse) {

    $hasAccess = true;

} elseif ($subscription) {

    if (
        $subscription['status'] === 'active' &&
        !empty($subscription['expires_at'])
    ) {

        $expiresTimestamp =
            strtotime($subscription['expires_at']);

        if ($expiresTimestamp > time()) {

            $hasAccess = true;

            $secondsRemaining =
                $expiresTimestamp - time();

            $daysRemaining = max(
                1,
                (int)ceil($secondsRemaining / 86400)
            );

        } else {

            $stmt = $pdo->prepare("
                UPDATE subscriptions
                SET status = 'expired'
                WHERE id = ?
            ");

            $stmt->execute([
                (int)$subscription['id']
            ]);

            $subscription['status'] = 'expired';
        }
    }
}

/*
|--------------------------------------------------------------------------
| إذا لا يوجد وصول
|--------------------------------------------------------------------------
*/

if (!$hasAccess) {

    header(
        'Location: course.php?course_id=' .
        $courseId
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| المحاضرات
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        course_id,
        title,
        lesson_number,
        youtube_url,
        description,
        duration,
        active
    FROM course_lessons
    WHERE course_id = ?
      AND active = 1
    ORDER BY lesson_number ASC, id ASC
");

$stmt->execute([$courseId]);

$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| تحويل YouTube إلى Embed
|--------------------------------------------------------------------------
*/

function youtubeEmbedUrl($url)
{
    $url = trim((string)$url);

    if ($url === '') {
        return '';
    }

    /*
    | youtube.com/watch?v=
    */

    if (preg_match(
        '/youtube\.com\/watch\?v=([^&]+)/i',
        $url,
        $matches
    )) {

        return
            'https://www.youtube.com/embed/' .
            rawurlencode($matches[1]);
    }

    /*
    | youtu.be/
    */

    if (preg_match(
        '/youtu\.be\/([^?&]+)/i',
        $url,
        $matches
    )) {

        return
            'https://www.youtube.com/embed/' .
            rawurlencode($matches[1]);
    }

    /*
    | youtube.com/embed/
    */

    if (preg_match(
        '/youtube\.com\/embed\/([^?&]+)/i',
        $url,
        $matches
    )) {

        return
            'https://www.youtube.com/embed/' .
            rawurlencode($matches[1]);
    }

    /*
    | Shorts
    */

    if (preg_match(
        '/youtube\.com\/shorts\/([^?&]+)/i',
        $url,
        $matches
    )) {

        return
            'https://www.youtube.com/embed/' .
            rawurlencode($matches[1]);
    }

    return '';
}

/*
|--------------------------------------------------------------------------
| أول محاضرة
|--------------------------------------------------------------------------
*/

$firstLesson = !empty($lessons)
    ? $lessons[0]
    : null;

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
        <?= e($course['title']) ?> - المحاضرات
    </title>

    <style>

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            margin: 0;

            font-family:
                Tahoma,
                Arial,
                sans-serif;

            background:
                linear-gradient(
                    180deg,
                    #ede7d2 0%,
                    #f5f2e8 48%,
                    #ffffff 100%
                );

            color: #345c49;

            min-height: 100vh;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .page {
            width: 100%;
            max-width: 700px;

            margin: auto;

            padding:
                15px
                15px
                40px;
        }

        /* =====================================================
           Header
        ===================================================== */

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-bottom: 15px;
        }

        .back-btn {
            width: 44px;
            height: 44px;

            border-radius: 14px;

            background:
                rgba(255,255,255,.85);

            border:
                1px solid
                rgba(52,92,73,.08);

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 26px;

            box-shadow:
                0 7px 22px
                rgba(52,92,73,.08);
        }

        .top-title {
            flex: 1;

            text-align: center;

            font-size: 17px;

            font-weight: 900;

            margin:
                0
                12px;
        }

        .top-space {
            width: 44px;
        }

        /* =====================================================
           Course Header
        ===================================================== */

        .course-header {
            background:
                linear-gradient(
                    135deg,
                    #345c49,
                    #52755f
                );

            color: white;

            border-radius: 27px;

            padding: 21px;

            margin-bottom: 15px;

            box-shadow:
                0 16px 35px
                rgba(52,92,73,.20);
        }

        .course-small {
            font-size: 12px;

            opacity: .78;

            margin-bottom: 6px;
        }

        .course-title {
            font-size: 21px;

            font-weight: 900;

            line-height: 1.65;

            margin: 0;
        }

        .course-meta {
            display: flex;

            flex-wrap: wrap;

            gap: 8px;

            margin-top: 13px;
        }

        .course-meta span {
            background:
                rgba(255,255,255,.14);

            border:
                1px solid
                rgba(255,255,255,.12);

            padding:
                7px
                10px;

            border-radius: 12px;

            font-size: 11px;
        }

        /* =====================================================
           Subscription
        ===================================================== */

        .subscription-bar {
            background:
                #e4f0e7;

            border:
                1px solid
                #c1d9c7;

            color: #345c49;

            border-radius: 19px;

            padding: 13px 15px;

            margin-bottom: 15px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;
        }

        .subscription-title {
            font-size: 13px;

            font-weight: 900;
        }

        .subscription-days {
            font-size: 12px;

            color: #718078;
        }

        /* =====================================================
           Video
        ===================================================== */

        .video-card {
            background: #111;

            border-radius: 23px;

            overflow: hidden;

            margin-bottom: 17px;

            box-shadow:
                0 14px 35px
                rgba(0,0,0,.16);
        }

        .video-wrapper {
            position: relative;

            width: 100%;

            padding-top: 56.25%;

            background: #111;
        }

        .video-wrapper iframe {
            position: absolute;

            inset: 0;

            width: 100%;
            height: 100%;

            border: 0;
        }

        .video-placeholder {
            position: absolute;

            inset: 0;

            display: flex;

            align-items: center;
            justify-content: center;

            text-align: center;

            color: #ddd;

            padding: 20px;

            font-size: 14px;

            line-height: 1.8;
        }

        /* =====================================================
           Current Lesson
        ===================================================== */

        .current-lesson {
            background:
                rgba(255,255,255,.92);

            border-radius: 22px;

            padding: 17px;

            margin-bottom: 15px;

            box-shadow:
                0 9px 25px
                rgba(52,92,73,.07);
        }

        .lesson-number {
            color: #8a968e;

            font-size: 12px;

            margin-bottom: 5px;
        }

        .current-title {
            font-size: 18px;

            font-weight: 900;

            line-height: 1.6;
        }

        .current-description {
            margin-top: 9px;

            color: #707d75;

            font-size: 13px;

            line-height: 1.8;

            white-space: pre-line;
        }

        /* =====================================================
           Lessons List
        ===================================================== */

        .section-title {
            font-size: 18px;

            font-weight: 900;

            margin:
                20px
                2px
                12px;
        }

        .lesson-card {
            width: 100%;

            background: white;

            border-radius: 20px;

            padding: 13px;

            margin-bottom: 10px;

            border:
                1px solid
                rgba(52,92,73,.06);

            box-shadow:
                0 7px 21px
                rgba(52,92,73,.055);

            display: flex;

            align-items: center;

            gap: 11px;

            cursor: pointer;

            transition: .18s;
        }

        .lesson-card:active {
            transform: scale(.985);
        }

        .lesson-card.selected {
            background:
                #edf3ed;

            border-color:
                #b9cfbd;
        }

        .lesson-icon {
            width: 50px;
            height: 50px;

            flex-shrink: 0;

            border-radius: 16px;

            background:
                #e8e8e8;

            display: flex;

            align-items: center;
            justify-content: center;

            color: #345c49;

            font-size: 20px;

            font-weight: 900;
        }

        .lesson-card.selected .lesson-icon {
            background:
                #345c49;

            color: white;
        }

        .lesson-info {
            flex: 1;

            min-width: 0;
        }

        .lesson-label {
            color: #89958d;

            font-size: 11px;

            margin-bottom: 4px;
        }

        .lesson-title {
            color: #345c49;

            font-size: 14px;

            font-weight: 900;

            line-height: 1.55;
        }

        .lesson-duration {
            color: #8b958f;

            font-size: 11px;

            white-space: nowrap;
        }

        .lesson-arrow {
            color: #9bac78;

            font-size: 18px;
        }

        /* =====================================================
           Empty
        ===================================================== */

        .empty {
            background: white;

            border-radius: 22px;

            padding: 35px 20px;

            text-align: center;

            box-shadow:
                0 8px 25px
                rgba(52,92,73,.06);
        }

        .empty-icon {
            font-size: 45px;

            margin-bottom: 10px;
        }

        .empty-title {
            font-size: 17px;

            font-weight: 900;

            margin-bottom: 7px;
        }

        .empty-text {
            color: #7e8982;

            font-size: 13px;

            line-height: 1.8;
        }

        .student-mini {
            text-align: center;

            color: #89958d;

            font-size: 12px;

            margin-top: 27px;
        }

    </style>

</head>

<body>

<div class="page">

    <!-- =====================================================
         Header
    ====================================================== -->

    <div class="topbar">

        <a
            href="course.php?course_id=<?= $courseId ?>"
            class="back-btn"
        >
            ‹
        </a>

        <div class="top-title">
            محاضرات الدورة
        </div>

        <div class="top-space"></div>

    </div>


    <!-- =====================================================
         Course Header
    ====================================================== -->

    <div class="course-header">

        <div class="course-small">
            <?= e($course['teacher_name']) ?>
        </div>

        <h1 class="course-title">
            <?= e($course['title']) ?>
        </h1>

        <div class="course-meta">

            <?php if (!empty($course['subject_name'])): ?>

                <span>
                    📚 <?= e($course['subject_name']) ?>
                </span>

            <?php endif; ?>


            <span>
                🎥 <?= count($lessons) ?> محاضرة
            </span>


            <span>
                ⏱ <?= (int)$course['duration_days'] ?> يوم
            </span>

        </div>

    </div>


    <!-- =====================================================
         Subscription
    ====================================================== -->

    <div class="subscription-bar">

        <div>

            <div class="subscription-title">
                <?= $isFreeCourse ? '🎁 دورة مجانية' : '✅ اشتراك فعال' ?>
            </div>

            <div class="subscription-days">

                <?php if ($isFreeCourse): ?>

                    يمكنك مشاهدة جميع المحاضرات.

                <?php else: ?>

                    متبقي
                    <strong><?= $daysRemaining ?></strong>
                    يوم على الاشتراك.

                <?php endif; ?>

            </div>

        </div>

        <div>
            🔐
        </div>

    </div>


    <?php if (!empty($lessons)): ?>

        <!-- =================================================
             Video
        ================================================== -->

        <div class="video-card">

            <div
                class="video-wrapper"
                id="videoWrapper"
            >

                <?php

                $firstEmbed =
                    youtubeEmbedUrl(
                        $firstLesson['youtube_url']
                    );

                ?>

                <?php if ($firstEmbed): ?>

                    <iframe
                        id="youtubeFrame"
                        src="<?= e($firstEmbed) ?>?rel=0&modestbranding=1"
                        title="<?= e($firstLesson['title']) ?>"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen
                    ></iframe>

                <?php else: ?>

                    <div class="video-placeholder">
                        رابط الفيديو غير صالح أو غير متوفر لهذه المحاضرة.
                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- =================================================
             Current Lesson
        ================================================== -->

        <div
            class="current-lesson"
            id="currentLesson"
        >

            <div
                class="lesson-number"
                id="currentNumber"
            >
                المحاضرة
                <?= (int)$firstLesson['lesson_number'] ?>
            </div>

            <div
                class="current-title"
                id="currentTitle"
            >
                <?= e($firstLesson['title']) ?>
            </div>


            <?php if (!empty($firstLesson['description'])): ?>

                <div
                    class="current-description"
                    id="currentDescription"
                >
                    <?= e($firstLesson['description']) ?>
                </div>

            <?php else: ?>

                <div
                    class="current-description"
                    id="currentDescription"
                    style="display:none;"
                ></div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             Lessons
        ================================================== -->

        <div class="section-title">
            جميع المحاضرات
        </div>


        <div id="lessonsList">

            <?php foreach ($lessons as $index => $lesson): ?>

                <div
                    class="lesson-card <?= $index === 0 ? 'selected' : '' ?>"
                    data-index="<?= $index ?>"
                    data-url="<?= e($lesson['youtube_url']) ?>"
                    data-title="<?= e($lesson['title']) ?>"
                    data-number="<?= (int)$lesson['lesson_number'] ?>"
                    data-description="<?= e($lesson['description']) ?>"
                >

                    <div class="lesson-icon">
                        <?= $index + 1 ?>
                    </div>


                    <div class="lesson-info">

                        <div class="lesson-label">
                            المحاضرة
                            <?= (int)$lesson['lesson_number'] ?>
                        </div>

                        <div class="lesson-title">
                            <?= e($lesson['title']) ?>
                        </div>

                    </div>


                    <?php if (!empty($lesson['duration'])): ?>

                        <div class="lesson-duration">
                            <?= e($lesson['duration']) ?>
                        </div>

                    <?php endif; ?>


                    <div class="lesson-arrow">
                        ‹
                    </div>

                </div>

            <?php endforeach; ?>

        </div>


    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">
                🎥
            </div>

            <div class="empty-title">
                لا توجد محاضرات حالياً
            </div>

            <div class="empty-text">
                لم تتم إضافة محاضرات لهذه الدورة حتى الآن.
            </div>

        </div>

    <?php endif; ?>


    <div class="student-mini">

        أهلاً بك
        <?= e($student['name']) ?>
        في منصة أكاديمي 🌿

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| تحويل رابط YouTube إلى Embed
|--------------------------------------------------------------------------
*/

function youtubeEmbedUrl(url) {

    if (!url) {
        return '';
    }

    url = url.trim();

    let match;

    /*
    | youtube.com/watch?v=
    */

    match =
        url.match(
            /youtube\.com\/watch\?v=([^&]+)/i
        );

    if (match) {

        return (
            'https://www.youtube.com/embed/' +
            encodeURIComponent(match[1])
        );
    }

    /*
    | youtu.be/
    */

    match =
        url.match(
            /youtu\.be\/([^?&]+)/i
        );

    if (match) {

        return (
            'https://www.youtube.com/embed/' +
            encodeURIComponent(match[1])
        );
    }

    /*
    | youtube.com/embed/
    */

    match =
        url.match(
            /youtube\.com\/embed\/([^?&]+)/i
        );

    if (match) {

        return (
            'https://www.youtube.com/embed/' +
            encodeURIComponent(match[1])
        );
    }

    /*
    | Shorts
    */

    match =
        url.match(
            /youtube\.com\/shorts\/([^?&]+)/i
        );

    if (match) {

        return (
            'https://www.youtube.com/embed/' +
            encodeURIComponent(match[1])
        );
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| اختيار المحاضرة
|--------------------------------------------------------------------------
*/

const lessonCards =
    document.querySelectorAll('.lesson-card');

const youtubeFrame =
    document.getElementById('youtubeFrame');

const currentNumber =
    document.getElementById('currentNumber');

const currentTitle =
    document.getElementById('currentTitle');

const currentDescription =
    document.getElementById('currentDescription');


lessonCards.forEach(function(card) {

    card.addEventListener('click', function() {

        /*
        | إزالة التحديد
        */

        lessonCards.forEach(function(item) {

            item.classList.remove('selected');

        });


        /*
        | تحديد الحالية
        */

        card.classList.add('selected');


        /*
        | البيانات
        */

        const url =
            card.dataset.url || '';

        const title =
            card.dataset.title || '';

        const number =
            card.dataset.number || '';

        const description =
            card.dataset.description || '';


        /*
        | الفيديو
        */

        const embedUrl =
            youtubeEmbedUrl(url);


        if (youtubeFrame && embedUrl) {

            youtubeFrame.src =
                embedUrl +
                '?rel=0&modestbranding=1';

        }


        /*
        | بيانات المحاضرة
        */

        if (currentNumber) {

            currentNumber.innerText =
                'المحاضرة ' + number;

        }


        if (currentTitle) {

            currentTitle.innerText =
                title;

        }


        if (currentDescription) {

            if (description.trim() !== '') {

                currentDescription.innerText =
                    description;

                currentDescription.style.display =
                    'block';

            } else {

                currentDescription.innerText =
                    '';

                currentDescription.style.display =
                    'none';
            }
        }


        /*
        | الانتقال للفيديو
        */

        window.scrollTo({

            top: 0,

            behavior: 'smooth'

        });

    });

});

</script>

</body>

</html>