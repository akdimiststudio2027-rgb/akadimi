<?php

require_once __DIR__ . '/../bootstrap.php';
$identity = authenticated();

if ($identity['role'] !== 'student') {
    api_response(['error' => 'Student access required.'], 403);
}

$stmt = db()->prepare(<<<'SQL'
    SELECT
        t.id,
        t.name,
        COUNT(DISTINCT c.id) AS courses_count,
        (
            SELECT COUNT(*)
            FROM student_teacher_messages m
            WHERE m.student_id = ?
              AND m.teacher_id = t.id
              AND m.sender_type = 'teacher'
              AND m.is_read = 0
        ) AS unread_count
    FROM subscriptions s
    INNER JOIN courses c ON c.id = s.course_id AND c.active = 1
    INNER JOIN teachers t ON t.id = c.teacher_id AND t.active = 1
    WHERE s.student_id = ?
      AND s.status = 'active'
      AND s.starts_at <= NOW()
      AND s.expires_at > NOW()
    GROUP BY t.id, t.name
    ORDER BY t.name ASC
    SQL);
$stmt->execute([(int)$identity['id'], (int)$identity['id']]);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($teachers as &$teacher) {
    $teacher['id'] = (int)$teacher['id'];
    $teacher['courses_count'] = (int)$teacher['courses_count'];
    $teacher['unread_count'] = (int)$teacher['unread_count'];
}
unset($teacher);

api_response(['teachers' => $teachers]);