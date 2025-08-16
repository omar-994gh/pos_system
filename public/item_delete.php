<?php
// public/item_delete.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Item.php';

Auth::requireLogin();
if (!Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$id      = $_GET['id'] ?? null;
$groupId = $_GET['group_id'] ?? null;
if ($id) {
    (new Item($db))->delete((int)$id);
}

header("Location: items.php?group_id=$groupId");
exit;
