<?php

session_start();

require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = db();

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/*
|--------------------------------------------------------------------------
| جلب طلبات الاشتراك
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        sr.id,
        sr.request_number,
        sr.student_id,
        sr.course_id,
        sr.amount,
        sr.status,
        sr.notes,
        sr.admin_notes,
        sr.created_at,
        sr.updated_at,
        sr.approved_at,

        st.name AS student_name,
        st.phone AS student_phone,

        c.title AS course_title,
        c.duration_days,

        t.name AS teacher_name,

        s.name AS subject_name

    FROM subscription_requests sr

    INNER JOIN students st
        ON st.id = sr.student_id

    INNER JOIN courses c
        ON c.id = sr.course_id

    LEFT JOIN teachers t
        ON t.id = c.teacher_id

    LEFT JOIN subjects s
        ON s.id = c.subject_id

    ORDER BY
        CASE
            WHEN sr.status = 'pending' THEN 0
            ELSE 1
        END,
        sr.id DESC
");

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| الإحصائيات
|--------------------------------------------------------------------------
*/

$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

foreach ($requests as $request) {

    if ($request['status'] === 'pending') {
        $pendingCount++;
    }

    if ($request['status'] === 'approved') {
        $approvedCount++;
    }

    if ($request['status'] === 'rejected') {
        $rejectedCount++;
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>طلبات الاشتراك - منصة أكاديمي</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f5f2;
            color: #26382f;
        }

        .page {
            padding: 30px;
            max-width: 1400px;
            margin: auto;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .title h1 {
            margin: 0 0 7px;
            font-size: 28px;
        }

        .title p {
            margin: 0;
            color: #777;
            font-size: 14px;
        }

        .back {
            text-decoration: none;
            background: #345c49;
            color: white;
            padding: 11px 18px;
            border-radius: 10px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,.05);
        }

        .stat .number {
            font-size: 28px;
            font-weight: bold;
            color: #345c49;
            margin-bottom: 5px;
        }

        .stat .label {
            color: #777;
        }

        .table-box {
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,.06);
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            font-size: 18px;
            font-weight: bold;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            background: #345c49;
            color: white;
            padding: 14px 12px;
            text-align: right;
            font-size: 13px;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            font-size: 13px;
        }

        tr:hover td {
            background: #fafbf9;
        }

        .student-name {
            font-weight: bold;
            color: #345c49;
        }

        .phone {
            color: #777;
            margin-top: 4px;
            direction: ltr;
            text-align: right;
        }

        .course {
            font-weight: bold;
        }

        .teacher {
            color: #777;
            margin-top: 4px;
        }

        .amount {
            font-weight: bold;
            white-space: nowrap;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status.approved {
            background: #d8f3df;
            color: #236b36;
        }

        .status.rejected {
            background: #f8d7da;
            color: #842029;
        }

        .status.cancelled {
            background: #e8e8e8;
            color: #666;
        }

        .date {
            color: #777;
            white-space: nowrap;
        }

        .actions {
            display: flex;
            gap: 7px;
        }

        .btn {
            border: 0;
            cursor: pointer;
            border-radius: 9px;
            padding: 8px 12px;
            font-family: inherit;
            font-size: 12px;
            color: white;
        }

        .approve {
            background: #345c49;
        }

        .reject {
            background: #b94a48;
        }

        .btn:hover {
            opacity: .88;
        }

        .empty {
            text-align: center;
            padding: 60px 20px;
            color: #777;
        }

        .request-number {
            font-family: monospace;
            color: #345c49;
            direction: ltr;
            display: inline-block;
        }

        @media (max-width: 700px) {

            .page {
                padding: 15px;
            }

            .top {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .title h1 {
                font-size: 23px;
            }
        }

    </style>

</head>

<body>

<div class="page">

    <div class="top">

        <div class="title">

            <h1>طلبات الاشتراك</h1>

            <p>
                إدارة طلبات اشتراك الطلاب بالدورات
            </p>

        </div>

        <a href="index.php" class="back">
            لوحة التحكم
        </a>

    </div>

    <div class="stats">

        <div class="stat">
            <div class="number">
                <?= $pendingCount ?>
            </div>

            <div class="label">
                طلبات قيد الانتظار
            </div>
        </div>

        <div class="stat">
            <div class="number">
                <?= $approvedCount ?>
            </div>

            <div class="label">
                الطلبات المقبولة
            </div>
        </div>

        <div class="stat">
            <div class="number">
                <?= $rejectedCount ?>
            </div>

            <div class="label">
                الطلبات المرفوضة
            </div>
        </div>

    </div>

    <div class="table-box">

        <div class="table-header">
            جميع طلبات الاشتراك
        </div>

        <?php if (empty($requests)): ?>

            <div class="empty">
                لا توجد طلبات اشتراك حالياً.
            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>

                    <tr>
                        <th>رقم الطلب</th>
                        <th>الطالب</th>
                        <th>الدورة</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>تاريخ الطلب</th>
                        <th>الإجراء</th>
                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($requests as $request): ?>

                        <tr>

                            <td>
                                <span class="request-number">
                                    <?= e($request['request_number']) ?>
                                </span>
                            </td>

                            <td>

                                <div class="student-name">
                                    <?= e($request['student_name']) ?>
                                </div>

                                <div class="phone">
                                    <?= e($request['student_phone']) ?>
                                </div>

                            </td>

                            <td>

                                <div class="course">
                                    <?= e($request['course_title']) ?>
                                </div>

                                <?php if (!empty($request['teacher_name'])): ?>

                                    <div class="teacher">
                                        الأستاذ:
                                        <?= e($request['teacher_name']) ?>
                                    </div>

                                <?php endif; ?>

                            </td>

                            <td>

                                <span class="amount">
                                    <?= number_format((float)$request['amount']) ?>
                                    د.ع
                                </span>

                            </td>

                            <td>

                                <span class="status <?= e($request['status']) ?>">

                                    <?php

                                    if ($request['status'] === 'pending') {
                                        echo 'قيد الانتظار';
                                    } elseif ($request['status'] === 'approved') {
                                        echo 'مقبول';
                                    } elseif ($request['status'] === 'rejected') {
                                        echo 'مرفوض';
                                    } elseif ($request['status'] === 'cancelled') {
                                        echo 'ملغي';
                                    } else {
                                        echo e($request['status']);
                                    }

                                    ?>

                                </span>

                            </td>

                            <td>

                                <div class="date">
                                    <?= e($request['created_at']) ?>
                                </div>

                            </td>

                            <td>

                                <?php if ($request['status'] === 'pending'): ?>

                                    <div class="actions">

                                        <form method="POST"
                                              action="subscription_request_action.php"
                                              onsubmit="return confirm('هل أنت متأكد من قبول هذا الطلب؟');">

                                            <input type="hidden"
                                                   name="request_id"
                                                   value="<?= (int)$request['id'] ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="approve">

                                            <button
                                                type="submit"
                                                class="btn approve">
                                                قبول
                                            </button>

                                        </form>

                                        <form method="POST"
                                              action="subscription_request_action.php"
                                              onsubmit="return confirm('هل أنت متأكد من رفض هذا الطلب؟');">

                                            <input type="hidden"
                                                   name="request_id"
                                                   value="<?= (int)$request['id'] ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="reject">

                                            <button
                                                type="submit"
                                                class="btn reject">
                                                رفض
                                            </button>

                                        </form>

                                    </div>

                                <?php else: ?>

                                    <span style="color:#999;">
                                        لا يوجد إجراء
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>