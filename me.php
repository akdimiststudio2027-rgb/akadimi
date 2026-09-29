<?php

require_once __DIR__ . '/../bootstrap.php';
$identity = authenticated();

if ($identity['role'] !== 'student') {
    api_response(['error' => 'Student access required.'], 403);
}

$stmt = db()->prepare(<<<'SQL'
    SELECT
        s.id,
        s.name,
        s.phone,
        s.governorate_id,
        g.name AS governorate_name,
        s.stage_id,
        st.name AS stage_name,
        s.curriculum_id,
        c.name AS curriculum_name,
        s.active
    FROM students s
    LEFT JOIN governorates g ON g.id = s.governorate_id
    LEFT JOIN stages st ON st.id = s.stage_id
    LEFT JOIN curricula c ON c.id = s.curriculum_id
    WHERE s.id = ?
    LIMIT 1
    SQL);
$stmt->execute([(int)$identity['id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student || (int)$student['active'] !== 1) {
    api_response(['error' => 'Student account not found.'], 404);
}

api_response(['user' => $student]);