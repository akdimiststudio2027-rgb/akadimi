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

$message = '';
$error = '';

/* =========================================================
   حفظ التعديلات
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name   = trim($_POST['name'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $gender = trim($_POST['gender'] ?? '');

    /* التحقق */

    if ($name === '') {

        $error = 'يرجى إدخال اسم الطالب.';

    } elseif ($phone === '') {

        $error = 'يرجى إدخال رقم الهاتف.';

    } elseif (!in_array($gender, ['', 'male', 'female'], true)) {

        $error = 'اختيار الجنس غير صحيح.';

    } else {

        try {

            /*
             * التأكد من أن رقم الهاتف
             * غير مستخدم من طالب آخر
             */

            $stmt = $pdo->prepare("
                SELECT id
                FROM students
                WHERE phone = ?
                  AND id <> ?
                LIMIT 1
            ");

            $stmt->execute([
                $phone,
                $student_id
            ]);

            $phone_exists = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($phone_exists) {

                $error = 'رقم الهاتف مستخدم من حساب طالب آخر.';

            } else {

                /*
                 * تحديث بيانات الطالب
                 */

                $stmt = $pdo->prepare("
                    UPDATE students
                    SET
                        name = ?,
                        phone = ?,
                        gender = ?
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $name,
                    $phone,
                    $gender !== '' ? $gender : null,
                    $student_id
                ]);

                $message = 'تم حفظ بيانات حسابك بنجاح.';

            }

        } catch (PDOException $e) {

            $error = 'حدث خطأ أثناء حفظ البيانات.';

        }
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
        gender,
        governorate_id,
        stage_id,
        curriculum_id,
        active
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$student_id]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student || (int)$student['active'] !== 1) {

    session_destroy();

    header('Location: login.php');
    exit;
}

/* =========================================================
   المحافظة
========================================================= */

$governorate_name = 'غير محددة';

try {

    $stmt = $pdo->prepare("
        SELECT name
        FROM governorates
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        (int)$student['governorate_id']
    ]);

    $governorate_name = $stmt->fetchColumn() ?: 'غير محددة';

} catch (Exception $e) {

    $governorate_name = 'غير محددة';
}

/* =========================================================
   المرحلة
========================================================= */

$stage_name = 'غير محددة';

try {

    $stmt = $pdo->prepare("
        SELECT name
        FROM stages
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        (int)$student['stage_id']
    ]);

    $stage_name = $stmt->fetchColumn() ?: 'غير محددة';

} catch (Exception $e) {

    $stage_name = 'غير محددة';
}

/* =========================================================
   المنهج
========================================================= */

$curriculum_name = 'غير محدد';

try {

    $stmt = $pdo->prepare("
        SELECT name
        FROM curricula
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        (int)$student['curriculum_id']
    ]);

    $curriculum_name = $stmt->fetchColumn() ?: 'غير محدد';

} catch (Exception $e) {

    $curriculum_name = 'غير محدد';
}

/* =========================================================
   الحرف الأول
========================================================= */

$student_name = trim($student['name'] ?? 'الطالب');

if ($student_name === '') {
    $student_name = 'الطالب';
}

$avatar = mb_substr(
    $student_name,
    0,
    1,
    'UTF-8'
);

/* =========================================================
   الجنس
========================================================= */

$gender_text = 'غير محدد';

