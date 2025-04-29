<?php
session_start();
require_once '../config/database.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

class AdminDashboard {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function getStatistik() {
        $statistik = [];

        // Total Alat
        $query = "SELECT COUNT(*) AS total_alat FROM alat_mendaki";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $statistik['total_alat'] = $row['TOTAL_ALAT'];

        // Alat Tersedia
        $query = "SELECT COUNT(*) AS alat_tersedia FROM alat_mendaki WHERE jumlah_tersedia > 0";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $statistik['alat_tersedia'] = $row['ALAT_TERSEDIA'];

        // Total Peminjaman Bulan Ini
        $query = "SELECT COUNT(*) AS total_peminjaman 
                  FROM peminjaman 
                  WHERE EXTRACT(MONTH FROM tanggal_pinjam) = EXTRACT(MONTH FROM SYSDATE)
                  AND EXTRACT(YEAR FROM tanggal_pinjam) = EXTRACT(YEAR FROM SYSDATE)";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $statistik['total_peminjaman'] = $row['TOTAL_PEMINJAMAN'];

        // Peminjaman Menunggu Persetujuan
        $query = "SELECT COUNT(*) AS peminjaman_pending 
                  FROM peminjaman 
                  WHERE STATUS_PEMINJAMAN = 'Diajukan'";  // Pakai huruf besar
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $statistik['peminjaman_pending'] = $row['PEMINJAMAN_PENDING'];

        return $statistik;
    }

    public function getPeminjamanTerakhir($limit = 5) {
        $query = "SELECT p.PEMINJAMAN_ID, u.NAMA_LENGKAP, p.TANGGAL_PINJAM, p.STATUS_PEMINJAMAN
                  FROM peminjaman p
                  JOIN users u ON p.USER_ID = u.USER_ID
                  ORDER BY p.TANGGAL_PINJAM DESC
                  FETCH FIRST :limit ROWS ONLY";
    
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':limit', $limit, -1, SQLT_INT);
        oci_execute($stmt);
    
        $peminjaman = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $peminjaman[] = [
                'PEMINJAMAN_ID' => $row['PEMINJAMAN_ID'],
                'NAMA_LENGKAP' => $row['NAMA_LENGKAP'],
                'TANGGAL_PINJAM' => $row['TANGGAL_PINJAM'],
                'STATUS_PEMINJAMAN' => $row['STATUS_PEMINJAMAN']
            ];
        }
    
        return $peminjaman;
    }
}

$database = new Database();
$adminDashboard = new AdminDashboard($database);
$statistik = $adminDashboard->getStatistik();
$peminjamanTerakhir = $adminDashboard->getPeminjamanTerakhir();
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin - Peminjaman Alat Pendaki</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'components/sidebar.php'; ?>

    <div class="ml-64 p-8">
        <div class="container mx-auto">
            <div class="dashboard-header mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Dashboard Admin</h1>
                <p class="text-gray-600">Selamat datang, <?= $_SESSION['username'] ?></p>
            </div>

            <div class="grid grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <h3 class="text-gray-600 mb-2">Total Alat</h3>
                    <div class="text-3xl font-bold text-blue-600"><?= $statistik['total_alat'] ?></div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <h3 class="text-gray-600 mb-2">Alat Tersedia</h3>
                    <div class="text-3xl font-bold text-green-600"><?= $statistik['alat_tersedia'] ?></div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <h3 class="text-gray-600 mb-2">Peminjaman Bulan Ini</h3>
                    <div class="text-3xl font-bold text-purple-600"><?= $statistik['total_peminjaman'] ?></div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <h3 class="text-gray-600 mb-2">Menunggu Persetujuan</h3>
                    <div class="text-3xl font-bold text-red-600"><?= $statistik['peminjaman_pending'] ?></div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-2xl font-bold mb-4">Peminjaman Terakhir</h2>
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600">
                            <th class="py-2 px-4 text-left">ID Peminjaman</th>
                            <th class="py-2 px-4 text-left">Nama Peminjam</th>
                            <th class="py-2 px-4 text-left">Tanggal Pinjam</th>
                            <th class="py-2 px-4 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($peminjamanTerakhir as $peminjaman): ?>
<tr class="border-b border-gray-200 hover:bg-gray-50">
    <td class="py-3 px-4"><?= $peminjaman['PEMINJAMAN_ID'] ?></td>
    <td class="py-3 px-4"><?= $peminjaman['NAMA_LENGKAP'] ?></td>
    <td class="py-3 px-4"><?= date('d M Y', strtotime($peminjaman['TANGGAL_PINJAM'])) ?></td>
    <td class="py-3 px-4">
        <span class="<?= 
            $peminjaman['STATUS_PEMINJAMAN'] == 'Diajukan' ? 'bg-yellow-100 text-yellow-800' : 
            ($peminjaman['STATUS_PEMINJAMAN'] == 'Disetujui' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800')
        ?> px-2 py-1 rounded-full text-xs">
            <?= $peminjaman['STATUS_PEMINJAMAN'] ?>
        </span>
    </td>
</tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
</body>
</html>