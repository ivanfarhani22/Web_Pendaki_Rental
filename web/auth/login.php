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

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Peminjaman Alat Mendaki</title>
    <!-- Tailwind CSS dan Font Awesome via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="/pendaki_gear/web/favicon.ico" type="image/x-icon">
    <style>
        .mountain-bg {
            background-image: url('https://st2.depositphotos.com/5991120/8867/v/450/depositphotos_88677448-stock-illustration-beautiful-green-mountains-summer-landscape.jpg');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center mountain-bg">
    <div class="max-w-md w-full p-6 bg-white rounded-xl shadow-lg bg-opacity-90 backdrop-filter backdrop-blur-sm">
        <div class="flex justify-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100">
                <i class="fas fa-hiking text-green-600 text-4xl"></i>
            </div>
        </div>
        
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-8">Selamat Datang</h2>
        
        <form method="POST" class="space-y-6">
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline"><?= $error_message ?></span>
                </div>
            <?php endif; ?>

            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fas fa-user text-gray-400"></i>
                    </div>
                    <input type="text" id="username" name="username" required 
                        class="pl-10 block w-full shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm border-gray-300 rounded-md py-2 px-3 border">
                </div>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" id="password" name="password" required 
                        class="pl-10 block w-full shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm border-gray-300 rounded-md py-2 px-3 border">
                </div>
            </div>

            <div>
                <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 ease-in-out">
                    Masuk
                </button>
            </div>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Belum punya akun? 
                <a href="register.php" class="font-medium text-green-600 hover:text-green-500 transition duration-150 ease-in-out">
                    Daftar di sini
                </a>
            </p>
        </div>
        
        <div class="mt-8 text-center">
            <p class="text-xs text-gray-500">
                Sistem Peminjaman Alat Mendaki &copy; 2025
            </p>
        </div>
    </div>
</body>
</html>