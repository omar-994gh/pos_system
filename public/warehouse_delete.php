<?php
    require_once '../src/init.php'; require_once '../src/Auth.php';
    require_once '../src/warehouse_management.php';
    Auth::requireLogin(); if (!Auth::isAdmin()) exit;
    $model = new Warehouse($db);
    $model->delete((int)$_GET['id']);
    header('Location: warehouses.php');
?>