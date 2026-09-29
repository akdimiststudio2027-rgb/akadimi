<?php
require_once __DIR__ . '/../config/config.php';

$pdo = db();

/*
|--------------------------------------------------------------------------
| حذف دفعة
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$id]);
    }

    header("Location: payments.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| إضافة / تعديل دفعة
|--------------------------------------------------------------------------
*/
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    $student_id = (int) ($_POST['student_id'] ?? 0);
    $course_id = (int) ($_POST['course_id'] ?? 0);

    $amount = trim($_POST['amount'] ?? '');
    $provider = trim($_POST['provider'] ?? '');
    $transaction_ref = trim($_POST['transaction_ref'] ?? '');
    $status = trim($_POST['status'] ?? 'pending');
    $paid_at = trim($_POST['paid_at'] ?? '');

    $allowed_statuses = ['pending', 'paid', 'failed', 'cancelled'];

    if ($student_id <= 0) {
        $error = 'يرجى اختيار الطالب.';
    } elseif ($course_id <= 0) {
        $error = 'يرجى اختيار الدورة.';
    } elseif ($amount === '' || !is_numeric($amount)) {
        $error = 'يرجى إدخال مبلغ صحيح.';
    } elseif (!in_array($status, $allowed_statuses, true)) {
        $error = 'حالة الدفع غير صحيحة.';
    } else {

        /*
        إذا كانت الدفعة مدفوعة ولم يتم إدخال تاريخ،
        نضع الوقت الحالي تلقائياً.
        */
        if ($status === 'paid' && $paid_at === '') {
            $paid_at = date('Y-m-d H:i:s');
        }

        if ($paid_at !== '') {
            $paid_at = str_replace('T', ' ', $paid_at);

            if (strlen($paid_at) === 16) {
                $paid_at .= ':00';
            }
        } else {
            $paid_at = null;
        }

        try {

            if ($id > 0) {

                $stmt = $pdo->prepare("
                    UPDATE payments
                    SET
                        student_id = ?,
                        course_id = ?,
                        amount = ?,
                        provider = ?,
                        transaction_ref = ?,
                        status = ?,
                        paid_at = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $student_id,
                    $course_id,
                    $amount,
                    $provider,
                    $transaction_ref,
                    $status,
                    $paid_at,
                    $id
                ]);

                $message = 'تم تعديل الدفعة بنجاح.';

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO payments
                    (
                        student_id,
                        course_id,
                        amount,
                        provider,
                        transaction_ref,
                        status,
                        paid_at
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $student_id,
                    $course_id,
                    $amount,
                    $provider,
                    $transaction_ref,
                    $status,
                    $paid_at
                ]);

                $message = 'تمت إضافة الدفعة بنجاح.';
            }

        } catch (PDOException $e) {
            $error = 'حدث خطأ أثناء حفظ الدفعة: ' . $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| بيانات التعديل
|--------------------------------------------------------------------------
*/
$edit_payment = null;

if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        $edit_payment = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

/*
|--------------------------------------------------------------------------
| الطلاب
|--------------------------------------------------------------------------
*/
$students = $pdo->query("
    SELECT id, name, phone
    FROM students
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| الدورات
|--------------------------------------------------------------------------
*/
$courses = $pdo->query("
    SELECT id, title, price
    FROM courses
    ORDER BY title ASC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| المدفوعات
|--------------------------------------------------------------------------
*/
$payments = $pdo->query("
    SELECT
        p.*,
        s.name AS student_name,
        s.phone AS student_phone,
        c.title AS course_title
    FROM payments p
    LEFT JOIN students s ON s.id = p.student_id
    LEFT JOIN courses c ON c.id = p.course_id
    ORDER BY p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>المدفوعات - منصة أكاديمي</title>

    <style>
        body {
            margin: 0;
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            width: 95%;
            max-width: 1400px;
            margin: 30px auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .title {
            font-size: 28px;
            font-weight: bold;
        }

        .back {
            text-decoration: none;
            background: #374151;
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
        }

        .card {
            background: white;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 25px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        }

        .card h2 {
            margin-top: 0;
            font-size: 21px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field label {
            font-weight: bold;
            font-size: 14px;
        }

        input,
        select {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        button,
        .btn {
            border: none;
            cursor: pointer;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 8px;
            font-size: 14px;
            display: inline-block;
        }

        .btn-save {
            background: #2563eb;
            color: white;
        }

        .btn-cancel {
            background: #6b7280;
            color: white;
        }

        .btn-edit {
            background: #f59e0b;
            color: white;
        }

        .btn-delete {
            background: #dc2626;
            color: white;
        }

        .message {
            background: #dcfce7;
            color: #166534;
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 18px;
            direction: rtl;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        th,
        td {
            padding: 13px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: right;
        }

        th {
            background: #f3f4f6;
            font-weight: bold;
        }

        tr:hover td {
            background: #fafafa;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-paid {
            background: #dcfce7;
            color: #166534;
        }

        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-cancelled {
            background: #e5e7eb;
            color: #374151;
        }

        .small {
            color: #6b7280;
            font-size: 12px;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #6b7280;
        }

        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .container {
                width: 92%;
            }

            .title {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="topbar">
        <div class="title">💳 إدارة المدفوعات</div>

        <a href="index.php" class="back">
            العودة للوحة التحكم
        </a>
    </div>

    <?php if ($message): ?>
        <div class="message">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>


    <!-- إضافة / تعديل -->
    <div class="card">

        <h2>
            <?= $edit_payment ? '✏️ تعديل دفعة' : '➕ إضافة دفعة' ?>
        </h2>

        <form method="POST">

            <input
                type="hidden"
                name="id"
                value="<?= $edit_payment ? (int)$edit_payment['id'] : 0 ?>"
            >

            <div class="form-grid">

                <!-- الطالب -->
                <div class="field">
                    <label>الطالب</label>

                    <select name="student_id" required>

                        <option value="">اختر الطالب</option>

                        <?php foreach ($students as $student): ?>

                            <option
                                value="<?= (int)$student['id'] ?>"
                                <?= (
                                    $edit_payment &&
                                    (int)$edit_payment['student_id'] === (int)$student['id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= e($student['name']) ?>
                                - <?= e($student['phone']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>


                <!-- الدورة -->
                <div class="field">
                    <label>الدورة</label>

                    <select name="course_id" required>

                        <option value="">اختر الدورة</option>

                        <?php foreach ($courses as $course): ?>

                            <option
                                value="<?= (int)$course['id'] ?>"
                                <?= (
                                    $edit_payment &&
                                    (int)$edit_payment['course_id'] === (int)$course['id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= e($course['title']) ?>
                                <?php if ($course['price'] !== null): ?>
                                    - <?= e($course['price']) ?>
                                <?php endif; ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>


                <!-- المبلغ -->
                <div class="field">
                    <label>المبلغ</label>

                    <input
                        type="number"
                        name="amount"
                        step="0.01"
                        min="0"
                        required
                        value="<?= $edit_payment ? e($edit_payment['amount']) : '' ?>"
                        placeholder="مثال: 50000"
                    >
                </div>


                <!-- مزود الدفع -->
                <div class="field">
                    <label>مزود الدفع</label>

                    <input
                        type="text"
                        name="provider"
                        value="<?= $edit_payment ? e($edit_payment['provider']) : '' ?>"
                        placeholder="مثال: Mastercard"
                    >
                </div>


                <!-- رقم العملية -->
                <div class="field">
                    <label>رقم العملية</label>

                    <input
                        type="text"
                        name="transaction_ref"
                        value="<?= $edit_payment ? e($edit_payment['transaction_ref']) : '' ?>"
                        placeholder="رقم العملية / المرجع"
                    >
                </div>


                <!-- الحالة -->
                <div class="field">
                    <label>حالة الدفع</label>

                    <select name="status" required>

                        <?php
                        $current_status = $edit_payment['status'] ?? 'pending';
                        ?>

                        <option
                            value="pending"
                            <?= $current_status === 'pending' ? 'selected' : '' ?>
                        >
                            قيد الانتظار
                        </option>

                        <option
                            value="paid"
                            <?= $current_status === 'paid' ? 'selected' : '' ?>
                        >
                            مدفوع
                        </option>

                        <option
                            value="failed"
                            <?= $current_status === 'failed' ? 'selected' : '' ?>
                        >
                            فشل
                        </option>

                        <option
                            value="cancelled"
                            <?= $current_status === 'cancelled' ? 'selected' : '' ?>
                        >
                            ملغى
                        </option>

                    </select>
                </div>


                <!-- تاريخ الدفع -->
                <div class="field">

                    <label>تاريخ الدفع</label>

                    <input
                        type="datetime-local"
                        name="paid_at"
                        value="<?php
                            if ($edit_payment && !empty($edit_payment['paid_at'])) {
                                echo e(date(
                                    'Y-m-d\TH:i',
                                    strtotime($edit_payment['paid_at'])
                                ));
                            }
                        ?>"
                    >

                </div>

            </div>


            <div class="actions">

                <button type="submit" class="btn btn-save">
                    <?= $edit_payment ? 'حفظ التعديل' : 'إضافة الدفعة' ?>
                </button>

                <?php if ($edit_payment): ?>

                    <a href="payments.php" class="btn btn-cancel">
                        إلغاء التعديل
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </div>


    <!-- قائمة المدفوعات -->
    <div class="card">

        <h2>📋 سجل المدفوعات</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>#</th>
                        <th>الطالب</th>
                        <th>الدورة</th>
                        <th>المبلغ</th>
                        <th>مزود الدفع</th>
                        <th>رقم العملية</th>
                        <th>الحالة</th>
                        <th>تاريخ الدفع</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!$payments): ?>

                    <tr>
                        <td colspan="9" class="empty">
                            لا توجد مدفوعات حالياً.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($payments as $payment): ?>

                        <tr>

                            <td>
                                <?= (int)$payment['id'] ?>
                            </td>

                            <td>

                                <?= e($payment['student_name'] ?? 'غير معروف') ?>

                                <?php if (!empty($payment['student_phone'])): ?>

                                    <div class="small">
                                        <?= e($payment['student_phone']) ?>
                                    </div>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= e($payment['course_title'] ?? 'غير معروف') ?>
                            </td>

                            <td>
                                <?= e($payment['amount']) ?>
                            </td>

                            <td>
                                <?= e($payment['provider'] ?? '-') ?>
                            </td>

                            <td>
                                <?= e($payment['transaction_ref'] ?? '-') ?>
                            </td>

                            <td>

                                <?php
                                $status = $payment['status'];

                                $status_class = 'status-pending';
                                $status_text = 'قيد الانتظار';

                                if ($status === 'paid') {
                                    $status_class = 'status-paid';
                                    $status_text = 'مدفوع';
                                } elseif ($status === 'failed') {
                                    $status_class = 'status-failed';
                                    $status_text = 'فشل';
                                } elseif ($status === 'cancelled') {
                                    $status_class = 'status-cancelled';
                                    $status_text = 'ملغى';
                                }
                                ?>

                                <span class="status <?= $status_class ?>">
                                    <?= $status_text ?>
                                </span>

                            </td>

                            <td>
                                <?= !empty($payment['paid_at'])
                                    ? e($payment['paid_at'])
                                    : '-' ?>
                            </td>

                            <td>

                                <a
                                    href="payments.php?edit=<?= (int)$payment['id'] ?>"
                                    class="btn btn-edit"
                                >
                                    تعديل
                                </a>

                                <a
                                    href="payments.php?delete=<?= (int)$payment['id'] ?>"
                                    class="btn btn-delete"
                                    onclick="return confirm('هل أنت متأكد من حذف هذه الدفعة؟');"
                                >
                                    حذف
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>