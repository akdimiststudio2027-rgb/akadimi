<?php

session_start();

require_once __DIR__ . '/../config/config.php';

$pdo = db();

if (empty($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
        require_once __DIR__ . '/student_auth_check.php';

}

$student_id = (int) $_SESSION['student_id'];

$ref = trim($_GET['ref'] ?? '');

if ($ref === '') {
    header("Location: index.php");
    exit;
}

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | جلب عملية الدفع
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            student_id,
            course_id,
            amount,
            status
        FROM payments
        WHERE student_id = ?
          AND transaction_ref = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $student_id,
        $ref
    ]);

    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception("عملية الدفع غير موجودة.");
    }

    /*
    |--------------------------------------------------------------------------
    | إذا كانت مدفوعة مسبقاً
    |--------------------------------------------------------------------------
    */

    if ($payment['status'] !== 'paid') {

        $stmt = $pdo->prepare("
            UPDATE payments
            SET
                status = 'paid',
                paid_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $payment['id']
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | التحقق من الاشتراك
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM subscriptions
        WHERE student_id = ?
          AND course_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $student_id,
        $payment['course_id']
    ]);

    $existing_subscription = $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | إنشاء الاشتراك
    |--------------------------------------------------------------------------
    */

    if (!$existing_subscription) {

        $stmt = $pdo->prepare("
            SELECT duration_days
            FROM courses
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $payment['course_id']
        ]);

        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            throw new Exception("الدورة غير موجودة.");
        }

        $duration_days = max(
            1,
            (int)$course['duration_days']
        );

        $starts_at = date('Y-m-d H:i:s');

        $expires_at = date(
            'Y-m-d H:i:s',
            strtotime("+{$duration_days} days")
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
            VALUES (?, ?, ?, ?, 'active')
        ");

        $stmt->execute([
            $student_id,
            $payment['course_id'],
            $starts_at,
            $expires_at
        ]);
    }


    $pdo->commit();

    header(
        "Location: payment_success_view.php?course_id=" .
        (int)$payment['course_id']
    );

    exit;


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die(
        "حدث خطأ أثناء تفعيل الاشتراك: " .
        e($e->getMessage())
    );
}