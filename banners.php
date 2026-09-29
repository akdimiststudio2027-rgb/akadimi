<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

require_once __DIR__ . '/../config/config.php';

$pdo = db();

/* =========================================================
   حماية الأدمن
========================================================= */

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

/* =========================================================
   دالة حماية النصوص
========================================================= */

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

/* =========================================================
   مجلد الصور
========================================================= */

$uploadDir = __DIR__ . '/../uploads/banners/';

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

/* =========================================================
   إضافة إعلان
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_banner'])) {

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;

    if ($title === '') {
        header('Location: banners.php?error=title');
        exit;
    }

    /* -----------------------------------------------------
       الصورة
    ----------------------------------------------------- */

    $imageName = '';

    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] === UPLOAD_ERR_OK
    ) {

        $tmpName = $_FILES['image']['tmp_name'];
        $originalName = $_FILES['image']['name'];

        $extension = strtolower(
            pathinfo(
                $originalName,
                PATHINFO_EXTENSION
            )
        );

        $allowed = [
            'jpg',
            'jpeg',
            'png',
            'webp'
        ];

        if (!in_array($extension, $allowed, true)) {
            header('Location: banners.php?error=image');
            exit;
        }

        $imageName =
            'banner_' .
            time() .
            '_' .
            bin2hex(random_bytes(4)) .
            '.' .
            $extension;

        $destination =
            $uploadDir .
            $imageName;

        if (!move_uploaded_file(
            $tmpName,
            $destination
        )) {

            header('Location: banners.php?error=upload');
            exit;
        }
    }

    try {

        $stmt = $pdo->prepare("
            INSERT INTO banners
            (
                title,
                description,
                image,
                link,
                sort_order,
                active,
                created_at,
                updated_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            $title,
            $description,
            $imageName,
            $link,
            $sortOrder,
            $active
        ]);

        header('Location: banners.php?success=added');
        exit;

    } catch (PDOException $e) {

        if ($imageName !== '') {
            @unlink(
                $uploadDir .
                $imageName
            );
        }

        header('Location: banners.php?error=db');
        exit;
    }
}

/* =========================================================
   حذف إعلان
========================================================= */

if (
    isset($_GET['delete']) &&
    (int)$_GET['delete'] > 0
) {

    $bannerId = (int)$_GET['delete'];

    try {

        $stmt = $pdo->prepare("
            SELECT image
            FROM banners
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $bannerId
        ]);

        $banner = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($banner) {

            $stmt = $pdo->prepare("
                DELETE FROM banners
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $bannerId
            ]);

            if (!empty($banner['image'])) {

                $imagePath =
                    $uploadDir .
                    basename($banner['image']);

                if (is_file($imagePath)) {
                    @unlink($imagePath);
                }
            }
        }

        header('Location: banners.php?success=deleted');
        exit;

    } catch (PDOException $e) {

        header('Location: banners.php?error=db');
        exit;
    }
}

/* =========================================================
   تفعيل / تعطيل
========================================================= */

if (
    isset($_GET['toggle']) &&
    (int)$_GET['toggle'] > 0
) {

    $bannerId = (int)$_GET['toggle'];

    try {

        $stmt = $pdo->prepare("
            UPDATE banners
            SET
                active = IF(active = 1, 0, 1),
                updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $bannerId
        ]);

        header('Location: banners.php?success=updated');
        exit;

    } catch (PDOException $e) {

        header('Location: banners.php?error=db');
        exit;
    }
}

/* =========================================================
   الإعلانات
========================================================= */

try {

    $stmt = $pdo->query("
        SELECT
            id,
            title,
            description,
            image,
            link,
            sort_order,
            active,
            created_at,
            updated_at
        FROM banners
        ORDER BY
            sort_order ASC,
            id DESC
    ");

    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $banners = [];
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

<title>الإعلانات | لوحة التحكم</title>

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

    background: #f4f6f3;

    color: #24362d;

}

.page {

    max-width: 1200px;

    margin: auto;

    padding: 25px;

}

.header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 25px;

}

.title {

    font-size: 25px;

    font-weight: bold;

    color: #345c49;

}

.subtitle {

    color: #78857d;

    font-size: 13px;

    margin-top: 5px;

}

.back {

    text-decoration: none;

    background: #345c49;

    color: white;

    padding: 11px 18px;

    border-radius: 12px;

}

/* =====================================================
   Alerts
===================================================== */

