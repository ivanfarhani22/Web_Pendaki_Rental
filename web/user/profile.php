<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

class UserProfile {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function getUserProfile($user_id) {
        $query = "SELECT 
                    USERNAME, 
                    NAMA_LENGKAP, 
                    EMAIL, 
                    NO_TELEPON, 
                    ROLE,
                    TANGGAL_REGISTRASI
                  FROM USERS 
                  WHERE USER_ID = :user_id";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':user_id', $user_id);
        
        // Add define to resolve fetch error
        oci_define_by_name($stmt, 'USERNAME', $username);
        oci_define_by_name($stmt, 'NAMA_LENGKAP', $nama_lengkap);
        oci_define_by_name($stmt, 'EMAIL', $email);
        oci_define_by_name($stmt, 'NO_TELEPON', $no_telepon);
        oci_define_by_name($stmt, 'ROLE', $role);
        oci_define_by_name($stmt, 'TANGGAL_REGISTRASI', $tanggal_registrasi);

        oci_execute($stmt);
        oci_fetch($stmt);

        return [
            'USERNAME' => $username,
            'NAMA_LENGKAP' => $nama_lengkap,
            'EMAIL' => $email,
            'NO_TELEPON' => $no_telepon,
            'ROLE' => $role,
            'TANGGAL_REGISTRASI' => $tanggal_registrasi
        ];
    }

    public function updateProfile($user_id, $data) {
        $query = "UPDATE USERS 
                  SET NAMA_LENGKAP = :nama_lengkap, 
                      EMAIL = :email, 
                      NO_TELEPON = :no_telepon
                  WHERE USER_ID = :user_id";
        
        $stmt = oci_parse($this->conn, $query);
        
        oci_bind_by_name($stmt, ':nama_lengkap', $data['nama_lengkap']);
        oci_bind_by_name($stmt, ':email', $data['email']);
        oci_bind_by_name($stmt, ':no_telepon', $data['no_telepon']);
        oci_bind_by_name($stmt, ':user_id', $user_id);

        return oci_execute($stmt);
    }

    public function updatePassword($user_id, $current_password, $new_password) {
        // Verify current password first
        $verify_query = "SELECT PASSWORD FROM USERS WHERE USER_ID = :user_id";
        $verify_stmt = oci_parse($this->conn, $verify_query);
        oci_bind_by_name($verify_stmt, ':user_id', $user_id);
        
        // Add define for fetch
        oci_define_by_name($verify_stmt, 'PASSWORD', $stored_password);
        
        oci_execute($verify_stmt);
        oci_fetch($verify_stmt);

        if (!password_verify($current_password, $stored_password)) {
            return false;
        }

        // Update password
        $hash_password = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE USERS SET PASSWORD = :password WHERE USER_ID = :user_id";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':password', $hash_password);
        oci_bind_by_name($stmt, ':user_id', $user_id);

        return oci_execute($stmt);
    }
}

$database = new Database();
$userProfile = new UserProfile($database);

$user_id = $_SESSION['user_id'];
$profile = $userProfile->getUserProfile($user_id);

