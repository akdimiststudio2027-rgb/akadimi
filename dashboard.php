<?php

require_once __DIR__ . '/../bootstrap.php';
$identity = authenticated();

if ($identity['role'] !== 'admin') {
    api_response(['error' => 'Admin access required.'], 403);
}

function count_rows(PDO $pdo, string $table): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}

$pdo = db();
api_response([
    'stats' => [
        'students' => count_rows($pdo, 'students'),
        'teachers' => count_rows($pdo, 'teachers'),
        'courses' => count_rows($pdo, 'courses'),
        'subscriptions' => count_rows($pdo, 'subscriptions'),
    ],
]);