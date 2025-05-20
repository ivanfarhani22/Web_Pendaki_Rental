<?php
session_start();
require_once '../config/database.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Cek apakah peminjaman_id ada
if (!isset($_GET['peminjaman_id'])) {
    header('Location: riwayat_peminjaman.php');
    exit();
}

$peminjaman_id = $_GET['peminjaman_id'];

class ProsesPembayaran {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function getPeminjamanDetail($peminjaman_id, $user_id) {
        $query = "SELECT 
                    p.peminjaman_id, 
                    p.tanggal_pinjam, 
                    p.status_peminjaman, 
                    p.total_biaya,
                    d.tanggal_mulai,
                    d.tanggal_selesai,
                    a.nama_alat,
                    a.alat_id,
                    u.nama_lengkap,
                    u.email
                  FROM peminjaman p
                  JOIN detail_peminjaman d ON p.peminjaman_id = d.peminjaman_id
                  JOIN alat_mendaki a ON d.alat_id = a.alat_id
                  JOIN users u ON p.user_id = u.user_id
                  WHERE p.peminjaman_id = :peminjaman_id AND p.user_id = :user_id";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':peminjaman_id', $peminjaman_id);
        oci_bind_by_name($stmt, ':user_id', $user_id);
        oci_execute($stmt);

        return oci_fetch_assoc($stmt);
    }

    public function cekStatusPembayaran($peminjaman_id) {
        $query = "SELECT status_pembayaran FROM pembayaran 
                  WHERE peminjaman_id = :peminjaman_id
                  ORDER BY tanggal_pembayaran DESC";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':peminjaman_id', $peminjaman_id);
        oci_execute($stmt);
        
        $row = oci_fetch_assoc($stmt);
        return $row ? $row['STATUS_PEMBAYARAN'] : null;
    }

    public function processPembayaran($peminjaman_id, $metode_pembayaran) {
        try {
            // Begin transaction manually with standard OCI functions
            // First, we'll get the connection from the database object
            $this->conn = oci_connect('pendaki', 'password123', '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=ORCLPDB)))');
            
            // Since oci_set_autocommit() is unavailable, we'll manage the transaction manually
            
            // Ambil total biaya peminjaman
            $query_biaya = "SELECT total_biaya FROM peminjaman WHERE peminjaman_id = :peminjaman_id";
            $stmt_biaya = oci_parse($this->conn, $query_biaya);
            oci_bind_by_name($stmt_biaya, ':peminjaman_id', $peminjaman_id);
            oci_execute($stmt_biaya, OCI_DEFAULT); // OCI_DEFAULT means don't commit yet
            $row = oci_fetch_assoc($stmt_biaya);
            $total_biaya = $row['TOTAL_BIAYA'];
            
            // Insert ke tabel pembayaran
            $query_insert = "INSERT INTO pembayaran (
                             peminjaman_id, jumlah_pembayaran, metode_pembayaran, status_pembayaran)
                             VALUES (:peminjaman_id, :jumlah_pembayaran, :metode_pembayaran, 'Lunas')";
            $stmt_insert = oci_parse($this->conn, $query_insert);
            oci_bind_by_name($stmt_insert, ':peminjaman_id', $peminjaman_id);
            oci_bind_by_name($stmt_insert, ':jumlah_pembayaran', $total_biaya);
            oci_bind_by_name($stmt_insert, ':metode_pembayaran', $metode_pembayaran);
            oci_execute($stmt_insert, OCI_DEFAULT); // OCI_DEFAULT means don't commit yet
            
            // Update status peminjaman menjadi "Dikonfirmasi"
            $query_update = "UPDATE peminjaman 
                             SET status_peminjaman = 'Dikonfirmasi' 
                             WHERE peminjaman_id = :peminjaman_id";
            $stmt_update = oci_parse($this->conn, $query_update);
            oci_bind_by_name($stmt_update, ':peminjaman_id', $peminjaman_id);
            oci_execute($stmt_update, OCI_DEFAULT); // OCI_DEFAULT means don't commit yet
            
            // Commit transaksi
            $commit = oci_commit($this->conn);
            
            return $commit;
        } catch (Exception $e) {
            // Rollback on error
            oci_rollback($this->conn);
            return false;
        }
    }
}

$database = new Database();
$prosesPembayaran = new ProsesPembayaran($database);

// Ambil data peminjaman
$peminjaman = $prosesPembayaran->getPeminjamanDetail($peminjaman_id, $_SESSION['user_id']);

// Redirect jika peminjaman tidak ditemukan atau bukan milik user
if (!$peminjaman || $peminjaman['STATUS_PEMINJAMAN'] != 'Disetujui') {
    header('Location: ../peminjaman/riwayat_peminjaman.php');
    exit();
}

// Cek status pembayaran saat ini
$status_pembayaran = $prosesPembayaran->cekStatusPembayaran($peminjaman_id);
if ($status_pembayaran == 'Lunas') {
    header('Location: ../peminjaman/riwayat_peminjaman.php');
    exit();
}

