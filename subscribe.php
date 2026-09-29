<?php
session_start();

require_once __DIR__ . '/../config/config.php';


$pdo = db();

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = (int) $_SESSION['student_id'];
$course_id  = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;

if ($course_id <= 0) {
    header("Location: subjects.php");
    exit;
        require_once __DIR__ . '/student_auth_check.php';

}

/*
|--------------------------------------------------------------------------
| جلب بيانات الدورة
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.title,
        c.description,
        c.price,
        c.duration_days,
        s.name AS subject_name,
        t.name AS teacher_name
    FROM courses c
    INNER JOIN subjects s ON s.id = c.subject_id
    INNER JOIN teachers t ON t.id = c.teacher_id
    WHERE c.id = ?
      AND c.active = 1
    LIMIT 1
");

$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("الدورة غير موجودة أو غير متاحة حاليًا.");
}

/*
|--------------------------------------------------------------------------
| التحقق من وجود اشتراك فعال
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT id, starts_at, expires_at, status
    FROM subscriptions
    WHERE student_id = ?
      AND course_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([$student_id, $course_id]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

if ($subscription && $subscription['status'] === 'active') {
    header("Location: course_details.php?id=" . $course_id);
    exit;
}

/*
|--------------------------------------------------------------------------
| إنشاء طلب دفع
|--------------------------------------------------------------------------
*/
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $provider = isset($_POST['provider'])
        ? trim($_POST['provider'])
        : '';

    if ($provider === '') {
        $error = 'يرجى اختيار طريقة الدفع.';
    } else {

        /*
         * السعر يؤخذ من قاعدة البيانات وليس من الفورم
         */
        $amount = (float) $course['price'];

        /*
         * رقم طلب مؤقت للدفع
         */
        $transaction_ref = 'AKD-' . date('YmdHis') . '-' . $student_id . '-' . $course_id;

        try {

            $stmt = $pdo->prepare("
                INSERT INTO payments
                (
                    student_id,
                    course_id,
                    amount,
                    provider,
                    transaction_ref,
                    status,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");

            $stmt->execute([
                $student_id,
                $course_id,
                $amount,
                $provider,
                $transaction_ref
            ]);

            /*
             * حاليًا نخلي الطلب Pending
             * إلى أن نربطه ببوابة الدفع الحقيقية.
             */

            header(
                "Location: payment_pending.php?ref=" .
                urlencode($transaction_ref)
            );
            exit;

        } catch (PDOException $e) {

            $error = 'حدث خطأ أثناء إنشاء طلب الدفع. حاول مرة أخرى.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>الاشتراك بالدورة</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f8fc;
            color: #1f2937;
        }

        .container {
            width: 100%;
            max-width: 650px;
            margin: 0 auto;
            padding: 25px 16px 50px;
        }

        .top {
            margin-bottom: 20px;
        }

        .back {
            display: inline-block;
            text-decoration: none;
            color: #078f83;
            font-weight: bold;
            margin-bottom: 15px;
        }

        h1 {
            margin: 0;
            font-size: 26px;
        }

        .card {
            background: #fff;
            border-radius: 18px;
            padding: 22px;
            margin-top: 18px;
            box-shadow: 0 8px 30px rgba(0,0,0,.06);
        }

        .course-title {
            font-size: 23px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .info {
            margin: 8px 0;
            color: #64748b;
        }

        .price {
            margin-top: 20px;
            padding: 18px;
            background: #f0faf8;
            border-radius: 14px;
            text-align: center;
        }

        .price-label {
            color: #64748b;
            font-size: 14px;
        }

        .price-value {
            font-size: 30px;
            font-weight: bold;
            color: #078f83;
            margin-top: 5px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 14px;
        }

        .method {
            display: block;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .method:hover {
            border-color: #078f83;
        }

        .method input {
            margin-left: 8px;
        }

        .method-title {
            font-weight: bold;
        }

        .method-desc {
            color: #64748b;
            font-size: 13px;
            margin-top: 5px;
        }

        .btn {
            width: 100%;
            border: none;
            background: #078f83;
            color: #fff;
            padding: 15px;
            border-radius: 13px;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn:hover {
            background: #067a70;
        }

        .error {
            background: #fff1f2;
            color: #be123c;
            padding: 13px;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .note {
            margin-top: 15px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.8;
            text-align: center;
        }

    </style>
</head>

<body>

<div class="container">

    <div class="top">

        <a class="back"
           href="course_details.php?id=<?= (int)$course['id'] ?>">
            ← الرجوع للدورة
        </a>

        <h1>الاشتراك بالدورة</h1>

    </div>

    <div class="card">

        <div class="course-title">
            <?= e($course['title']) ?>
        </div>

        <div class="info">
            👨‍🏫 الأستاذ:
            <?= e($course['teacher_name']) ?>
        </div>

        <div class="info">
            📚 المادة:
            <?= e($course['subject_name']) ?>
        </div>

        <div class="info">
            ⏱️ مدة الاشتراك:
            <?= (int)$course['duration_days'] ?> يوم
        </div>

        <?php if (!empty($course['description'])): ?>

            <div class="info">
                <?= nl2br(e($course['description'])) ?>
            </div>

        <?php endif; ?>

        <div class="price">

            <div class="price-label">
                قيمة الاشتراك
            </div>

            <div class="price-value">
                <?= number_format((float)$course['price']) ?>
                د.ع
            </div>

        </div>

    </div>


    <div class="card">

        <div class="section-title">
            اختر طريقة الدفع
        </div>

        <?php if ($error): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <form method="POST">

            <label class="method">

                <input
                    type="radio"
                    name="provider"
                    value="electronic"
                >

                <span class="method-title">
                    💳 الدفع الإلكتروني
                </span>

                <div class="method-desc">
                    سيتم تحويلك إلى بوابة الدفع الإلكتروني بعد إنشاء الطلب.
                </div>

            </label>


            <button type="submit" class="btn">
                متابعة إلى الدفع
            </button>

        </form>


        <div class="note">
            بعد تأكيد عملية الدفع سيتم تفعيل اشتراكك
            وإتاحة محاضرات الدورة لك.
        </div>

    </div>

</div>

</body>
</html>