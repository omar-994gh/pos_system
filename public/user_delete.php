<?php
// public/user_delete.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/User.php';

Auth::requireLogin();
if (!Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? null;
if ($id) {
    $userModel = new User($db);
    $userModel->delete((int)$id);
}

header('Location: users.php');
exit;
