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
   رقم الدورة
   نقبل course_id وكذلك id
========================================================= */

$courseId = 0;

if (isset($_GET['course_id'])) {
    $courseId = (int) $_GET['course_id'];
} elseif (isset($_GET['id'])) {
    $courseId = (int) $_GET['id'];
}

if ($courseId <= 0) {
    header('Location: index.php');
    exit;
}

/* =========================================================
   حماية
========================================================= */

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

/* =========================================================
   بيانات الطالب
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        phone,
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

/* =========================================================
   بيانات الدورة
========================================================= */

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
        t.phone AS teacher_phone,

        sub.name AS subject_name,

        (
            SELECT COUNT(*)
            FROM course_lessons cl
            WHERE cl.course_id = c.id
              AND cl.active = 1
        ) AS lessons_count

    FROM courses c

    LEFT JOIN teachers t
        ON t.id = c.teacher_id

    LEFT JOIN subjects sub
        ON sub.id = c.subject_id

    WHERE c.id = ?
      AND c.active = 1

    LIMIT 1
");

$stmt->execute([$courseId]);

$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: index.php');
    exit;
}

/* =========================================================
   الاشتراك الحالي
========================================================= */

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

/* =========================================================
   تحديث الاشتراك المنتهي
========================================================= */

if (
    $subscription &&
    $subscription['status'] === 'active' &&
    !empty($subscription['expires_at'])
) {

    if (strtotime($subscription['expires_at']) < time()) {

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

/* =========================================================
   طلب الاشتراك الحالي
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        request_number,
        amount,
        status,
        whatsapp_number,
        notes,
        admin_notes,
        created_at,
        updated_at,
        approved_at
    FROM subscription_requests
    WHERE student_id = ?
      AND course_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([
    $studentId,
    $courseId
]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================================================
   حالة الدورة
========================================================= */

$subscriptionActive =
    $subscription &&
    $subscription['status'] === 'active';

$requestPending =
    $request &&
    in_array(
        strtolower((string)$request['status']),
        ['pending', 'new', 'waiting'],
        true
    );

$requestApproved =
    $request &&
    in_array(
        strtolower((string)$request['status']),
        ['approved', 'active', 'accepted'],
        true
    );

/* =========================================================
   السعر
========================================================= */

$price = (float)$course['price'];

if ($price > 0) {

    $priceText =
        number_format(
            $price,
            0,
            '.',
            ','
        ) . ' د.ع';

} else {

    $priceText = 'مجاناً';
}

/* =========================================================
   صورة الأستاذ
========================================================= */

$teacherImage = '';

if (!empty($course['teacher_image'])) {

    $teacherImage =
        '../uploads/teachers/' .
        ltrim(
            $course['teacher_image'],
            '/'
        );
}

/* =========================================================
   رابط الرجوع
========================================================= */

$backUrl = 'index.php';

if (!empty($course['teacher_id'])) {
    $backUrl =
        'teacher.php?id=' .
        (int)$course['teacher_id'] .
        '&subject_id=' .
        (int)$course['subject_id'];
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
    <?= e($course['title']) ?> - منصة أكاديمي
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
            #f5f7f4 45%,
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

    max-width: 650px;

    margin: auto;

    padding:
        15px 14px 40px;
}

/* =========================================================
   TOPBAR
========================================================= */

.topbar {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 15px;
}

.back-btn {

    width: 44px;

    height: 44px;

    min-width: 44px;

    border-radius: 14px;

    background:
        rgba(255,255,255,.9);

    border:
        1px solid rgba(52,92,73,.08);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 27px;

    box-shadow:
        0 7px 22px rgba(52,92,73,.08);
}

.top-title {

    flex: 1;

    min-width: 0;

    text-align: center;

    font-size: 17px;

    font-weight: 900;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}

/* =========================================================
   COURSE CARD
========================================================= */

.course-card {

    background: #fff;

    border-radius: 28px;

    overflow: hidden;

    box-shadow:
        0 15px 40px rgba(52,92,73,.10);

    border:
        1px solid rgba(52,92,73,.06);
}

/* =========================================================
   COURSE HERO
========================================================= */

.course-hero {

    padding:
        24px 18px 21px;

    background:
        linear-gradient(
            135deg,
            #345c49,
            #607b68
        );

    color: #fff;

    position: relative;
}

.subject-badge {

    display: inline-block;

    padding:
        7px 11px;

    border-radius: 12px;

    background:
        rgba(255,255,255,.15);

    font-size: 11px;

    margin-bottom: 10px;
}

.course-title {

    margin: 0;

    font-size: 23px;

    line-height: 1.6;

    font-weight: 900;
}

.teacher-line {

    margin-top: 13px;

    display: flex;

    align-items: center;

    gap: 10px;
}

.teacher-image {

    width: 45px;

    height: 45px;

    min-width: 45px;

    border-radius: 14px;

    object-fit: cover;

    background: #9bac78;
}

.teacher-placeholder {

    width: 45px;

    height: 45px;

    min-width: 45px;

    border-radius: 14px;

    background: #9bac78;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 22px;
}

.teacher-small {

    font-size: 10px;

    opacity: .72;
}

.teacher-name {

    margin-top: 2px;

    font-size: 13px;

    font-weight: 800;
}

/* =========================================================
   BODY
========================================================= */

.course-body {

    padding: 18px;
}

.description {

    color: #69776f;

    font-size: 13px;

    line-height: 1.9;

    margin-bottom: 17px;
}

/* =========================================================
   STATS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3,1fr);

    gap: 8px;
}

.stat {

    background: #f4f6f3;

    border-radius: 15px;

    padding: 12px 5px;

    text-align: center;
}

.stat-icon {

    font-size: 18px;

    margin-bottom: 4px;
}

.stat-number {

    color: #345c49;

    font-size: 14px;

    font-weight: 900;
}

.stat-label {

    color: #8a958f;

    font-size: 9px;

    margin-top: 3px;
}

/* =========================================================
   PRICE
========================================================= */

.price-box {

    margin-top: 15px;

    padding: 15px;

    border-radius: 18px;

    background: #ede7d2;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;
}

.price-label {

    color: #727e74;

    font-size: 11px;
}

.price {

    color: #345c49;

    font-size: 20px;

    font-weight: 900;
}

/* =========================================================
   STATUS
========================================================= */

.status {

    margin-top: 15px;

    padding: 14px;

    border-radius: 17px;

    text-align: center;

    font-size: 13px;

    font-weight: 800;
}

.status.active {

    background: #e5f0e9;

    color: #345c49;
}

.status.pending {

    background: #fff4d7;

    color: #876600;
}

.status.approved {

    background: #e5f0e9;

    color: #345c49;
}

/* =========================================================
   ACTION
========================================================= */

.action {

    margin-top: 17px;
}

.action-btn {

    display: flex;

    align-items: center;

    justify-content: center;

    width: 100%;

    padding: 15px;

    border-radius: 18px;

    background: #345c49;

    color: #fff;

    font-size: 14px;

    font-weight: 900;

    box-shadow:
        0 9px 22px rgba(52,92,73,.16);
}

.action-btn.secondary {

    background: #9bac78;
}

.action-note {

    margin-top: 8px;

    color: #929b95;

    text-align: center;

    font-size: 10px;

    line-height: 1.7;
}

/* =========================================================
   LESSONS
========================================================= */

.lessons-section {

    margin-top: 17px;

    background: #fff;

    border-radius: 24px;

    padding: 17px;

    box-shadow:
        0 9px 28px rgba(52,92,73,.06);
}

.section-title {

    font-size: 17px;

    font-weight: 900;

    color: #345c49;

    margin-bottom: 12px;
}

.lesson {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 11px;

    border:
        1px solid #edf0ed;

    border-radius: 16px;

    margin-bottom: 8px;

    background: #fff;
}

.lesson-number {

    width: 40px;

    height: 40px;

    min-width: 40px;

    border-radius: 12px;

    background: #e6eee9;

    color: #345c49;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 900;

    font-size: 13px;
}

.lesson-info {

    min-width: 0;

    flex: 1;
}

.lesson-title {

    color: #35453c;

    font-size: 12px;

    font-weight: 800;

    line-height: 1.6;
}

.lesson-note {

    color: #9aa49e;

    font-size: 9px;

    margin-top: 3px;
}

.lesson-lock {

    color: #9bac78;

    font-size: 16px;
}

/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding: 25px 10px;

    text-align: center;

    color: #89948e;

    font-size: 12px;
}

</style>

</head>

<body>

<div class="page">

    <!-- TOPBAR -->

    <div class="topbar">

        <a
            href="<?= e($backUrl) ?>"
            class="back-btn"
        >
            ‹
        </a>

        <div class="top-title">
            تفاصيل الدورة
        </div>

    </div>


    <!-- COURSE -->

    <div class="course-card">

        <div class="course-hero">

            <?php if (!empty($course['subject_name'])): ?>

                <div class="subject-badge">
                    📚 <?= e($course['subject_name']) ?>
                </div>

            <?php endif; ?>


            <h1 class="course-title">
                <?= e($course['title']) ?>
            </h1>


            <div class="teacher-line">

                <?php if ($teacherImage !== ''): ?>

                    <img
                        src="<?= e($teacherImage) ?>"
                        class="teacher-image"
                        alt="<?= e($course['teacher_name']) ?>"
                    >

                <?php else: ?>

                    <div class="teacher-placeholder">
                        👨‍🏫
                    </div>

                <?php endif; ?>


                <div>

                    <div class="teacher-small">
                        الأستاذ
                    </div>

                    <div class="teacher-name">
                        <?= e($course['teacher_name'] ?: 'الأستاذ') ?>
                    </div>

                </div>

            </div>

        </div>


        <div class="course-body">


            <?php if (!empty($course['description'])): ?>

                <div class="description">
                    <?= nl2br(e($course['description'])) ?>
                </div>

            <?php endif; ?>


            <!-- STATS -->

            <div class="stats">

                <div class="stat">

                    <div class="stat-icon">
                        🎓
                    </div>

                    <div class="stat-number">
                        <?= (int)$course['lessons_count'] ?>
                    </div>

                    <div class="stat-label">
                        محاضرة
                    </div>

                </div>


                <div class="stat">

                    <div class="stat-icon">
                        📅
                    </div>

                    <div class="stat-number">
                        <?= (int)$course['duration_days'] ?>
                    </div>

                    <div class="stat-label">
                        يوم
                    </div>

                </div>


                <div class="stat">

                    <div class="stat-icon">
                        📚
                    </div>

                    <div class="stat-number">
                        <?= e($course['subject_name'] ?: '—') ?>
                    </div>

                    <div class="stat-label">
                        المادة
                    </div>

                </div>

            </div>


            <!-- PRICE -->

            <div class="price-box">

                <div class="price-label">
                    سعر الاشتراك بالدورة
                </div>

                <div class="price">
                    <?= e($priceText) ?>
                </div>

            </div>


            <!-- =================================================
                 ACTION
            ================================================== -->

            <div class="action">

                <?php if ($subscriptionActive): ?>

                    <div class="status active">
                        ✓ لديك اشتراك فعال بهذه الدورة
                    </div>

                    <a
                        href="lessons.php?course_id=<?= (int)$courseId ?>&i=1"
                        class="action-btn"
                        style="margin-top:10px;"
                    >
                        ▶ دخول إلى المحاضرات
                    </a>


                <?php elseif ($requestPending): ?>

                    <div class="status pending">
                        ⏳ طلب الاشتراك قيد المراجعة
                    </div>

                    <div class="action-note">
                        تم إرسال طلبك إلى الإدارة وسيتم التواصل معك على رقم الواتساب المسجل.
                    </div>


                <?php elseif ($requestApproved): ?>

                    <div class="status approved">
                        ✓ تمت الموافقة على طلب الاشتراك
                    </div>

                    <div class="action-note">
                        سيتم تفعيل الاشتراك بعد إكمال إجراءات الدفع والتأكيد.
                    </div>


                <?php else: ?>

                    <a
                        href="subscription_request.php?course_id=<?= (int)$courseId ?>"
                        class="action-btn"
                    >
                        📝 طلب الاشتراك بالدورة
                    </a>

                    <div class="action-note">
                        عند الضغط على الزر سيتم إنشاء طلب اشتراك وإرساله إلى الإدارة.
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- =================================================
         LESSONS PREVIEW
    ================================================== -->

    <div class="lessons-section">

        <div class="section-title">
            محاضرات الدورة
        </div>

        <?php

        $stmt = $pdo->prepare("
            SELECT
                id,
                title,
                lesson_number,
                duration
            FROM course_lessons
            WHERE course_id = ?
              AND active = 1
            ORDER BY lesson_number ASC, id ASC
        ");

        $stmt->execute([$courseId]);

        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ?>


        <?php if (!empty($lessons)): ?>

            <?php foreach ($lessons as $index => $lesson): ?>

                <div class="lesson">

                    <div class="lesson-number">
                        <?= !empty($lesson['lesson_number'])
                            ? (int)$lesson['lesson_number']
                            : $index + 1 ?>
                    </div>

                    <div class="lesson-info">

                        <div class="lesson-title">
                            <?= e($lesson['title']) ?>
                        </div>

                        <div class="lesson-note">

                            <?php if ($subscriptionActive): ?>

                                اضغط لدخول المحاضرة

                            <?php else: ?>

                                🔒 متاحة بعد الاشتراك

                            <?php endif; ?>

                        </div>

                    </div>

                    <div class="lesson-lock">
                        <?= $subscriptionActive ? '▶' : '🔒' ?>
                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty">
                لا توجد محاضرات مضافة لهذه الدورة حالياً.
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>

