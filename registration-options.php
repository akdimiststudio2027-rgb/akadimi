<?php

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_response(['error' => 'Method not allowed.'], 405);
}

$pdo = db();

$setting = $pdo->prepare(
    "SELECT setting_value FROM settings WHERE setting_key = 'registration_enabled' LIMIT 1"
);
$setting->execute();
$registrationEnabled = $setting->fetchColumn() !== '0';

$governorates = $pdo->query(
    'SELECT id, name FROM governorates ORDER BY name ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$stages = $pdo->query(
    'SELECT id, name FROM stages WHERE active = 1 ORDER BY id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$curricula = $pdo->query(<<<'SQL'
    SELECT c.id, c.name, c.stage_id
    FROM curricula c
    INNER JOIN stages s ON s.id = c.stage_id
    WHERE c.active = 1 AND s.active = 1
    ORDER BY c.stage_id ASC, c.id ASC
    SQL)->fetchAll(PDO::FETCH_ASSOC);

foreach ($governorates as &$governorate) {
    $governorate['id'] = (int)$governorate['id'];
}
unset($governorate);

foreach ($stages as &$stage) {
    $stage['id'] = (int)$stage['id'];
}
unset($stage);

foreach ($curricula as &$curriculum) {
    $curriculum['id'] = (int)$curriculum['id'];
    $curriculum['stage_id'] = (int)$curriculum['stage_id'];
}
unset($curriculum);

api_response([
    'registration_enabled' => $registrationEnabled,
    'governorates' => $governorates,
    'stages' => $stages,
    'curricula' => $curricula,
]);