// Proses pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_pembayaran'])) {
    $metode_pembayaran = $_POST['metode_pembayaran'];
    
    $hasil_pembayaran = $prosesPembayaran->processPembayaran($peminjaman_id, $metode_pembayaran);
    
    if ($hasil_pembayaran) {
        $_SESSION['pesan_sukses'] = 'Pembayaran berhasil dilakukan!';
        header('Location: ../peminjaman/riwayat_peminjaman.php');
        exit();
    } else {
        $pesan_error = 'Terjadi kesalahan dalam proses pembayaran.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pembayaran Peminjaman</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-100 to-green-50 min-h-screen">
    <?php include '../includes/header.php'; ?>
    
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white shadow-lg rounded-lg overflow-hidden border border-green-100">
            <div class="bg-gradient-to-r from-green-600 to-green-400 p-6">
                <h1 class="text-3xl font-bold text-white flex items-center">
                    <i class="fas fa-money-bill-wave mr-4"></i>
                    Pembayaran Peminjaman
                </h1>
            </div>
            
            <div class="p-6">
                <?php if(isset($pesan_error)): ?>
                    <div class="bg-red-100 border-red-400 text-red-700 border p-4 rounded mb-6">
                        <?= $pesan_error ?>
                    </div>
                <?php endif; ?>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                    <h2 class="text-xl text-green-800 font-bold mb-4">Detail Peminjaman</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-600 mb-2">Nama Peminjam:</p>
                            <p class="font-semibold"><?= $peminjaman['NAMA_LENGKAP'] ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600 mb-2">Email:</p>
                            <p class="font-semibold"><?= $peminjaman['EMAIL'] ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600 mb-2">Alat yang Dipinjam:</p>
                            <p class="font-semibold"><?= $peminjaman['NAMA_ALAT'] ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600 mb-2">Tanggal Pinjam:</p>
                            <p class="font-semibold"><?= date('d M Y', strtotime($peminjaman['TANGGAL_PINJAM'])) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600 mb-2">Periode Peminjaman:</p>
                            <p class="font-semibold">
                                <?= date('d M Y', strtotime($peminjaman['TANGGAL_MULAI'])) ?> - 
                                <?= date('d M Y', strtotime($peminjaman['TANGGAL_SELESAI'])) ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-600 mb-2">Total Biaya:</p>
                            <p class="font-semibold text-2xl text-green-800">
                                Rp <?= number_format($peminjaman['TOTAL_BIAYA'], 0, ',', '.') ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <div class="mb-6">
                        <h2 class="text-xl text-green-800 font-bold mb-4">Metode Pembayaran</h2>
                        
                        <div class="flex justify-center">
                            <div class="border-2 border-green-500 rounded-lg p-6 bg-green-50 shadow-md hover:shadow-lg transition duration-300 w-full md:w-2/3 max-w-lg">
                                <input type="radio" id="cash" name="metode_pembayaran" value="Cash" class="mr-2" checked required>
                                <label for="cash" class="cursor-pointer">
                                    <div class="flex items-center mb-3 justify-center">
                                        <div class="bg-green-100 p-3 rounded-full mr-3">
                                            <i class="fas fa-money-bill-alt text-green-600 text-2xl"></i>
                                        </div>
                                        <span class="font-bold text-xl text-green-800">Bayar di Tempat</span>
                                    </div>
                                    
                                    <div class="mt-2 p-3 bg-white rounded-lg border border-green-200">
                                        <p class="text-gray-700 mb-2 text-center">
                                            <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                                            Pembayaran dilakukan secara tunai saat pengambilan alat di lokasi
                                        </p>
                                        <ul class="text-sm text-gray-600 mt-3">
                                            <li class="flex items-start mb-2">
                                                <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                                <span>Tidak ada biaya tambahan</span>
                                            </li>
                                            <li class="flex items-start mb-2">
                                                <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                                <span>Periksa kondisi alat sebelum melakukan pembayaran</span>
                                            </li>
                                            <li class="flex items-start">
                                                <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                                                <span>Proses mudah dan cepat</span>
                                            </li>
                                        </ul>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 mb-6">
                        <h2 class="text-xl text-gray-800 font-bold mb-4">Petunjuk Pembayaran</h2>
                        
                        <div class="mb-4">
                            <p class="text-gray-700 mb-2">
                                <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                                Silakan pilih metode pembayaran dan klik tombol "Bayar Sekarang" untuk melanjutkan proses pembayaran.
                            </p>
                            <p class="text-gray-700">
                                <i class="fas fa-exclamation-triangle mr-2 text-yellow-500"></i>
                                Pembayaran harus diselesaikan dalam waktu 24 jam atau peminjaman akan otomatis dibatalkan.
                            </p>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-4">
                            <p class="text-sm text-gray-600">
                                Dengan melakukan pembayaran, Anda menyetujui 
                                <a href="#" class="text-green-600 hover:underline">syarat dan ketentuan</a> 
                                yang berlaku.
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <a href="riwayat_peminjaman.php" class="bg-gray-400 text-white px-6 py-2 rounded hover:bg-gray-500 transition">
                            <i class="fas fa-arrow-left mr-2"></i> Kembali
                        </a>
                        <button type="submit" name="submit_pembayaran" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">
                            <i class="fas fa-check mr-2"></i> Bayar Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Make payment box look selected by default
        document.addEventListener('DOMContentLoaded', function() {
            const paymentBox = document.querySelector('.border-2.border-green-500');
            if (paymentBox) {
                const radio = paymentBox.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                }
            }
        });
    </script>
</body>
</html>