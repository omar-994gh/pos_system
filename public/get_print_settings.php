<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // اتصل بقاعدة البيانات
    require_once __DIR__ . '/../config/db.php';

    // جلب أحدث إعدادات
    $stmt = $db->query("SELECT * FROM System_Settings ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        echo json_encode([]);
        exit;
    }

    $output = [];

    foreach ($settings as $key => $value) {
        // نتجاهل العمود id و updated_at
        if (in_array($key, ['id', 'updated_at'])) {
            continue;
        }
        // الحقول التي تبدأ بـ field_ هي خيارات طباعة
        if (strpos($key, 'field_') === 0) {
            $output[] = [
                'key'       => $key,
                'value'     => $value,
                'is_usable' => (int)$value
            ];
        } else {
            // بقية الإعدادات دومًا صالحة للاستخدام
            $output[] = [
                'key'       => $key,
                'value'     => $value,
                'is_usable' => 1
            ];
        }
    }

    echo json_encode($output, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
