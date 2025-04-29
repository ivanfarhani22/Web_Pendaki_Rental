<?php
session_start();
require_once '../config/database.php';

class Auth {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function login($username, $password) {
        // Paksa username jadi lowercase buat hindari case-sensitive issue
        $query = "SELECT user_id, username, password, role FROM users WHERE LOWER(username) = LOWER(:username)";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':username', $username);
        oci_execute($stmt);
        
        $user = oci_fetch_assoc($stmt);

        // Debugging - Hapus ini kalau udah fix
        if (!$user) {
            return "Username tidak ditemukan!";
        }

        // Verifikasi password
        if (password_verify($password, $user['PASSWORD'])) {
            $_SESSION['user_id'] = $user['USER_ID'];
            $_SESSION['username'] = $user['USERNAME'];
            $_SESSION['role'] = $user['ROLE'];

            // Redirect berdasarkan role
            if ($user['ROLE'] === 'admin') {
                header('Location: ../admin/index.php');
            } else {
                header('Location: ../user/index.php');
            }
            exit();
        } else {
            return "Password salah!";
        }
    }
}

// Inisialisasi
$database = new Database();
$auth = new Auth($database);

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $error_message = $auth->login($username, $password);
}
?>

<!-- Halaman Login HTML -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Peminjaman Alat Mendaki</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <form method="POST" class="login-form">
            <h2>Login</h2>

            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?= $error_message ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-login">Login</button>

            <div class="register-link">
                Belum punya akun? <a href="register.php">Daftar di sini</a>
            </div>
        </form>
    </div>
</body>
</html>
