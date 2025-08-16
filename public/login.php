<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/Auth.php';


$key = $_COOKIE['license_key'] ?? '';
$stmt = $db->prepare(
  "SELECT COUNT(*) AS cnt 
     FROM Licenses 
    WHERE license_key = :key AND is_used = 1"
);
$stmt->execute([':key'=>$key]);
if ((int)$stmt->fetch()['cnt'] !== 1) {
  header('Location: activate.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = Auth::login($db, $username, $password);
    if ($user !== false) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تسجيل الدخول</title>
    <link rel="stylesheet" href="../assets/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="p-5">
    <div class="container" style="max-width:400px;">
        <h2 class="mb-4 text-center">تسجيل الدخول</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <div class="mb-3">
                <label for="username" class="form-label">اسم المستخدم</label>
                <input type="text" name="username" id="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">كلمة المرور</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">دخول</button>
        </form>
        <p class="mt-3 text-center">
            لا تملك حساباً؟ <a href="register.php">سجل جديد</a>
        </p>
    </div>
</body>
</html>
