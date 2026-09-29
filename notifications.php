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

/*
|--------------------------------------------------------------------------
| تحديد جميع الإشعارات كمقروءة
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {

    try {
        $pdo = db();

        $stmt = $pdo->prepare("
            UPDATE student_notifications
            SET is_read = 1
            WHERE student_id = ?
        ");

        $stmt->execute([$student_id]);

    } catch (PDOException $e) {
        // لا نعرض تفاصيل قاعدة البيانات للطالب
    }

    header('Location: notifications.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| جلب إشعارات الطالب
|--------------------------------------------------------------------------
*/

$notifications = [];

try {

    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT
            id,
            student_id,
            title,
            message,
            type,
            is_read,
            created_at
        FROM student_notifications
        WHERE student_id = ?
        ORDER BY created_at DESC, id DESC
    ");

    $stmt->execute([$student_id]);

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $notifications = [];
}


/*
|--------------------------------------------------------------------------
| عدد الإشعارات غير المقروءة
|--------------------------------------------------------------------------
*/

$unread_count = 0;

foreach ($notifications as $notification) {

    if ((int)$notification['is_read'] === 0) {
        $unread_count++;
    }

}


/*
|--------------------------------------------------------------------------
| أيقونة الإشعار
|--------------------------------------------------------------------------
*/

function notificationIcon($type)
{
    switch ($type) {

        case 'success':
            return '✅';

        case 'warning':
            return '⚠️';

        case 'payment':
            return '💳';

        case 'course':
            return '📚';

        case 'lesson':
            return '🎬';

        case 'teacher':
            return '👨‍🏫';

        case 'message':
            return '💬';

        default:
            return '🔔';
    }
}


/*
|--------------------------------------------------------------------------
| تنسيق التاريخ
|--------------------------------------------------------------------------
*/

function notificationDate($date)
{
    if (empty($date)) {
        return '';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return e($date);
    }

    return date('Y/m/d - h:i A', $timestamp);
}

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>الإشعارات - منصة أكاديمي</title>

<style>

* {
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}

body {
    margin: 0;
    background: #e8e8e8;
    font-family: Tahoma, Arial, sans-serif;
    color: #26352e;
}

.page {
    max-width: 650px;
    margin: auto;
    min-height: 100vh;
    padding-bottom: 90px;
}


/* الهيدر */

.header {

    background: #345c49;

    color: white;

    padding: 22px 18px 24px;

    border-radius: 0 0 25px 25px;

    box-shadow: 0 4px 15px rgba(0,0,0,.10);
}

.header-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;
}

.header-title {

    font-size: 23px;

    font-weight: bold;
}

.header-subtitle {

    margin-top: 7px;

    font-size: 13px;

    opacity: .85;
}

.notification-count {

    min-width: 40px;

    height: 40px;

    border-radius: 50%;

    background: #ede7d2;

    color: #345c49;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 17px;

    font-weight: bold;
}


/* المحتوى */

.content {

    padding: 18px 15px;
}

.top-actions {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 15px;

    gap: 10px;
}

.section-title {

    font-size: 18px;

    font-weight: bold;

    color: #345c49;
}

.read-button {

    border: none;

    background: #ede7d2;

    color: #345c49;

    padding: 10px 13px;

    border-radius: 12px;

    font-family: inherit;

    font-size: 12px;

    font-weight: bold;

    cursor: pointer;
}


/* بطاقة الإشعار */

.notification {

    position: relative;

    background: white;

    border-radius: 18px;

    padding: 16px;

    margin-bottom: 12px;

    box-shadow: 0 4px 14px rgba(0,0,0,.06);

    border: 1px solid rgba(52,92,73,.08);
}

.notification.unread {

    border-right: 4px solid #345c49;

    background: #fff;
}

.notification.read {

    opacity: .78;
}

.notification-top {

    display: flex;

    align-items: flex-start;

    gap: 12px;
}

.notification-icon {

    width: 48px;

    height: 48px;

    min-width: 48px;

    border-radius: 15px;

    background: #ede7d2;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;
}

.notification-info {

    flex: 1;

    min-width: 0;
}

.notification-title-row {

    display: flex;

    align-items: center;

    gap: 7px;

    margin-bottom: 6px;
}

.notification-title {

    color: #345c49;

    font-size: 16px;

    font-weight: bold;

    line-height: 1.5;
}

.unread-dot {

    width: 8px;

    height: 8px;

    min-width: 8px;

    border-radius: 50%;

    background: #345c49;
}

.notification-message {

    color: #59655f;

    font-size: 13px;

    line-height: 1.9;

    white-space: pre-line;
}

.notification-date {

    margin-top: 12px;

    padding-top: 10px;

    border-top: 1px solid #eee;

    color: #8a918d;

    font-size: 11px;
}


/* لا توجد إشعارات */

