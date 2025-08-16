<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Printer.php';

Auth::requireLogin();
if (!Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? null;
if ($id) {
    (new Printer($db))->delete((int)$id);
}

header('Location: printers.php');
exit;
