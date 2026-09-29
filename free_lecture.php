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


if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$student_id = (int) $_SESSION['student_id'];
$teacher_id = isset($_GET['teacher_id']) ? (int) $_GET['teacher_id'] : 0;
$subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;

if ($teacher_id <= 0) {
    die('رقم الأستاذ غير موجود.');
}


/* =========================
   بيانات الطالب
========================= */

$stmt = $pdo->prepare("
    SELECT id, name, stage_id, curriculum_id
    FROM students
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$student_id]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header('Location: login.php');
    exit;
}


/* =========================
   بيانات الأستاذ
========================= */

$sql = "
    SELECT
        t.id,
        t.name,
        t.bio,
        t.image,
        t.phone,
        t.facebook,
        t.instagram,
        t.youtube,
        t.tiktok,
        t.telegram,
        t.chat_enabled,
        t.stage_id,
        t.subject_id,
        s.name AS subject_name
    FROM teachers t
    LEFT JOIN subjects s ON s.id = t.subject_id
    WHERE t.id = ?
      AND t.active = 1
      AND t.stage_id = ?
";

$params = [
    $teacher_id,
    (int)$student['stage_id']
];

if ($subject_id > 0) {
    $sql .= " AND t.subject_id = ?";
    $params[] = $subject_id;
}

$sql .= " LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    die('الأستاذ غير موجود أو غير متاح لمرحلتك الدراسية.');
}


/* =========================
   تثبيت المادة
========================= */

$subject_id = (int)$teacher['subject_id'];
$subject_name = $teacher['subject_name'] ?? 'المادة';


/* =========================
   البحث عن المحاضرة المجانية
   نستخدم أول محاضرة فعالة تحتوي
   على رابط يوتيوب من دورات الأستاذ
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
    INNER JOIN courses c
        ON c.id = cl.course_id
    WHERE c.teacher_id = ?
      AND c.subject_id = ?
      AND c.active = 1
      AND cl.active = 1
      AND cl.youtube_url IS NOT NULL
      AND cl.youtube_url <> ''
    ORDER BY
        c.id ASC,
        cl.lesson_number ASC,
        cl.id ASC
    LIMIT 1
");

$stmt->execute([
    $teacher_id,
    $subject_id
]);

$lecture = $stmt->fetch(PDO::FETCH_ASSOC);


/* =========================
   تحويل رابط يوتيوب إلى Embed
========================= */

function youtubeEmbedUrl($url)
{
    $url = trim((string)$url);

    if ($url === '') {
        return '';
    }

    /*
     * youtu.be/VIDEO_ID
     */
    if (preg_match(
        '~youtu\.be/([A-Za-z0-9_-]{6,})~',
        $url,
        $matches
    )) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }

    /*
     * youtube.com/watch?v=VIDEO_ID
     */
    if (preg_match(
        '~youtube\.com/watch\?v=([A-Za-z0-9_-]{6,})~',
        $url,
        $matches
    )) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }

    /*
     * youtube.com/embed/VIDEO_ID
     */
    if (preg_match(
        '~youtube\.com/embed/([A-Za-z0-9_-]{6,})~',
        $url,
        $matches
    )) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }

    /*
     * youtube.com/shorts/VIDEO_ID
     */
    if (preg_match(
        '~youtube\.com/shorts/([A-Za-z0-9_-]{6,})~',
        $url,
        $matches
    )) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }

    /*
     * محاولة استخراج v من الرابط
     */
    $parts = parse_url($url);

    if (!empty($parts['query'])) {

        parse_str($parts['query'], $query);

        if (!empty($query['v'])) {

            $video_id = preg_replace(
                '/[^A-Za-z0-9_-]/',
                '',
                $query['v']
            );

            if ($video_id !== '') {
                return 'https://www.youtube.com/embed/' . $video_id;
            }
        }
    }

    return '';
}


$embed_url = '';

