<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Group.php';

Auth::requireLogin();
if (!Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? null;
if ($id) {
    (new Group($db))->delete((int)$id);
}

header('Location: groups.php');
exit;
