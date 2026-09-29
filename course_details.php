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
| بيانات الطالب
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, name
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


/*
|--------------------------------------------------------------------------
| بيانات الدورة
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.title,
        c.description,
        c.price,
        c.duration_days,

        s.id AS subject_id,
        s.name AS subject_name,

        t.id AS teacher_id,
        t.name AS teacher_name,
        t.image AS teacher_image

    FROM courses c

    INNER JOIN subjects s
        ON s.id = c.subject_id

    INNER JOIN teachers t
        ON t.id = c.teacher_id

    WHERE c.id = ?
      AND c.active = 1

    LIMIT 1
");

$stmt->execute([$course_id]);

$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die('الدورة غير موجودة أو غير متاحة حالياً.');
}


/*
|--------------------------------------------------------------------------
| عدد المحاضرات
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM course_lessons
    WHERE course_id = ?
      AND active = 1
");

$stmt->execute([$course_id]);

$lessons_count = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| هل الطالب مشترك بالدورة؟
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        starts_at,
        expires_at,
        status

    FROM subscriptions

    WHERE student_id = ?
      AND course_id = ?

    ORDER BY id DESC

    LIMIT 1
");

$stmt->execute([
    $student_id,
    $course_id
]);

$subscription = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| تحديد الاشتراك الفعال
|--------------------------------------------------------------------------
*/

$is_active_subscription = false;

