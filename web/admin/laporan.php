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
        /* Print styling */
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
                padding: 20px;
                background-color: white;
            }
            
            .print-container {
                width: 100%;
                margin: 0;
                padding: 20px;
                background-color: white;
            }
            
            .table-compact th, 
            .table-compact td {
                padding: 8px 6px;
                font-size: 11pt;
            }
            
            /* Make sure status badges print with correct colors */
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
            
            table {
                width: 100%;
                border-collapse: collapse;
            }
            
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            
            .card-list {
                display: none;
            }
        }

        /* Default styling for screen */
        body {
            background-color: #f7fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Status badges styling */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
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
        
        /* Card styles for reports */
        .report-card {
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .report-card:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }
        
        .report-header {
            padding: 1.25rem;
            background: linear-gradient(135deg,rgb(31, 41, 55) 0%,rgb(31, 41, 55) 100%);
            color: white;
        }
        
        .mini-stat-card {
            border-radius: 8px;
            padding: 1rem;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .mini-stat-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }
        
        /* Improved table styling */
        .table-modern {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            font-size: 0.875rem;
        }
        
        .table-modern th {
            background-color: #F3F4F6;
            color: #4B5563;
            font-weight: 600;
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }
        
        .table-modern td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #E5E7EB;
        }
        
        .table-modern tbody tr {
            background-color: white;
            transition: all 0.2s ease;
        }
        
        .table-modern tbody tr:hover {
            background-color: #F9FAFB;
        }
        
        .table-modern tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Button styling */
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background-color: #4F46E5;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #4338CA;
        }
        
        .btn-secondary {
            background-color: #F3F4F6;
            color: #4B5563;
        }
        
        .btn-secondary:hover {
            background-color: #E5E7EB;
        }
        
        /* Custom animation for card displays */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .table-compact {
            font-size: 0.875rem;
        }

        .table-compact th, 
        .table-compact td {
            padding: 0.5rem 0.75rem;
        }

        .print-only {
            display: none;
        }

        /* Responsiveness */
        .overflow-x-auto {
            -webkit-overflow-scrolling: touch;
        }

        /* Status indicators with improved styling */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        .status-indicator-dot {
            height: 0.5rem;
            width: 0.5rem;
            border-radius: 50%;
            margin-right: 0.375rem;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="ml-0 md:ml-64 p-4 md:p-8 pt-16 md:pt-8 transition-all duration-300 no-print">
        <div class="container mx-auto">
            <!-- Page Header with Gradient Background -->
            <div class="bg-gradient-to-r from-gray-800 to-gray-800 rounded-xl shadow-lg p-6 mb-6 text-white">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold mb-2">Laporan Peminjaman</h1>
                        <p class="text-indigo-100">Manajemen dan analisis data peminjaman alat pendaki</p>
                    </div>
                    <button onclick="window.print()" 
                            class="mt-4 md:mt-0 bg-white text-indigo-600 hover:bg-indigo-50 px-4 sm:px-6 py-2 text-sm sm:text-base rounded-md transition-all shadow-md flex items-center">
                        <i class="fas fa-print mr-2"></i>Cetak Laporan
                    </button>
                </div>
            </div>
            
            <!-- Filter Form Card -->
            <div class="bg-white p-5 rounded-xl shadow-md mb-6 transition-all hover:shadow-lg">
                <h2 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
                    <i class="fas fa-filter mr-2 text-indigo-500"></i>Filter Data
                </h2>
                <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-2">Tanggal Awal</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-calendar-alt text-gray-400"></i>
                            </div>
                            <input type="date" name="tanggal_awal" 
                                   value="<?= $tanggal_awal ?>" 
                                   class="pl-10 w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-2">Tanggal Akhir</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-calendar-alt text-gray-400"></i>
                            </div>
                            <input type="date" name="tanggal_akhir" 
                                   value="<?= $tanggal_akhir ?>" 
                                   class="pl-10 w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="flex items-end space-x-2 col-span-1 sm:col-span-2">
                        <button type="submit" 
                                class="btn btn-primary flex-1 sm:flex-none">
                            <i class="fas fa-search mr-2"></i>Terapkan Filter
                        </button>
                        <a href="laporan.php" 
                           class="btn btn-secondary flex-1 sm:flex-none">
                            <i class="fas fa-sync-alt mr-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Quick Stats Dashboard -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- Total Peminjaman -->
                <div class="bg-white p-4 rounded-xl shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-center">
                        <div class="rounded-full bg-blue-100 p-3 mr-4">
                            <i class="fas fa-clipboard-list text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 font-medium">Total Peminjaman</div>
                            <div class="text-xl font-bold text-gray-800">
                                <?= count($laporanPeminjaman) ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Pendapatan -->
                <div class="bg-white p-4 rounded-xl shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-center">
                        <div class="rounded-full bg-green-100 p-3 mr-4">
                            <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 font-medium">Total Pendapatan</div>
                            <div class="text-xl font-bold text-gray-800">
                                Rp <?= number_format(array_sum(array_column($laporanPendapatan, 'TOTAL_PENDAPATAN')), 0, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Jumlah Alat -->
                <div class="bg-white p-4 rounded-xl shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-center">
                        <div class="rounded-full bg-purple-100 p-3 mr-4">
                            <i class="fas fa-campground text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 font-medium">Jumlah Alat</div>
                            <div class="text-xl font-bold text-gray-800">
                                <?= count($laporanAlatPopuler) ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Periode Laporan -->
                <div class="bg-white p-4 rounded-xl shadow-sm hover:shadow-md transition-all">
                    <div class="flex items-center">
                        <div class="rounded-full bg-yellow-100 p-3 mr-4">
                            <i class="fas fa-calendar-alt text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 font-medium">Periode</div>
                            <div class="text-sm font-medium text-gray-800">
                                <?php if ($tanggal_awal && $tanggal_akhir): ?>
                                    <?= date('d M Y', strtotime($tanggal_awal)) ?> - <?= date('d M Y', strtotime($tanggal_akhir)) ?>
                                <?php else: ?>
                                    Semua Data
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Report Sections -->
            <div class="space-y-6">
                <!-- Section 1: Daftar Peminjaman -->
                <div class="report-card bg-white overflow-hidden">
                    <div class="report-header bg-indigo-600 p-4 flex justify-between items-center">
                        <h2 class="text-lg sm:text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-clipboard-list mr-2"></i>
                            Daftar Peminjaman
                        </h2>
                        <span class="bg-indigo-800 text-white text-xs px-2 py-1 rounded-md">
                            <?= count($laporanPeminjaman) ?> Transaksi
                        </span>
                    </div>
                    
                    <!-- Table Container -->
                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="table-modern" id="tabelPeminjaman">
                                <thead>
                                    <tr>
                                        <th class="rounded-tl-lg">ID</th>
                                        <th>Nama Peminjam</th>
                                        <th>Tgl Pinjam</th>
                                        <th>Tgl Kembali</th>
                                        <th>Status</th>
                                        <th>Pembayaran</th>
                                        <th class="rounded-tr-lg">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($laporanPeminjaman)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-gray-500">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fas fa-search text-gray-400 text-3xl mb-2"></i>
                                                Tidak ada data peminjaman
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach($laporanPeminjaman as $index => $item): ?>
                                        <tr>
                                            <td><?= $item['PEMINJAMAN_ID'] ?></td>
                                            <td class="font-medium"><?= $item['NAMA_LENGKAP'] ?></td>
                                            <td><?= date('d/m/Y', strtotime($item['TANGGAL_PINJAM'])) ?></td>
                                            <td><?= $item['TANGGAL_KEMBALI'] ? date('d/m/Y', strtotime($item['TANGGAL_KEMBALI'])) : '-' ?></td>
                                            <td>
                                                <?php 
                                                $statusClass = '';
                                                $dotColor = '';
                                                switch($item['STATUS_PEMINJAMAN']) {
                                                    case 'Selesai':
                                                        $statusClass = 'bg-green-100 text-green-700';
                                                        $dotColor = 'bg-green-500';
                                                        break;
                                                    case 'Sedang Dipinjam':
                                                        $statusClass = 'bg-blue-100 text-blue-700';
                                                        $dotColor = 'bg-blue-500';
                                                        break;
                                                    case 'Disetujui':
                                                        $statusClass = 'bg-indigo-100 text-indigo-700';
                                                        $dotColor = 'bg-indigo-500';
                                                        break;
                                                    case 'Diajukan':
                                                        $statusClass = 'bg-yellow-100 text-yellow-700';
                                                        $dotColor = 'bg-yellow-500';
                                                        break;
                                                    case 'Ditolak':
                                                        $statusClass = 'bg-red-100 text-red-700';
                                                        $dotColor = 'bg-red-500';
                                                        break;
                                                }
                                                ?>
                                                <span class="status-indicator <?= $statusClass ?>">
                                                    <span class="status-indicator-dot <?= $dotColor ?>"></span>
                                                    <?= $item['STATUS_PEMINJAMAN'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $paymentStatusClass = '';
                                                $paymentDotColor = '';
                                                switch($item['STATUS_PEMBAYARAN'] ?? 'Belum Ada') {
                                                    case 'Lunas':
                                                        $paymentStatusClass = 'bg-green-100 text-green-700';
                                                        $paymentDotColor = 'bg-green-500';
                                                        break;
                                                    case 'Menunggu':
                                                        $paymentStatusClass = 'bg-yellow-100 text-yellow-700';
                                                        $paymentDotColor = 'bg-yellow-500';
                                                        break;
                                                    case 'Gagal':
                                                        $paymentStatusClass = 'bg-red-100 text-red-700';
                                                        $paymentDotColor = 'bg-red-500';
                                                        break;
                                                    default:
                                                        $paymentStatusClass = 'bg-gray-100 text-gray-700';
                                                        $paymentDotColor = 'bg-gray-500';
                                                }
                                                ?>
                                                <span class="status-indicator <?= $paymentStatusClass ?>">
                                                    <span class="status-indicator-dot <?= $paymentDotColor ?>"></span>
                                                    <?= $item['STATUS_PEMBAYARAN'] ?? 'Belum Ada' ?>
                                                </span>
                                            </td>
                                            <td class="font-medium text-right">
                                                Rp <?= number_format($item['TOTAL_BIAYA'], 0, ',', '.') ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Laporan Pendapatan Bulanan -->
                <div class="report-card bg-white overflow-hidden">
                    <div class="report-header bg-green-600 p-4 flex justify-between items-center">
                        <h2 class="text-lg sm:text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-chart-line mr-2"></i>
                            Laporan Pendapatan Bulanan
                        </h2>
                        <span class="bg-green-800 text-white text-xs px-2 py-1 rounded-md">
                            <?= count($laporanPendapatan) ?> Periode
                        </span>
                    </div>
                    
                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="table-modern" id="tabelPendapatan">
                                <thead>
                                    <tr>
                                        <th>Tahun</th>
                                        <th>Bulan</th>
                                        <th>Jumlah Peminjaman</th>
                                        <th>Total Pendapatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($laporanPendapatan)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-gray-500">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fas fa-chart-bar text-gray-400 text-3xl mb-2"></i>
                                                Tidak ada data pendapatan
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach($laporanPendapatan as $index => $item): ?>
                                        <tr>
                                            <td><?= $item['TAHUN'] ?></td>
                                            <td><?= getNamaBulan((int)$item['BULAN']) ?></td>
                                            <td class="text-center"><?= $item['JUMLAH_PEMINJAMAN'] ?></td>
                                            <td class="font-medium text-right">
                                                Rp <?= number_format($item['TOTAL_PENDAPATAN'], 0, ',', '.') ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Laporan Alat Populer -->
                <div class="report-card bg-white overflow-hidden">
                    <div class="report-header bg-purple-600 p-4 flex justify-between items-center">
                        <h2 class="text-lg sm:text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-fire-alt mr-2"></i>
                            Alat Pendaki Paling Popular
                        </h2>
                        <span class="bg-purple-800 text-white text-xs px-2 py-1 rounded-md">
                            <?= count($laporanAlatPopuler) ?> Alat
                        </span>
                    </div>
                    
                    <div class="p-4">
                        <div class="overflow-x-auto">
                            <table class="table-modern" id="tabelAlatPopular">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Alat</th>
                                        <th>Kategori</th>
                                        <th>Jml Peminjaman</th>
                                        <th>Unit Dipinjam</th>
                                        <th>Total Pendapatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($laporanAlatPopuler)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-gray-500">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fas fa-campground text-gray-400 text-3xl mb-2"></i>
                                                Tidak ada data alat populer
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach($laporanAlatPopuler as $index => $item): ?>
                                        <tr>
                                            <td><?= $item['ALAT_ID'] ?></td>
                                            <td class="font-medium"><?= $item['NAMA_ALAT'] ?></td>
                                            <td><?= $item['NAMA_KATEGORI'] ?></td>
                                            <td class="text-center"><?= $item['JUMLAH_PEMINJAMAN'] ?></td>
                                            <td class="text-center"><?= $item['TOTAL_UNIT_DIPINJAM'] ?></td>
                                            <td class="font-medium text-right">
                                                Rp <?= number_format($item['TOTAL_PENDAPATAN'], 0, ',', '.') ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Version (Only shown when printing) -->
    <div class="print-only">
        <div class="print-container">
            <!-- Print Header -->
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold">Laporan Peminjaman Alat Pendaki</h1>
                <p class="text-gray-700">
                    Periode: 
                    <?php if ($tanggal_awal && $tanggal_akhir): ?>
                        <?= date('d M Y', strtotime($tanggal_awal)) ?> - <?= date('d M Y', strtotime($tanggal_akhir)) ?>
                    <?php else: ?>
                        Semua Data
                    <?php endif; ?>
                </p>
                <p class="text-gray-500 text-sm">Dicetak pada: <?= date('d F Y H:i') ?></p>
            </div>
            
            <!-- Quick Stats for Print -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="border border-gray-300 p-3 text-center">
                    <div class="text-lg font-bold"><?= count($laporanPeminjaman) ?></div>
                    <div class="text-gray-600 text-sm">Total Peminjaman</div>
                </div>
                <div class="border border-gray-300 p-3 text-center">
                    <div class="text-lg font-bold">Rp <?= number_format(array_sum(array_column($laporanPendapatan, 'TOTAL_PENDAPATAN')), 0, ',', '.') ?></div>
                    <div class="text-gray-600 text-sm">Total Pendapatan</div>
                </div>
                <div class="border border-gray-300 p-3 text-center">
                    <div class="text-lg font-bold"><?= count($laporanAlatPopuler) ?></div>
                    <div class="text-gray-600 text-sm">Jumlah Alat</div>
                </div>
                <div class="border border-gray-300 p-3 text-center">
                    <div class="text-lg font-bold">
                        <?php 
                            $totalUnitPinjam = array_sum(array_column($laporanAlatPopuler, 'TOTAL_UNIT_DIPINJAM'));
                            echo number_format($totalUnitPinjam, 0, ',', '.');
                        ?>
                    </div>
                    <div class="text-gray-600 text-sm">Total Unit Dipinjam</div>
                </div>
            </div>
            
            <!-- Daftar Peminjaman untuk Print -->
            <div class="mb-8">
                <h2 class="text-xl font-bold mb-3">1. Daftar Peminjaman</h2>
                <table class="w-full border-collapse border border-gray-300 table-compact">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300">ID</th>
                            <th class="border border-gray-300">Nama Peminjam</th>
                            <th class="border border-gray-300">Tgl Pinjam</th>
                            <th class="border border-gray-300">Tgl Kembali</th>
                            <th class="border border-gray-300">Status</th>
                            <th class="border border-gray-300">Pembayaran</th>
                            <th class="border border-gray-300">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($laporanPeminjaman)): ?>
                            <tr>
                                <td colspan="7" class="border border-gray-300 text-center py-2">Tidak ada data peminjaman</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($laporanPeminjaman as $item): ?>
                            <tr>
                                <td class="border border-gray-300"><?= $item['PEMINJAMAN_ID'] ?></td>
                                <td class="border border-gray-300"><?= $item['NAMA_LENGKAP'] ?></td>
                                <td class="border border-gray-300"><?= date('d/m/Y', strtotime($item['TANGGAL_PINJAM'])) ?></td>
                                <td class="border border-gray-300"><?= $item['TANGGAL_KEMBALI'] ? date('d/m/Y', strtotime($item['TANGGAL_KEMBALI'])) : '-' ?></td>
                                <td class="border border-gray-300">
                                    <span class="status-badge 
                                        <?php 
                                        switch($item['STATUS_PEMINJAMAN']) {
                                            case 'Selesai': echo 'status-lunas'; break;
                                            case 'Sedang Dipinjam': echo 'status-menunggu'; break;
                                            case 'Ditolak': echo 'status-gagal'; break;
                                            default: echo 'status-menunggu'; break;
                                        }
                                        ?>">
                                        <?= $item['STATUS_PEMINJAMAN'] ?>
                                    </span>
                                </td>
                                <td class="border border-gray-300">
                                    <span class="status-badge 
                                        <?php 
                                        $status = $item['STATUS_PEMBAYARAN'] ?? 'Belum Ada';
                                        switch($status) {
                                            case 'Lunas': echo 'status-lunas'; break;
                                            case 'Menunggu': echo 'status-menunggu'; break;
                                            case 'Gagal': echo 'status-gagal'; break;
                                            default: echo 'status-menunggu'; break;
                                        }
                                        ?>">
                                        <?= $status ?>
                                    </span>
                                </td>
                                <td class="border border-gray-300 text-right">Rp <?= number_format($item['TOTAL_BIAYA'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Laporan Pendapatan Bulanan untuk Print -->
            <div class="mb-8">
                <h2 class="text-xl font-bold mb-3">2. Laporan Pendapatan Bulanan</h2>
                <table class="w-full border-collapse border border-gray-300 table-compact">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300">Tahun</th>
                            <th class="border border-gray-300">Bulan</th>
                            <th class="border border-gray-300">Jumlah Peminjaman</th>
                            <th class="border border-gray-300">Total Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($laporanPendapatan)): ?>
                            <tr>
                                <td colspan="4" class="border border-gray-300 text-center py-2">Tidak ada data pendapatan</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($laporanPendapatan as $item): ?>
                            <tr>
                                <td class="border border-gray-300"><?= $item['TAHUN'] ?></td>
                                <td class="border border-gray-300"><?= getNamaBulan((int)$item['BULAN']) ?></td>
                                <td class="border border-gray-300 text-center"><?= $item['JUMLAH_PEMINJAMAN'] ?></td>
                                <td class="border border-gray-300 text-right">Rp <?= number_format($item['TOTAL_PENDAPATAN'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="bg-gray-100 font-bold">
                                <td colspan="3" class="border border-gray-300">Total</td>
                                <td class="border border-gray-300 text-right">
                                    Rp <?= number_format(array_sum(array_column($laporanPendapatan, 'TOTAL_PENDAPATAN')), 0, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Laporan Alat Populer untuk Print -->
            <div class="mb-8">
                <h2 class="text-xl font-bold mb-3">3. Alat Pendaki Paling Populer</h2>
                <table class="w-full border-collapse border border-gray-300 table-compact">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300">ID</th>
                            <th class="border border-gray-300">Nama Alat</th>
                            <th class="border border-gray-300">Kategori</th>
                            <th class="border border-gray-300">Jml Peminjaman</th>
                            <th class="border border-gray-300">Unit Dipinjam</th>
                            <th class="border border-gray-300">Total Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($laporanAlatPopuler)): ?>
                            <tr>
                                <td colspan="6" class="border border-gray-300 text-center py-2">Tidak ada data alat populer</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($laporanAlatPopuler as $item): ?>
                            <tr>
                                <td class="border border-gray-300"><?= $item['ALAT_ID'] ?></td>
                                <td class="border border-gray-300"><?= $item['NAMA_ALAT'] ?></td>
                                <td class="border border-gray-300"><?= $item['NAMA_KATEGORI'] ?></td>
                                <td class="border border-gray-300 text-center"><?= $item['JUMLAH_PEMINJAMAN'] ?></td>
                                <td class="border border-gray-300 text-center"><?= $item['TOTAL_UNIT_DIPINJAM'] ?></td>
                                <td class="border border-gray-300 text-right">Rp <?= number_format($item['TOTAL_PENDAPATAN'], 0, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Footer for Print -->
            <div class="text-center text-gray-500 text-sm mt-8">
                <p>Laporan ini dicetak oleh sistem peminjaman alat pendaki.</p>
                <p>© <?= date('Y') ?> - Peminjaman Alat Pendaki</p>
            </div>
        </div>
    </div>

    <!-- JavaScript for Interaction -->
    <script>
        // Function to handle print action
        document.addEventListener('DOMContentLoaded', function() {
            // Simple animation for stats cards
            const statCards = document.querySelectorAll('.mini-stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('animate-fade-in');
                }, index * 100);
            });
        });
    </script>
</body>
</html>