<?php
// public/pos_handler.php

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Order.php';

Auth::requireLogin();

// قراءة JSON
$payload = json_decode(file_get_contents('php://input'), true);
$items   = $payload['items'] ?? [];
$total   = floatval($payload['total'] ?? 0);

try {
    // دمج وتلخيص الكميات
    $merged = [];
    foreach ($items as $it) {
        $id = $it['item_id'];
        if (!isset($merged[$id])) {
            $merged[$id] = [
                'item_id'    => $id,
                'quantity'   => $it['quantity'],
                'unit_price' => $it['unit_price'],
            ];
        } else {
            $merged[$id]['quantity'] += $it['quantity'];
        }
    }
    $cleanItems = array_values($merged);

    // حساب التسلسل اليومي
    $stmt = $db->prepare("
        SELECT COUNT(*) AS cnt
          FROM Orders
         WHERE DATE(created_at) = DATE('now','localtime')
    ");
    $stmt->execute();
    $todayCount = (int)$stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    $orderSeq = $todayCount + 1;

    // الإنشاء
    $orderModel = new Order($db);
    $success = $orderModel->create(
        (int)$_SESSION['user_id'],
        $cleanItems,
        $total,
        $orderSeq
    );

    if (! $success) {
        throw new Exception('فشل في حفظ الطلب أو بنوده');
    }

    $orderId = (int)$db->lastInsertId();
    echo json_encode([
        'success'  => true,
        'orderId'  => $orderId,
        'orderSeq' => $orderSeq,
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
