<?php
// public/activate.php
require_once __DIR__ . '/../src/init.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lic_file'])) {
    $content = trim(file_get_contents($_FILES['lic_file']['tmp_name']));
    $hash    = hash('sha256', $content);
    // تحقق من المفتاح والهاش
    $stmt = $db->prepare(
      "SELECT id FROM Licenses 
       WHERE license_key = :key 
         AND file_hash = :hash 
         AND is_used = 0"
    );
    $stmt->execute([':key'=>$content, ':hash'=>$hash]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $db->prepare("UPDATE Licenses SET is_used = 1 WHERE id = :id")
           ->execute([':id'=>$row['id']]);
        setcookie('license_key', $content, time()+60*60*24*30, '/');
        header('Location: login.php');
        exit;
    } else {
        $error = 'ملف الترخيص غير صالح أو مُستخدم سابقًا.';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>تفعيل النظام</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="../assets/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f5f5f5;
    }
    .card-activate {
      max-width: 400px;
      margin: 60px auto;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .logo {
      width: 120px;
      height: 120px;
      object-fit: contain;
      margin-bottom: 15px;
    }
    .file-input-label {
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="card card-activate">
    <div class="card-body text-center">
      <img src="images/comp-logo.png" alt="Logo" class="logo">
      <h4 class="card-title mb-3">تفعيل النظام</h4>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
      <?php else: ?>
        <p class="text-muted">للمتابعة، قم برفع ملف الترخيص (<code>.lic</code>) الذي حصلت عليه.</p>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="mt-3">
        <div class="mb-3 text-start">
          <label class="form-label file-input-label">
            <span class="btn btn-outline-primary w-100">
              <i class="bi bi-file-earmark-lock me-2"></i>اختر ملف الترخيص
            </span>
            <input type="file" name="lic_file" accept=".lic" required
                   style="display:none" onchange="this.form.submit()">
          </label>
        </div>
        <small class="text-muted d-block mb-3">يمكنك سحب الملف هنا أيضًا.</small>
        <noscript>
          <button type="submit" class="btn btn-primary w-100">تفعيل</button>
        </noscript>
      </form>
    </div>
  </div>

  <script src="../assets/bootstrap.bundle.min.js"></script>
  <!-- تشغيل السحب والإفلات -->
  <script>
    const form = document.querySelector('form');
    const dropZone = form.querySelector('.card-body');

    dropZone.addEventListener('dragover', e => {
      e.preventDefault();
      dropZone.classList.add('bg-light');
    });
    dropZone.addEventListener('dragleave', e => {
      dropZone.classList.remove('bg-light');
    });
    dropZone.addEventListener('drop', e => {
      e.preventDefault();
      dropZone.classList.remove('bg-light');
      const fileInput = form.querySelector('input[type="file"]');
      fileInput.files = e.dataTransfer.files;
      form.submit();
    });
  </script>
</body>
</html>
