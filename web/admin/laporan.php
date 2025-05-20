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
                    p.tanggal_kembali,
                    p.status_peminjaman,
                    p.total_biaya,
                    pb.status_pembayaran
                  FROM peminjaman p
                  JOIN users u ON p.user_id = u.user_id
                  LEFT JOIN pembayaran pb ON p.peminjaman_id = pb.peminjaman_id";
        
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

        $query .= " ORDER BY p.tanggal_pinjam DESC";
        
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
                    COUNT(DISTINCT p.peminjaman_id) AS jumlah_peminjaman,
                    SUM(pb.jumlah_pembayaran) AS total_pendapatan
                  FROM peminjaman p
                  JOIN pembayaran pb ON p.peminjaman_id = pb.peminjaman_id
                  WHERE pb.status_pembayaran = 'Lunas'";
        
        // Filter tanggal jika diberikan
        if ($tanggal_awal) {
            $query .= " AND p.tanggal_pinjam >= TO_DATE(:tanggal_awal, 'YYYY-MM-DD')";
        }
        if ($tanggal_akhir) {
            $query .= " AND p.tanggal_pinjam <= TO_DATE(:tanggal_akhir, 'YYYY-MM-DD')";
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
    
    public function getLaporanAlatPopuler($tanggal_awal = null, $tanggal_akhir = null) {
        $query = "SELECT 
                    a.alat_id,
                    a.nama_alat,
                    k.nama_kategori,
                    COUNT(dp.detail_id) AS jumlah_peminjaman,
                    SUM(dp.jumlah_pinjam) AS total_unit_dipinjam,
                    SUM(a.harga_sewa * dp.jumlah_pinjam * 
                        (EXTRACT(DAY FROM dp.tanggal_selesai) - EXTRACT(DAY FROM dp.tanggal_mulai) + 1)) AS total_pendapatan
                  FROM detail_peminjaman dp
                  JOIN alat_mendaki a ON dp.alat_id = a.alat_id
                  JOIN kategori_alat k ON a.kategori_id = k.kategori_id
                  JOIN peminjaman p ON dp.peminjaman_id = p.peminjaman_id
                  JOIN pembayaran pb ON p.peminjaman_id = pb.peminjaman_id
                  WHERE pb.status_pembayaran = 'Lunas'";
        
        // Filter tanggal jika diberikan
        if ($tanggal_awal) {
            $query .= " AND p.tanggal_pinjam >= TO_DATE(:tanggal_awal, 'YYYY-MM-DD')";
        }
        if ($tanggal_akhir) {
            $query .= " AND p.tanggal_pinjam <= TO_DATE(:tanggal_akhir, 'YYYY-MM-DD')";
        }

        $query .= " GROUP BY a.alat_id, a.nama_alat, k.nama_kategori
                    ORDER BY jumlah_peminjaman DESC";
        
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
$laporanAlatPopuler = $laporan->getLaporanAlatPopuler($tanggal_awal, $tanggal_akhir);

// Tambahkan fungsi untuk mendapatkan nama bulan dalam bahasa Indonesia
function getNamaBulan($bulan) {
    $nama_bulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];
    
    return $nama_bulan[$bulan] ?? $bulan;
}

// Untuk menyimpan data laporan dalam format yang mudah di-export
$laporan_data = [
    'tanggal_cetak' => date('d F Y'),
    'filter_tanggal' => [
        'awal' => $tanggal_awal ? date('d F Y', strtotime($tanggal_awal)) : 'Semua',
        'akhir' => $tanggal_akhir ? date('d F Y', strtotime($tanggal_akhir)) : 'Semua'
    ],
    'peminjaman' => $laporanPeminjaman,
    'pendapatan' => $laporanPendapatan,
    'alat_populer' => $laporanAlatPopuler
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan - Peminjaman Alat Pendaki</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            .page-break {
                page-break-after: always;
            }
            body {
                font-size: 12pt;
                margin: 0;
                padding: 0;
                background-color: white;
            }
            .print-container {
                width: 100%;
                margin: 0;
                padding: 20px;
                background-color: white;
            }
            .table-compact th, .table-compact td {
                padding: 8px 6px;
                font-size: 11pt;
            }
            .content-container {
                margin-left: 0 !important; /* Hilangkan margin untuk layout print */
                padding: 20px !important;
            }
            .status-badge {
                border: 1px solid;
            }
            .status-lunas {
                background-color: #DEF7EC !important;
                color: #057A55 !important;
            }
            .status-menunggu {
                background-color: #FEF3C7 !important;  
                color: #D97706 !important;
            }
            .status-gagal {
                background-color: #FEE2E2 !important;
                color: #DC2626 !important;
            }
        }
        
        .content-container {
            margin-left: 250px; /* Sesuaikan dengan lebar sidebar */
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-lunas {
            background-color: #DEF7EC;
            color: #057A55;
        }
        
        .status-menunggu {
            background-color: #FEF3C7;  
            color: #D97706;
        }
        
        .status-gagal {
            background-color: #FEE2E2;
            color: #DC2626;
        }
        
        .table-compact {
            font-size: 0.875rem;
        }
        
        .table-compact th, .table-compact td {
            padding: 0.5rem 0.75rem;
        }
        
        .print-only {
            display: none;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 bg-gray-800 w-64 no-print">
        <?php include 'components/sidebar.php'; ?>
    </div>

    <!-- Content Area (Tampilan Web) -->
    <div class="content-container no-print p-8">
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
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="laporan.php" 
                           class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300 transition">
                            <i class="fas fa-sync-alt mr-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="flex justify-end mb-4">
                <button onclick="window.print()" 
                        class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 transition">
                    <i class="fas fa-print mr-2"></i>Cetak Laporan
                </button>
            </div>
            
          <!-- Preview Laporan -->
            <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md mb-6">
                <h2 class="text-xl font-semibold mb-4">Laporan Lengkap</h2>
                
                <!-- Bagian 1: Laporan Peminjaman -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium mb-2">1. Daftar Peminjaman</h3>
                    
                    <!-- Tampilan desktop - tabel -->
                    <div class="hidden sm:block table-responsive">
                        <table class="w-full border-collapse table-compact">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border py-2 px-3 text-left">ID</th>
                                    <th class="border py-2 px-3 text-left">Nama Peminjam</th>
                                    <th class="border py-2 px-3 text-left">Tanggal Pinjam</th>
                                    <th class="border py-2 px-3 text-left">Tanggal Kembali</th>
                                    <th class="border py-2 px-3 text-left">Status</th>
                                    <th class="border py-2 px-3 text-left">Pembayaran</th>
                                    <th class="border py-2 px-3 text-left">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($laporanPeminjaman)): ?>
                                <tr>
                                    <td colspan="7" class="border py-3 px-3 text-center text-gray-500">Tidak ada data peminjaman</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach($laporanPeminjaman as $item): ?>
                                    <tr class="border-b">
                                        <td class="border py-2 px-3"><?= $item['PEMINJAMAN_ID'] ?></td>
                                        <td class="border py-2 px-3"><?= $item['NAMA_LENGKAP'] ?></td>
                                        <td class="border py-2 px-3"><?= date('d M Y', strtotime($item['TANGGAL_PINJAM'])) ?></td>
                                        <td class="border py-2 px-3"><?= $item['TANGGAL_KEMBALI'] ? date('d M Y', strtotime($item['TANGGAL_KEMBALI'])) : '-' ?></td>
                                        <td class="border py-2 px-3">
                                            <?php 
                                            $statusClass = '';
                                            switch($item['STATUS_PEMINJAMAN']) {
                                                case 'Selesai':
                                                    $statusClass = 'bg-green-100 text-green-700';
                                                    break;
                                                case 'Sedang Dipinjam':
                                                    $statusClass = 'bg-blue-100 text-blue-700';
                                                    break;
                                                case 'Disetujui':
                                                    $statusClass = 'bg-indigo-100 text-indigo-700';
                                                    break;
                                                case 'Diajukan':
                                                    $statusClass = 'bg-yellow-100 text-yellow-700';
                                                    break;
                                                case 'Ditolak':
                                                    $statusClass = 'bg-red-100 text-red-700';
                                                    break;
                                            }
                                            ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $statusClass ?>">
                                                <?= $item['STATUS_PEMINJAMAN'] ?>
                                            </span>
                                        </td>
                                        <td class="border py-2 px-3">
                                            <?php 
                                            $paymentStatusClass = '';
                                            switch($item['STATUS_PEMBAYARAN'] ?? 'Belum Ada') {
                                                case 'Lunas':
                                                    $paymentStatusClass = 'status-lunas';
                                                    break;
                                                case 'Menunggu':
                                                    $paymentStatusClass = 'status-menunggu';
                                                    break;
                                                case 'Gagal':
                                                    $paymentStatusClass = 'status-gagal';
                                                    break;
                                                default:
                                                    $paymentStatusClass = 'bg-gray-100 text-gray-700';
                                            }
                                            ?>
                                            <span class="status-badge <?= $paymentStatusClass ?>">
                                                <?= $item['STATUS_PEMBAYARAN'] ?? 'Belum Ada' ?>
                                            </span>
                                        </td>
                                        <td class="border py-2 px-3">
                                            Rp <?= number_format($item['TOTAL_BIAYA'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Tampilan mobile - kartu -->
                    <div class="sm:hidden card-list">
                        <?php if (empty($laporanPeminjaman)): ?>
                        <div class="text-center text-gray-500 py-4">Tidak ada data peminjaman</div>
                        <?php else: ?>
                            <?php foreach($laporanPeminjaman as $item): ?>
                            <div class="card-item bg-white border rounded-lg p-4 mb-4">
                                <div class="flex justify-between items-start">
                                    <div class="text-lg font-medium">ID: <?= $item['PEMINJAMAN_ID'] ?></div>
                                    <div>
                                        <?php 
                                        $statusClass = '';
                                        switch($item['STATUS_PEMINJAMAN']) {
                                            case 'Selesai':
                                                $statusClass = 'bg-green-100 text-green-700';
                                                break;
                                            case 'Sedang Dipinjam':
                                                $statusClass = 'bg-blue-100 text-blue-700';
                                                break;
                                            case 'Disetujui':
                                                $statusClass = 'bg-indigo-100 text-indigo-700';
                                                break;
                                            case 'Diajukan':
                                                $statusClass = 'bg-yellow-100 text-yellow-700';
                                                break;
                                            case 'Ditolak':
                                                $statusClass = 'bg-red-100 text-red-700';
                                                break;
                                        }
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $statusClass ?>">
                                            <?= $item['STATUS_PEMINJAMAN'] ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-2">
                                    <div class="text-sm text-gray-500">Nama Peminjam</div>
                                    <div class="font-medium"><?= $item['NAMA_LENGKAP'] ?></div>
                                </div>

                                <div class="grid grid-cols-2 gap-2 mt-2">
                                    <div>
                                        <div class="text-sm text-gray-500">Tanggal Pinjam</div>
                                        <div><?= date('d M Y', strtotime($item['TANGGAL_PINJAM'])) ?></div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">Tanggal Kembali</div>
                                        <div><?= $item['TANGGAL_KEMBALI'] ? date('d M Y', strtotime($item['TANGGAL_KEMBALI'])) : '-' ?></div>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center mt-3">
                                    <div>
                                        <div class="text-sm text-gray-500">Pembayaran</div>
                                        <?php 
                                        $paymentStatusClass = '';
                                        switch($item['STATUS_PEMBAYARAN'] ?? 'Belum Ada') {
                                            case 'Lunas':
                                                $paymentStatusClass = 'status-lunas';
                                                break;
                                            case 'Menunggu':
                                                $paymentStatusClass = 'status-menunggu';
                                                break;
                                            case 'Gagal':
                                                $paymentStatusClass = 'status-gagal';
                                                break;
                                            default:
                                                $paymentStatusClass = 'bg-gray-100 text-gray-700';
                                        }
                                        ?>
                                        <span class="status-badge <?= $paymentStatusClass ?>">
                                            <?= $item['STATUS_PEMBAYARAN'] ?? 'Belum Ada' ?>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">Total</div>
                                        <div class="font-bold">Rp <?= number_format($item['TOTAL_BIAYA'], 0, ',', '.') ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Bagian 2: Laporan Pendapatan -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium mb-2">2. Pendapatan Bulanan</h3>
                    
                    <!-- Tampilan desktop & mobile - tabel responsif -->
                    <div class="table-responsive">
                        <table class="w-full border-collapse table-compact">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border py-2 px-3 text-left">Tahun</th>
                                    <th class="border py-2 px-3 text-left">Bulan</th>
                                    <th class="border py-2 px-3 text-left">Jumlah Peminjaman</th>
                                    <th class="border py-2 px-3 text-left">Total Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($laporanPendapatan)): ?>
                                <tr>
                                    <td colspan="4" class="border py-3 px-3 text-center text-gray-500">Tidak ada data pendapatan</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach($laporanPendapatan as $item): ?>
                                    <tr class="border-b">
                                        <td class="border py-2 px-3"><?= $item['TAHUN'] ?></td>
                                        <td class="border py-2 px-3"><?= getNamaBulan($item['BULAN']) ?></td>
                                        <td class="border py-2 px-3"><?= $item['JUMLAH_PEMINJAMAN'] ?></td>
                                        <td class="border py-2 px-3">Rp <?= number_format($item['TOTAL_PENDAPATAN'], 0, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="bg-gray-100">
                                        <td colspan="3" class="border py-2 px-3 font-semibold text-right">Total Pendapatan:</td>
                                        <td class="border py-2 px-3 font-semibold">
                                            Rp <?= number_format(array_sum(array_column($laporanPendapatan, 'TOTAL_PENDAPATAN')), 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Bagian 3: Alat Populer -->
                <div>
                    <h3 class="text-lg font-medium mb-2">3. Alat Terpopuler</h3>
                    
                    <!-- Tampilan desktop - tabel penuh -->
                    <div class="hidden md:block table-responsive">
                        <table class="w-full border-collapse table-compact">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border py-2 px-3 text-left">ID Alat</th>
                                    <th class="border py-2 px-3 text-left">Nama Alat</th>
                                    <th class="border py-2 px-3 text-left">Kategori</th>
                                    <th class="border py-2 px-3 text-left">Jumlah Peminjaman</th>
                                    <th class="border py-2 px-3 text-left">Total Unit Dipinjam</th>
                                    <th class="border py-2 px-3 text-left">Total Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($laporanAlatPopuler)): ?>
                                <tr>
                                    <td colspan="6" class="border py-3 px-3 text-center text-gray-500">Tidak ada data alat populer</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach($laporanAlatPopuler as $item): ?>
                                    <tr class="border-b">
                                        <td class="border py-2 px-3"><?= $item['ALAT_ID'] ?></td>
                                        <td class="border py-2 px-3"><?= $item['NAMA_ALAT'] ?></td>
                                        <td class="border py-2 px-3"><?= $item['NAMA_KATEGORI'] ?></td>
                                        <td class="border py-2 px-3"><?= $item['JUMLAH_PEMINJAMAN'] ?></td>
                                        <td class="border py-2 px-3"><?= $item['TOTAL_UNIT_DIPINJAM'] ?></td>
                                        <td class="border py-2 px-3">Rp <?= number_format($item['TOTAL_PENDAPATAN'], 0, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="bg-gray-100">
                                        <td colspan="5" class="border py-2 px-3 font-semibold text-right">Total Pendapatan dari Alat:</td>
                                        <td class="border py-2 px-3 font-semibold">
                                            Rp <?= number_format(array_sum(array_column($laporanAlatPopuler, 'TOTAL_PENDAPATAN')), 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Tampilan mobile - tabel sederhana -->
                    <div class="md:hidden table-responsive">
                        <table class="w-full border-collapse table-compact">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border py-2 px-3 text-left">Nama Alat</th>
                                    <th class="border py-2 px-3 text-left">Kategori</th>
                                    <th class="border py-2 px-3 text-left">Jml. Pinjam</th>
                                    <th class="border py-2 px-3 text-left">Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($laporanAlatPopuler)): ?>
                                <tr>
                                    <td colspan="4" class="border py-3 px-3 text-center text-gray-500">Tidak ada data alat populer</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach($laporanAlatPopuler as $item): ?>
                                    <tr class="border-b">
                                        <td class="border py-2 px-3"><?= $item['NAMA_ALAT'] ?></td>
                                        <td class="border py-2 px-3"><?= $item['NAMA_KATEGORI'] ?></td>
                                        <td class="border py-2 px-3"><?= $item['JUMLAH_PEMINJAMAN'] ?></td>
                                        <td class="border py-2 px-3">Rp <?= number_format($item['TOTAL_PENDAPATAN'], 0, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="bg-gray-100">
                                        <td colspan="3" class="border py-2 px-3 font-semibold text-right">Total:</td>
                                        <td class="border py-2 px-3 font-semibold">
                                            Rp <?= number_format(array_sum(array_column($laporanAlatPopuler, 'TOTAL_PENDAPATAN')), 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
            <?php include 'components/footer.php'; ?>
    </div>



    <!-- Print-only version of the report -->
    <div class="print-only print-container">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold">LAPORAN PEMINJAMAN ALAT PENDAKI</h1>
            <p class="mt-2">Periode: <?= date('d M Y', strtotime($tanggal_awal)) ?> - <?= date('d M Y', strtotime($tanggal_akhir)) ?></p>
        </div>
        
        <!-- Bagian 1: Laporan Peminjaman -->
        <div class="mb-8">
            <h2 class="text-xl font-bold mb-3">1. Daftar Peminjaman</h2>
            <table class="w-full border-collapse table-compact">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border py-2 px-3 text-left">ID</th>
                        <th class="border py-2 px-3 text-left">Nama Peminjam</th>
                        <th class="border py-2 px-3 text-left">Tanggal Pinjam</th>
                        <th class="border py-2 px-3 text-left">Tanggal Kembali</th>
                        <th class="border py-2 px-3 text-left">Status</th>
                        <th class="border py-2 px-3 text-left">Pembayaran</th>
                        <th class="border py-2 px-3 text-left">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($laporanPeminjaman)): ?>
                    <tr>
                        <td colspan="7" class="border py-3 px-3 text-center text-gray-500">Tidak ada data peminjaman</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($laporanPeminjaman as $item): ?>
                        <tr class="border-b">
                            <td class="border py-2 px-3"><?= $item['PEMINJAMAN_ID'] ?></td>
                            <td class="border py-2 px-3"><?= $item['NAMA_LENGKAP'] ?></td>
                            <td class="border py-2 px-3"><?= date('d M Y', strtotime($item['TANGGAL_PINJAM'])) ?></td>
                            <td class="border py-2 px-3"><?= $item['TANGGAL_KEMBALI'] ? date('d M Y', strtotime($item['TANGGAL_KEMBALI'])) : '-' ?></td>
                            <td class="border py-2 px-3"><?= $item['STATUS_PEMINJAMAN'] ?></td>
                            <td class="border py-2 px-3">
                                <span class="status-badge <?= $paymentStatusClass = $item['STATUS_PEMBAYARAN'] == 'Lunas' ? 'status-lunas' : ($item['STATUS_PEMBAYARAN'] == 'Menunggu' ? 'status-menunggu' : ($item['STATUS_PEMBAYARAN'] == 'Gagal' ? 'status-gagal' : 'bg-gray-100 text-gray-700')) ?>">
                                    <?= $item['STATUS_PEMBAYARAN'] ?? 'Belum Ada' ?>
                                </span>
                            </td>
                            <td class="border py-2 px-3">
                                Rp <?= number_format($item['TOTAL_BIAYA'], 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Bagian 2: Laporan Pendapatan -->
        <div class="mb-8">
            <h2 class="text-xl font-bold mb-3">2. Pendapatan Bulanan</h2>
            <table class="w-full border-collapse table-compact">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border py-2 px-3 text-left">Tahun</th>
                        <th class="border py-2 px-3 text-left">Bulan</th>
                        <th class="border py-2 px-3 text-left">Jumlah Peminjaman</th>
                        <th class="border py-2 px-3 text-left">Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($laporanPendapatan)): ?>
                    <tr>
                        <td colspan="4" class="border py-3 px-3 text-center text-gray-500">Tidak ada data pendapatan</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($laporanPendapatan as $item): ?>
                        <tr class="border-b">
                            <td class="border py-2 px-3"><?= $item['TAHUN'] ?></td>
                            <td class="border py-2 px-3"><?= getNamaBulan($item['BULAN']) ?></td>
                            <td class="border py-2 px-3"><?= $item['JUMLAH_PEMINJAMAN'] ?></td>
                            <td class="border py-2 px-3">Rp <?= number_format($item['TOTAL_PENDAPATAN'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-100">
                            <td colspan="3" class="border py-2 px-3 font-semibold text-right">Total Pendapatan:</td>
                            <td class="border py-2 px-3 font-semibold">
                                Rp <?= number_format(array_sum(array_column($laporanPendapatan, 'TOTAL_PENDAPATAN')), 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Bagian 3: Alat Populer -->
        <div>
            <h2 class="text-xl font-bold mb-3">3. Alat Terpopuler</h2>
            <table class="w-full border-collapse table-compact">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border py-2 px-3 text-left">ID Alat</th>
                        <th class="border py-2 px-3 text-left">Nama Alat</th>
                        <th class="border py-2 px-3 text-left">Kategori</th>
                        <th class="border py-2 px-3 text-left">Jumlah Peminjaman</th>
                        <th class="border py-2 px-3 text-left">Total Unit Dipinjam</th>
                        <th class="border py-2 px-3 text-left">Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($laporanAlatPopuler)): ?>
                    <tr>
                        <td colspan="6" class="border py-3 px-3 text-center text-gray-500">Tidak ada data alat populer</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($laporanAlatPopuler as $item): ?>
                        <tr class="border-b">
                            <td class="border py-2 px-3"><?= $item['ALAT_ID'] ?></td>
                            <td class="border py-2 px-3"><?= $item['NAMA_ALAT'] ?></td>
                            <td class="border py-2 px-3"><?= $item['NAMA_KATEGORI'] ?></td>
                            <td class="border py-2 px-3"><?= $item['JUMLAH_PEMINJAMAN'] ?></td>
                            <td class="border py-2 px-3"><?= $item['TOTAL_UNIT_DIPINJAM'] ?></td>
                            <td class="border py-2 px-3">Rp <?= number_format($item['TOTAL_PENDAPATAN'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-100">
                            <td colspan="5" class="border py-2 px-3 font-semibold text-right">Total Pendapatan dari Alat:</td>
                            <td class="border py-2 px-3 font-semibold">
                                Rp <?= number_format(array_sum(array_column($laporanAlatPopuler, 'TOTAL_PENDAPATAN')), 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-8 text-right">
            <p>Dicetak pada: <?= date('d M Y H:i:s') ?></p>
        </div>
    </div>
    

    <!-- JavaScript section for exporting data if needed -->
    <script>
        // Function to download data as CSV
        function downloadCSV(data, filename) {
            const csvContent = "data:text/csv;charset=utf-8," + data;
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Example function to format table data as CSV
        function exportTableToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                for (let j = 0; j < cols.length; j++) {
                    // Replace any commas in the cell text with a space to avoid CSV format issues
                    let data = cols[j].innerText.replace(/,/g, ' ');
                    // Remove thousand separators from numbers
                    data = data.replace(/\./g, '');
                    // Enclose data in quotes if it contains quotes or newline
                    if (data.includes('"') || data.includes('\n')) {
                        data = '"' + data.replace(/"/g, '""') + '"';
                    }
                    row.push(data);
                }
                csv.push(row.join(','));
            }
            downloadCSV(csv.join('\n'), filename);
        }
    </script>
</body>
</html>