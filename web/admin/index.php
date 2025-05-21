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

        // Total Peminjaman Aktif
        $query = "SELECT COUNT(*) AS peminjaman_aktif 
                  FROM peminjaman 
                  WHERE status_peminjaman = 'Sedang Dipinjam'";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $statistik['peminjaman_aktif'] = $row['PEMINJAMAN_AKTIF'];

        // Total Pembayaran Bulan Ini
        $query = "SELECT COUNT(*) AS total_pembayaran 
                  FROM pembayaran 
                  WHERE EXTRACT(MONTH FROM tanggal_pembayaran) = EXTRACT(MONTH FROM SYSDATE)
                  AND EXTRACT(YEAR FROM tanggal_pembayaran) = EXTRACT(YEAR FROM SYSDATE)";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $statistik['total_pembayaran'] = $row['TOTAL_PEMBAYARAN'];

        return $statistik;
    }

    public function getPeminjamanTerakhir($limit = 5) {
        $query = "SELECT p.PEMINJAMAN_ID, u.NAMA_LENGKAP, p.TANGGAL_PINJAM, p.TANGGAL_KEMBALI, p.STATUS_PEMINJAMAN, p.TOTAL_BIAYA
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
                'TANGGAL_KEMBALI' => $row['TANGGAL_KEMBALI'],
                'STATUS_PEMINJAMAN' => $row['STATUS_PEMINJAMAN'],
                'TOTAL_BIAYA' => $row['TOTAL_BIAYA']
            ];
        }
    
        return $peminjaman;
    }

    public function getPembayaranTerakhir($limit = 5) {
        $query = "SELECT pb.PEMBAYARAN_ID, u.NAMA_LENGKAP, pb.JUMLAH_PEMBAYARAN, pb.TANGGAL_PEMBAYARAN, 
                         pb.METODE_PEMBAYARAN, pb.STATUS_PEMBAYARAN, p.PEMINJAMAN_ID
                  FROM pembayaran pb
                  JOIN peminjaman p ON pb.PEMINJAMAN_ID = p.PEMINJAMAN_ID
                  JOIN users u ON p.USER_ID = u.USER_ID
                  ORDER BY pb.TANGGAL_PEMBAYARAN DESC
                  FETCH FIRST :limit ROWS ONLY";
    
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':limit', $limit, -1, SQLT_INT);
        oci_execute($stmt);
    
        $pembayaran = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $pembayaran[] = [
                'PEMBAYARAN_ID' => $row['PEMBAYARAN_ID'],
                'PEMINJAMAN_ID' => $row['PEMINJAMAN_ID'],
                'NAMA_LENGKAP' => $row['NAMA_LENGKAP'],
                'JUMLAH_PEMBAYARAN' => $row['JUMLAH_PEMBAYARAN'],
                'TANGGAL_PEMBAYARAN' => $row['TANGGAL_PEMBAYARAN'],
                'METODE_PEMBAYARAN' => $row['METODE_PEMBAYARAN'],
                'STATUS_PEMBAYARAN' => $row['STATUS_PEMBAYARAN']
            ];
        }
    
        return $pembayaran;
    }
}

