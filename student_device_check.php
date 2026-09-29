<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

$pdo = db();

$deviceCookieName = 'akadimi_device_token';

/*
|--------------------------------------------------------------------------
| التأكد من تسجيل دخول الطالب
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$studentId = (int) $_SESSION['student_id'];

if ($studentId <= 0) {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| جلب بيانات الطالب والجهاز
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        active,
        device_token
    FROM students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$studentId]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| الطالب غير موجود
|--------------------------------------------------------------------------
*/

if (!$student) {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| الحساب غير فعال
|--------------------------------------------------------------------------
*/

if ((int) $student['active'] !== 1) {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| الكوكي الموجودة في جهاز الطالب
|--------------------------------------------------------------------------
*/

$currentDeviceToken = isset($_COOKIE[$deviceCookieName])
    ? (string) $_COOKIE[$deviceCookieName]
    : '';

$dbDeviceToken = isset($student['device_token'])
    ? (string) $student['device_token']
    : '';

/*
|--------------------------------------------------------------------------
| الأدمن ألغى ربط الجهاز
|
| device_token = NULL
|--------------------------------------------------------------------------
*/

if ($dbDeviceToken === '') {

    setcookie(
        $deviceCookieName,
        '',
        time() - 3600,
        '/'
    );

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

    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| لا توجد كوكي للجهاز
|--------------------------------------------------------------------------
*/

if ($currentDeviceToken === '') {

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

    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| التأكد من تطابق الجهاز
|--------------------------------------------------------------------------
*/

if (!hash_equals($dbDeviceToken, $currentDeviceToken)) {

    setcookie(
        $deviceCookieName,
        '',
        time() - 3600,
        '/'
    );

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

    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| الجهاز صحيح
|--------------------------------------------------------------------------
|
| نترك الصفحة تكمل عملها.
|--------------------------------------------------------------------------
*/