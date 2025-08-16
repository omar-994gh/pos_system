<?php
// public/item_save.php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Item.php';

Auth::requireLogin();
if (!Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$model = new Item($db);

$id       = $_POST['id'] ?? null;
$data     = [
    'group_id' => (int)$_POST['group_id'],
    'name_ar'  => trim($_POST['name_ar'] ?? ''),
    'name_en'  => trim($_POST['name_en'] ?? ''),
    'barcode'  => trim($_POST['barcode'] ?? ''),
    'price'    => floatval($_POST['price'] ?? 0),
    'stock'    => floatval($_POST['stock'] ?? 0),
    'unit'     => trim($_POST['unit'] ?? ''),
];

if ($id) {
    $model->update((int)$id, $data);
    $gid = $data['group_id'];
} else {
    $model->create($data);
    $gid = $data['group_id'];
}

header("Location: items.php?group_id=$gid");
exit;
