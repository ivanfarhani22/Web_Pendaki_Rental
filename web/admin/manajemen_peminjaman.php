<?php
session_start();
require_once '../config/database.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

class ManajemenPeminjaman {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function getDaftarPeminjaman($status_filter = null) {
        $query = "SELECT p.*, 
                         u.NAMA_LENGKAP,
                         NVL(p.TANGGAL_KEMBALI, 
                             (SELECT MAX(dp.TANGGAL_SELESAI) 
                              FROM detail_peminjaman dp 
                              WHERE dp.PEMINJAMAN_ID = p.PEMINJAMAN_ID)) AS TANGGAL_KEMBALI_FINAL
                  FROM peminjaman p
                  JOIN users u ON p.USER_ID = u.USER_ID";
        
        // Modify query to handle both null and specific status filters
        if ($status_filter !== null) {
            // If a specific status is provided, add WHERE clause
            $query .= " WHERE p.STATUS_PEMINJAMAN = :status";
        }

        $query .= " ORDER BY p.TANGGAL_PINJAM DESC";

        $stmt = oci_parse($this->conn, $query);
        
        if ($status_filter !== null) {
            oci_bind_by_name($stmt, ':status', $status_filter);
        }

        oci_execute($stmt);

        $daftarPeminjaman = [];
        while ($row = oci_fetch_assoc($stmt)) {
            // Rename the computed column 
            $row['TANGGAL_KEMBALI'] = $row['TANGGAL_KEMBALI_FINAL'];
            $daftarPeminjaman[] = $row;
        }
        return $daftarPeminjaman;
    }

    public function getDetailPeminjaman($peminjamanId) {
        $query = "SELECT p.*, 
                         u.NAMA_LENGKAP, 
                         u.EMAIL, 
                         u.NOMOR_TELEPON,
                         LISTAGG(a.NAMA_ALAT, ', ') WITHIN GROUP (ORDER BY a.NAMA_ALAT) AS DAFTAR_ALAT,
                         MAX(dp.TANGGAL_SELESAI) AS TANGGAL_SELESAI_DETAIL,
                         LISTAGG(dp.ALAT_ID, ',') WITHIN GROUP (ORDER BY a.NAMA_ALAT) AS ALAT_IDS,
                         LISTAGG(dp.JUMLAH_PINJAM, ',') WITHIN GROUP (ORDER BY a.NAMA_ALAT) AS JUMLAH_PINJAMS
                  FROM peminjaman p
                  JOIN users u ON p.USER_ID = u.USER_ID
                  JOIN detail_peminjaman dp ON p.PEMINJAMAN_ID = dp.PEMINJAMAN_ID
                  JOIN alat_mendaki a ON dp.ALAT_ID = a.ALAT_ID
                  WHERE p.PEMINJAMAN_ID = :peminjaman_id
                  GROUP BY p.PEMINJAMAN_ID, u.NAMA_LENGKAP, u.EMAIL, u.NOMOR_TELEPON, 
                           p.PEMINJAMAN_ID, p.USER_ID, p.TANGGAL_PINJAM, p.STATUS_PEMINJAMAN, 
                           p.TOTAL_BIAYA, p.TANGGAL_KEMBALI";

        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':peminjaman_id', $peminjamanId);
        oci_execute($stmt);

        $detail = oci_fetch_assoc($stmt);

        // If tanggal_kembali is null, use the tanggal_selesai from detail_peminjaman
        if (empty($detail['TANGGAL_KEMBALI']) && !empty($detail['TANGGAL_SELESAI_DETAIL'])) {
            $detail['TANGGAL_KEMBALI'] = $detail['TANGGAL_SELESAI_DETAIL'];
        }

