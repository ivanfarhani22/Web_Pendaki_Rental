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

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Peminjaman Alat Mendaki</title>
    <!-- Tailwind CSS dan Font Awesome via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .mountain-bg {
            background-image: url('https://st2.depositphotos.com/5991120/8867/v/450/depositphotos_88677448-stock-illustration-beautiful-green-mountains-summer-landscape.jpg');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>
<body class="bg-gray-100 py-8 mountain-bg">
    <div class="max-w-2xl w-full mx-auto p-6 bg-white rounded-xl shadow-lg bg-opacity-90 backdrop-filter backdrop-blur-sm">
        <div class="flex justify-center mb-6">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100">
                <i class="fas fa-mountain text-green-600 text-4xl"></i>
            </div>
        </div>
        
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">Daftar Peminjam</h2>
        
        <form method="POST" class="space-y-4">
            <?php if (!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline"><?= $error_message ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline"><?= $success_message ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Username -->
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

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" id="email" name="email" required 
                            class="pl-10 block w-full shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm border-gray-300 rounded-md py-2 px-3 border">
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="password" name="password" required 
                            class="pl-10 block w-full shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm border-gray-300 rounded-md py-2 px-3 border">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Password minimal 8 karakter</p>
                </div>

                <!-- Konfirmasi Password -->
                <div>
                    <label for="konfirmasi_password" class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="konfirmasi_password" name="konfirmasi_password" required 
                            class="pl-10 block w-full shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm border-gray-300 rounded-md py-2 px-3 border">
                    </div>
                </div>

                <!-- Nama Lengkap -->
                <div>
                    <label for="nama_lengkap" class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-id-card text-gray-400"></i>
                        </div>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" required 
                            class="pl-10 block w-full shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm border-gray-300 rounded-md py-2 px-3 border">
                    </div>
                </div>

                <!-- Nomor Telepon -->
                <div>
                    <label for="no_telepon" class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-phone text-gray-400"></i>
                        </div>
                        <input type="text" id="no_telepon" name="no_telepon" required 
                            class="pl-10 block w-full shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm border-gray-300 rounded-md py-2 px-3 border">
                    </div>
                </div>
            </div>

            <div class="pt-5">
                <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 ease-in-out">
                    Daftar Sekarang
                </button>
            </div>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Sudah punya akun? 
                <a href="login.php" class="font-medium text-green-600 hover:text-green-500 transition duration-150 ease-in-out">
                    Masuk di sini
                </a>
            </p>
        </div>
        
        <div class="mt-6 text-center">
            <p class="text-xs text-gray-500">
                Sistem Peminjaman Alat Mendaki &copy; 2025
            </p>
        </div>
    </div>
</body>
</html>