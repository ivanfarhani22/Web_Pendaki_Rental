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
    <title>Profil Pengguna</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6 text-center">Profil Pengguna</h1>

        <?php if(!empty($pesan)): ?>
            <div class="max-w-xl mx-auto mb-6 <?= $hasil ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700' ?> px-4 py-3 rounded relative" role="alert">
                <?= htmlspecialchars($pesan) ?>
            </div>
        <?php endif; ?>

        <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-2xl font-semibold mb-4">Informasi Akun</h2>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Username</label>
                        <input type="text" 
                               value="<?= htmlspecialchars($profile['USERNAME']) ?>" 
                               readonly 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-200">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Nama Lengkap</label>
                        <input type="text" 
                               name="nama_lengkap" 
                               value="<?= htmlspecialchars($profile['NAMA_LENGKAP']) ?>" 
                               required 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                        <input type="email" 
                               name="email" 
                               value="<?= htmlspecialchars($profile['EMAIL']) ?>" 
                               required 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Nomor Telepon</label>
                        <input type="tel" 
                               name="no_telepon" 
                               value="<?= htmlspecialchars($profile['NO_TELEPON']) ?>" 
                               required 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Role</label>
                        <input type="text" 
                               value="<?= htmlspecialchars($profile['ROLE']) ?>" 
                               readonly 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-200">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Registrasi</label>
                        <input type="text" 
                               value="<?= htmlspecialchars($profile['TANGGAL_REGISTRASI']) ?>" 
                               readonly 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-200">
                    </div>

                    <button type="submit" 
                            class="bg-blue-700 hover:bg-blue-900 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Perbarui Profil
                    </button>
                </form>
            </div>

            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-2xl font-semibold mb-4">Ganti Password</h2>
                <form method="POST">
                    <input type="hidden" name="update_password" value="1">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Password Lama</label>
                        <input type="password" 
                               name="current_password" 
                               required 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Password Baru</label>
                        <input type="password" 
                               name="new_password" 
                               required 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Konfirmasi Password Baru</label>
                        <input type="password" 
                               name="confirm_password" 
                               required 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <button type="submit" 
                            class="bg-blue-700 hover:bg-blue-900 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Ganti Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>