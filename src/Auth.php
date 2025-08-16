<?php
class Auth
{
    /**
     * محاولة تسجيل الدخول
     * @param PDO    $db
     * @param string $username
     * @param string $password
     * @return array|false  بيانات المستخدم في حال النجاح، أو false عند الفشل
     */
    public static function login(PDO $db, string $username, string $password)
    {
        $stmt = $db->prepare('SELECT id, username, password_hash, role FROM Users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // تخزين بيانات المستخدم في الجلسة
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];
            return [
                'id'   => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ];
        }
        return false;
    }

    /**
     * تسجيل مستخدم جديد
     * @param PDO    $db
     * @param string $username
     * @param string $password
     * @param string $role     ('Admin' أو 'Cashier')
     * @return bool|string     true عند النجاح، أو رسالة خطأ
     */
    public static function register(PDO $db, string $username, string $password, string $role = 'Cashier')
    {
        // تحقق من وجود المستخدم مسبقاً
        $stmt = $db->prepare('SELECT COUNT(*) FROM Users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            return 'اسم المستخدم موجود بالفعل.';
        }

        // إنشاء الهاش
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare(
            'INSERT INTO Users (username, password_hash, role) 
             VALUES (:username, :hash, :role)'
        );
        $success = $stmt->execute([
            ':username' => $username,
            ':hash'     => $hash,
            ':role'     => $role,
        ]);

        return $success ? true : 'حدث خطأ أثناء التسجيل.';
    }

    /**
     * تسجيل الخروج
     */
    public static function logout()
    {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }

    /**
     * هل المستخدم مسجل دخول؟
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * فرض تسجيل الدخول قبل الوصول للصفحة
     */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * هل المستخدم مشرف (Admin)؟
     * @return bool
     */
    public static function isAdmin(): bool
    {
        return self::isLoggedIn() && ($_SESSION['role'] === 'Admin');
    }

    /**
     * هل المستخدم كاشير (Cashier)؟
     * @return bool
     */
    public static function isCashier(): bool
    {
        return self::isLoggedIn() && ($_SESSION['role'] === 'Cashier');
    }
}
