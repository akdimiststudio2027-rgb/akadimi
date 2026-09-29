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
   دالة حماية النصوص
========================================================= */

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/* =========================================================
   جلب بيانات الطالب
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone,
        stage_id,
        curriculum_id
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

$stageId      = (int)($student['stage_id'] ?? 0);
$curriculumId = (int)($student['curriculum_id'] ?? 0);

/* =========================================================
   تحديث الاشتراكات المنتهية
========================================================= */

try {

    $stmt = $pdo->prepare("
        UPDATE subscriptions
        SET status = 'expired'
        WHERE student_id = ?
          AND status = 'active'
          AND expires_at IS NOT NULL
          AND expires_at < NOW()
    ");

    $stmt->execute([$studentId]);

} catch (Exception $e) {

    // لا نوقف الصفحة إذا حدث خطأ بالتحديث

}

/* =========================================================
   جلب دورات الطالب
========================================================= */

$stmt = $pdo->prepare("
    SELECT

        s.id AS subscription_id,
        s.status AS subscription_status,
        s.starts_at,
        s.expires_at,

        c.id AS course_id,
        c.title AS course_title,
        c.description AS course_description,
        c.price,
        c.duration_days,

        t.id AS teacher_id,
        t.name AS teacher_name,
        t.image AS teacher_image,

        sub.id AS subject_id,
        sub.name AS subject_name,

        (
            SELECT COUNT(*)
            FROM course_lessons cl
            WHERE cl.course_id = c.id
              AND cl.active = 1
        ) AS lessons_count

    FROM subscriptions s

    INNER JOIN courses c
        ON c.id = s.course_id

    LEFT JOIN teachers t
        ON t.id = c.teacher_id

    LEFT JOIN subjects sub
        ON sub.id = c.subject_id

    WHERE s.student_id = ?

    ORDER BY s.id DESC
");

$stmt->execute([$studentId]);

$myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   حساب الأيام المتبقية
========================================================= */

foreach ($myCourses as &$course) {

    $course['days_remaining'] = 0;

    if (!empty($course['expires_at'])) {

        try {

            $now = new DateTime();
            $expires = new DateTime($course['expires_at']);

            if ($expires > $now) {

                $diff = $now->diff($expires);

                $course['days_remaining'] = (int)$diff->days;
            }

        } catch (Exception $e) {

            $course['days_remaining'] = 0;

        }
    }
}

unset($course);

/* =========================================================
   جلب الدورات المتاحة للطالب
========================================================= */

$availableCourses = [];

if ($stageId > 0) {

    $stmt = $pdo->prepare("
        SELECT

            c.id AS course_id,
            c.teacher_id,
            c.subject_id,

            c.title AS course_title,
            c.description AS course_description,
            c.price,
            c.duration_days,

            t.name AS teacher_name,
            t.image AS teacher_image,
            t.stage_id,
            t.curriculum_id,

            sub.name AS subject_name,

            (
                SELECT COUNT(*)
                FROM course_lessons cl
                WHERE cl.course_id = c.id
                  AND cl.active = 1
            ) AS lessons_count

        FROM courses c

        INNER JOIN teachers t
            ON t.id = c.teacher_id
           AND t.active = 1

        LEFT JOIN subjects sub
            ON sub.id = c.subject_id

        WHERE c.active = 1

          AND t.stage_id = ?

          AND (
                t.curriculum_id = ?
                OR t.curriculum_id IS NULL
              )

        ORDER BY c.id DESC
    ");

    $stmt->execute([
        $stageId,
        $curriculumId
    ]);

    $availableCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================================================
   معرفة الدورات التي الطالب مشترك بها
========================================================= */

$subscribedCourseIds = [];

foreach ($myCourses as $course) {

    if (
        isset($course['course_id']) &&
        $course['subscription_status'] === 'active'
    ) {

        $subscribedCourseIds[] = (int)$course['course_id'];
    }
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
        دوراتي - منصة أكاديمي التعليمية
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {

            margin: 0;

            font-family:
                Tahoma,
                Arial,
                sans-serif;

            background: #e8e8e8;

            color: #345c49;
        }

        a {
            text-decoration: none;
        }

        .page {

            max-width: 650px;

            margin: auto;

            min-height: 100vh;

            padding-bottom: 35px;
        }

        /* =====================================================
           HEADER
        ===================================================== */

        .header {

            background: #345c49;

            color: white;

            padding:
                20px
                17px
                25px;

            border-radius:
                0
                0
                30px
                30px;
        }

        .header-top {

            display: flex;

            align-items: center;

            gap: 12px;
        }

        .back {

            width: 44px;
            height: 44px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.13);

            display: flex;

            align-items: center;

            justify-content: center;

            color: white;

            font-size: 25px;

            flex-shrink: 0;
        }

        .header-title {

            flex: 1;
        }

        .header-title h1 {

            margin: 0;

            font-size: 22px;
        }

        .header-title p {

            margin:
                5px
                0
                0;

            font-size: 12px;

            opacity: .8;
        }

        .header-browse {

            width: 44px;
            height: 44px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.13);

            display: flex;

            align-items: center;

            justify-content: center;

            color: white;

            font-size: 20px;

            flex-shrink: 0;
        }

        /* =====================================================
           CONTENT
        ===================================================== */

        .content {

            padding:
                20px
                15px;
        }

        .welcome {

            margin-bottom: 20px;
        }

        .welcome h2 {

            margin: 0;

            font-size: 20px;

            color: #345c49;
        }

        .welcome p {

            margin:
                7px
                0
                0;

            color: #777;

            font-size: 13px;

            line-height: 1.7;
        }

        /* =====================================================
           SECTION TITLE
        ===================================================== */

        .section-head {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin:
                25px
                2px
                13px;
        }

        .section-head h2 {

            margin: 0;

            font-size: 18px;

            color: #345c49;
        }

        .section-head span {

            font-size: 12px;

            color: #888;
        }

        /* =====================================================
           COURSE CARD
        ===================================================== */

        .course-card {

            background: white;

            border-radius: 25px;

            overflow: hidden;

            margin-bottom: 20px;

            border:
                1px solid
                rgba(52,92,73,.07);

            box-shadow:
                0
                9px
                28px
                rgba(52,92,73,.09);
        }

        /* =====================================================
           COURSE IMAGE
        ===================================================== */

        .course-image {

            width: 100%;

            height: 205px;

            position: relative;

            background:
                linear-gradient(
                    135deg,
                    #345c49,
                    #9bac78
                );

            overflow: hidden;
        }

        .course-image img {

            width: 100%;

            height: 100%;

            object-fit: cover;

            display: block;
        }

        .image-overlay {

            position: absolute;

            inset: 0;

            background:
                linear-gradient(
                    to top,
                    rgba(0,0,0,.48),
                    rgba(0,0,0,0)
                );
        }

        .course-image-placeholder {

            width: 100%;
            height: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 65px;

            color: white;
        }

        .course-badge {

            position: absolute;

            top: 13px;

            right: 13px;

            background: #ede7d2;

            color: #345c49;

            padding:
                7px
                12px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;
        }

        .course-image-title {

            position: absolute;

            bottom: 14px;

            right: 15px;

            left: 15px;

            color: white;

            font-size: 18px;

            font-weight: bold;

            line-height: 1.5;

            text-shadow:
                0
                2px
                5px
                rgba(0,0,0,.4);
        }

        /* =====================================================
           COURSE DETAILS
        ===================================================== */

        .course-details {

            padding: 17px;
        }

        .course-subject {

            display: inline-block;

            background: #ede7d2;

            color: #345c49;

            padding:
                6px
                11px;

            border-radius: 20px;

            font-size: 11px;

            margin-bottom: 9px;
        }

        .course-title {

            margin: 0;

            color: #345c49;

            font-size: 18px;

            line-height: 1.55;
        }

        .teacher-line {

            margin-top: 8px;

            color: #777;

            font-size: 12px;
        }

        .teacher-line strong {

            color: #345c49;
        }

        /* =====================================================
           DESCRIPTION
        ===================================================== */

        .course-description {

            margin-top: 12px;

            color: #777;

            font-size: 12px;

            line-height: 1.8;

            background: #f7f7f7;

            border-radius: 14px;

            padding: 10px 12px;
        }

        /* =====================================================
           META
        ===================================================== */

        .course-meta {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 9px;

            margin-top: 14px;
        }

        .meta-box {

            background: #f5f5f5;

            border-radius: 15px;

            padding: 11px;

            text-align: center;
        }

        .meta-label {

            display: block;

            font-size: 10px;

            color: #888;

            margin-bottom: 5px;
        }

        .meta-value {

            display: block;

            font-size: 14px;

            font-weight: bold;

            color: #345c49;
        }

        /* =====================================================
           PRICE
        ===================================================== */

        .price-box {

            margin-top: 13px;

            background: #ede7d2;

            border-radius: 16px;

            padding: 12px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;
        }

        .price-label {

            font-size: 12px;

            color: #666;
        }

        .price-value {

            color: #345c49;

            font-size: 17px;

            font-weight: bold;
        }

        /* =====================================================
           STATUS
        ===================================================== */

        .status {

            margin-top: 13px;

            padding: 11px 13px;

            border-radius: 15px;

            text-align: center;

            font-size: 12px;
        }

        .status.active {

            background: #e5f0e9;

            color: #345c49;
        }

        .status.expired {

            background: #f8e7e7;

            color: #a33;
        }

        .status.pending {

            background: #fff5dc;

            color: #946d00;
        }

        /* =====================================================
           BUTTON
        ===================================================== */

        .open-btn {

            display: flex;

            align-items: center;

            justify-content: center;

            width: 100%;

            margin-top: 14px;

            padding: 14px;

            border-radius: 17px;

            background: #345c49;

            color: white;

            font-size: 14px;

            font-weight: bold;

            transition: .2s;
        }

        .open-btn:hover {

            opacity: .92;
        }

        .browse-all-btn {

            display: flex;

            align-items: center;

            justify-content: center;

            width: 100%;

            margin-top: 12px;

            padding: 13px;

            border-radius: 17px;

            background: #9bac78;

            color: white;

            font-size: 13px;

            font-weight: bold;
        }

        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {

            background: white;

            border-radius: 24px;

            padding:
                32px
                20px;

            text-align: center;

            box-shadow:
                0
                8px
                25px
                rgba(0,0,0,.05);

            margin-bottom: 25px;
        }

        .empty-icon {

            width: 75px;
            height: 75px;

            margin:
                0
                auto
                13px;

            border-radius: 24px;

            background: #ede7d2;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 38px;
        }

        .empty h3 {

            margin: 0;

            color: #345c49;

            font-size: 18px;
        }

        .empty p {

            color: #777;

            font-size: 12px;

            line-height: 1.9;

            margin:
                8px
                0
                15px;
        }

        .empty .browse-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            background: #345c49;

            color: white;

            padding:
                12px
                24px;

            border-radius: 15px;

            font-size: 13px;

            font-weight: bold;
        }

        /* =====================================================
           NO AVAILABLE COURSES
        ===================================================== */

        .no-courses {

            background: white;

            border-radius: 22px;

            padding:
                25px
                18px;

            text-align: center;

            color: #777;

            font-size: 13px;

            line-height: 1.8;
        }

        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 430px) {

            .course-image {
                height: 190px;
            }

            .course-title {
                font-size: 17px;
            }

            .course-details {
                padding: 15px;
            }

            .course-meta {
                gap: 7px;
            }

            .meta-box {
                padding: 10px 7px;
            }

        }

    </style>

</head>

<body>

<div class="page">

    <!-- =====================================================
         HEADER
    ===================================================== -->

    <div class="header">

        <div class="header-top">

            <a
                href="index.php"
                class="back"
            >
                ‹
            </a>

            <div class="header-title">

                <h1>
                    دوراتي
                </h1>

                <p>
                    دوراتك التعليمية والدورات المتاحة لك
                </p>

            </div>

            <a
                href="#available-courses"
                class="header-browse"
                title="تصفح الدورات"
            >
                📚
            </a>

        </div>

    </div>


    <!-- =====================================================
         CONTENT
    ===================================================== -->

    <div class="content">

        <div class="welcome">

            <h2>
                أهلاً <?= e($student['name']) ?> 👋
            </h2>

            <p>
                من هنا تقدر تدخل دوراتك أو تختار دورة جديدة من الدورات المتاحة إلك.
            </p>

        </div>


        <!-- =================================================
             دوراتي
        ================================================= -->

        <?php if (!empty($myCourses)): ?>

            <div class="section-head">

                <h2>
                    دوراتي
                </h2>

                <span>
                    <?= count($myCourses) ?> دورة
                </span>

            </div>


            <?php foreach ($myCourses as $course): ?>

                <div class="course-card">

                    <!-- صورة الدورة -->

                    <div class="course-image">

                        <?php if (!empty($course['teacher_image'])): ?>

                            <img
                                src="../uploads/teachers/<?= e($course['teacher_image']) ?>"
                                alt="<?= e($course['teacher_name']) ?>"
                            >

                            <div class="image-overlay"></div>

                        <?php else: ?>

                            <div class="course-image-placeholder">
                                👨‍🏫
                            </div>

                        <?php endif; ?>


                        <?php if (!empty($course['subject_name'])): ?>

                            <div class="course-badge">
                                <?= e($course['subject_name']) ?>
                            </div>

                        <?php endif; ?>


                        <div class="course-image-title">

                            <?= e($course['course_title']) ?>

                        </div>

                    </div>


                    <!-- تفاصيل الدورة -->

                    <div class="course-details">

                        <?php if (!empty($course['subject_name'])): ?>

                            <span class="course-subject">

                                <?= e($course['subject_name']) ?>

                            </span>

                        <?php endif; ?>


                        <h3 class="course-title">

                            <?= e($course['course_title']) ?>

                        </h3>


                        <?php if (!empty($course['teacher_name'])): ?>

                            <div class="teacher-line">

                                الأستاذ:

                                <strong>
                                    <?= e($course['teacher_name']) ?>
                                </strong>

                            </div>

                        <?php endif; ?>


                        <div class="course-meta">

                            <div class="meta-box">

                                <span class="meta-label">
                                    المحاضرات
                                </span>

                                <span class="meta-value">
                                    <?= (int)$course['lessons_count'] ?>
                                </span>

                            </div>


                            <div class="meta-box">

                                <span class="meta-label">
                                    مدة الدورة
                                </span>

                                <span class="meta-value">
                                    <?= (int)$course['duration_days'] ?> يوم
                                </span>

                            </div>


                            <?php if (!empty($course['expires_at'])): ?>

                                <div class="meta-box">

                                    <span class="meta-label">
                                        انتهاء الاشتراك
                                    </span>

                                    <span class="meta-value">

                                        <?= e(
                                            date(
                                                'Y-m-d',
                                                strtotime($course['expires_at'])
                                            )
                                        ) ?>

                                    </span>

                                </div>

                            <?php endif; ?>


                            <?php if (
                                $course['subscription_status'] === 'active'
                            ): ?>

                                <div class="meta-box">

                                    <span class="meta-label">
                                        المتبقي
                                    </span>

                                    <span class="meta-value">

                                        <?= (int)$course['days_remaining'] ?>
                                        يوم

                                    </span>

                                </div>

                            <?php endif; ?>

                        </div>


                        <?php if (
                            $course['subscription_status'] === 'active'
                        ): ?>

                            <div class="status active">

                                ✓ اشتراكك فعال

                            </div>

                            <a
                                href="course.php?course_id=<?= (int)$course['course_id'] ?>"
                                class="open-btn"
                            >
                                دخول إلى الدورة
                            </a>

                        <?php elseif (
                            $course['subscription_status'] === 'expired'
                        ): ?>

                            <div class="status expired">

                                انتهى الاشتراك

                            </div>

                            <a
                                href="course.php?course_id=<?= (int)$course['course_id'] ?>"
                                class="open-btn"
                            >
                                عرض الدورة وتجديد الاشتراك
                            </a>

                        <?php elseif (
                            $course['subscription_status'] === 'pending'
                        ): ?>

                            <div class="status pending">

                                طلب الاشتراك قيد المراجعة

                            </div>

                            <a
                                href="course.php?course_id=<?= (int)$course['course_id'] ?>"
                                class="open-btn"
                            >
                                متابعة طلب الاشتراك
                            </a>

                        <?php else: ?>

                            <a
                                href="course.php?course_id=<?= (int)$course['course_id'] ?>"
                                class="open-btn"
                            >
                                عرض الدورة
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>


        <?php else: ?>

            <!-- =================================================
                 لا توجد دورات مشتركة
            ================================================= -->

            <div class="empty">

                <div class="empty-icon">
                    📚
                </div>

                <h3>
                    ما عندك دورات مشتركة حالياً
                </h3>

                <p>
                    لا تقلق، الدورات المتاحة إلك موجودة بالأسفل.
                    اختار الدورة المناسبة واطلع على تفاصيلها.
                </p>

                <a
                    href="#available-courses"
                    class="browse-btn"
                >
                    تصفح الدورات المتاحة
                </a>

            </div>

        <?php endif; ?>


        <!-- =================================================
             الدورات المتاحة
        ================================================= -->

        <div
            id="available-courses"
            class="section-head"
        >

            <h2>
                الدورات المتاحة إلك
            </h2>

            <span>
                <?= count($availableCourses) ?> دورة
            </span>

        </div>


        <?php if (!empty($availableCourses)): ?>


            <?php foreach ($availableCourses as $course): ?>

                <?php

                $isSubscribed = in_array(
                    (int)$course['course_id'],
                    $subscribedCourseIds,
                    true
                );

                ?>


                <div class="course-card">

                    <!-- =================================================
                         صورة الدورة
                    ================================================= -->

                    <div class="course-image">

                        <?php if (!empty($course['teacher_image'])): ?>

                            <img
                                src="../uploads/teachers/<?= e($course['teacher_image']) ?>"
                                alt="<?= e($course['teacher_name']) ?>"
                            >

                            <div class="image-overlay"></div>

                        <?php else: ?>

                            <div class="course-image-placeholder">
                                👨‍🏫
                            </div>

                        <?php endif; ?>


                        <?php if (!empty($course['subject_name'])): ?>

                            <div class="course-badge">
                                <?= e($course['subject_name']) ?>
                            </div>

                        <?php endif; ?>


                        <div class="course-image-title">

                            <?= e($course['course_title']) ?>

                        </div>

                    </div>


                    <!-- =================================================
                         تفاصيل الدورة
                    ================================================= -->

                    <div class="course-details">

                        <?php if (!empty($course['subject_name'])): ?>

                            <span class="course-subject">

                                <?= e($course['subject_name']) ?>

                            </span>

                        <?php endif; ?>


                        <h3 class="course-title">

                            <?= e($course['course_title']) ?>

                        </h3>


                        <?php if (!empty($course['teacher_name'])): ?>

                            <div class="teacher-line">

                                الأستاذ:

                                <strong>
                                    <?= e($course['teacher_name']) ?>
                                </strong>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty($course['course_description'])): ?>

                            <div class="course-description">

                                <?= nl2br(
                                    e(
                                        mb_strimwidth(
                                            $course['course_description'],
                                            0,
                                            180,
                                            '...'
                                        )
                                    )
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <div class="course-meta">

                            <div class="meta-box">

                                <span class="meta-label">
                                    المحاضرات
                                </span>

                                <span class="meta-value">

                                    <?= (int)$course['lessons_count'] ?>

                                </span>

                            </div>


                            <div class="meta-box">

                                <span class="meta-label">
                                    مدة الدورة
                                </span>

                                <span class="meta-value">

                                    <?= (int)$course['duration_days'] ?>
                                    يوم

                                </span>

                            </div>

                        </div>


                        <div class="price-box">

                            <span class="price-label">
                                سعر الاشتراك
                            </span>

                            <span class="price-value">

                                <?= number_format(
                                    (float)$course['price']
                                ) ?>

                                د.ع

                            </span>

                        </div>


                        <?php if ($isSubscribed): ?>

                            <div class="status active">

                                ✓ أنت مشترك بهذه الدورة

                            </div>

                            <a
                                href="course.php?course_id=<?= (int)$course['course_id'] ?>"
                                class="open-btn"
                            >
                                دخول إلى الدورة
                            </a>

                        <?php else: ?>

                            <a
                                href="course.php?course_id=<?= (int)$course['course_id'] ?>"
                                class="open-btn"
                            >
                                عرض الدورة والاشتراك
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>


        <?php else: ?>

            <div class="no-courses">

                📚

                <br>

                حالياً لا توجد دورات متاحة لمرحلتك الدراسية.

                <br>

                سيتم إضافة الدورات الجديدة هنا عند توفرها.

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>