if (($student['gender'] ?? '') === 'male') {

    $gender_text = 'ذكر';

} elseif (($student['gender'] ?? '') === 'female') {

    $gender_text = 'أنثى';
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
    حسابي | منصة أكاديمي
</title>

<style>

/* =========================================================
   الأساسيات
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
}

body {

    font-family:
        Tahoma,
        Arial,
        sans-serif;

    background: #f5f8fc;

    color: #17202a;

    min-height: 100vh;

    padding-bottom: 95px;
}

a {
    text-decoration: none;
    color: inherit;
}

button,
input,
select {
    font-family: inherit;
}

/* =========================================================
   الحاوية
========================================================= */

.container {

    width: 100%;

    max-width: 700px;

    margin: auto;

    padding: 18px;
}

/* =========================================================
   الهيدر
========================================================= */

.top-header {

    background: #ffffff;

    border-radius: 24px;

    padding: 18px;

    display: flex;

    align-items: center;

    gap: 14px;

    box-shadow:
        0 8px 25px rgba(0,0,0,.05);

    margin-bottom: 18px;
}

.back-btn {

    width: 42px;
    height: 42px;

    border-radius: 14px;

    background: #eef4f2;

    color: #345c49;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;

    flex-shrink: 0;
}

.header-text {

    flex: 1;
}

.header-text h1 {

    margin: 0;

    font-size: 21px;

    color: #345c49;
}

.header-text p {

    margin: 5px 0 0;

    font-size: 12px;

    color: #87928d;
}

/* =========================================================
   بطاقة الحساب
========================================================= */

.profile-card {

    background: #ffffff;

    border-radius: 26px;

    padding: 24px 18px;

    box-shadow:
        0 10px 30px rgba(0,0,0,.05);

    margin-bottom: 18px;
}

.profile-head {

    text-align: center;

    padding-bottom: 20px;

    border-bottom: 1px solid #edf1ef;
}

.avatar {

    width: 86px;
    height: 86px;

    margin: auto;

    border-radius: 50%;

    background: #08a394;

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 34px;

    font-weight: bold;

    box-shadow:
        0 8px 20px rgba(8,163,148,.20);
}

.profile-name {

    margin-top: 13px;

    font-size: 21px;

    font-weight: bold;

    color: #345c49;
}

.profile-phone {

    margin-top: 6px;

    font-size: 13px;

    color: #89938e;
}

/* =========================================================
   الرسائل
========================================================= */

.alert {

    border-radius: 15px;

    padding: 13px 15px;

    margin-bottom: 15px;

    font-size: 13px;

    line-height: 1.7;
}

.alert-success {

    background: #e8f8f2;

    color: #16745f;

    border: 1px solid #c9eee2;
}

.alert-error {

    background: #fff0f0;

    color: #a52828;

    border: 1px solid #ffd3d3;
}

/* =========================================================
   عنوان القسم
========================================================= */

.section-title {

    font-size: 17px;

    font-weight: bold;

    color: #345c49;

    margin-bottom: 14px;
}

/* =========================================================
   الحقول
========================================================= */

.form-group {

    margin-bottom: 16px;
}

.form-group label {

    display: block;

    margin-bottom: 7px;

    font-size: 13px;

    color: #52615a;

    font-weight: bold;
}

.form-control {

    width: 100%;

    border: 1px solid #dfe7e3;

    background: #f9fbfa;

    border-radius: 14px;

    padding: 13px 14px;

    font-size: 14px;

    color: #26342e;

    outline: none;

    transition: .2s;
}

.form-control:focus {

    border-color: #08a394;

    background: #ffffff;

    box-shadow:
        0 0 0 3px rgba(8,163,148,.08);
}

/* =========================================================
   معلومات الدراسة
========================================================= */

.study-box {

    background: #f5f8f6;

    border-radius: 19px;

    padding: 15px;

    margin-top: 8px;

    margin-bottom: 20px;
}

.study-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding: 11px 0;

    border-bottom: 1px solid #e5ece8;

    font-size: 13px;
}

.study-row:last-child {
    border-bottom: 0;
}

.study-label {

    color: #7a8780;
}

.study-value {

    color: #345c49;

    font-weight: bold;

    text-align: left;
}

/* =========================================================
   زر الحفظ
========================================================= */

.save-btn {

    width: 100%;

    border: 0;

    border-radius: 15px;

    padding: 14px;

    background: #08a394;

    color: #ffffff;

    font-size: 15px;

    font-weight: bold;

    cursor: pointer;

    box-shadow:
        0 8px 18px rgba(8,163,148,.18);

    transition: .2s;
}

.save-btn:hover {

    opacity: .93;

    transform: translateY(-1px);
}

/* =========================================================
   تسجيل الخروج
========================================================= */

.logout-btn {

    display: block;

    text-align: center;

    background: #fff1f1;

    color: #b33131;

    border-radius: 15px;

    padding: 13px;

    margin-top: 12px;

    font-size: 14px;

    font-weight: bold;
}

/* =========================================================
   الشريط السفلي
========================================================= */

.bottom-nav-wrap {

    position: fixed;

    left: 0;
    right: 0;
    bottom: 10px;

    padding: 0 12px;

    z-index: 100;
}

.bottom-nav {

    width: 100%;

    max-width: 700px;

    margin: auto;

    background: rgba(255,255,255,.94);

    border: 1px solid rgba(52,92,73,.10);

    box-shadow:
        0 10px 30px rgba(0,0,0,.12);

    backdrop-filter: blur(15px);

    border-radius: 22px;

    padding: 7px;

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 5px;
}

.nav-item {

    height: 58px;

    border-radius: 17px;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    gap: 4px;

    color: #8b9791;

    font-size: 10px;
}

.nav-icon {

    font-size: 19px;

    line-height: 1;
}

.nav-item.active {

    background: #345c49;

    color: #ffffff;

    box-shadow:
        0 7px 17px rgba(52,92,73,.20);
}

/* =========================================================
   الموبايل
========================================================= */

@media (max-width: 420px) {

    .container {
        padding: 12px;
    }

    .profile-card {
        padding: 20px 15px;
    }

    .header-text h1 {
        font-size: 19px;
    }

}

</style>

</head>

<body>