        return $detail;
    }

    public function updateStatusPeminjaman($peminjamanId, $status) {
        try {
            // Validate status first - only allow valid statuses defined in the constraint
            $valid_statuses = ['Sedang Dipinjam', 'Selesai'];
            if (!in_array($status, $valid_statuses)) {
                throw new Exception("Status tidak valid. Status harus berupa: " . implode(", ", $valid_statuses));
            }
            
            // Get current status and alat info before updating
            $query_detail = "SELECT p.STATUS_PEMINJAMAN, dp.ALAT_ID, dp.JUMLAH_PINJAM 
                          FROM peminjaman p
                          JOIN detail_peminjaman dp ON p.PEMINJAMAN_ID = dp.PEMINJAMAN_ID
                          WHERE p.PEMINJAMAN_ID = :peminjaman_id";
            $stmt_detail = oci_parse($this->conn, $query_detail);
            oci_bind_by_name($stmt_detail, ':peminjaman_id', $peminjamanId);
            $exec_detail = oci_execute($stmt_detail, OCI_DEFAULT); // Use OCI_DEFAULT to not auto-commit
            
            if (!$exec_detail) {
                $error = oci_error($stmt_detail);
                throw new Exception("Error fetching peminjaman details: " . $error['message']);
            }
            
            $details = [];
            $current_status = null;
            
            while ($row = oci_fetch_assoc($stmt_detail)) {
                $current_status = $row['STATUS_PEMINJAMAN'];
                $details[] = [
                    'alat_id' => $row['ALAT_ID'],
                    'jumlah' => $row['JUMLAH_PINJAM']
                ];
            }
            
            // Make sure we found the peminjaman
            if ($current_status === null) {
                throw new Exception("Peminjaman ID tidak ditemukan");
            }
            
            // Debug to check the data
            error_log("Current status: " . $current_status . ", New status: " . $status);
            error_log("Number of details: " . count($details));
            
            // Update peminjaman status
            $query = "UPDATE peminjaman SET STATUS_PEMINJAMAN = :status";
            
            // If status is "Selesai", set the return date to today
            if ($status === 'Selesai') {
                $query .= ", TANGGAL_KEMBALI = SYSDATE";
            }
            
            $query .= " WHERE PEMINJAMAN_ID = :peminjaman_id";
            
            $stmt = oci_parse($this->conn, $query);
            oci_bind_by_name($stmt, ':status', $status);
            oci_bind_by_name($stmt, ':peminjaman_id', $peminjamanId);
            $result = oci_execute($stmt, OCI_DEFAULT); // Use OCI_DEFAULT to not auto-commit
            
            if (!$result) {
                $error = oci_error($stmt);
                throw new Exception("Error updating peminjaman status: " . $error['message']);
            }
            
            // Update stock if changing from "Sedang Dipinjam" to "Selesai"
            if ($status === 'Selesai' && $current_status !== 'Selesai' && !empty($details)) {
                foreach ($details as $detail) {
                    $query_update_stock = "UPDATE alat_mendaki 
                                        SET jumlah_tersedia = jumlah_tersedia + :jumlah 
                                        WHERE alat_id = :alat_id";
                    $stmt_update_stock = oci_parse($this->conn, $query_update_stock);
                    oci_bind_by_name($stmt_update_stock, ':jumlah', $detail['jumlah']);
                    oci_bind_by_name($stmt_update_stock, ':alat_id', $detail['alat_id']);
                    $exec_stock = oci_execute($stmt_update_stock, OCI_DEFAULT);
                    
                    if (!$exec_stock) {
                        $error = oci_error($stmt_update_stock);
                        throw new Exception("Error updating stock: " . $error['message']);
                    }
                }
            }
            
            // Commit transaction if all operations successful
            $commit = oci_commit($this->conn);
            if (!$commit) {
                $error = oci_error($this->conn);
                throw new Exception("Error committing transaction: " . $error['message']);
            }
            
            return [
                'result' => true,
                'pesan' => 'Status peminjaman berhasil diupdate menjadi ' . $status
            ];
        } catch (Exception $e) {
            // Rollback transaction if there's an error
            oci_rollback($this->conn);
            return [
                'result' => false,
                'pesan' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    // Fungsi untuk mengecek dan update stok alat berdasarkan tanggal pengembalian
    public function checkAndUpdateExpiredRentals() {
        $query = "SELECT p.PEMINJAMAN_ID, dp.ALAT_ID, dp.JUMLAH_PINJAM
                 FROM peminjaman p
                 JOIN detail_peminjaman dp ON p.PEMINJAMAN_ID = dp.PEMINJAMAN_ID
                 WHERE p.STATUS_PEMINJAMAN = 'Sedang Dipinjam'
                 AND dp.TANGGAL_SELESAI < TRUNC(SYSDATE)
                 AND p.TANGGAL_KEMBALI IS NULL";
                 
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        
        $updated = 0;
        
        while ($row = oci_fetch_assoc($stmt)) {
            // Update peminjaman status to completed
            $this->updateStatusPeminjaman($row['PEMINJAMAN_ID'], 'Selesai');
            $updated++;
        }
        
        return $updated;
    }
}

$database = new Database();
$manajemenPeminjaman = new ManajemenPeminjaman($database);

// Cek dan update peminjaman yang sudah melewati tanggal pengembalian
$expired_updated = $manajemenPeminjaman->checkAndUpdateExpiredRentals();

// Proses filter status
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
// Proses update status jika ada form submit
$result = false;
$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $response = $manajemenPeminjaman->updateStatusPeminjaman(
        $_POST['peminjaman_id'], 
        $_POST['status_baru']
    );
    $result = $response['result'];
    $pesan = $response['pesan'];
}

$daftarPeminjaman = $manajemenPeminjaman->getDaftarPeminjaman($status_filter);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Peminjaman - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .gradient-header {
            background: linear-gradient(to right,rgb(31, 41, 55),rgb(31, 41, 55));
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .status-pill {
            transition: all 0.2s ease;
        }
        .status-pill:hover {
            transform: scale(1.05);
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
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
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-0 md:ml-64 p-4 md:p-8 pt-16 md:pt-8 transition-all duration-300">
        <div class="container mx-auto">
            <!-- Header with gradient background -->
            <div class="gradient-header rounded-lg shadow-lg p-6 mb-6 text-white flex justify-between items-center">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold mb-1">Manajemen Peminjaman</h1>
                    <p class="text-blue-100 text-sm lg:text-base">Kelola semua aktivitas peminjaman dengan mudah</p>
                </div>
                <div class="hidden md:block">
                    <i class="fas fa-book-reader text-4xl text-blue-100 opacity-80"></i>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if(!empty($pesan)): ?>
                <div class="<?= $result ? 'bg-green-100 text-green-800 border-l-4 border-green-500' : 'bg-red-100 text-red-800 border-l-4 border-red-500' ?> p-4 rounded shadow-md mb-6 flex items-center text-sm lg:text-base animate-fade-in">
                    <i class="<?= $result ? 'fas fa-check-circle' : 'fas fa-exclamation-circle' ?> mr-3 text-xl"></i>
                    <?= $pesan ?>
                </div>
            <?php endif; ?>
            
            <?php if($expired_updated > 0): ?>
                <div class="bg-blue-100 text-blue-800 border-l-4 border-blue-500 p-4 rounded shadow-md mb-6 flex items-center text-sm lg:text-base">
                    <i class="fas fa-info-circle mr-3 text-xl"></i>
                    <?= $expired_updated ?> peminjaman yang melewati tanggal pengembalian telah otomatis diupdate menjadi Selesai.
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Total Peminjaman Card -->
                <div class="bg-white rounded-lg shadow-md p-5 card-hover border-t-4 border-blue-800">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-gray-500 text-sm font-medium mb-1">Total Peminjaman</h3>
                            <p class="text-2xl font-bold text-gray-800"><?= count($daftarPeminjaman) ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-book text-blue-600"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Sedang Dipinjam Card -->
                <div class="bg-white rounded-lg shadow-md p-5 card-hover border-t-4 border-green-800">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-gray-500 text-sm font-medium mb-1">Sedang Dipinjam</h3>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php 
                                $count = 0;
                                foreach($daftarPeminjaman as $p) {
                                    if($p['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam') $count++;
                                }
                                echo $count;
                                ?>
                            </p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-clock text-green-600"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Selesai Card -->
                <div class="bg-white rounded-lg shadow-md p-5 card-hover border-t-4 border-indigo-800">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-gray-500 text-sm font-medium mb-1">Telah Selesai</h3>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php 
                                $count = 0;
                                foreach($daftarPeminjaman as $p) {
                                    if($p['STATUS_PEMINJAMAN'] == 'Selesai') $count++;
                                }
                                echo $count;
                                ?>
                            </p>
                        </div>
                        <div class="bg-indigo-100 p-3 rounded-full">
                            <i class="fas fa-check-circle text-indigo-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Status - Responsive -->
            <div class="mb-6">
                <?php 
                $statuses = [
                    null => 'Semua',
                    'Sedang Dipinjam' => 'Sedang Dipinjam',
                    'Selesai' => 'Selesai'
                ];
                ?>
                <!-- Mobile: Dropdown with custom styling -->
                <div class="block lg:hidden mb-4">
                    <div class="relative">
                        <select id="mobile-filter" class="w-full p-3 border border-gray-300 rounded-lg bg-white text-gray-700 appearance-none pr-10 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" onchange="window.location.href = this.value">
                            <?php foreach($statuses as $status => $label): ?>
                                <option value="?status=<?= $status ?? '' ?>" <?= $status_filter === $status ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                            <i class="fas fa-chevron-down text-gray-500"></i>
                        </div>
                    </div>
                </div>

                <!-- Desktop: Buttons with improved styling -->
                <div class="hidden lg:flex space-x-3">
                    <?php foreach($statuses as $status => $label): ?>
                        <a href="?status=<?= $status ?? '' ?>" 
                           class="px-5 py-2.5 rounded-md transition flex items-center space-x-2 <?= 
                               $status_filter === $status ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300'
                           ?>">
                            <i class="fas <?= 
                                $label == 'Semua' ? 'fa-list' : 
                                ($label == 'Sedang Dipinjam' ? 'fa-clock' : 'fa-check-circle') 
                            ?>"></i>
                            <span><?= $label ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Table Container with improved styling -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200">
                <!-- Table Header -->
                <div class="p-5 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-800">Daftar Peminjaman</h2>
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-calendar-alt mr-1"></i> <?= date('d M Y') ?>
                    </div>
                </div>
                
                <!-- Desktop Table with improved styling -->
                <div class="hidden lg:block overflow-x-auto custom-scrollbar">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 text-gray-600">
                                <th class="py-4 px-6 text-left font-semibold">ID Peminjaman</th>
                                <th class="py-4 px-6 text-left font-semibold">Nama Peminjam</th>
                                <th class="py-4 px-6 text-left font-semibold">Tanggal Pinjam</th>
                                <th class="py-4 px-6 text-left font-semibold">Tanggal Kembali</th>
                                <th class="py-4 px-6 text-left font-semibold">Status</th>
                                <th class="py-4 px-6 text-center font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($daftarPeminjaman as $index => $peminjaman): ?>
                            <tr class="<?= $index % 2 == 0 ? 'bg-white' : 'bg-gray-50' ?> hover:bg-blue-50 transition-colors">
                                <td class="py-4 px-6 font-medium"><?= $peminjaman['PEMINJAMAN_ID'] ?></td>
                                <td class="py-4 px-6"><?= $peminjaman['NAMA_LENGKAP'] ?></td>
                                <td class="py-4 px-6">
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar-plus text-blue-500 mr-2"></i>
                                        <?= date('d M Y', strtotime($peminjaman['TANGGAL_PINJAM'])) ?>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <?php if(!empty($peminjaman['TANGGAL_KEMBALI'])): ?>
                                    <div class="flex items-center">
                                        <i class="fas fa-calendar-check text-green-500 mr-2"></i>
                                        <?= date('d M Y', strtotime($peminjaman['TANGGAL_KEMBALI'])) ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="<?= 
                                        $peminjaman['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam' ? 'bg-green-100 text-green-800' : 
                                        'bg-blue-100 text-blue-800'
                                    ?> px-3 py-1.5 rounded-full text-xs font-medium inline-flex items-center status-pill">
                                        <i class="<?= $peminjaman['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam' ? 'fas fa-clock' : 'fas fa-check-circle' ?> mr-1"></i>
                                        <?= $peminjaman['STATUS_PEMINJAMAN'] ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <button onclick="showDetailPeminjaman(<?= $peminjaman['PEMINJAMAN_ID'] ?>)" 
                                            class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition flex items-center mx-auto">
                                        <i class="fas fa-eye mr-1.5"></i> Detail
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards with improved styling -->
                <div class="lg:hidden divide-y divide-gray-200">
                    <?php foreach($daftarPeminjaman as $peminjaman): ?>
                    <div class="p-4 card-hover">
                        <div class="flex flex-col space-y-4">
                            <!-- Header dengan ID dan Status -->
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="flex items-center">
                                        <i class="fas fa-bookmark text-blue-500 mr-2"></i>
                                        <p class="text-sm text-gray-500">ID Peminjaman</p>
                                    </div>
                                    <p class="font-semibold text-gray-800 text-lg"><?= $peminjaman['PEMINJAMAN_ID'] ?></p>
                                </div>
                                <span class="<?= 
                                    $peminjaman['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam' ? 'bg-green-100 text-green-800' : 
                                    'bg-blue-100 text-blue-800'
                                ?> px-3 py-1.5 rounded-full text-xs font-medium inline-flex items-center status-pill">
                                    <i class="<?= $peminjaman['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam' ? 'fas fa-clock' : 'fas fa-check-circle' ?> mr-1"></i>
                                    <?= $peminjaman['STATUS_PEMINJAMAN'] ?>
                                </span>
                            </div>

                            <!-- Nama Peminjam -->
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <div class="flex items-center mb-1.5">
                                    <i class="fas fa-user text-indigo-500 mr-2"></i>
                                    <p class="text-sm text-gray-500">Nama Peminjam</p>
                                </div>
                                <p class="font-medium text-gray-800"><?= $peminjaman['NAMA_LENGKAP'] ?></p>
                            </div>

                            <!-- Tanggal dalam cards terpisah -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-blue-50 p-3 rounded-lg">
                                    <div class="flex items-center mb-1.5">
                                        <i class="fas fa-calendar-plus text-blue-500 mr-2"></i>
                                        <p class="text-xs text-gray-500">Tanggal Pinjam</p>
                                    </div>
                                    <p class="text-sm font-medium text-gray-800"><?= date('d M Y', strtotime($peminjaman['TANGGAL_PINJAM'])) ?></p>
                                </div>
                                <div class="bg-green-50 p-3 rounded-lg">
                                    <div class="flex items-center mb-1.5">
                                        <i class="fas fa-calendar-check text-green-500 mr-2"></i>
                                        <p class="text-xs text-gray-500">Tanggal Kembali</p>
                                    </div>
                                    <p class="text-sm font-medium text-gray-800"><?= !empty($peminjaman['TANGGAL_KEMBALI']) ? date('d M Y', strtotime($peminjaman['TANGGAL_KEMBALI'])) : '-' ?></p>
                                </div>
                            </div>

                            <!-- Button Detail -->
                            <div class="pt-2">
                                <button onclick="showDetailPeminjaman(<?= $peminjaman['PEMINJAMAN_ID'] ?>)" 
                                        class="w-full bg-blue-500 text-white py-3 px-4 rounded-lg hover:bg-blue-600 transition flex items-center justify-center shadow-md">
                                    <i class="fas fa-eye mr-2"></i>Lihat Detail Peminjaman
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Empty State -->
                <?php if(empty($daftarPeminjaman)): ?>
                <div class="p-8 text-center">
                    <div class="bg-gray-100 p-6 rounded-full inline-block mb-4">
                        <i class="fas fa-search text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-700 mb-2">Data Peminjaman Kosong</h3>
                    <p class="text-gray-500 max-w-md mx-auto">Tidak ada data peminjaman yang sesuai dengan filter yang dipilih.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination (jika dibutuhkan) -->
            <div class="mt-6 flex justify-center">
                <nav class="inline-flex rounded-md shadow">
                    <a href="#" class="py-2 px-4 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50 text-gray-500">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="#" class="py-2 px-4 bg-blue-600 border border-blue-600 text-white hover:bg-blue-700">1</a>
                    <a href="#" class="py-2 px-4 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700">2</a>
                    <a href="#" class="py-2 px-4 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700">3</a>
                    <a href="#" class="py-2 px-4 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50 text-gray-500">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </nav>
            </div>
        </div>

        <!-- Modal Detail Peminjaman -->
        <div id="modal-detail-peminjaman" 
             class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-auto transform transition-all duration-300 scale-95 opacity-0" id="modal-content">
                <div class="relative">
                    <div class="p-5 lg:p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                <i class="fas fa-clipboard-list text-blue-500 mr-2"></i>
                                Detail Peminjaman
                            </h2>
                            <button onclick="closeDetailModal()" 
                                    class="text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-100 transition">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                    </div>
                    <div id="detail-peminjaman-content" class="p-5 lg:p-6 max-h-[70vh] overflow-y-auto custom-scrollbar"></div>
                    <div class="p-5 lg:p-6 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>

    <script>
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