if ($lecture) {
    $embed_url = youtubeEmbedUrl($lecture['youtube_url']);
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
        المحاضرة المجانية | <?= e($teacher['name']) ?>
    </title>

    <style>

        :root {
            --primary: #345c49;
            --light: #e8e8e8;
            --green: #9bac78;
            --cream: #ede7d2;
            --white: #ffffff;
            --text: #26332c;
            --muted: #777;
            --border: #e2e2e2;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f7f8f6;
            font-family:
                Tahoma,
                Arial,
                sans-serif;
            color: var(--text);
        }

        a {
            text-decoration: none;
        }

        .page {
            max-width: 700px;
            margin: auto;
            min-height: 100vh;
            background: #f7f8f6;
        }

        /* =========================
           Header
        ========================= */

        .header {
            background: var(--primary);
            color: #fff;
            padding: 18px 18px 22px;
            border-radius: 0 0 24px 24px;
        }

        .header-top {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: rgba(255,255,255,.14);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .header-title {
            flex: 1;
        }

        .header-title strong {
            display: block;
            font-size: 18px;
            margin-bottom: 4px;
        }

        .header-title span {
            font-size: 13px;
            opacity: .82;
        }

        /* =========================
           Content
        ========================= */

        .content {
            padding: 20px 16px 40px;
        }

        .teacher-box {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
            box-shadow: 0 5px 18px rgba(0,0,0,.04);
        }

        .teacher-image {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            object-fit: cover;
            background: var(--cream);
            flex-shrink: 0;
        }

        .teacher-placeholder {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: var(--cream);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .teacher-info {
            min-width: 0;
        }

        .teacher-info h2 {
            margin: 0 0 7px;
            font-size: 18px;
        }

        .teacher-info p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        /* =========================
           Lecture
        ========================= */

        .lecture-card {
            background: var(--white);
            border-radius: 22px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 7px 22px rgba(0,0,0,.05);
        }

        .lecture-title {
            padding: 18px 18px 12px;
        }

        .lecture-title .badge {
            display: inline-block;
            background: var(--cream);
            color: var(--primary);
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .lecture-title h1 {
            margin: 0 0 8px;
            font-size: 21px;
            line-height: 1.5;
        }

        .lecture-title .course-name {
            color: var(--muted);
            font-size: 13px;
        }

        .video-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            background: #111;
        }

        .video-wrapper iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }

        .lecture-details {
            padding: 18px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .detail-item {
            background: var(--light);
            color: #4d554f;
            padding: 8px 11px;
            border-radius: 12px;
            font-size: 12px;
        }

        .description {
            color: #666;
            font-size: 14px;
            line-height: 1.9;
            margin-top: 12px;
        }

        /* =========================
           Empty
        ========================= */

        .empty-box {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 35px 20px;
            text-align: center;
            box-shadow: 0 5px 18px rgba(0,0,0,.04);
        }

        .empty-icon {
            width: 75px;
            height: 75px;
            margin: 0 auto 18px;
            border-radius: 24px;
            background: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
        }

        .empty-box h2 {
            margin: 0 0 10px;
            color: var(--primary);
            font-size: 19px;
        }

        .empty-box p {
            margin: 0 auto 20px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
            max-width: 420px;
        }

        /* =========================
           Back button
        ========================= */

        .main-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--primary);
            color: #fff;
            padding: 13px 22px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: bold;
        }

        .main-btn:hover {
            opacity: .92;
        }

        @media (max-width: 480px) {

            .content {
                padding: 16px 12px 35px;
            }

            .header {
                padding: 16px 14px 20px;
            }

            .lecture-title h1 {
                font-size: 19px;
            }

        }

    </style>

</head>

<body>

<div class="page">

    <!-- =========================
         Header
    ========================= -->

    <div class="header">

        <div class="header-top">

            <a
                class="back-btn"
                href="teacher.php?id=<?= $teacher_id ?>&subject_id=<?= $subject_id ?>"
            >
                ←
            </a>

            <div class="header-title">

                <strong>المحاضرة المجانية</strong>

                <span>
                    <?= e($subject_name) ?>
                </span>

            </div>

        </div>

    </div>


    <div class="content">


        <!-- =========================
             Teacher
        ========================= -->

        <div class="teacher-box">

            <?php if (!empty($teacher['image'])): ?>

                <img
                    class="teacher-image"
                    src="<?= e($teacher['image']) ?>"
                    alt="<?= e($teacher['name']) ?>"
                >

            <?php else: ?>

                <div class="teacher-placeholder">
                    <?= e(mb_substr($teacher['name'], 0, 1, 'UTF-8')) ?>
                </div>

            <?php endif; ?>


            <div class="teacher-info">

                <h2>
                    <?= e($teacher['name']) ?>
                </h2>

                <p>
                    <?= e($subject_name) ?>
                </p>

            </div>

        </div>


        <?php if ($lecture && $embed_url): ?>

            <!-- =========================
                 Lecture
            ========================= -->

            <div class="lecture-card">

                <div class="lecture-title">

                    <div class="badge">
                        🎁 محاضرة مجانية
                    </div>

                    <h1>
                        <?= e($lecture['title']) ?>
                    </h1>

                    <div class="course-name">

                        ضمن دورة:
                        <?= e($lecture['course_title']) ?>

                    </div>

                </div>


                <div class="video-wrapper">

                    <iframe
                        src="<?= e($embed_url) ?>"
                        title="<?= e($lecture['title']) ?>"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen
                    ></iframe>

                </div>


                <div class="lecture-details">

                    <div class="detail-row">

                        <?php if (!empty($lecture['lesson_number'])): ?>

                            <div class="detail-item">
                                📚 المحاضرة رقم
                                <?= e($lecture['lesson_number']) ?>
                            </div>

                        <?php endif; ?>


                        <?php if (!empty($lecture['duration'])): ?>

                            <div class="detail-item">
                                ⏱️ <?= e($lecture['duration']) ?>
                            </div>

                        <?php endif; ?>

                    </div>


                    <?php if (!empty($lecture['description'])): ?>

                        <div class="description">

                            <?= nl2br(e($lecture['description'])) ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


        <?php else: ?>

            <!-- =========================
                 No Lecture
            ========================= -->

            <div class="empty-box">

                <div class="empty-icon">
                    🎬
                </div>

                <h2>
                    المحاضرة المجانية غير متوفرة حالياً
                </h2>

                <p>
                    لم تتم إضافة محاضرة مجانية لهذا الأستاذ
                    والمادة حتى الآن.
                    <br>
                    يمكنك العودة إلى صفحة الأستاذ
                    للاطلاع على الدورات المتاحة.
                </p>

                <a
                    class="main-btn"
                    href="teacher.php?id=<?= $teacher_id ?>&subject_id=<?= $subject_id ?>"
                >
                    ← العودة إلى صفحة الأستاذ
                </a>

            </div>

        <?php endif; ?>


    </div>

</div>

</body>

</html>