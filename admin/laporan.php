<?php
session_start();
require_once '../config/database.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

class Laporan {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function getLaporanPeminjaman($tanggal_awal = null, $tanggal_akhir = null) {
        $query = "SELECT 
                    p.peminjaman_id, 
                    u.nama_lengkap, 
                    p.tanggal_pinjam, 
                    COALESCE(p.tanggal_kembali, 
                        (SELECT MAX(dp.tanggal_selesai) 
                         FROM detail_peminjaman dp 
                         WHERE dp.peminjaman_id = p.peminjaman_id)) AS tanggal_kembali,
                    p.status_peminjaman,
                    SUM(a.harga_sewa) AS total_biaya
                  FROM peminjaman p
                  JOIN users u ON p.user_id = u.user_id
                  JOIN detail_peminjaman dp ON p.peminjaman_id = dp.peminjaman_id
                  JOIN alat_mendaki a ON dp.alat_id = a.alat_id";
        
        // Filter tanggal jika diberikan
        $conditions = [];
        if ($tanggal_awal) {
            $conditions[] = "p.tanggal_pinjam >= TO_DATE(:tanggal_awal, 'YYYY-MM-DD')";
        }
        if ($tanggal_akhir) {
            $conditions[] = "p.tanggal_pinjam <= TO_DATE(:tanggal_akhir, 'YYYY-MM-DD')";
        }

        if ($conditions) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        $query .= " GROUP BY p.peminjaman_id, u.nama_lengkap, p.tanggal_pinjam, p.tanggal_kembali, p.status_peminjaman
                    ORDER BY p.tanggal_pinjam DESC";
        
        $stmt = oci_parse($this->conn, $query);
        
        // Bind parameter tanggal jika ada
        if ($tanggal_awal) {
            oci_bind_by_name($stmt, ':tanggal_awal', $tanggal_awal);
        }
        if ($tanggal_akhir) {
            oci_bind_by_name($stmt, ':tanggal_akhir', $tanggal_akhir);
        }
        
        oci_execute($stmt);

        $laporan = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $laporan[] = $row;
        }

        return $laporan;
    }

    public function getLaporanPendapatan($tanggal_awal = null, $tanggal_akhir = null) {
        $query = "SELECT 
                    EXTRACT(YEAR FROM p.tanggal_pinjam) AS tahun,
                    EXTRACT(MONTH FROM p.tanggal_pinjam) AS bulan,
                    COUNT(p.peminjaman_id) AS jumlah_peminjaman,
                    SUM(a.harga_sewa) AS total_pendapatan
                  FROM peminjaman p
                  JOIN detail_peminjaman dp ON p.peminjaman_id = dp.peminjaman_id
                  JOIN alat_mendaki a ON dp.alat_id = a.alat_id
                  WHERE p.status_peminjaman = 'Selesai'";
        
        // Filter tanggal jika diberikan
        $conditions = [];
        if ($tanggal_awal) {
            $conditions[] = "p.tanggal_pinjam >= TO_DATE(:tanggal_awal, 'YYYY-MM-DD')";
        }
        if ($tanggal_akhir) {
            $conditions[] = "p.tanggal_pinjam <= TO_DATE(:tanggal_akhir, 'YYYY-MM-DD')";
        }

        if ($conditions) {
            $query .= " AND " . implode(" AND ", $conditions);
        }

        $query .= " GROUP BY 
                    EXTRACT(YEAR FROM p.tanggal_pinjam),
                    EXTRACT(MONTH FROM p.tanggal_pinjam)
                    ORDER BY tahun, bulan";
        
        $stmt = oci_parse($this->conn, $query);
        
        // Bind parameter tanggal jika ada
        if ($tanggal_awal) {
            oci_bind_by_name($stmt, ':tanggal_awal', $tanggal_awal);
        }
        if ($tanggal_akhir) {
            oci_bind_by_name($stmt, ':tanggal_akhir', $tanggal_akhir);
        }
        
        oci_execute($stmt);

        $laporan = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $laporan[] = $row;
        }

        return $laporan;
    }
}

$database = new Database();
$laporan = new Laporan($database);

// Proses filter laporan
$tanggal_awal = $_GET['tanggal_awal'] ?? null;
$tanggal_akhir = $_GET['tanggal_akhir'] ?? null;

$laporanPeminjaman = $laporan->getLaporanPeminjaman($tanggal_awal, $tanggal_akhir);
$laporanPendapatan = $laporan->getLaporanPendapatan($tanggal_awal, $tanggal_akhir);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan - Peminjaman Alat Pendaki</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'components/sidebar.php'; ?>

    <div class="ml-64 p-8">
        <div class="container mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Peminjaman</h1>

            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Tanggal Awal</label>
                        <input type="date" name="tanggal_awal" 
                               value="<?= $tanggal_awal ?>" 
                               class="w-full px-3 py-2 border rounded-md">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Tanggal Akhir</label>
                        <input type="date" name="tanggal_akhir" 
                               value="<?= $tanggal_akhir ?>" 
                               class="w-full px-3 py-2 border rounded-md">
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                            Filter
                        </button>
                        <a href="laporan.php" 
                           class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 transition">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                <h2 class="text-xl font-semibold mb-4">Daftar Peminjaman</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600">
                                <th class="py-2 px-4 text-left">ID Peminjaman</th>
                                <th class="py-2 px-4 text-left">Nama Peminjam</th>
                                <th class="py-2 px-4 text-left">Tanggal Pinjam</th>
                                <th class="py-2 px-4 text-left">Tanggal Kembali</th>
                                <th class="py-2 px-4 text-left">Status</th>
                                <th class="py-2 px-4 text-left">Total Biaya</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($laporanPeminjaman as $item): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-4"><?= $item['PEMINJAMAN_ID'] ?></td>
                                <td class="py-3 px-4"><?= $item['NAMA_LENGKAP'] ?></td>
                                <td class="py-3 px-4"><?= date('d M Y', strtotime($item['TANGGAL_PINJAM'])) ?></td>
                                <td class="py-3 px-4"><?= date('d M Y', strtotime($item['TANGGAL_KEMBALI'])) ?></td>
                                <td class="py-3 px-4">
                                    <span class="<?= 
                                        $item['STATUS_PEMINJAMAN'] == 'Selesai' ? 'bg-green-100 text-green-800' : 
                                        ($item['STATUS_PEMINJAMAN'] == 'Sedang Berjalan' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800')
                                    ?> px-2 py-1 rounded-full text-xs">
                                        <?= $item['STATUS_PEMINJAMAN'] ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    Rp <?= number_format($item['TOTAL_BIAYA'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                <h2 class="text-xl font-semibold mb-4">Laporan Pendapatan</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600">
                                <th class="py-2 px-4 text-left">Tahun</th>
                                <th class="py-2 px-4 text-left">Bulan</th>
                                <th class="py-2 px-4 text-left">Jumlah Peminjaman</th>
                                <th class="py-2 px-4 text-left">Total Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($laporanPendapatan as $item): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-4"><?= $item['TAHUN'] ?></td>
                                <td class="py-3 px-4"><?= date('F', mktime(0, 0, 0, $item['BULAN'], 10)) ?></td>
                                <td class="py-3 px-4"><?= $item['JUMLAH_PEMINJAMAN'] ?></td>
                                <td class="py-3 px-4">
                                    Rp <?= number_format($item['TOTAL_PENDAPATAN'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end">
                <button onclick="window.print()" 
                        class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 transition">
                    <i class="fas fa-print mr-2"></i>Cetak Laporan
                </button>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
</body>
</html>