<div class="container">

    <!-- =====================================================
         الهيدر
    ====================================================== -->

    <header class="top-header">

        <a
            href="index.php"
            class="back-btn"
        >
            →
        </a>

        <div class="header-text">

            <h1>
                حسابي
            </h1>

            <p>
                إدارة معلومات حسابك الشخصي
            </p>

        </div>

    </header>


    <!-- =====================================================
         بطاقة الحساب
    ====================================================== -->

    <section class="profile-card">

        <div class="profile-head">

            <div class="avatar">

                <?= e($avatar) ?>

            </div>

            <div class="profile-name">

                <?= e($student['name']) ?>

            </div>

            <div class="profile-phone">

                <?= e($student['phone']) ?>

            </div>

        </div>


        <!-- الرسائل -->

        <?php if ($message !== ''): ?>

            <div
                class="alert alert-success"
                style="margin-top:18px;"
            >
                ✅ <?= e($message) ?>
            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div
                class="alert alert-error"
                style="margin-top:18px;"
            >
                ⚠️ <?= e($error) ?>
            </div>

        <?php endif; ?>


        <!-- =================================================
             تعديل البيانات
        ================================================== -->

        <div
            class="section-title"
            style="margin-top:20px;"
        >
            ✏️ تعديل معلومات الحساب
        </div>


        <form
            method="POST"
            action=""
        >

            <!-- الاسم -->

            <div class="form-group">

                <label>
                    اسم الطالب
                </label>

                <input
                    type="text"
                    name="name"
                    class="form-control"
                    value="<?= e($student['name']) ?>"
                    required
                >

            </div>


            <!-- الهاتف -->

            <div class="form-group">

                <label>
                    رقم الهاتف
                </label>

                <input
                    type="text"
                    name="phone"
                    class="form-control"
                    value="<?= e($student['phone']) ?>"
                    required
                    inputmode="tel"
                >

            </div>


            <!-- الجنس -->

            <div class="form-group">

                <label>
                    الجنس
                </label>

                <select
                    name="gender"
                    class="form-control"
                >

                    <option
                        value=""
                        <?= empty($student['gender']) ? 'selected' : '' ?>
                    >
                        غير محدد
                    </option>

                    <option
                        value="male"
                        <?= ($student['gender'] ?? '') === 'male'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        ذكر
                    </option>

                    <option
                        value="female"
                        <?= ($student['gender'] ?? '') === 'female'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        أنثى
                    </option>

                </select>

            </div>


            <!-- زر الحفظ -->

            <button
                type="submit"
                class="save-btn"
            >
                💾 حفظ التعديلات
            </button>

        </form>

    </section>


    <!-- =====================================================
         المعلومات الدراسية
    ====================================================== -->

    <section class="profile-card">

        <div class="section-title">

            🎓 معلوماتي الدراسية

        </div>


        <div class="study-box">

            <div class="study-row">

                <span class="study-label">
                    المحافظة
                </span>

                <span class="study-value">
                    <?= e($governorate_name) ?>
                </span>

            </div>


            <div class="study-row">

                <span class="study-label">
                    المرحلة
                </span>

                <span class="study-value">
                    <?= e($stage_name) ?>
                </span>

            </div>


            <div class="study-row">

                <span class="study-label">
                    المنهج
                </span>

                <span class="study-value">
                    <?= e($curriculum_name) ?>
                </span>

            </div>


            <div class="study-row">

                <span class="study-label">
                    الجنس
                </span>

                <span class="study-value">
                    <?= e($gender_text) ?>
                </span>

            </div>

        </div>


        <div
            style="
            font-size:12px;
            color:#89938e;
            line-height:1.8;
            "
        >

            🔒 المحافظة والمرحلة والمنهج مرتبطة بحسابك الدراسي،
            لذلك لا يمكن تعديلها من هذه الصفحة.

        </div>

    </section>


    <!-- =====================================================
         تسجيل الخروج
    ====================================================== -->

    <a
        href="logout.php"
        class="logout-btn"
    >
        🚪 تسجيل الخروج
    </a>

</div>


<!-- =========================================================
     Bottom Navigation
========================================================= -->

<div class="bottom-nav-wrap">

    <nav class="bottom-nav">

        <!-- الرئيسية -->

        <a
            href="index.php"
            class="nav-item"
        >

            <div class="nav-icon">
                🏠
            </div>

            الرئيسية

        </a>


        <!-- مفكرتي -->

        <a
            href="notes.php"
            class="nav-item"
        >

            <div class="nav-icon">
                📝
            </div>

            مفكرتي

        </a>


        <!-- الإعدادات -->

        <a
            href="settings.php"
            class="nav-item"
        >

            <div class="nav-icon">
                ⚙️
            </div>

            الإعدادات

        </a>


        <!-- حسابي -->

        <a
            href="profile.php"
            class="nav-item active"
        >

            <div class="nav-icon">
                👤
            </div>

            حسابي

        </a>

    </nav>

</div>

</body>

</html>