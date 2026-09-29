<?php

session_start();

require_once __DIR__ . '/../config/config.php';

$pdo = db();

/*
|--------------------------------------------------------------------------
| التحقق من دخول الأدمن
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| تحديد الدورة
|--------------------------------------------------------------------------
*/

$course_id = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);

$edit_id = (int)($_GET['edit'] ?? 0);


/*
|--------------------------------------------------------------------------
| الرسائل
|--------------------------------------------------------------------------
*/

$message = '';
$error = '';


/*
|--------------------------------------------------------------------------
| حذف محاضرة
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lesson'])) {

    $delete_id = (int)($_POST['delete_lesson'] ?? 0);

    if ($delete_id > 0) {

        try {

            $stmt = $pdo->prepare("
                DELETE FROM course_lessons
                WHERE id = ?
            ");

            $stmt->execute([
                $delete_id
            ]);

            $message = 'تم حذف المحاضرة بنجاح.';

        } catch (PDOException $e) {

            $error = 'حدث خطأ أثناء حذف المحاضرة.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| إضافة / تعديل محاضرة
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lesson'])) {

    $lesson_id = (int)($_POST['lesson_id'] ?? 0);

    $course_id = (int)($_POST['course_id'] ?? 0);

    $title = trim($_POST['title'] ?? '');

    $lesson_number = (int)($_POST['lesson_number'] ?? 1);

    $youtube_url = trim($_POST['youtube_url'] ?? '');

    $description = trim($_POST['description'] ?? '');

    $duration = trim($_POST['duration'] ?? '');

    $active = isset($_POST['active']) ? 1 : 0;


    if ($course_id <= 0) {

        $error = 'يرجى اختيار الدورة.';

    } elseif ($title === '') {

        $error = 'يرجى إدخال عنوان المحاضرة.';

    } elseif ($youtube_url === '') {

        $error = 'يرجى إدخال رابط YouTube.';

    } elseif ($lesson_number <= 0) {

        $error = 'رقم المحاضرة غير صحيح.';

    } else {

        try {

            if ($lesson_id > 0) {

                /*
                | تعديل
                */

                $stmt = $pdo->prepare("
                    UPDATE course_lessons

                    SET
                        course_id = ?,
                        title = ?,
                        lesson_number = ?,
                        youtube_url = ?,
                        description = ?,
                        duration = ?,
                        active = ?

                    WHERE id = ?
                ");

                $stmt->execute([
                    $course_id,
                    $title,
                    $lesson_number,
                    $youtube_url,
                    $description !== '' ? $description : null,
                    $duration !== '' ? $duration : null,
                    $active,
                    $lesson_id
                ]);

                $message = 'تم تعديل المحاضرة بنجاح.';

            } else {

                /*
                | إضافة
                */

                $stmt = $pdo->prepare("
                    INSERT INTO course_lessons
                    (
                        course_id,
                        title,
                        lesson_number,
                        youtube_url,
                        description,
                        duration,
                        active
                    )

                    VALUES
                    (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $course_id,
                    $title,
                    $lesson_number,
                    $youtube_url,
                    $description !== '' ? $description : null,
                    $duration !== '' ? $duration : null,
                    $active
                ]);

                $message = 'تمت إضافة المحاضرة بنجاح.';
            }

            /*
            | تنظيف وضع التعديل
            */

            $edit_id = 0;

        } catch (PDOException $e) {

            $error = 'حدث خطأ أثناء حفظ المحاضرة: ' . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| جلب الدورات
|--------------------------------------------------------------------------
*/

$courses = [];

try {

    $stmt = $pdo->query("
        SELECT
            id,
            title,
            active
        FROM courses
        ORDER BY id DESC
    ");

    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $error = 'تعذر جلب الدورات.';
}


/*
|--------------------------------------------------------------------------
| جلب الدورة المختارة
|--------------------------------------------------------------------------
*/

$current_course = null;

if ($course_id > 0) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            active
        FROM courses
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $course_id
    ]);

    $current_course = $stmt->fetch(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| جلب المحاضرة للتعديل
|--------------------------------------------------------------------------
*/

$edit_lesson = null;

if ($edit_id > 0) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            course_id,
            title,
            lesson_number,
            youtube_url,
            description,
            duration,
            active,
            created_at

        FROM course_lessons

        WHERE id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $edit_id
    ]);

    $edit_lesson = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($edit_lesson) {

        $course_id = (int)$edit_lesson['course_id'];

        $stmt = $pdo->prepare("
            SELECT
                id,
                title,
                active
            FROM courses
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $course_id
        ]);

        $current_course = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}


