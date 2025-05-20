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
        // Modified method to avoid using oci_set_autocommit
        try {
            // Get current status and alat info before updating
            $query_detail = "SELECT p.STATUS_PEMINJAMAN, dp.ALAT_ID, dp.JUMLAH_PINJAM 
                          FROM peminjaman p
                          JOIN detail_peminjaman dp ON p.PEMINJAMAN_ID = dp.PEMINJAMAN_ID
                          WHERE p.PEMINJAMAN_ID = :peminjaman_id";
            $stmt_detail = oci_parse($this->conn, $query_detail);
            oci_bind_by_name($stmt_detail, ':peminjaman_id', $peminjamanId);
            oci_execute($stmt_detail);
            
            $details = [];
            $current_status = null;
            
            while ($row = oci_fetch_assoc($stmt_detail)) {
                $current_status = $row['STATUS_PEMINJAMAN'];
                $details[] = [
                    'alat_id' => $row['ALAT_ID'],
                    'jumlah' => $row['JUMLAH_PINJAM']
                ];
            }
            
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
            $result = oci_execute($stmt);
            
            // Update stock if changing from another status to "Selesai"
            if ($result && $status === 'Selesai' && $current_status !== 'Selesai') {
                foreach ($details as $detail) {
                    $query_update_stock = "UPDATE alat_mendaki 
                                        SET jumlah_tersedia = jumlah_tersedia + :jumlah 
                                        WHERE alat_id = :alat_id";
                    $stmt_update_stock = oci_parse($this->conn, $query_update_stock);
                    oci_bind_by_name($stmt_update_stock, ':jumlah', $detail['jumlah']);
                    oci_bind_by_name($stmt_update_stock, ':alat_id', $detail['alat_id']);
                    oci_execute($stmt_update_stock);
                }
            }
            
            // If changing from "Ditolak" to something else, decrease stock if needed
            if ($result && $current_status === 'Ditolak' && $status !== 'Ditolak') {
                foreach ($details as $detail) {
                    $query_update_stock = "UPDATE alat_mendaki 
                                        SET jumlah_tersedia = jumlah_tersedia - :jumlah 
                                        WHERE alat_id = :alat_id";
                    $stmt_update_stock = oci_parse($this->conn, $query_update_stock);
                    oci_bind_by_name($stmt_update_stock, ':jumlah', $detail['jumlah']);
                    oci_bind_by_name($stmt_update_stock, ':alat_id', $detail['alat_id']);
                    oci_execute($stmt_update_stock);
                }
            }
            
            // Commit transaction
            $commit = oci_commit($this->conn);
            
            return [
                'result' => $result && $commit,
                'pesan' => $result ? 'Status peminjaman berhasil diupdate' : 'Gagal mengupdate status peminjaman'
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
                 WHERE p.STATUS_PEMINJAMAN = 'Disetujui'
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
</head>
<body class="bg-gray-100">
    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-0 md:ml-64 p-4 md:p-8 pt-16 md:pt-8">
        <div class="container mx-auto">
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-800 mb-4 lg:mb-6">Manajemen Peminjaman</h1>

            <?php if(!empty($pesan)): ?>
                <div class="<?= $result ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> p-3 lg:p-4 rounded mb-4 lg:mb-6 text-sm lg:text-base">
                    <?= $pesan ?>
                </div>
            <?php endif; ?>
            
            <?php if($expired_updated > 0): ?>
                <div class="bg-blue-100 text-blue-800 p-3 lg:p-4 rounded mb-4 lg:mb-6 text-sm lg:text-base">
                    <?= $expired_updated ?> peminjaman yang melewati tanggal pengembalian telah otomatis diupdate menjadi Selesai.
                </div>
            <?php endif; ?>

            <!-- Filter Status - Responsive -->
            <div class="mb-4 lg:mb-6">
                <?php 
                $statuses = [
                    null => 'Semua',
                    'Diajukan' => 'Diajukan',
                    'Disetujui' => 'Disetujui', 
                    'Ditolak' => 'Ditolak', 
                    'Selesai' => 'Selesai'
                ];
                ?>
                <!-- Mobile: Dropdown -->
                <div class="block lg:hidden mb-4">
                    <select id="mobile-filter" class="w-full p-3 border border-gray-300 rounded-lg bg-white text-gray-700" onchange="window.location.href = this.value">
                        <?php foreach($statuses as $status => $label): ?>
                            <option value="?status=<?= $status ?? '' ?>" <?= $status_filter === $status ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Desktop: Buttons -->
                <div class="hidden lg:flex space-x-2">
                    <?php foreach($statuses as $status => $label): ?>
                        <a href="?status=<?= $status ?? '' ?>" 
                           class="px-4 py-2 rounded-md transition <?= 
                               $status_filter === $status ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                           ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Table Container -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <!-- Desktop Table -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600">
                                <th class="py-3 px-4 text-left font-semibold">ID Peminjaman</th>
                                <th class="py-3 px-4 text-left font-semibold">Nama Peminjam</th>
                                <th class="py-3 px-4 text-left font-semibold">Tanggal Pinjam</th>
                                <th class="py-3 px-4 text-left font-semibold">Tanggal Kembali</th>
                                <th class="py-3 px-4 text-left font-semibold">Status</th>
                                <th class="py-3 px-4 text-left font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($daftarPeminjaman as $peminjaman): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-4"><?= $peminjaman['PEMINJAMAN_ID'] ?></td>
                                <td class="py-3 px-4"><?= $peminjaman['NAMA_LENGKAP'] ?></td>
                                <td class="py-3 px-4"><?= date('d M Y', strtotime($peminjaman['TANGGAL_PINJAM'])) ?></td>
                                <td class="py-3 px-4"><?= !empty($peminjaman['TANGGAL_KEMBALI']) ? date('d M Y', strtotime($peminjaman['TANGGAL_KEMBALI'])) : '-' ?></td>
                                <td class="py-3 px-4">
                                    <span class="<?= 
                                        $peminjaman['STATUS_PEMINJAMAN'] == 'Diajukan' ? 'bg-yellow-100 text-yellow-800' : 
                                        ($peminjaman['STATUS_PEMINJAMAN'] == 'Disetujui' ? 'bg-green-100 text-green-800' : 
                                        ($peminjaman['STATUS_PEMINJAMAN'] == 'Ditolak' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'))
                                    ?> px-2 py-1 rounded-full text-xs font-medium">
                                        <?= $peminjaman['STATUS_PEMINJAMAN'] ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <button onclick="showDetailPeminjaman(<?= $peminjaman['PEMINJAMAN_ID'] ?>)" 
                                            class="bg-blue-500 text-white px-3 py-1 rounded-md hover:bg-blue-600 transition">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="lg:hidden">
                    <?php foreach($daftarPeminjaman as $peminjaman): ?>
                    <div class="border-b border-gray-200 p-4 last:border-b-0">
                        <div class="flex flex-col space-y-3">
                            <!-- Header dengan ID dan Status -->
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm text-gray-500">ID Peminjaman</p>
                                    <p class="font-semibold text-gray-800"><?= $peminjaman['PEMINJAMAN_ID'] ?></p>
                                </div>
                                <span class="<?= 
                                    $peminjaman['STATUS_PEMINJAMAN'] == 'Diajukan' ? 'bg-yellow-100 text-yellow-800' : 
                                    ($peminjaman['STATUS_PEMINJAMAN'] == 'Disetujui' ? 'bg-green-100 text-green-800' : 
                                    ($peminjaman['STATUS_PEMINJAMAN'] == 'Ditolak' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'))
                                ?> px-2 py-1 rounded-full text-xs font-medium">
                                    <?= $peminjaman['STATUS_PEMINJAMAN'] ?>
                                </span>
                            </div>

                            <!-- Nama Peminjam -->
                            <div>
                                <p class="text-sm text-gray-500">Nama Peminjam</p>
                                <p class="font-medium text-gray-800"><?= $peminjaman['NAMA_LENGKAP'] ?></p>
                            </div>

                            <!-- Tanggal dalam satu baris -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-sm text-gray-500">Tanggal Pinjam</p>
                                    <p class="text-sm font-medium text-gray-800"><?= date('d M Y', strtotime($peminjaman['TANGGAL_PINJAM'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Tanggal Kembali</p>
                                    <p class="text-sm font-medium text-gray-800"><?= !empty($peminjaman['TANGGAL_KEMBALI']) ? date('d M Y', strtotime($peminjaman['TANGGAL_KEMBALI'])) : '-' ?></p>
                                </div>
                            </div>

                            <!-- Button Detail -->
                            <div class="pt-2">
                                <button onclick="showDetailPeminjaman(<?= $peminjaman['PEMINJAMAN_ID'] ?>)" 
                                        class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 transition">
                                    <i class="fas fa-eye mr-2"></i>Detail
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Modal Detail Peminjaman -->
        <div id="modal-detail-peminjaman" 
             class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-auto">
                <div class="p-4 lg:p-6">
                    <div class="flex justify-between items-center border-b pb-3 mb-4">
                        <h2 class="text-lg lg:text-xl font-semibold">Detail Peminjaman</h2>
                        <button onclick="closeDetailModal()" 
                                class="text-gray-500 hover:text-gray-700 p-1">
                            <i class="fas fa-times text-xl lg:text-2xl"></i>
                        </button>
                    </div>
                    <div id="detail-peminjaman-content"></div>
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
                document.getElementById('modal-detail-peminjaman').classList.remove('hidden');
            });
    }

    function closeDetailModal() {
        document.getElementById('modal-detail-peminjaman').classList.add('hidden');
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