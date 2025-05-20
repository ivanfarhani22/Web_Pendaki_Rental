<?php
session_start();
require_once '../config/database.php';

// Pastikan pengguna belum login
if (isset($_SESSION['user_id'])) {
    header('Location: ../user/index.php');
    exit();
}

// Inisialisasi variabel
$error_message = '';
$success_message = '';

class Auth {
    private $conn;
    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function register($username, $email, $password, $nama_lengkap, $no_telepon) {
        if (empty($username) || empty($email) || empty($password) || empty($nama_lengkap) || empty($no_telepon)) {
            return "Semua field harus diisi!";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Format email tidak valid!";
        }

        if (strlen($password) < 8) {
            return "Password minimal 8 karakter!";
        }

        // Cek apakah username atau email sudah ada
        $check_query = "SELECT COUNT(*) as count FROM users WHERE LOWER(username) = LOWER(:username) OR email = :email";
        $check_stmt = oci_parse($this->conn, $check_query);
        oci_bind_by_name($check_stmt, ':username', $username);
        oci_bind_by_name($check_stmt, ':email', $email);
        oci_execute($check_stmt);
        $row = oci_fetch_assoc($check_stmt);

        if ($row['COUNT'] > 0) {
            return "Username atau email sudah terdaftar!";
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO users (user_id, username, email, password, nama_lengkap, no_telepon, role, tanggal_registrasi) 
                  VALUES (seq_users.NEXTVAL, :username, :email, :password, :nama_lengkap, :no_telepon, 'peminjam', SYSDATE)";
        
        $stmt = oci_parse($this->conn, $query);
        
        oci_bind_by_name($stmt, ':username', $username);
        oci_bind_by_name($stmt, ':email', $email);
        oci_bind_by_name($stmt, ':password', $hashed_password);
        oci_bind_by_name($stmt, ':nama_lengkap', $nama_lengkap);
        oci_bind_by_name($stmt, ':no_telepon', $no_telepon);
        
        $result = oci_execute($stmt);

        return $result ? true : "Gagal mendaftarkan pengguna!";
    }
}

// Proses registrasi
$database = new Database();
$auth = new Auth($database);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $no_telepon = trim($_POST['no_telepon']);

    if ($password !== $konfirmasi_password) {
        $error_message = "Konfirmasi password tidak cocok!";
    } else {
        $register_result = $auth->register($username, $email, $password, $nama_lengkap, $no_telepon);
        
        if ($register_result === true) {
            $success_message = "Registrasi berhasil! Silakan login.";
        } else {
            $error_message = $register_result;
        }
    }
}
?>


<!-- Halaman Register HTML -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar - Peminjaman Alat Mendaki</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <form method="POST" class="login-form">
            <h2>Daftar Pendaki</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?= $error_message ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message"><?= $success_message ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="konfirmasi_password">Konfirmasi Password</label>
                <input type="password" id="konfirmasi_password" name="konfirmasi_password" required>
            </div>

            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap</label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" required>
            </div>

            <div class="form-group">
                <label for="no_telepon">Nomor Telepon</label>
                <input type="text" id="no_telepon" name="no_telepon" required>
            </div>

            <button type="submit" class="btn-register">Daftar</button>

            <div class="login-link">
                Sudah punya akun? <a href="login.php">Masuk di sini</a>
            </div>
        </form>
    </div>
</body>
</html>