/*
|--------------------------------------------------------------------------
| جلب محاضرات الدورة
|--------------------------------------------------------------------------
*/

$lessons = [];

if ($course_id > 0) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            course_id,
            title,
            lesson_number,
            youtube_url,
            description,
            duration,
            active,
            created_at

        FROM course_lessons

        WHERE course_id = ?

        ORDER BY lesson_number ASC, id ASC
    ");

    $stmt->execute([
        $course_id
    ]);

    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    إدارة محاضرات الدورة - منصة أكاديمي
</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    background: #f4f7fb;

    color: #172033;

    font-family:
        Tahoma,
        Arial,
        sans-serif;
}

.container {

    width: 100%;

    max-width: 1200px;

    margin: auto;

    padding: 25px;
}


/*
|--------------------------------------------------------------------------
| العنوان
|--------------------------------------------------------------------------
*/

.top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 25px;
}

.top h1 {

    margin: 0;

    font-size: 26px;
}

.back {

    text-decoration: none;

    background: white;

    color: #078f83;

    border: 1px solid #e0e6ed;

    padding: 11px 17px;

    border-radius: 11px;
}


/*
|--------------------------------------------------------------------------
| الرسائل
|--------------------------------------------------------------------------
*/

.alert {

    padding: 14px 17px;

    border-radius: 12px;

    margin-bottom: 18px;

    font-size: 14px;
}

.success {

    background: #e8faf5;

    color: #087f72;
}

.danger {

    background: #fff0f0;

    color: #bd3030;
}


/*
|--------------------------------------------------------------------------
| اختيار الدورة
|--------------------------------------------------------------------------
*/

.card {

    background: white;

    border: 1px solid #e2e8f0;

    border-radius: 20px;

    padding: 22px;

    margin-bottom: 20px;

    box-shadow:
        0 8px 25px rgba(20,50,80,.04);
}

.card-title {

    font-size: 18px;

    font-weight: bold;

    margin-bottom: 15px;
}

select,
input,
textarea {

    width: 100%;

    border: 1px solid #dce3eb;

    background: #fff;

    border-radius: 11px;

    padding: 12px 13px;

    font-family: inherit;

    font-size: 14px;

    outline: none;
}

select:focus,
input:focus,
textarea:focus {

    border-color: #08a394;
}

textarea {

    min-height: 110px;

    resize: vertical;
}


/*
|--------------------------------------------------------------------------
| نموذج المحاضرة
|--------------------------------------------------------------------------
*/

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 15px;
}

.field {

    margin-bottom: 2px;
}

.field.full {

    grid-column: 1 / -1;
}

.field label {

    display: block;

    margin-bottom: 7px;

    font-size: 13px;

    font-weight: bold;
}

.checkbox {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-top: 14px;

    font-size: 13px;
}

.checkbox input {

    width: auto;
}

.btn {

    border: 0;

    cursor: pointer;

    border-radius: 11px;

    padding: 12px 20px;

    font-family: inherit;

    font-size: 14px;
}

.btn-primary {

    background: #08a394;

    color: white;
}

.btn-secondary {

    background: #eef2f6;

    color: #39485b;

    text-decoration: none;

    display: inline-block;
}


/*
|--------------------------------------------------------------------------
| الجدول
|--------------------------------------------------------------------------
*/

.table-wrap {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;

    min-width: 750px;
}

th {

    background: #f6f8fb;

    color: #687789;

    font-size: 12px;

    padding: 13px;

    text-align: right;
}

td {

    padding: 14px 13px;

    border-top: 1px solid #edf0f4;

    font-size: 13px;

    vertical-align: middle;
}

.lesson-number {

    width: 38px;

    height: 38px;

    display: flex;

    justify-content: center;

    align-items: center;

    background: #e8f8f5;

    color: #078f83;

    border-radius: 10px;

    font-weight: bold;
}

.badge {

    display: inline-block;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 11px;
}

.badge.active {

    background: #e7faf4;

    color: #078f83;
}

.badge.inactive {

    background: #f1f3f6;

    color: #7b8795;
}

.actions {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;
}

.action {

    text-decoration: none;

    border: 0;

    cursor: pointer;

    font-family: inherit;

    font-size: 12px;

    padding: 8px 11px;

    border-radius: 8px;
}

.edit {

    background: #eef6ff;

    color: #2876bd;
}

.delete {

    background: #fff0f0;

    color: #c12d2d;
}


