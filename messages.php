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
| التحقق من دخول الطالب
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['student_id'])) {

    header('Location: login.php');
    exit;

}

$student_id = (int) $_SESSION['student_id'];


/*
|--------------------------------------------------------------------------
| الأستاذ المحدد
|--------------------------------------------------------------------------
*/

$teacher_id = isset($_GET['teacher_id'])
    ? (int) $_GET['teacher_id']
    : 0;


/*
|--------------------------------------------------------------------------
| إرسال رسالة
|--------------------------------------------------------------------------
*/

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $post_teacher_id = (int) ($_POST['teacher_id'] ?? 0);

    $message = trim($_POST['message'] ?? '');


    if ($post_teacher_id <= 0) {

        $error = 'الأستاذ غير محدد';

    } elseif ($message === '') {

        $error = 'اكتب الرسالة أولاً';

    } else {

        /*
        |--------------------------------------------------------------------------
        | التأكد أن الطالب مشترك بدورة لهذا الأستاذ
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT c.id

            FROM subscriptions sub

            INNER JOIN courses c
                ON c.id = sub.course_id

            WHERE
                sub.student_id = ?
                AND c.teacher_id = ?

            LIMIT 1
        ");

        $stmt->execute([
            $student_id,
            $post_teacher_id
        ]);

        $allowed = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$allowed) {

            $error = 'يمكنك مراسلة الأساتذة الذين لديك اشتراك في دوراتهم فقط';

        } else {

            /*
            |--------------------------------------------------------------------------
            | إضافة الرسالة
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO student_teacher_messages
                (
                    student_id,
                    teacher_id,
                    sender_type,
                    message,
                    is_read
                )
                VALUES (?, ?, 'student', ?, 0)
            ");

            $stmt->execute([
                $student_id,
                $post_teacher_id,
                $message
            ]);


            $success = 'تم إرسال الرسالة بنجاح';

            $teacher_id = $post_teacher_id;
        }
    }
}


/*
|--------------------------------------------------------------------------
| جلب الأساتذة المشترك الطالب بدوراتهم
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT DISTINCT
        t.id,
        t.name

    FROM subscriptions sub

    INNER JOIN courses c
        ON c.id = sub.course_id

    INNER JOIN teachers t
        ON t.id = c.teacher_id

    WHERE
        sub.student_id = ?
        AND c.active = 1
        AND t.active = 1

    ORDER BY t.name ASC
");

$stmt->execute([$student_id]);

$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| بيانات الأستاذ المحدد
|--------------------------------------------------------------------------
*/

$selected_teacher = null;

if ($teacher_id > 0) {

    foreach ($teachers as $teacher) {

        if ((int)$teacher['id'] === $teacher_id) {

            $selected_teacher = $teacher;

            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| جلب المحادثة
|--------------------------------------------------------------------------
*/

$messages = [];

if ($selected_teacher) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            sender_type,
            message,
            is_read,
            created_at

        FROM student_teacher_messages

        WHERE
            student_id = ?
            AND teacher_id = ?

        ORDER BY id ASC
    ");

    $stmt->execute([
        $student_id,
        $teacher_id
    ]);

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | تحويل رسائل الأستاذ إلى مقروءة
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE student_teacher_messages

        SET is_read = 1

        WHERE
            student_id = ?
            AND teacher_id = ?
            AND sender_type = 'teacher'
            AND is_read = 0
    ");

    $stmt->execute([
        $student_id,
        $teacher_id
    ]);
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
    الرسائل - منصة أكاديمي
</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Tahoma,
        Arial,
        sans-serif;

    background: #f5f8fc;

    color: #111827;
}

.app {

    width: 100%;

    max-width: 1100px;

    margin: auto;

    padding: 20px;
}


/* =====================================================
   الهيدر
===================================================== */

.header {

    background: white;

    border: 1px solid #e5eaf1;

    border-radius: 20px;

    padding: 18px 20px;

    margin-bottom: 18px;

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.header-title {

    font-size: 22px;

    font-weight: bold;
}

.header-subtitle {

    color: #8b98aa;

    font-size: 13px;

    margin-top: 6px;
}

.back {

    text-decoration: none;

    color: #087f7c;

    background: #e8faf7;

    padding: 10px 14px;

    border-radius: 10px;

    font-size: 13px;
}


/* =====================================================
   التخطيط
===================================================== */

.messages-layout {

    display: grid;

    grid-template-columns: 300px 1fr;

    gap: 15px;
}


/* =====================================================
   قائمة الأساتذة
===================================================== */

.teachers-card {

    background: white;

    border: 1px solid #e5eaf1;

    border-radius: 20px;

    padding: 15px;

    min-height: 500px;
}

.card-title {

    font-weight: bold;

    font-size: 17px;

    margin-bottom: 15px;
}

.teacher {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 12px;

    border-radius: 14px;

    text-decoration: none;

    color: #111827;

    margin-bottom: 7px;
}

.teacher:hover {

    background: #f3f8f8;
}

.teacher.active {

    background: #e8faf7;

    color: #087f7c;
}

.teacher-avatar {

    width: 42px;

    height: 42px;

    border-radius: 50%;

    background: #08a394;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: bold;

    flex-shrink: 0;
}

.teacher-name {

    font-weight: bold;

    font-size: 14px;
}

.teacher-label {

    color: #8b98aa;

    font-size: 11px;

    margin-top: 4px;
}


/* =====================================================
   المحادثة
===================================================== */

.chat-card {

    background: white;

    border: 1px solid #e5eaf1;

    border-radius: 20px;

    min-height: 500px;

    display: flex;

    flex-direction: column;

    overflow: hidden;
}

.chat-header {

    padding: 16px 18px;

    border-bottom: 1px solid #edf0f4;

    font-weight: bold;
}

.chat-messages {

    flex: 1;

    min-height: 350px;

    max-height: 500px;

    overflow-y: auto;

    padding: 20px;
}

.message-row {

    display: flex;

    margin-bottom: 12px;
}

.message-row.student {

    justify-content: flex-start;
}

.message-row.teacher {

    justify-content: flex-end;
}

.message {

    max-width: 75%;

    padding: 11px 14px;

    border-radius: 16px;

    line-height: 1.7;

    font-size: 14px;
}

.message.student {

    background: #e8faf7;

    color: #134e4a;

    border-bottom-right-radius: 4px;
}

.message.teacher {

    background: #f0f2f5;

    color: #374151;

    border-bottom-left-radius: 4px;
}

.message-time {

    display: block;

    margin-top: 5px;

    font-size: 10px;

    color: #8b98aa;
}


/* =====================================================
   إرسال
===================================================== */

.chat-form {

    border-top: 1px solid #edf0f4;

    padding: 12px;

    display: flex;

    gap: 8px;
}

.chat-form textarea {

    flex: 1;

    min-height: 48px;

    max-height: 120px;

    resize: vertical;

    border: 1px solid #dce2ea;

    border-radius: 12px;

    padding: 12px;

    font-family: Tahoma, Arial, sans-serif;

    outline: none;
}

.chat-form button {

    border: 0;

    background: #08a394;

    color: white;

    border-radius: 12px;

    padding: 0 20px;

    cursor: pointer;

    font-family: Tahoma, Arial, sans-serif;
}


/* =====================================================
   الحالات
===================================================== */

.empty {

    text-align: center;

    color: #8b98aa;

    padding: 60px 20px;

    line-height: 2;
}

.alert {

    padding: 12px;

    border-radius: 10px;

    margin-bottom: 12px;

    font-size: 13px;
}

.alert-error {

    background: #fee2e2;

    color: #991b1b;
}

.alert-success {

    background: #dcfce7;

    color: #166534;
}


/* =====================================================
   الموبايل
===================================================== */

@media (max-width: 750px) {

    .app {

        padding:
            14px 14px 85px;
    }

    .messages-layout {

        grid-template-columns: 1fr;
    }

    .teachers-card {

        min-height: auto;
    }

    .chat-card {

        min-height: 500px;
    }

    .header-title {

        font-size: 19px;
    }

    .message {

        max-width: 85%;
    }
}

</style>

</head>


<body>


<div class="app">


    <!-- =================================================
         الهيدر
    ================================================== -->

    <div class="header">

        <div>

            <div class="header-title">
                💬 الرسائل
            </div>

            <div class="header-subtitle">
                تواصل مع الأساتذة المشترك معهم
            </div>

        </div>


        <a
            href="index.php"
            class="back"
        >
            🏠 الرئيسية
        </a>

    </div>


    <?php if ($error !== ''): ?>

        <div class="alert alert-error">

            ⚠️ <?= e($error) ?>

        </div>

    <?php endif; ?>


    <?php if ($success !== ''): ?>

        <div class="alert alert-success">

            ✅ <?= e($success) ?>

        </div>

    <?php endif; ?>


    <div class="messages-layout">


        <!-- =================================================
             الأساتذة
        ================================================== -->

        <div class="teachers-card">

            <div class="card-title">
                👨‍🏫 أساتذتي
            </div>


            <?php if (!$teachers): ?>

                <div class="empty">

                    لا يوجد أساتذة مرتبطون بدوراتك حالياً.

                    <br>

                    اشترك بدورة تعليمية ليظهر الأستاذ هنا.

                </div>

            <?php else: ?>


                <?php foreach ($teachers as $teacher): ?>

                    <?php

                    $letter = mb_substr(
                        $teacher['name'],
                        0,
                        1,
                        'UTF-8'
                    );

                    ?>


                    <a
                        href="messages.php?teacher_id=<?= (int)$teacher['id'] ?>"
                        class="teacher <?= (
                            $selected_teacher &&
                            (int)$selected_teacher['id'] === (int)$teacher['id']
                        ) ? 'active' : '' ?>"
                    >

                        <div class="teacher-avatar">

                            <?= e($letter) ?>

                        </div>


                        <div>

                            <div class="teacher-name">

                                <?= e($teacher['name']) ?>

                            </div>

                            <div class="teacher-label">

                                أستاذك في إحدى دوراتك

                            </div>

                        </div>

                    </a>


                <?php endforeach; ?>


            <?php endif; ?>

        </div>


        <!-- =================================================
             المحادثة
        ================================================== -->

        <div class="chat-card">


            <?php if (!$selected_teacher): ?>

                <div class="empty">

                    <div style="font-size:40px;">
                        💬
                    </div>

                    <br>

                    اختر أحد الأساتذة من القائمة لبدء المحادثة.

                </div>


            <?php else: ?>


                <div class="chat-header">

                    👨‍🏫
                    <?= e($selected_teacher['name']) ?>

                </div>


                <div class="chat-messages" id="chatMessages">


                    <?php if (!$messages): ?>

                        <div class="empty">

                            لا توجد رسائل بينكما حتى الآن.

                            <br>

                            يمكنك إرسال أول رسالة للأستاذ.

                        </div>


                    <?php else: ?>


                        <?php foreach ($messages as $msg): ?>

                            <div
                                class="message-row <?= (
                                    $msg['sender_type'] === 'student'
                                ) ? 'student' : 'teacher' ?>"
                            >

                                <div
                                    class="message <?= (
                                        $msg['sender_type'] === 'student'
                                    ) ? 'student' : 'teacher' ?>"
                                >

                                    <?= nl2br(
                                        e($msg['message'])
                                    ) ?>


                                    <span class="message-time">

                                        <?= e(
                                            $msg['created_at']
                                        ) ?>

                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


                <!-- إرسال رسالة -->

                <form
                    method="POST"
                    class="chat-form"
                >

                    <input
                        type="hidden"
                        name="teacher_id"
                        value="<?= (int)$selected_teacher['id'] ?>"
                    >


                    <textarea
                        name="message"
                        placeholder="اكتب رسالتك للأستاذ..."
                        required
                    ></textarea>


                    <button type="submit">

                        إرسال ➤

                    </button>

                </form>


            <?php endif; ?>


        </div>


    </div>


</div>


<script>

/*
|--------------------------------------------------------------------------
| النزول إلى آخر رسالة
|--------------------------------------------------------------------------
*/

const chat = document.getElementById('chatMessages');

if (chat) {

    chat.scrollTop = chat.scrollHeight;
}

</script>


</body>

</html>