if ($subscription) {

    if (
        $subscription['status'] === 'active'
        &&
        (
            empty($subscription['expires_at'])
            ||
            strtotime($subscription['expires_at']) >= time()
        )
    ) {
        $is_active_subscription = true;
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
    <?= e($course['title']) ?> | منصة أكاديمي
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f5f8fc;
    color: #172033;
    font-family: Tahoma, Arial, sans-serif;
}

.container {
    max-width: 900px;
    margin: auto;
    padding: 18px;
}


/* Header */

.header {
    background: #fff;
    border: 1px solid #e3e9f0;
    border-radius: 20px;

    padding: 18px 20px;

    margin-bottom: 18px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;
}

.welcome {
    color: #8996a7;
    font-size: 11px;
    margin-bottom: 5px;
}

.student-name {
    font-size: 20px;
    font-weight: bold;
}

.back {
    text-decoration: none;

    color: #078f83;
    background: #e8f8f5;

    padding: 10px 14px;

    border-radius: 10px;

    font-size: 12px;
}


/* الدورة */

.course-box {
    background: white;

    border: 1px solid #e1e7ef;

    border-radius: 22px;

    overflow: hidden;
}


/* العنوان */

.course-top {
    background: #078f83;

    color: white;

    padding: 28px 24px;
}

.course-label {
    font-size: 11px;
    opacity: .8;
    margin-bottom: 8px;
}

.course-title {
    font-size: 25px;
    font-weight: bold;
    line-height: 1.5;
}

.course-subject {
    font-size: 12px;
    opacity: .85;
    margin-top: 8px;
}


/* المحتوى */

.course-content {
    padding: 24px;
}


/* الأستاذ */

.teacher {
    display: flex;

    align-items: center;

    gap: 13px;

    padding-bottom: 20px;

    border-bottom: 1px solid #edf0f4;
}

.teacher-image {
    width: 58px;
    height: 58px;

    flex: 0 0 58px;

    border-radius: 15px;

    overflow: hidden;

    background: #e8f8f5;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 26px;
}

.teacher-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.teacher-label {
    color: #8996a7;
    font-size: 10px;
    margin-bottom: 4px;
}

.teacher-name {
    font-size: 15px;
    font-weight: bold;
}


/* الوصف */

.description-title {
    font-size: 16px;
    font-weight: bold;

    margin-top: 22px;
    margin-bottom: 10px;
}

.description {
    color: #667386;

    font-size: 13px;

    line-height: 2;
}


/* المعلومات */

.info-grid {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 10px;

    margin-top: 22px;
}

.info-item {
    background: #f6f8fa;

    border-radius: 13px;

    padding: 14px;

    text-align: center;
}

.info-icon {
    font-size: 20px;
    margin-bottom: 7px;
}

.info-value {
    font-size: 14px;
    font-weight: bold;
}

.info-label {
    color: #8996a7;
    font-size: 9px;
    margin-top: 4px;
}


/* السعر */

.price-box {
    margin-top: 22px;

    background: #f3fbfa;

    border: 1px solid #d9f0ec;

    border-radius: 16px;

    padding: 18px;

    text-align: center;
}

.price-label {
    color: #8996a7;
    font-size: 11px;
}

.price {
    color: #078f83;

    font-size: 27px;

    font-weight: bold;

    margin-top: 5px;
}


/* زر الاشتراك */

.subscribe-btn {
    display: block;

    width: 100%;

    margin-top: 18px;

    padding: 14px;

    border: 0;

    border-radius: 12px;

    background: #078f83;

    color: white;

    text-decoration: none;

    text-align: center;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;
}


/* اشتراك فعال */

.active-box {
    margin-top: 18px;

    background: #ecfdf5;

    border: 1px solid #bbf7d0;

    border-radius: 15px;

    padding: 17px;

    text-align: center;

    color: #166534;
}

.active-title {
    font-size: 15px;
    font-weight: bold;
    margin-bottom: 6px;
}

.active-info {
    font-size: 11px;
}


/* موبايل */

@media (max-width: 600px) {

    .container {
        padding: 12px;
    }

    .course-top {
        padding: 22px 18px;
    }

    .course-title {
        font-size: 21px;
    }

    .course-content {
        padding: 18px;
    }

    .info-grid {
        grid-template-columns: 1fr 1fr;
    }

}

</style>

</head>

<body>

<div class="container">


    <!-- الهيدر -->

    <div class="header">

        <div>

            <div class="welcome">
                أهلاً بك
            </div>

            <div class="student-name">
                <?= e($student['name']) ?>
            </div>

        </div>

        <a
            href="courses.php?teacher_id=<?= (int)$course['teacher_id'] ?>&subject_id=<?= (int)$course['subject_id'] ?>"
            class="back"
        >
            ← الدورات
        </a>

    </div>


    <!-- الدورة -->

    <div class="course-box">


        <div class="course-top">

            <div class="course-label">
                دورة تعليمية
            </div>

            <div class="course-title">
                <?= e($course['title']) ?>
            </div>

            <div class="course-subject">
                📚 <?= e($course['subject_name']) ?>
            </div>

        </div>


        <div class="course-content">


            <!-- الأستاذ -->

            <div class="teacher">

                <div class="teacher-image">

                    <?php if (!empty($course['teacher_image'])): ?>

                        <img
                            src="../uploads/teachers/<?= e($course['teacher_image']) ?>"
                            alt="<?= e($course['teacher_name']) ?>"
                        >

                    <?php else: ?>

                        👨‍🏫

                    <?php endif; ?>

                </div>

                <div>

                    <div class="teacher-label">
                        الأستاذ
                    </div>

                    <div class="teacher-name">
                        <?= e($course['teacher_name']) ?>
                    </div>

                </div>

            </div>


            <!-- الوصف -->

            <div class="description-title">
                عن الدورة
            </div>

            <div class="description">

                <?= nl2br(e(
                    $course['description']
                    ?: 'لا يوجد وصف للدورة حالياً.'
                )) ?>

            </div>


            <!-- معلومات الدورة -->

            <div class="info-grid">


                <div class="info-item">

                    <div class="info-icon">
                        🎥
                    </div>

                    <div class="info-value">
                        <?= $lessons_count ?>
                    </div>

                    <div class="info-label">
                        محاضرة
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-icon">
                        📅
                    </div>

                    <div class="info-value">
                        <?= (int)$course['duration_days'] ?>
                    </div>

                    <div class="info-label">
                        يوم اشتراك
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-icon">
                        👨‍🏫
                    </div>

                    <div class="info-value">
                        <?= e($course['teacher_name']) ?>
                    </div>

                    <div class="info-label">
                        الأستاذ
                    </div>

                </div>


            </div>


            <!-- السعر -->

            <div class="price-box">

                <div class="price-label">
                    سعر الاشتراك
                </div>

                <div class="price">

                    <?= number_format((float)$course['price']) ?>

                    د.ع

                </div>

            </div>


            <?php if ($is_active_subscription): ?>


                <div class="active-box">

                    <div class="active-title">
                        ✅ أنت مشترك بهذه الدورة
                    </div>

                    <div class="active-info">

                        يمكنك الآن الدخول إلى محاضرات الدورة.

                    </div>

                </div>


                <a
                    href="lessons.php"
                    class="subscribe-btn"
                >
                    🎥 الذهاب إلى المحاضرات
                </a>


            <?php else: ?>


                <a
                    href="subscribe.php?course_id=<?= (int)$course['id'] ?>"
                    class="subscribe-btn"
                >
                    💳 اشترك الآن
                </a>


            <?php endif; ?>


        </div>

    </div>

</div>

</body>

</html>