/*
|--------------------------------------------------------------------------
| لا توجد محاضرات
|--------------------------------------------------------------------------
*/

.empty {

    text-align: center;

    padding: 45px 20px;

    color: #8b98a9;
}


/*
|--------------------------------------------------------------------------
| موبايل
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .container {
        padding: 15px;
    }

    .top {
        align-items: flex-start;
        flex-direction: column;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .field.full {
        grid-column: auto;
    }

}

</style>

</head>


<body>


<div class="container">


    <!-- =================================================
         العنوان
    ================================================== -->

    <div class="top">

        <h1>
            🎥 إدارة محاضرات الدورات
        </h1>

        <a
            href="courses.php"
            class="back"
        >
            ← العودة إلى الدورات
        </a>

    </div>


    <!-- =================================================
         الرسائل
    ================================================== -->

    <?php if ($message): ?>

        <div class="alert success">

            <?= e($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert danger">

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         اختيار الدورة
    ================================================== -->

    <div class="card">

        <div class="card-title">

            📚 اختر الدورة

        </div>


        <form method="get">

            <select
                name="course_id"
                onchange="this.form.submit()"
            >

                <option value="">
                    -- اختر الدورة --
                </option>


                <?php foreach ($courses as $course): ?>

                    <option
                        value="<?= (int)$course['id'] ?>"
                        <?= $course_id == $course['id']
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= e($course['title']) ?>

                        —

                        #<?= (int)$course['id'] ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </form>

    </div>


    <?php if ($current_course): ?>


        <!-- =================================================
             معلومات الدورة
        ================================================== -->

        <div class="card">

            <div class="card-title">

                الدورة:

                <?= e($current_course['title']) ?>

            </div>

            <div style="color:#8a98ab;font-size:13px;">

                يمكنك من هنا إضافة محاضرات هذه الدورة،
                وتعديلها أو حذفها.

            </div>

        </div>


        <!-- =================================================
             إضافة / تعديل
        ================================================== -->

        <div class="card">

            <div class="card-title">

                <?= $edit_lesson
                    ? '✏️ تعديل المحاضرة'
                    : '➕ إضافة محاضرة جديدة'
                ?>

            </div>


            <form method="post">

                <input
                    type="hidden"
                    name="course_id"
                    value="<?= (int)$course_id ?>"
                >


                <?php if ($edit_lesson): ?>

                    <input
                        type="hidden"
                        name="lesson_id"
                        value="<?= (int)$edit_lesson['id'] ?>"
                    >

                <?php endif; ?>


                <div class="form-grid">


                    <!-- عنوان المحاضرة -->

                    <div class="field full">

                        <label>
                            عنوان المحاضرة
                        </label>

                        <input
                            type="text"
                            name="title"
                            required
                            placeholder="مثال: مقدمة في الفصل الأول"
                            value="<?= e(
                                $edit_lesson['title']
                                ?? ''
                            ) ?>"
                        >

                    </div>


                    <!-- رقم المحاضرة -->

                    <div class="field">

                        <label>
                            رقم المحاضرة
                        </label>

                        <input
                            type="number"
                            name="lesson_number"
                            min="1"
                            required
                            value="<?= (int)(
                                $edit_lesson['lesson_number']
                                ?? (
                                    count($lessons) + 1
                                )
                            ) ?>"
                        >

                    </div>


                    <!-- المدة -->

                    <div class="field">

                        <label>
                            مدة المحاضرة
                        </label>

                        <input
                            type="text"
                            name="duration"
                            placeholder="مثال: 45 دقيقة"
                            value="<?= e(
                                $edit_lesson['duration']
                                ?? ''
                            ) ?>"
                        >

                    </div>


                    <!-- رابط يوتيوب -->

                    <div class="field full">

                        <label>
                            رابط YouTube
                        </label>

                        <input
                            type="url"
                            name="youtube_url"
                            required
                            placeholder="https://www.youtube.com/watch?v=..."
                            value="<?= e(
                                $edit_lesson['youtube_url']
                                ?? ''
                            ) ?>"
                        >

                        <div
                            style="
                            color:#8a98ab;
                            font-size:11px;
                            margin-top:7px;
                            "
                        >
                            سيتم تشغيل الفيديو داخل منصة أكاديمي.
                        </div>

                    </div>


                    <!-- الوصف -->

                    <div class="field full">

                        <label>
                            وصف المحاضرة
                        </label>

                        <textarea
                            name="description"
                            placeholder="اكتب وصفاً مختصراً للمحاضرة..."
                        ><?= e(
                            $edit_lesson['description']
                            ?? ''
                        ) ?></textarea>

                    </div>


                </div>


                <!-- التفعيل -->

                <label class="checkbox">

                    <input
                        type="checkbox"
                        name="active"
                        value="1"
                        <?= !isset($edit_lesson)
                            || (int)$edit_lesson['active'] === 1
                            ? 'checked'
                            : ''
                        ?>
                    >

                    المحاضرة مفعلة ويمكن للطالب مشاهدتها

                </label>


                <div
                    style="
                    margin-top:18px;
                    display:flex;
                    gap:10px;
                    flex-wrap:wrap;
                    "
                >

                    <button
                        type="submit"
                        name="save_lesson"
                        value="1"
                        class="btn btn-primary"
                    >

                        <?= $edit_lesson
                            ? '💾 حفظ التعديل'
                            : '➕ إضافة المحاضرة'
                        ?>

                    </button>


                    <?php if ($edit_lesson): ?>

                        <a
                            href="course_lessons.php?course_id=<?= (int)$course_id ?>"
                            class="btn btn-secondary"
                        >

                            إلغاء التعديل

                        </a>

                    <?php endif; ?>

                </div>


            </form>

        </div>


        <!-- =================================================
             قائمة المحاضرات
        ================================================== -->

        <div class="card">

            <div class="card-title">

                🎬 محاضرات الدورة

                <span
                    style="
                    color:#8a98ab;
                    font-size:12px;
                    font-weight:normal;
                    "
                >

                    (<?= count($lessons) ?>)

                </span>

            </div>


            <?php if (!$lessons): ?>

                <div class="empty">

                    <div style="font-size:45px;">
                        🎥
                    </div>

                    <p>
                        لا توجد محاضرات لهذه الدورة حتى الآن.
                    </p>

                </div>

            <?php else: ?>


                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    المحاضرة
                                </th>

                                <th>
                                    المدة
                                </th>

                                <th>
                                    الحالة
                                </th>

                                <th>
                                    التاريخ
                                </th>

                                <th>
                                    الإجراءات
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($lessons as $lesson): ?>

                            <tr>


                                <td>

                                    <div class="lesson-number">

                                        <?= (int)$lesson['lesson_number'] ?>

                                    </div>

                                </td>


                                <td>

                                    <strong>

                                        <?= e($lesson['title']) ?>

                                    </strong>


                                    <?php if (!empty($lesson['description'])): ?>

                                        <div
                                            style="
                                            color:#8a98ab;
                                            font-size:11px;
                                            margin-top:5px;
                                            "
                                        >

                                            <?= e(
                                                mb_substr(
                                                    $lesson['description'],
                                                    0,
                                                    80
                                                )
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= e(
                                        $lesson['duration']
                                        ?: '-'
                                    ) ?>

                                </td>


                                <td>

                                    <?php if ((int)$lesson['active'] === 1): ?>

                                        <span class="badge active">
                                            مفعلة
                                        </span>

                                    <?php else: ?>

                                        <span class="badge inactive">
                                            غير مفعلة
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= e(
                                        $lesson['created_at']
                                    ) ?>

                                </td>


                                <td>

                                    <div class="actions">


                                        <!-- تعديل -->

                                        <a
                                            href="course_lessons.php?course_id=<?= (int)$course_id ?>&edit=<?= (int)$lesson['id'] ?>"
                                            class="action edit"
                                        >

                                            ✏️ تعديل

                                        </a>


                                        <!-- حذف -->

                                        <form
                                            method="post"
                                            style="display:inline;"
                                            onsubmit="
                                                return confirm(
                                                    'هل أنت متأكد من حذف هذه المحاضرة؟'
                                                );
                                            "
                                        >

                                            <input
                                                type="hidden"
                                                name="course_id"
                                                value="<?= (int)$course_id ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_lesson"
                                                value="<?= (int)$lesson['id'] ?>"
                                                class="action delete"
                                            >

                                                🗑 حذف

                                            </button>

                                        </form>


                                    </div>

                                </td>


                            </tr>

                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php endif; ?>


        </div>


    <?php else: ?>


        <!-- =================================================
             لا توجد دورة مختارة
        ================================================== -->

        <div class="card">

            <div class="empty">

                <div style="font-size:55px;">
                    📚
                </div>

                <h3>
                    اختر دورة للبدء
                </h3>

                <p>
                    اختر دورة من القائمة أعلاه حتى تتمكن من
                    إضافة وإدارة محاضراتها.
                </p>

            </div>

        </div>


    <?php endif; ?>


</div>


</body>

</html>