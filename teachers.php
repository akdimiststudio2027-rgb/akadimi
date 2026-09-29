<?php

require_once __DIR__ . '/../bootstrap.php';
$identity = authenticated();

if ($identity['role'] !== 'student') {
    api_response(['error' => 'Student access required.'], 403);
}

$pdo = db();
$studentStmt = $pdo->prepare(
    'SELECT stage_id, curriculum_id FROM students WHERE id = ? AND active = 1 LIMIT 1'
);
$studentStmt->execute([(int)$identity['id']]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student || empty($student['stage_id']) || empty($student['curriculum_id'])) {
    api_response(['error' => 'حدّث المرحلة والمنهاج بحسابك حتى نعرض المدرّسين المناسبين.'], 422);
}

$stmt = $pdo->prepare(<<<'SQL'
    SELECT
        t.id AS teacher_id,
        t.name AS teacher_name,
        t.image AS teacher_image,
        c.id AS course_id,
        c.title AS course_title,
        c.academic_year,
        c.price,
        c.duration_days,
        sub.name AS subject_name,
        COUNT(cl.id) AS lessons_count
    FROM teachers t
    INNER JOIN courses c
        ON c.teacher_id = t.id
       AND c.active = 1
    LEFT JOIN subjects sub ON sub.id = c.subject_id
    LEFT JOIN course_lessons cl
        ON cl.course_id = c.id
       AND cl.active = 1
    WHERE t.active = 1
      AND t.stage_id = ?
      AND (t.curriculum_id = ? OR t.curriculum_id IS NULL)
    GROUP BY
        t.id,
        t.name,
        t.image,
        c.id,
        c.title,
        c.academic_year,
        c.price,
        c.duration_days,
        sub.name
    ORDER BY t.id DESC, c.id DESC
    SQL);
$stmt->execute([
    (int)$student['stage_id'],
    (int)$student['curriculum_id'],
]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$origin = ($isHttps ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
$teachers = [];

foreach ($rows as $row) {
    $teacherId = (int)$row['teacher_id'];
    if (!isset($teachers[$teacherId])) {
        $teacherImage = trim((string)($row['teacher_image'] ?? ''));
        $imageUrl = null;

        if (preg_match('~^https?://~i', $teacherImage)) {
            $imageUrl = $teacherImage;
        } elseif ($teacherImage !== '') {
            $filename = basename(str_replace('\\', '/', $teacherImage));
            if ($filename !== '' && $origin !== 'http://' && $origin !== 'https://') {
                $imageUrl = $origin . '/uploads/teachers/' . rawurlencode($filename);
            }
        }

        $teachers[$teacherId] = [
            'id' => $teacherId,
            'name' => $row['teacher_name'],
            'image_url' => $imageUrl,
            'courses_count' => 0,
            'lessons_count' => 0,
            'courses' => [],
        ];
    }

    $lessonCount = (int)$row['lessons_count'];
    $teachers[$teacherId]['courses_count']++;
    $teachers[$teacherId]['lessons_count'] += $lessonCount;
    $teachers[$teacherId]['courses'][] = [
        'id' => (int)$row['course_id'],
        'title' => $row['course_title'],
        'academic_year' => $row['academic_year'],
        'price' => (float)$row['price'],
        'duration_days' => (int)$row['duration_days'],
        'subject_name' => $row['subject_name'],
        'lessons_count' => $lessonCount,
    ];
}

api_response(['teachers' => array_values($teachers)]);