// Handle profile update
$pesan = '';
$hasil = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $data = [
            'nama_lengkap' => $_POST['nama_lengkap'],
            'email' => $_POST['email'],
            'no_telepon' => $_POST['no_telepon']
        ];

        $hasil = $userProfile->updateProfile($user_id, $data);
        $pesan = $hasil ? 
            'Profil berhasil diperbarui.' : 
            'Gagal memperbarui profil.';
        
        // Refresh profile data
        $profile = $userProfile->getUserProfile($user_id);
    }

    // Handle password change
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $pesan = 'Konfirmasi password tidak cocok.';
        } else {
            $hasil = $userProfile->updatePassword($user_id, $current_password, $new_password);
            $pesan = $hasil ? 
                'Password berhasil diperbarui.' : 
                'Gagal memperbarui password. Pastikan password lama benar.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pengguna - Peminjaman Alat Mendaki</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-green-50 via-teal-50 to-blue-100 min-h-screen">

    <?php include '../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8 pt-32">
        <div class="max-w-5xl mx-auto bg-white bg-opacity-90 backdrop-filter backdrop-blur-sm rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center justify-center mb-6">
                <div class="bg-green-100 rounded-full p-3 mr-3">
                    <i class="fas fa-user-circle text-green-600 text-4xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-800">Profil Pengguna</h1>
            </div>
            
            <?php if(!empty($pesan)): ?>
                <div class="max-w-3xl mx-auto mb-6 <?= $hasil ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700' ?> px-4 py-3 rounded-lg shadow-sm" role="alert">
                    <div class="flex items-center">
                        <i class="<?= $hasil ? 'fas fa-check-circle text-green-500' : 'fas fa-exclamation-circle text-red-500' ?> mr-2"></i>
                        <span><?= htmlspecialchars($pesan) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <!-- Informasi Akun -->
                <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
                    <div class="flex items-center mb-6">
                        <i class="fas fa-id-card text-green-500 text-xl mr-2"></i>
                        <h2 class="text-2xl font-semibold text-gray-800">Informasi Akun</h2>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <i class="fas fa-user text-gray-500 mr-2"></i>Username
                            </label>
                            <input type="text" 
                                   value="<?= htmlspecialchars($profile['USERNAME']) ?>" 
                                   readonly 
                                   class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 bg-gray-100">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <i class="fas fa-address-card text-gray-500 mr-2"></i>Nama Lengkap
                            </label>
                            <input type="text" 
                                   name="nama_lengkap" 
                                   value="<?= htmlspecialchars($profile['NAMA_LENGKAP']) ?>" 
                                   required 
                                   class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <i class="fas fa-envelope text-gray-500 mr-2"></i>Email
                            </label>
                            <input type="email" 
                                   name="email" 
                                   value="<?= htmlspecialchars($profile['EMAIL']) ?>" 
                                   required 
                                   class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <i class="fas fa-phone text-gray-500 mr-2"></i>Nomor Telepon
                            </label>
                            <input type="tel" 
                                   name="no_telepon" 
                                   value="<?= htmlspecialchars($profile['NO_TELEPON']) ?>" 
                                   required 
                                   class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <i class="fas fa-user-tag text-gray-500 mr-2"></i>Role
                            </label>
                            <div class="flex items-center bg-gray-100 border rounded-lg px-3 py-2">
                                <span class="text-gray-700 capitalize"><?= htmlspecialchars($profile['ROLE']) ?></span>
                                <?php if($profile['ROLE'] == 'admin'): ?>
                                    <span class="ml-2 px-2 py-1 text-xs font-semibold bg-purple-100 text-purple-800 rounded-full">Admin</span>
                                <?php else: ?>
                                    <span class="ml-2 px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Peminjam</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <i class="fas fa-calendar-alt text-gray-500 mr-2"></i>Tanggal Registrasi
                            </label>
                            <div class="flex items-center bg-gray-100 border rounded-lg px-3 py-2">
                                <span class="text-gray-700"><?= htmlspecialchars($profile['TANGGAL_REGISTRASI']) ?></span>
                            </div>
                        </div>

                        <button type="submit" 
                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 transition-all duration-200 flex items-center justify-center w-full">
                            <i class="fas fa-save mr-2"></i> Perbarui Profil
                        </button>
                    </form>
                </div>

                <!-- Ganti Password -->
                <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
                    <div class="flex items-center mb-6">
                        <i class="fas fa-key text-green-500 text-xl mr-2"></i>
                        <h2 class="text-2xl font-semibold text-gray-800">Ganti Password</h2>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="update_password" value="1">
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <i class="fas fa-lock text-gray-500 mr-2"></i>Password Lama
                            </label>
                            <input type="password" 
                                   name="current_password" 
                                   required 
                                   class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200">
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <i class="fas fa-lock text-gray-500 mr-2"></i>Password Baru
                            </label>
                            <input type="password" 
                                   name="new_password" 
                                   required 
                                   class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200">
                            <p class="text-sm text-gray-500 mt-1">
                                <i class="fas fa-info-circle"></i> Password minimal 8 karakter
                            </p>
                        </div>

                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-bold mb-2">
                                <i class="fas fa-lock text-gray-500 mr-2"></i>Konfirmasi Password Baru
                            </label>
                            <input type="password" 
                                   name="confirm_password" 
                                   required 
                                   class="shadow appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-all duration-200">
                        </div>

                        <button type="submit" 
                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 transition-all duration-200 flex items-center justify-center w-full">
                            <i class="fas fa-key mr-2"></i> Ganti Password
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="mt-8 text-center">
                <a href="../user/index.php" class="inline-flex items-center text-green-600 hover:text-green-800 transition-colors duration-200">
                    <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>