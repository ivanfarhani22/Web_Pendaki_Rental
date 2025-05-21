<?php
session_start();
require_once '../config/database.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

class ManajemenPembayaran {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function getDaftarPembayaran($status_filter = null) {
        $query = "SELECT pb.*, 
                         u.NAMA_LENGKAP,
                         p.TANGGAL_PINJAM,
                         p.TOTAL_BIAYA AS TOTAL_PEMINJAMAN
                  FROM pembayaran pb
                  JOIN peminjaman p ON pb.PEMINJAMAN_ID = p.PEMINJAMAN_ID
                  JOIN users u ON p.USER_ID = u.USER_ID";
        
        // Modify query if a specific status filter is provided
        if ($status_filter !== null) {
            $query .= " WHERE pb.STATUS_PEMBAYARAN = :status";
        }

        $query .= " ORDER BY pb.TANGGAL_PEMBAYARAN DESC";

        $stmt = oci_parse($this->conn, $query);
        
        if ($status_filter !== null) {
            oci_bind_by_name($stmt, ':status', $status_filter);
        }

        oci_execute($stmt);

        $daftarPembayaran = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $daftarPembayaran[] = $row;
        }
        return $daftarPembayaran;
    }

    public function getDetailPembayaran($pembayaranId) {
        $query = "SELECT pb.*,
                         p.PEMINJAMAN_ID,
                         p.TANGGAL_PINJAM,
                         p.TANGGAL_KEMBALI,
                         p.STATUS_PEMINJAMAN,
                         p.TOTAL_BIAYA AS TOTAL_PEMINJAMAN,
                         u.NAMA_LENGKAP,
                         u.EMAIL,
                         u.NO_TELEPON,
                         LISTAGG(a.NAMA_ALAT, ', ') WITHIN GROUP (ORDER BY a.NAMA_ALAT) AS DAFTAR_ALAT
                  FROM pembayaran pb
                  JOIN peminjaman p ON pb.PEMINJAMAN_ID = p.PEMINJAMAN_ID
                  JOIN users u ON p.USER_ID = u.USER_ID
                  JOIN detail_peminjaman dp ON p.PEMINJAMAN_ID = dp.PEMINJAMAN_ID
                  JOIN alat_mendaki a ON dp.ALAT_ID = a.ALAT_ID
                  WHERE pb.PEMBAYARAN_ID = :pembayaran_id
                  GROUP BY pb.PEMBAYARAN_ID, pb.PEMINJAMAN_ID, pb.TANGGAL_PEMBAYARAN, pb.JUMLAH_PEMBAYARAN, 
                           pb.METODE_PEMBAYARAN, pb.STATUS_PEMBAYARAN, pb.BUKTI_PEMBAYARAN,
                           p.PEMINJAMAN_ID, p.TANGGAL_PINJAM, p.TANGGAL_KEMBALI, p.STATUS_PEMINJAMAN, p.TOTAL_BIAYA,
                           u.NAMA_LENGKAP, u.EMAIL, u.NO_TELEPON";

        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':pembayaran_id', $pembayaranId);
        oci_execute($stmt);

        return oci_fetch_assoc($stmt);
    }

    public function updateStatusPembayaran($pembayaranId, $status, $jumlahPembayaran = null) {
        try {
            // Validate status
            $valid_statuses = ['DP', 'Lunas'];
            if (!in_array($status, $valid_statuses)) {
                throw new Exception("Status tidak valid. Status harus berupa: " . implode(", ", $valid_statuses));
            }
            
            // Get current payment info
            $query_detail = "SELECT pb.STATUS_PEMBAYARAN, pb.JUMLAH_PEMBAYARAN, p.TOTAL_BIAYA, pb.PEMINJAMAN_ID
                          FROM pembayaran pb
                          JOIN peminjaman p ON pb.PEMINJAMAN_ID = p.PEMINJAMAN_ID
                          WHERE pb.PEMBAYARAN_ID = :pembayaran_id";
            $stmt_detail = oci_parse($this->conn, $query_detail);
            oci_bind_by_name($stmt_detail, ':pembayaran_id', $pembayaranId);
            $exec_detail = oci_execute($stmt_detail, OCI_DEFAULT);
            
            if (!$exec_detail) {
                $error = oci_error($stmt_detail);
                throw new Exception("Error fetching pembayaran details: " . $error['message']);
            }
            
            $payment_details = oci_fetch_assoc($stmt_detail);
            
            // Make sure we found the payment
            if (!$payment_details) {
                throw new Exception("Pembayaran ID tidak ditemukan");
            }
            
            // If changing to "Lunas", make sure the final amount matches the total_biaya
            if ($status === 'Lunas') {
                $new_amount = ($jumlahPembayaran !== null) ? $jumlahPembayaran : $payment_details['TOTAL_BIAYA'];
                
                // Update payment
                $query = "UPDATE pembayaran 
                          SET STATUS_PEMBAYARAN = :status,
                              JUMLAH_PEMBAYARAN = :jumlah_pembayaran
                          WHERE PEMBAYARAN_ID = :pembayaran_id";
                
                $stmt = oci_parse($this->conn, $query);
                oci_bind_by_name($stmt, ':status', $status);
                oci_bind_by_name($stmt, ':jumlah_pembayaran', $new_amount);
                oci_bind_by_name($stmt, ':pembayaran_id', $pembayaranId);
            } else {
                // Just update status
                $query = "UPDATE pembayaran 
                          SET STATUS_PEMBAYARAN = :status
                          WHERE PEMBAYARAN_ID = :pembayaran_id";
                
                $stmt = oci_parse($this->conn, $query);
                oci_bind_by_name($stmt, ':status', $status);
                oci_bind_by_name($stmt, ':pembayaran_id', $pembayaranId);
            }
            
            $result = oci_execute($stmt, OCI_DEFAULT);
            
            if (!$result) {
                $error = oci_error($stmt);
                throw new Exception("Error updating pembayaran status: " . $error['message']);
            }
            
            // Log the payment update
            $log_query = "INSERT INTO log_sistem (keterangan, tanggal_log)
                          VALUES ('Pembayaran ID: ' || :pembayaran_id || 
                                  ' diupdate dari ' || :old_status || 
                                  ' menjadi ' || :new_status || 
                                  ' untuk Peminjaman ID: ' || :peminjaman_id,
                                  SYSDATE)";
            
            $log_stmt = oci_parse($this->conn, $log_query);
            $old_status = $payment_details['STATUS_PEMBAYARAN'];
            $peminjaman_id = $payment_details['PEMINJAMAN_ID'];
            
            oci_bind_by_name($log_stmt, ':pembayaran_id', $pembayaranId);
            oci_bind_by_name($log_stmt, ':old_status', $old_status);
            oci_bind_by_name($log_stmt, ':new_status', $status);
            oci_bind_by_name($log_stmt, ':peminjaman_id', $peminjaman_id);
            
            $log_result = oci_execute($log_stmt, OCI_DEFAULT);
            
            if (!$log_result) {
                $error = oci_error($log_stmt);
                throw new Exception("Error logging payment update: " . $error['message']);
            }
            
            // Commit transaction if all operations successful
            $commit = oci_commit($this->conn);
            if (!$commit) {
                $error = oci_error($this->conn);
                throw new Exception("Error committing transaction: " . $error['message']);
            }
            
            return [
                'result' => true,
                'pesan' => 'Status pembayaran berhasil diupdate menjadi ' . $status
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
}

$database = new Database();
$manajemenPembayaran = new ManajemenPembayaran($database);

// Proses filter status
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

// Proses update status jika ada form submit
$result = false;
$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $jumlahPembayaran = null;
    if (isset($_POST['jumlah_pembayaran']) && $_POST['jumlah_pembayaran'] !== '') {
        $jumlahPembayaran = $_POST['jumlah_pembayaran'];
    }
    
    $response = $manajemenPembayaran->updateStatusPembayaran(
        $_POST['pembayaran_id'], 
        $_POST['status_baru'],
        $jumlahPembayaran
    );
    $result = $response['result'];
    $pesan = $response['pesan'];
}

$daftarPembayaran = $manajemenPembayaran->getDaftarPembayaran($status_filter);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pembayaran - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="ml-0 md:ml-64 transition-all duration-300 p-4 md:p-8 pt-16 md:pt-8">
        <div class="container mx-auto">
            <!-- Header Section with Gradient Background -->
            <div class="bg-gradient-to-r from-gray-800 to-gray-800 rounded-lg mb-6 p-6 shadow-lg text-white">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold mb-2">Manajemen Pembayaran</h1>
                        <p class="text-blue-100">Kelola semua transaksi pembayaran dalam satu tampilan</p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <span class="bg-white bg-opacity-20 px-4 py-2 rounded-full">
                            <i class="fas fa-wallet mr-2"></i>Total: <?= count($daftarPembayaran) ?> Transaksi
                        </span>
                    </div>
                </div>
            </div>

            <?php if(!empty($pesan)): ?>
                <div class="<?= $result ? 'bg-green-100 text-green-800 border-l-4 border-green-500' : 'bg-red-100 text-red-800 border-l-4 border-red-500' ?> p-4 rounded-md mb-6 text-sm lg:text-base shadow-sm">
                    <div class="flex items-center">
                        <div class="mr-3">
                            <i class="fas <?= $result ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-xl"></i>
                        </div>
                        <div>
                            <?= $pesan ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Card Container -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <!-- Filter Status - Responsive -->
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-gray-700 mb-3">Filter Berdasarkan Status</h2>
                    <?php 
                    $statuses = [
                        null => 'Semua',
                        'DP' => 'Down Payment (DP)',
                        'Lunas' => 'Lunas'
                    ];
                    ?>
                    <!-- Mobile: Dropdown -->
                    <div class="block lg:hidden mb-4">
                        <select id="mobile-filter" class="w-full p-3 border border-gray-300 rounded-lg bg-white text-gray-700 focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50 transition" onchange="window.location.href = this.value">
                            <?php foreach($statuses as $status => $label): ?>
                                <option value="?status=<?= $status ?? '' ?>" <?= $status_filter === $status ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Desktop: Buttons -->
                    <div class="hidden lg:flex space-x-3">
                        <?php foreach($statuses as $status => $label): ?>
                            <a href="?status=<?= $status ?? '' ?>" 
                               class="px-5 py-2 rounded-full transition-all duration-200 <?= 
                                   $status_filter === $status 
                                   ? 'bg-indigo-600 text-white shadow-md' 
                                   : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                               ?>">
                                <?php if($status === null): ?>
                                    <i class="fas fa-list-ul mr-2"></i>
                                <?php elseif($status === 'DP'): ?>
                                    <i class="fas fa-hourglass-half mr-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-check-circle mr-2"></i>
                                <?php endif; ?>
                                <?= $label ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="mb-6">
                    <div class="relative">
                        <input type="text" placeholder="Cari pembayaran..." class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                        <div class="absolute left-3 top-3 text-gray-400">
                            <i class="fas fa-search"></i>
                        </div>
                    </div>
                </div>

                <!-- Table Container -->
                <div class="rounded-xl overflow-hidden border border-gray-200">
                    <!-- Desktop Table -->
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50 text-gray-600">
                                    <th class="py-4 px-6 text-left font-semibold">ID Pembayaran</th>
                                    <th class="py-4 px-6 text-left font-semibold">ID Peminjaman</th>
                                    <th class="py-4 px-6 text-left font-semibold">Nama Peminjam</th>
                                    <th class="py-4 px-6 text-left font-semibold">Tanggal Pembayaran</th>
                                    <th class="py-4 px-6 text-left font-semibold">Jumlah (Rp)</th>
                                    <th class="py-4 px-6 text-left font-semibold">Status</th>
                                    <th class="py-4 px-6 text-left font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($daftarPembayaran as $pembayaran): ?>
                                <tr class="border-t border-gray-200 hover:bg-gray-50 transition-colors">
                                    <td class="py-4 px-6"><?= $pembayaran['PEMBAYARAN_ID'] ?></td>
                                    <td class="py-4 px-6"><?= $pembayaran['PEMINJAMAN_ID'] ?></td>
                                    <td class="py-4 px-6 font-medium"><?= $pembayaran['NAMA_LENGKAP'] ?></td>
                                    <td class="py-4 px-6"><?= date('d M Y', strtotime($pembayaran['TANGGAL_PEMBAYARAN'])) ?></td>
                                    <td class="py-4 px-6 font-semibold">Rp <?= number_format($pembayaran['JUMLAH_PEMBAYARAN'], 0, ',', '.') ?></td>
                                    <td class="py-4 px-6">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= 
                                            $pembayaran['STATUS_PEMBAYARAN'] == 'DP' 
                                            ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' 
                                            : 'bg-green-100 text-green-800 border border-green-200'
                                        ?>">
                                            <?php if($pembayaran['STATUS_PEMBAYARAN'] == 'DP'): ?>
                                                <i class="fas fa-hourglass-half mr-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-check-circle mr-1"></i>
                                            <?php endif; ?>
                                            <?= $pembayaran['STATUS_PEMBAYARAN'] ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <button onclick="showDetailPembayaran(<?= $pembayaran['PEMBAYARAN_ID'] ?>)" 
                                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-all focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                                            <i class="fas fa-eye mr-2"></i>Detail
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="lg:hidden divide-y divide-gray-200">
                        <?php foreach($daftarPembayaran as $pembayaran): ?>
                        <div class="p-5">
                            <div class="flex flex-col space-y-4">
                                <!-- Header with Payment ID and Status -->
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase tracking-wider">ID Pembayaran</p>
                                        <p class="font-semibold text-gray-800 text-lg">#<?= $pembayaran['PEMBAYARAN_ID'] ?></p>
                                    </div>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= 
                                        $pembayaran['STATUS_PEMBAYARAN'] == 'DP' 
                                        ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' 
                                        : 'bg-green-100 text-green-800 border border-green-200'
                                    ?>">
                                        <?php if($pembayaran['STATUS_PEMBAYARAN'] == 'DP'): ?>
                                            <i class="fas fa-hourglass-half mr-1"></i>
                                        <?php else: ?>
                                            <i class="fas fa-check-circle mr-1"></i>
                                        <?php endif; ?>
                                        <?= $pembayaran['STATUS_PEMBAYARAN'] ?>
                                    </span>
                                </div>

                                <!-- Nama & ID Peminjaman -->
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Nama Peminjam</p>
                                    <p class="font-medium text-gray-800 text-lg"><?= $pembayaran['NAMA_LENGKAP'] ?></p>
                                    <div class="mt-2 flex items-center">
                                        <span class="text-xs text-gray-500">ID Peminjaman:</span>
                                        <span class="ml-2 text-sm font-medium text-blue-600">#<?= $pembayaran['PEMINJAMAN_ID'] ?></span>
                                    </div>
                                </div>

                                <!-- Tanggal & Jumlah -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Tanggal</p>
                                        <p class="font-medium text-gray-800">
                                            <i class="far fa-calendar-alt mr-2 text-gray-400"></i>
                                            <?= date('d M Y', strtotime($pembayaran['TANGGAL_PEMBAYARAN'])) ?>
                                        </p>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3">
                                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Jumlah</p>
                                        <p class="font-semibold text-gray-800">
                                            <i class="fas fa-money-bill-wave mr-2 text-gray-400"></i>
                                            Rp <?= number_format($pembayaran['JUMLAH_PEMBAYARAN'], 0, ',', '.') ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Button Detail -->
                                <div class="pt-2">
                                    <button onclick="showDetailPembayaran(<?= $pembayaran['PEMBAYARAN_ID'] ?>)" 
                                            class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-all flex items-center justify-center">
                                        <i class="fas fa-eye mr-2"></i>Lihat Detail
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="mt-6 flex justify-center">
                    <div class="inline-flex rounded-md shadow-sm">
                        <a href="#" class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <a href="#" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600">
                            1
                        </a>
                        <a href="#" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                            2
                        </a>
                        <a href="#" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                            3
                        </a>
                        <a href="#" class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Modal Detail Pembayaran -->
        <div id="modal-detail-pembayaran" 
             class="fixed inset-0 bg-black bg-opacity-60 z-50 hidden flex items-center justify-center p-4 backdrop-filter backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-auto transform transition-all duration-300">
                <div class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-t-xl p-5">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold">Detail Pembayaran</h2>
                        <button onclick="closeDetailModal()" 
                                class="text-white hover:bg-white hover:bg-opacity-20 rounded-full p-2 transition-all">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div id="detail-pembayaran-content"></div>
                    <div class="mt-8 flex justify-end">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>

    <script>
    function showDetailPembayaran(pembayaranId) {
        fetch(`ajax_detail_pembayaran.php?id=${pembayaranId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('detail-pembayaran-content').innerHTML = html;
                document.getElementById('modal-detail-pembayaran').classList.remove('hidden');
                // Tambah animasi modal
                const modalContent = document.querySelector('#modal-detail-pembayaran > div');
                modalContent.classList.add('animate-fade-in-up');
                setTimeout(() => {
                    modalContent.classList.remove('animate-fade-in-up');
                }, 500);
            });
    }

    function closeDetailModal() {
        const modalContent = document.querySelector('#modal-detail-pembayaran > div');
        modalContent.classList.add('animate-fade-out-down');
        
        setTimeout(() => {
            document.getElementById('modal-detail-pembayaran').classList.add('hidden');
            modalContent.classList.remove('animate-fade-out-down');
        }, 300);
    }

    // Close modal when clicking outside
    document.getElementById('modal-detail-pembayaran').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailModal();
        }
    });

    // Add animation classes
    document.head.insertAdjacentHTML('beforeend', `
        <style>
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translate3d(0, 20px, 0);
                }
                to {
                    opacity: 1;
                    transform: translate3d(0, 0, 0);
                }
            }
            
            @keyframes fadeOutDown {
                from {
                    opacity: 1;
                    transform: translate3d(0, 0, 0);
                }
                to {
                    opacity: 0;
                    transform: translate3d(0, 20px, 0);
                }
            }
            
            .animate-fade-in-up {
                animation: fadeInUp 0.3s ease-out forwards;
            }
            
            .animate-fade-out-down {
                animation: fadeOutDown 0.3s ease-out forwards;
            }
        </style>
    `);
    </script>
</body>
</html>