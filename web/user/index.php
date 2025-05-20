<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

class Dashboard {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function getPendingLoans($user_id) {
        $query = "SELECT 
                    p.peminjaman_id, 
                    a.nama_alat,
                    p.tanggal_pinjam, 
                    d.tanggal_mulai,
                    d.tanggal_selesai,
                    p.total_biaya,
                    p.status_peminjaman
                  FROM peminjaman p
                  JOIN detail_peminjaman d ON p.peminjaman_id = d.peminjaman_id
                  JOIN alat_mendaki a ON d.alat_id = a.alat_id
                  WHERE p.user_id = :user_id 
                  AND p.status_peminjaman = 'Diajukan'
                  ORDER BY p.tanggal_pinjam DESC
                  FETCH FIRST 3 ROWS ONLY";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':user_id', $user_id);
        oci_execute($stmt);

        $pendingLoans = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $pendingLoans[] = $row;
        }

        return $pendingLoans;
    }

    public function getUserProfile($user_id) {
        $query = "SELECT 
                    nama_lengkap, 
                    email, 
                    no_telepon
                  FROM users 
                  WHERE user_id = :user_id";
    
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':user_id', $user_id);
        oci_execute($stmt);
    
        return oci_fetch_assoc($stmt);
    }    

    public function getTotalLoans($user_id) {
        $query = "SELECT 
                    COUNT(*) as total_peminjaman,
                    SUM(CASE WHEN status_peminjaman = 'Selesai' THEN 1 ELSE 0 END) as total_selesai
                  FROM peminjaman
                  WHERE user_id = :user_id";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':user_id', $user_id);
        oci_execute($stmt);

        return oci_fetch_assoc($stmt);
    }
}

$database = new Database();
$dashboard = new Dashboard($database);

$pendingLoans = $dashboard->getPendingLoans($_SESSION['user_id']);
$userProfile = $dashboard->getUserProfile($_SESSION['user_id']);
$loanStats = $dashboard->getTotalLoans($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Pendaki</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-900 transition hover:shadow-xl">
                    <h3 class="text-xl font-semibold text-green-900 mb-4">Statistik Peminjaman</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Peminjaman</span>
                            <span class="font-bold text-green-800"><?= $loanStats['TOTAL_PEMINJAMAN'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Peminjaman Selesai</span>
                            <span class="font-bold text-green-800"><?= $loanStats['TOTAL_SELESAI'] ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-800 transition hover:shadow-xl">
                    <h3 class="text-xl font-semibold text-blue-800 mb-4">Aksi Cepat</h3>
                    <div class="flex space-x-4">
                        <a href="daftar_alat.php" class="flex-1 bg-green-800 hover:bg-green-900 text-white py-3 rounded-lg text-center font-medium transition">
                            Pinjam Alat
                        </a>
                        <a href="riwayat_peminjaman.php" class="flex-1 bg-blue-700 hover:bg-blue-800 text-white py-3 rounded-lg text-center font-medium transition">
                            Riwayat Peminjaman
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-8 bg-white rounded-xl shadow-lg p-6 border-l-4 border-teal-700">
                <h2 class="text-2xl font-bold text-teal-700 mb-6">Peminjaman Menunggu</h2>
                <?php if(empty($pendingLoans)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p>Tidak ada peminjaman yang menunggu.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-100 text-gray-600">
                                <tr>
                                    <th class="px-4 py-3 text-left rounded-tl-lg">Alat</th>
                                    <th class="px-4 py-3 text-left">Tanggal Pinjam</th>
                                    <th class="px-4 py-3 text-left">Periode</th>
                                    <th class="px-4 py-3 text-left">Total Biaya</th>
                                    <th class="px-4 py-3 text-left rounded-tr-lg">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach($pendingLoans as $loan): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-4"><?= htmlspecialchars($loan['NAMA_ALAT']) ?></td>
                                    <td class="px-4 py-4"><?= date('d M Y', strtotime($loan['TANGGAL_PINJAM'])) ?></td>
                                    <td class="px-4 py-4">
                                        <?= date('d M Y', strtotime($loan['TANGGAL_MULAI'])) ?> - 
                                        <?= date('d M Y', strtotime($loan['TANGGAL_SELESAI'])) ?>
                                    </td>
                                    <td class="px-4 py-4">Rp <?= number_format($loan['TOTAL_BIAYA'], 0, ',', '.') ?></td>
                                    <td class="px-4 py-4">
                                        <span class="<?= $loan['STATUS_PEMINJAMAN'] == 'Menunggu' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?> px-3 py-1 rounded-full text-xs font-medium">
                                            <?= $loan['STATUS_PEMINJAMAN'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>