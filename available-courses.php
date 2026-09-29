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

if (!$student) {
    api_response(['error' => 'Student account not found.'], 404);
}

$stmt = $pdo->prepare(<<<'SQL'
    SELECT
        c.id,
        c.title,
        c.description,
        c.academic_year,
        c.price,
        c.duration_days,
        t.name AS teacher_name,
        sub.name AS subject_name,
        (
            SELECT COUNT(*)
            FROM course_lessons cl
            WHERE cl.course_id = c.id AND cl.active = 1
        ) AS lessons_count,
        EXISTS (
            SELECT 1
            FROM subscriptions s
            WHERE s.student_id = ?
              AND s.course_id = c.id
              AND s.status = 'active'
              AND s.starts_at <= NOW()
              AND s.expires_at > NOW()
        ) AS has_active_subscription,
        (
            SELECT sr.status
            FROM subscription_requests sr
            WHERE sr.student_id = ?
              AND sr.course_id = c.id
            ORDER BY sr.id DESC
            LIMIT 1
        ) AS latest_request_status
    FROM courses c
    INNER JOIN teachers t ON t.id = c.teacher_id AND t.active = 1
    LEFT JOIN subjects sub ON sub.id = c.subject_id
    WHERE c.active = 1
      AND t.stage_id = ?
      AND (t.curriculum_id = ? OR t.curriculum_id IS NULL)
    ORDER BY c.id DESC
    SQL);
$stmt->execute([
    (int)$identity['id'],
    (int)$identity['id'],
    (int)$student['stage_id'],
    (int)$student['curriculum_id'],
]);

$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($courses as &$course) {
    $course['id'] = (int)$course['id'];
    $course['lessons_count'] = (int)$course['lessons_count'];
    $course['has_active_subscription'] = (bool)$course['has_active_subscription'];
    $course['price'] = (float)$course['price'];
    $course['duration_days'] = (int)$course['duration_days'];
    $course['can_request'] = !$course['has_active_subscription']
        && !in_array($course['latest_request_status'], ['pending', 'new', 'waiting'], true);
}
unset($course);

api_response(['courses' => $courses]);