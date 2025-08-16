<?php
session_start();
require_once __DIR__ . '/../config/db.php';
// إذا أردت استخدام autoload بطريقة PSR-4 يمكنك إضافتها هنا
// مثال بسيط لاستدعاء الكلاسات تلقائياً إذا وضعتها في مجلد src
spl_autoload_register(function($className) {
    $file = __DIR__ . '/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

function getClientMac(): string {
    if (strtoupper(substr(PHP_OS,0,3)) === 'WIN') {
        exec('getmac', $out);
        return strtok($out[0], ' ');
    } else {
        // نفترض eth0؛ عدّل إذا لزم الأمر
        exec("cat /sys/class/net/eth0/address", $out);
        return trim($out[0]);
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
?>
