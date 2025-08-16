<?php
// public/print.php
ini_set('display_errors',1);
error_reporting(E_ALL);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin:*'); 
header('Access-Control-Allow-Methods:POST,GET,OPTIONS');
header('Access-Control-Allow-Headers:Content-Type');

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['image'])) {
  echo json_encode(['success'=>false,'error'=>'لا توجد صورة']);
  exit;
}

// إعداد DB path
$dbFile = getenv('DB_PATH') ?: (__DIR__.'/../config/config.sqlite');
$pdo = new PDO('sqlite:'.$dbFile);

// جلب طابعات مفعلة
$stmt = $pdo->query("SELECT name AS printer_name, address AS ip_address, type FROM Printers");
$printers = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$printers) {
  echo json_encode(['success'=>false,'error'=>'لا توجد طابعات']);
  exit;
}

// فك تشفير الصورة وحفظها مؤقتاً
$img = str_replace('data:image/png;base64,','',$data['image']);
$bin = base64_decode($img);
$temp = __DIR__.'/temp_invoice.png';
file_put_contents($temp,$bin);

require __DIR__.'/../vendor/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\EscposImage;

$errors = [];
foreach ($printers as $p) {
  try {
    if ($p['type']==='usb') {
      $conn = new WindowsPrintConnector($p['printer_name']);
    } else {
      $conn = new NetworkPrintConnector($p['ip_address'], 9100);
    }
    $printer = new Printer($conn);
    $imgObj = EscposImage::load($temp);
    $printer->bitImage($imgObj);
    $printer->cut();
    $printer->close();
  } catch (Exception $e) {
    $errors[] = $e->getMessage();
  }
}
// حذف المؤقت
unlink($temp);

if ($errors) {
  echo json_encode(['success'=>false,'error'=>implode('; ',$errors)]);
} else {
  echo json_encode(['success'=>true]);
}

?>