$database = new Database();
$adminDashboard = new AdminDashboard($database);
$statistik = $adminDashboard->getStatistik();
$peminjamanTerakhir = $adminDashboard->getPeminjamanTerakhir();
$pembayaranTerakhir = $adminDashboard->getPembayaranTerakhir();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Peminjaman Alat Pendaki</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .stats-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .mountain-bg {
            background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MDAiIGhlaWdodD0iMTAwIiB2aWV3Qm94PSIwIDAgNDAwIDEwMCIgZmlsbD0ibm9uZSI+PHBhdGggZD0iTTAgMTAwaDQwMFY2MEwzNzAgNzVMMzIwIDMwTDI3MCA3MEwyMzAgNDBMMTkwIDYwTDE1MCA1MEwxMDAgODBMNTAgNjBMMCAzMFYxMDBaIiBmaWxsPSJyZ2JhKDI1NSwgMjU1LCAyNTUsIDAuMSkiLz48L3N2Zz4=');
            background-repeat: no-repeat;
            background-position: bottom;
            background-size: contain;
        }
        .gradient-heading {
            background: linear-gradient(to right, #374151, #4B5563);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .pulse-dot {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7);
            }
            
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 10px rgba(220, 38, 38, 0);
            }
            
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0);
            }
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c5c5c5;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #a0a0a0;
        }
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            width: 2px;
            height: 100%;
            background: #e5e7eb;
            z-index: 1;
        }
        .activity-item {
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content with responsive margin -->
    <div class="ml-0 md:ml-64 p-4 md:p-8 pt-16 md:pt-8 transition-all duration-300">
        <div class="container mx-auto">
            <!-- Dashboard Header with Background & User Info -->
            <div class="bg-gradient-to-r from-gray-800 to-gray-800 rounded-xl shadow-lg p-6 md:p-8 mb-8 text-white relative overflow-hidden">
                <div class="absolute inset-0 mountain-bg opacity-20"></div>
                <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold mb-2">Dashboard Admin</h1>
                        <p class="text-blue-100">Selamat datang kembali, <span class="font-semibold"><?= $_SESSION['username'] ?></span></p>
                    </div>
                    <div class="mt-4 md:mt-0 flex items-center">
                        <div class="bg-white bg-opacity-20 rounded-full p-2 mr-3">
                            <i class="fas fa-mountain text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-blue-100">Hari ini</p>
                            <p class="font-medium"><?= date('d F Y') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Grid - Enhanced & Responsive -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
                <!-- Total Alat -->
                <div class="stats-card bg-white shadow-md overflow-hidden">
                    <div class="p-4 md:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-gray-600 text-sm md:text-base font-medium">Total Alat</h3>
                            <div class="bg-blue-100 text-blue-600 p-2 rounded-full">
                                <i class="fas fa-toolbox"></i>
                            </div>
                        </div>
                        <div class="flex items-end">
                            <div class="text-2xl md:text-3xl font-bold text-gray-800"><?= $statistik['total_alat'] ?></div>
                            <span class="text-sm text-gray-500 ml-2 mb-1">item</span>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">Semua peralatan yang terdaftar</div>
                    </div>
                    <div class="h-1 bg-blue-800"></div>
                </div>
                
                <!-- Alat Tersedia -->
                <div class="stats-card bg-white shadow-md overflow-hidden">
                    <div class="p-4 md:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-gray-600 text-sm md:text-base font-medium">Alat Tersedia</h3>
                            <div class="bg-green-100 text-green-600 p-2 rounded-full">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                        <div class="flex items-end">
                            <div class="text-2xl md:text-3xl font-bold text-gray-800"><?= $statistik['alat_tersedia'] ?></div>
                            <span class="text-sm text-gray-500 ml-2 mb-1">item</span>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">Siap untuk dipinjam</div>
                    </div>
                    <div class="h-1 bg-green-800"></div>
                </div>

                <!-- Peminjaman Aktif -->
                <div class="stats-card bg-white shadow-md overflow-hidden">
                    <div class="p-4 md:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-gray-600 text-sm md:text-base font-medium">Peminjaman Aktif</h3>
                            <div class="bg-purple-100 text-purple-600 p-2 rounded-full">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                        </div>
                        <div class="flex items-end">
                            <div class="text-2xl md:text-3xl font-bold text-gray-800"><?= $statistik['peminjaman_aktif'] ?></div>
                            <span class="text-sm text-gray-500 ml-2 mb-1">transaksi</span>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">Sedang dipinjam</div>
                    </div>
                    <div class="h-1 bg-purple-800"></div>
                </div>

                <!-- Pembayaran Bulan Ini -->
                <div class="stats-card bg-white shadow-md overflow-hidden">
                    <div class="p-4 md:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-gray-600 text-sm md:text-base font-medium">Pembayaran Bulan Ini</h3>
                            <div class="bg-yellow-100 text-yellow-600 p-2 rounded-full">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                        <div class="flex items-end">
                            <div class="text-2xl md:text-3xl font-bold text-gray-800"><?= $statistik['total_pembayaran'] ?></div>
                            <span class="text-sm text-gray-500 ml-2 mb-1">transaksi</span>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">Periode <?= date('F Y') ?></div>
                    </div>
                    <div class="h-1 bg-yellow-800"></div>
                </div>
            </div>

            <!-- Grid Layout for Dashboard Content -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Recent Loans Table - Responsive -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden lg:col-span-2">
                    <div class="border-b px-6 py-4 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-800">Peminjaman Terakhir</h2>
                        <a href="manajemen_peminjaman.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                            Lihat Semua <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                    
                    <!-- Mobile Table (Card View) -->
                    <div class="md:hidden p-4">
                        <div class="space-y-4">
                            <?php foreach($peminjamanTerakhir as $index => $peminjaman): ?>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 hover:border-blue-300 transition-colors">
                                <div class="flex justify-between items-start mb-3">
                                    <span class="bg-gray-200 text-gray-700 px-2 py-1 rounded text-xs">#<?= $peminjaman['PEMINJAMAN_ID'] ?></span>
                                    <span class="<?= 
                                        $peminjaman['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam' ? 'bg-blue-100 text-blue-800 border border-blue-300' : 'bg-green-100 text-green-800 border border-green-300'
                                    ?> px-2 py-1 rounded-full text-xs flex items-center">
                                        <i class="<?= 
                                            $peminjaman['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam' ? 'fas fa-sync-alt mr-1' : 'fas fa-check mr-1'
                                        ?>"></i>
                                        <?= $peminjaman['STATUS_PEMINJAMAN'] ?>
                                    </span>
                                </div>
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mr-2">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="text-sm text-gray-800 font-medium"><?= $peminjaman['NAMA_LENGKAP'] ?></div>
                                </div>
                                <div class="flex items-center text-xs text-gray-600 mb-1">
                                    <i class="fas fa-calendar-plus mr-1"></i>
                                    Pinjam: <?= date('d M Y', strtotime($peminjaman['TANGGAL_PINJAM'])) ?>
                                </div>
                                <?php if($peminjaman['TANGGAL_KEMBALI']): ?>
                                <div class="flex items-center text-xs text-gray-600 mb-1">
                                    <i class="fas fa-calendar-minus mr-1"></i>
                                    Kembali: <?= date('d M Y', strtotime($peminjaman['TANGGAL_KEMBALI'])) ?>
                                </div>
                                <?php endif; ?>
                                <div class="flex items-center text-xs text-gray-600 font-medium mt-2">
                                    <i class="fas fa-money-bill mr-1"></i>
                                    Rp <?= number_format($peminjaman['TOTAL_BIAYA'], 0, ',', '.') ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Desktop Table -->
                    <div class="hidden md:block p-4 custom-scrollbar overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-gray-600 border-b">
                                    <th class="py-3 px-4 text-left font-semibold">ID</th>
                                    <th class="py-3 px-4 text-left font-semibold">Nama Peminjam</th>
                                    <th class="py-3 px-4 text-left font-semibold">Tanggal Pinjam</th>
                                    <th class="py-3 px-4 text-left font-semibold">Status</th>
                                    <th class="py-3 px-4 text-right font-semibold">Total Biaya</th>
                                    <th class="py-3 px-4 text-center font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($peminjamanTerakhir as $index => $peminjaman): ?>
                            <tr class="<?= $index % 2 == 0 ? 'bg-white' : 'bg-gray-50' ?> hover:bg-blue-50 transition-colors">
                                <td class="py-3 px-4 font-medium"><?= $peminjaman['PEMINJAMAN_ID'] ?></td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mr-2">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <?= $peminjaman['NAMA_LENGKAP'] ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar text-gray-500 mr-2"></i>
                                        <?= date('d M Y', strtotime($peminjaman['TANGGAL_PINJAM'])) ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="<?= 
                                        $peminjaman['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam' ? 'bg-blue-100 text-blue-800 border border-blue-300' : 'bg-green-100 text-green-800 border border-green-300'
                                    ?> px-2 py-1 rounded-full text-xs inline-flex items-center">
                                        <i class="<?= 
                                            $peminjaman['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam' ? 'fas fa-sync-alt mr-1' : 'fas fa-check mr-1'
                                        ?>"></i>
                                        <?= $peminjaman['STATUS_PEMINJAMAN'] ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right font-medium">
                                    Rp <?= number_format($peminjaman['TOTAL_BIAYA'], 0, ',', '.') ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <a href="manajemen_peminjaman.php?id=<?= $peminjaman['PEMINJAMAN_ID'] ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs transition-colors inline-flex items-center justify-center">
                                        <i class="fas fa-eye mr-1"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Payments Widget -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="border-b px-6 py-4 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-800">Pembayaran Terakhir</h2>
                        <a href="manajemen_pembayaran.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                            Lihat Semua <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                    <div class="p-4">
                        <div class="space-y-3">
                            <?php foreach($pembayaranTerakhir as $pembayaran): ?>
                            <div class="p-3 border border-gray-200 hover:border-blue-300 rounded-lg flex items-center justify-between hover:bg-blue-50 transition-colors">
                                <div class="flex items-center space-x-3">
                                    <div class="<?= 
                                        $pembayaran['STATUS_PEMBAYARAN'] == 'DP' ? 'bg-yellow-100 text-yellow-600' : 'bg-green-100 text-green-600'
                                    ?> p-2 rounded-full">
                                        <i class="<?= $pembayaran['STATUS_PEMBAYARAN'] == 'DP' ? 'fas fa-hourglass-half' : 'fas fa-check' ?>"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-800">#<?= $pembayaran['PEMINJAMAN_ID'] ?> - <?= $pembayaran['NAMA_LENGKAP'] ?></div>
                                        <div class="text-xs text-gray-500"><?= date('d/m/Y', strtotime($pembayaran['TANGGAL_PEMBAYARAN'])) ?> via <?= $pembayaran['METODE_PEMBAYARAN'] ?></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-gray-800">Rp <?= number_format($pembayaran['JUMLAH_PEMBAYARAN'], 0, ',', '.') ?></div>
                                    <span class="text-xs <?= $pembayaran['STATUS_PEMBAYARAN'] == 'DP' ? 'text-yellow-600' : 'text-green-600' ?> font-medium"><?= $pembayaran['STATUS_PEMBAYARAN'] ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Access Buttons -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6">
                <a href="manajemen_alat.php" class="bg-white rounded-xl shadow-md p-4 flex flex-col items-center justify-center text-center hover:bg-blue-50 transition-colors border border-gray-200 hover:border-blue-300">
                    <div class="bg-blue-100 text-blue-600 p-3 rounded-full mb-3">
                        <i class="fas fa-toolbox"></i>
                    </div>
                    <h3 class="text-sm font-medium text-gray-800">Manajemen Alat</h3>
                </a>
                
                <a href="manajemen_peminjaman.php" class="bg-white rounded-xl shadow-md p-4 flex flex-col items-center justify-center text-center hover:bg-green-50 transition-colors border border-gray-200 hover:border-green-300">
                    <div class="bg-green-100 text-green-600 p-3 rounded-full mb-3">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3 class="text-sm font-medium text-gray-800">Manajemen Peminjaman</h3>
                </a>
                
                <a href="manajemen_pembayaran.php" class="bg-white rounded-xl shadow-md p-4 flex flex-col items-center justify-center text-center hover:bg-yellow-50 transition-colors border border-gray-200 hover:border-yellow-300">
                    <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full mb-3">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3 class="text-sm font-medium text-gray-800">Manajemen Pembayaran</h3>
                </a>
                
                <a href="laporan.php" class="bg-white rounded-xl shadow-md p-4 flex flex-col items-center justify-center text-center hover:bg-indigo-50 transition-colors border border-gray-200 hover:border-indigo-300">
                    <div class="bg-indigo-100 text-indigo-600 p-3 rounded-full mb-3">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3 class="text-sm font-medium text-gray-800">Laporan</h3>
                </a>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>

    <script>
    // Animation for statistics cards
    document.addEventListener('DOMContentLoaded', function() {
        const statsCards = document.querySelectorAll('.stats-card');
        
        statsCards.forEach((card, index) => {
            setTimeout(() => {
                card.classList.add('animate-fadeIn');
            }, index * 100);
        });
    });

    function showDetailPeminjaman(peminjamanId) {
        fetch(`ajax_detail_peminjaman.php?id=${peminjamanId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('detail-peminjaman-content').innerHTML = html;
                const modal = document.getElementById('modal-detail-peminjaman');
                const modalContent = document.getElementById('modal-content');
                
                modal.classList.remove('hidden');
                
                // Animation
                setTimeout(() => {
                    modalContent.classList.remove('scale-95', 'opacity-0');
                    modalContent.classList.add('scale-100', 'opacity-100');
                }, 10);
            });
    }

    function closeDetailModal() {
        const modal = document.getElementById('modal-detail-peminjaman');
        const modalContent = document.getElementById('modal-content');
        
        // Animation
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // Close modal when clicking outside
    document.getElementById('modal-detail-peminjaman').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailModal();
        }
    });
    </script>
</body>
</html>