.empty {

    background: white;

    border-radius: 22px;

    padding: 45px 20px;

    text-align: center;

    box-shadow: 0 4px 14px rgba(0,0,0,.05);

    margin-top: 15px;
}

.empty-icon {

    width: 75px;

    height: 75px;

    margin: 0 auto 15px;

    border-radius: 50%;

    background: #ede7d2;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 35px;
}

.empty-title {

    color: #345c49;

    font-size: 19px;

    font-weight: bold;

    margin-bottom: 8px;
}

.empty-text {

    color: #777;

    font-size: 13px;

    line-height: 1.8;
}


/* القائمة السفلية */

.bottom-nav {

    position: fixed;

    bottom: 0;

    left: 50%;

    transform: translateX(-50%);

    width: 100%;

    max-width: 650px;

    height: 72px;

    background: rgba(255,255,255,.97);

    border-top: 1px solid #ddd;

    display: flex;

    align-items: center;

    justify-content: space-around;

    z-index: 1000;

    box-shadow: 0 -4px 15px rgba(0,0,0,.07);
}

.nav-item {

    text-decoration: none;

    color: #8a918d;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    gap: 4px;

    width: 25%;

    height: 100%;

    font-size: 11px;
}

.nav-icon {

    font-size: 21px;

    line-height: 1;
}

.nav-item.active {

    color: #345c49;

    font-weight: bold;
}

.nav-icon-wrap {

    position: relative;
}

.nav-badge {

    position: absolute;

    top: -7px;

    right: -10px;

    min-width: 17px;

    height: 17px;

    padding: 0 4px;

    border-radius: 20px;

    background: #345c49;

    color: white;

    font-size: 9px;

    display: flex;

    align-items: center;

    justify-content: center;
}

</style>

</head>

<body>


<div class="page">


    <!-- الهيدر -->

    <div class="header">

        <div class="header-row">

            <div>

                <div class="header-title">
                    🔔 الإشعارات
                </div>

                <div class="header-subtitle">
                    آخر التنبيهات والإشعارات الخاصة بك
                </div>

            </div>


            <div class="notification-count">

                <?= $unread_count ?>

            </div>

        </div>

    </div>


    <!-- المحتوى -->

    <div class="content">


        <div class="top-actions">

            <div class="section-title">
                الإشعارات
            </div>


            <?php if ($unread_count > 0): ?>

                <form method="POST">

                    <button
                        type="submit"
                        name="mark_all_read"
                        class="read-button">

                        ✓ تحديد الكل كمقروء

                    </button>

                </form>

            <?php endif; ?>

        </div>


        <?php if (!empty($notifications)): ?>


            <?php foreach ($notifications as $notification): ?>

                <?php

                $isUnread =
                    ((int)$notification['is_read'] === 0);

                $icon =
                    notificationIcon($notification['type']);

                ?>


                <div class="notification
                    <?= $isUnread ? 'unread' : 'read' ?>">


                    <div class="notification-top">


                        <div class="notification-icon">

                            <?= $icon ?>

                        </div>


                        <div class="notification-info">


                            <div class="notification-title-row">


                                <div class="notification-title">

                                    <?= e($notification['title']) ?>

                                </div>


                                <?php if ($isUnread): ?>

                                    <div
                                        class="unread-dot"
                                        title="غير مقروء">
                                    </div>

                                <?php endif; ?>


                            </div>


                            <div class="notification-message">

                                <?= e($notification['message']) ?>

                            </div>


                        </div>


                    </div>


                    <div class="notification-date">

                        🕐
                        <?= notificationDate($notification['created_at']) ?>

                    </div>


                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="empty">


                <div class="empty-icon">
                    🔔
                </div>


                <div class="empty-title">

                    لا توجد إشعارات

                </div>


                <div class="empty-text">

                    حالياً ما عندك أي إشعارات جديدة.

                    <br>

                    راح تظهر هنا التنبيهات الخاصة بدوراتك واشتراكاتك.

                </div>


            </div>


        <?php endif; ?>


    </div>

</div>


<!-- القائمة السفلية -->

<div class="bottom-nav">


    <a href="index.php" class="nav-item">

        <div class="nav-icon">
            🏠
        </div>

        <div>
            الرئيسية
        </div>

    </a>


    <a href="subjects.php" class="nav-item">

        <div class="nav-icon">
            📚
        </div>

        <div>
            المواد
        </div>

    </a>


    <a href="notifications.php"
       class="nav-item active">


        <div class="nav-icon-wrap">

            <div class="nav-icon">
                🔔
            </div>


            <?php if ($unread_count > 0): ?>

                <div class="nav-badge">

                    <?= $unread_count > 99 ? '99+' : $unread_count ?>

                </div>

            <?php endif; ?>


        </div>


        <div>
            الإشعارات
        </div>


    </a>


    <a href="profile.php" class="nav-item">

        <div class="nav-icon">
            👤
        </div>

        <div>
            حسابي
        </div>

    </a>


</div>


</body>

</html>