<?php

session_start();

require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = db();

/*
|--------------------------------------------------------------------------
| حماية النصوص
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| يجب أن يكون الطلب POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: subscription_requests.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| البيانات القادمة
|--------------------------------------------------------------------------
*/

$requestId = isset($_POST['request_id'])
    ? (int)$_POST['request_id']
    : 0;

$action = trim(
    $_POST['action'] ?? ''
);

if ($requestId <= 0) {
    header('Location: subscription_requests.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| السماح فقط بالقبول أو الرفض
|--------------------------------------------------------------------------
*/

if (!in_array($action, ['approve', 'reject'], true)) {
    header('Location: subscription_requests.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| معالجة الطلب
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | جلب طلب الاشتراك
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            sr.id,
            sr.request_number,
            sr.student_id,
            sr.course_id,
            sr.amount,
            sr.status,

            c.title AS course_title,
            c.duration_days,
            c.active AS course_active

        FROM subscription_requests sr

        INNER JOIN courses c
            ON c.id = sr.course_id

        WHERE sr.id = ?

        LIMIT 1

        FOR UPDATE
    ");

    $stmt->execute([
        $requestId
    ]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | الطلب غير موجود
    |--------------------------------------------------------------------------
    */

    if (!$request) {

        $pdo->rollBack();

        header(
            'Location: subscription_requests.php'
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | لا يمكن معالجة طلب سبق التعامل معه
    |--------------------------------------------------------------------------
    */

    if ($request['status'] !== 'pending') {

        $pdo->rollBack();

        header(
            'Location: subscription_requests.php'
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | رفض الطلب
    |--------------------------------------------------------------------------
    */

    if ($action === 'reject') {

        $stmt = $pdo->prepare("
            UPDATE subscription_requests

            SET
                status = 'rejected',
                updated_at = NOW()

            WHERE id = ?
        ");

        $stmt->execute([
            $requestId
        ]);

        $pdo->commit();

        header(
            'Location: subscription_requests.php'
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | قبول الطلب
    |--------------------------------------------------------------------------
    */

    /*
     * نتأكد أن الدورة ما زالت فعالة
     */

    if ((int)$request['course_active'] !== 1) {

        throw new Exception(
            'الدورة غير فعالة حالياً.'
        );
    }

    /*
     * مدة الدورة
     */

    $durationDays = (int)$request['duration_days'];

    if ($durationDays <= 0) {

        throw new Exception(
            'مدة الدورة غير صحيحة.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | التحقق من وجود اشتراك فعال سابق
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            status,
            expires_at

        FROM subscriptions

        WHERE student_id = ?
          AND course_id = ?

        ORDER BY id DESC

        LIMIT 1

        FOR UPDATE
    ");

    $stmt->execute([
        (int)$request['student_id'],
        (int)$request['course_id']
    ]);

    $existingSubscription =
        $stmt->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | إذا يوجد اشتراك فعال لم ينتهِ
    |--------------------------------------------------------------------------
    */

    if (
        $existingSubscription &&
        $existingSubscription['status'] === 'active' &&
        (
            empty($existingSubscription['expires_at']) ||
            strtotime($existingSubscription['expires_at']) >= time()
        )
    ) {

        throw new Exception(
            'الطالب لديه اشتراك فعال بهذه الدورة بالفعل.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | إنشاء الاشتراك
    |--------------------------------------------------------------------------
    */

    $startsAt = date('Y-m-d H:i:s');

    $expiresAt = date(
        'Y-m-d H:i:s',
        strtotime(
            '+' . $durationDays . ' days',
            strtotime($startsAt)
        )
    );

    $stmt = $pdo->prepare("
        INSERT INTO subscriptions
        (
            student_id,
            course_id,
            starts_at,
            expires_at,
            status
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            'active'
        )
    ");

    $stmt->execute([
        (int)$request['student_id'],
        (int)$request['course_id'],
        $startsAt,
        $expiresAt
    ]);

    /*
    |--------------------------------------------------------------------------
    | تحديث طلب الاشتراك
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE subscription_requests

        SET
            status = 'approved',
            updated_at = NOW(),
            approved_at = NOW()

        WHERE id = ?
    ");

    $stmt->execute([
        $requestId
    ]);

    /*
    |--------------------------------------------------------------------------
    | إنهاء العملية
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | العودة إلى طلبات الاشتراك
    |--------------------------------------------------------------------------
    */

    header(
        'Location: subscription_requests.php'
    );

    exit;


} catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | إلغاء العملية عند حدوث خطأ
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
    |--------------------------------------------------------------------------
    | عرض رسالة الخطأ
    |--------------------------------------------------------------------------
    */

    $errorMessage = $e->getMessage();

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
            خطأ - منصة أكاديمي
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

                background:
                    linear-gradient(
                        180deg,
                        #ede7d2,
                        #f5f7f4,
                        #fff
                    );

                color: #345c49;

                min-height: 100vh;

                display: flex;

                align-items: center;

                justify-content: center;

                padding: 20px;
            }

            .box {

                width: 100%;

                max-width: 500px;

                background: #fff;

                border-radius: 24px;

                padding: 30px;

                text-align: center;

                box-shadow:
                    0 15px 40px rgba(52,92,73,.10);
            }

            .icon {

                font-size: 50px;

                margin-bottom: 15px;
            }

            h2 {

                margin: 0 0 10px;

                color: #345c49;
            }

            p {

                color: #777;

                font-size: 14px;

                line-height: 1.8;
            }

            .btn {

                display: inline-block;

                margin-top: 15px;

                background: #345c49;

                color: white;

                padding: 12px 22px;

                border-radius: 14px;

                text-decoration: none;

                font-size: 13px;

                font-weight: bold;
            }

        </style>

    </head>

    <body>

        <div class="box">

            <div class="icon">
                ⚠️
            </div>

            <h2>
                لم تتم العملية
            </h2>

            <p>
                <?= e($errorMessage) ?>
            </p>

            <a
                href="subscription_requests.php"
                class="btn"
            >
                العودة إلى طلبات الاشتراك
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}