.alert {

    padding: 13px 16px;

    border-radius: 12px;

    margin-bottom: 18px;

    font-size: 13px;

}

.alert-success {

    background: #e4f0e7;

    color: #345c49;

}

.alert-error {

    background: #f8e4e4;

    color: #a33b3b;

}

/* =====================================================
   Add Box
===================================================== */

.add-box {

    background: white;

    border-radius: 20px;

    padding: 22px;

    margin-bottom: 25px;

    box-shadow:
        0 8px 25px rgba(52,92,73,.07);

}

.add-title {

    font-size: 18px;

    font-weight: bold;

    color: #345c49;

    margin-bottom: 18px;

}

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 15px;

}

.form-group {

    display: flex;

    flex-direction: column;

    gap: 7px;

}

.form-group.full {

    grid-column: 1 / -1;

}

label {

    font-size: 12px;

    font-weight: bold;

    color: #526159;

}

input,
textarea {

    width: 100%;

    border: 1px solid #dfe5df;

    border-radius: 11px;

    padding: 12px;

    outline: none;

    font-family: inherit;

    background: #fafbfa;

}

textarea {

    min-height: 90px;

    resize: vertical;

}

input:focus,
textarea:focus {

    border-color: #9bac78;

    background: white;

}

.image-input {

    background: #f7f8f6;

}

.check-row {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-top: 7px;

}

.check-row input {

    width: auto;

}

.submit-btn {

    margin-top: 18px;

    border: 0;

    background: #345c49;

    color: white;

    padding: 13px 25px;

    border-radius: 12px;

    font-family: inherit;

    font-weight: bold;

    cursor: pointer;

}

/* =====================================================
   Banners
===================================================== */

.banner-list {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 18px;

}

.banner-card {

    background: white;

    border-radius: 20px;

    overflow: hidden;

    box-shadow:
        0 8px 25px rgba(52,92,73,.07);

}

.banner-image {

    width: 100%;

    height: 190px;

    background:
        linear-gradient(
            135deg,
            #345c49,
            #9bac78
        );

    position: relative;

    overflow: hidden;

}

.banner-image img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;

}

.no-image {

    width: 100%;

    height: 100%;

    display: flex;

    align-items: center;

    justify-content: center;

    color: white;

    font-size: 35px;

}

.status {

    position: absolute;

    top: 10px;

    right: 10px;

    padding: 6px 10px;

    border-radius: 9px;

    font-size: 10px;

    font-weight: bold;

    color: white;

}

.status.active {

    background: #345c49;

}

.status.inactive {

    background: #8b8f8c;

}

.banner-body {

    padding: 17px;

}

.banner-title {

    font-size: 16px;

    font-weight: bold;

    color: #345c49;

}

.banner-description {

    color: #78857d;

    font-size: 12px;

    line-height: 1.7;

    margin-top: 7px;

    min-height: 20px;

}

.banner-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 7px;

    margin-top: 12px;

}

.meta {

    background: #f0f3ee;

    color: #617066;

    border-radius: 9px;

    padding: 6px 9px;

    font-size: 10px;

}

.actions {

    display: flex;

    gap: 8px;

    margin-top: 14px;

}

.action {

    flex: 1;

    text-align: center;

    padding: 9px;

    border-radius: 10px;

    text-decoration: none;

    font-size: 11px;

    font-weight: bold;

}

.toggle {

    background: #edf2ec;

    color: #345c49;

}

.delete {

    background: #f8e8e8;

    color: #a33b3b;

}

.empty {

    background: white;

    border-radius: 20px;

    padding: 45px 20px;

    text-align: center;

    color: #7c8881;

}

.empty-icon {

    font-size: 42px;

    margin-bottom: 10px;

}

@media (max-width: 700px) {

    .page {
        padding: 15px;
    }

    .header {
        align-items: flex-start;
        flex-direction: column;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-group.full {
        grid-column: auto;
    }

    .banner-list {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<div class="page">

    <div class="header">

        <div>

            <div class="title">
                📢 الإعلانات
            </div>

            <div class="subtitle">
                إدارة إعلانات البانر التي تظهر للطلاب
            </div>

        </div>

        <a
            href="index.php"
            class="back"
        >
            ← لوحة التحكم
        </a>

    </div>


    <?php if (isset($_GET['success'])): ?>

        <div class="alert alert-success">

            <?php if ($_GET['success'] === 'added'): ?>

                تم إضافة الإعلان بنجاح.

            <?php elseif ($_GET['success'] === 'deleted'): ?>

                تم حذف الإعلان بنجاح.

            <?php else: ?>

                تم تحديث الإعلان بنجاح.

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['error'])): ?>

        <div class="alert alert-error">

            <?php if ($_GET['error'] === 'title'): ?>

                يرجى كتابة عنوان الإعلان.

            <?php elseif ($_GET['error'] === 'image'): ?>

                صيغة الصورة غير مسموحة. استخدم JPG أو PNG أو WEBP.

            <?php elseif ($_GET['error'] === 'upload'): ?>

                تعذر رفع الصورة.

            <?php else: ?>

                حدث خطأ أثناء حفظ الإعلان.

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         إضافة إعلان
    ====================================================== -->

    <div class="add-box">

        <div class="add-title">
            ➕ إضافة إعلان جديد
        </div>

        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        عنوان الإعلان
                    </label>

                    <input
                        type="text"
                        name="title"
                        required
                        placeholder="مثال: تعلم مع أفضل الأساتذة"
                    >

                </div>


                <div class="form-group">

                    <label>
                        ترتيب الإعلان
                    </label>

                    <input
                        type="number"
                        name="sort_order"
                        value="0"
                        min="0"
                    >

                </div>


                <div class="form-group full">

                    <label>
                        وصف الإعلان
                    </label>

                    <textarea
                        name="description"
                        placeholder="اكتب وصفاً قصيراً للإعلان..."
                    ></textarea>

                </div>


                <div class="form-group">

                    <label>
                        رابط الإعلان - اختياري
                    </label>

                    <input
                        type="text"
                        name="link"
                        placeholder="https://..."
                    >

                </div>


                <div class="form-group">

                    <label>
                        صورة الإعلان
                    </label>

                    <input
                        type="file"
                        name="image"
                        class="image-input"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                </div>

            </div>


            <div class="check-row">

                <input
                    type="checkbox"
                    name="active"
                    id="active"
                    value="1"
                    checked
                >

                <label for="active">
                    تفعيل الإعلان مباشرة
                </label>

            </div>


            <button
                type="submit"
                name="add_banner"
                value="1"
                class="submit-btn"
            >
                إضافة الإعلان
            </button>

        </form>

    </div>


    <!-- =====================================================
         قائمة الإعلانات
    ====================================================== -->

    <?php if (!empty($banners)): ?>

        <div class="banner-list">

            <?php foreach ($banners as $banner): ?>

                <div class="banner-card">

                    <div class="banner-image">

                        <?php if (!empty($banner['image'])): ?>

                            <img
                                src="../uploads/banners/<?= e($banner['image']) ?>"
                                alt="<?= e($banner['title']) ?>"
                                onerror="this.style.display='none';"
                            >

                        <?php else: ?>

                            <div class="no-image">
                                📢
                            </div>

                        <?php endif; ?>


                        <?php if ((int)$banner['active'] === 1): ?>

                            <div class="status active">
                                فعال
                            </div>

                        <?php else: ?>

                            <div class="status inactive">
                                متوقف
                            </div>

                        <?php endif; ?>

                    </div>


                    <div class="banner-body">

                        <div class="banner-title">

                            <?= e($banner['title']) ?>

                        </div>


                        <div class="banner-description">

                            <?= e($banner['description']) ?>

                        </div>


                        <div class="banner-meta">

                            <div class="meta">

                                الترتيب:
                                <?= (int)$banner['sort_order'] ?>

                            </div>


                            <div class="meta">

                                <?= !empty($banner['link'])
                                    ? '🔗 يوجد رابط'
                                    : 'بدون رابط'
                                ?>

                            </div>

                        </div>


                        <div class="actions">

                            <a
                                href="banners.php?toggle=<?= (int)$banner['id'] ?>"
                                class="action toggle"
                            >

                                <?= (int)$banner['active'] === 1
                                    ? '⏸ تعطيل'
                                    : '▶ تفعيل'
                                ?>

                            </a>


                            <a
                                href="banners.php?delete=<?= (int)$banner['id'] ?>"
                                class="action delete"
                                onclick="return confirm('هل أنت متأكد من حذف هذا الإعلان؟');"
                            >

                                🗑 حذف

                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">
                📢
            </div>

            لا توجد إعلانات مضافة حالياً.

        </div>

    <?php endif; ?>

</div>

</body>

</html>
