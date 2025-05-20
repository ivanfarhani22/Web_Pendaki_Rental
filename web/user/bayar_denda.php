<?php
session_start();
require_once '../config/database.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Cek apakah ada parameter peminjaman_id dan jumlah_denda
if (!isset($_GET['peminjaman_id']) || !isset($_GET['jumlah_denda'])) {
    header('Location: riwayat_peminjaman.php');
    exit();
}

$peminjaman_id = $_GET['peminjaman_id'];
$jumlah_denda = $_GET['jumlah_denda'];
$user_id = $_SESSION['user_id'];

// Class untuk proses bayar denda
class DendaHandler {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function validateDenda($peminjaman_id, $user_id) {
        // Cek apakah peminjaman milik user yang sedang login
        $query = "SELECT 
                    p.peminjaman_id, 
                    p.status_peminjaman, 
                    p.user_id,
                    d.tanggal_selesai,
                    a.alat_id,
                    a.nama_alat,
                    CASE
                        WHEN p.status_peminjaman = 'Disetujui' AND d.tanggal_selesai < CURRENT_DATE
                        THEN (CURRENT_DATE - d.tanggal_selesai) * 10000
                        ELSE 0
                    END as denda
                  FROM peminjaman p
                  JOIN detail_peminjaman d ON p.peminjaman_id = d.peminjaman_id
                  JOIN alat_mendaki a ON d.alat_id = a.alat_id
                  WHERE p.peminjaman_id = :peminjaman_id";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':peminjaman_id', $peminjaman_id);
        oci_execute($stmt);
        
        $data = oci_fetch_assoc($stmt);
        
        if (!$data || $data['USER_ID'] != $user_id) {
            return ['valid' => false, 'message' => 'Peminjaman tidak ditemukan atau bukan milik Anda.'];
        }
        
        if ($data['STATUS_PEMINJAMAN'] != 'Disetujui') {
            return ['valid' => false, 'message' => 'Status peminjaman tidak valid untuk pembayaran denda.'];
        }
        
        if ($data['DENDA'] <= 0) {
            return ['valid' => false, 'message' => 'Tidak ada denda yang perlu dibayar.'];
        }
        
        return ['valid' => true, 'data' => $data];
    }
    
    public function processDendaPayment($peminjaman_id, $user_id, $jumlah_denda) {
        // Metode pembayaran hanya Cash
        $metode_pembayaran = 'Cash';
        
        // Validasi data
        $validation = $this->validateDenda($peminjaman_id, $user_id);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }
        
        // Mulai transaksi
        $this->conn = oci_connect('pendaki', 'password123', '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=ORCLPDB)))');
        oci_set_autocommit($this->conn, FALSE);
        
        try {
            // 1. Tambahkan data pembayaran denda ke tabel pembayaran
            $query_pembayaran = "INSERT INTO pembayaran (
                                    pembayaran_id, 
                                    peminjaman_id, 
                                    tanggal_pembayaran,
                                    jumlah_pembayaran, 
                                    metode_pembayaran, 
                                    status_pembayaran
                                ) VALUES (
                                    pembayaran_seq.NEXTVAL, 
                                    :peminjaman_id, 
                                    CURRENT_DATE, 
                                    :jumlah_denda, 
                                    :metode_pembayaran, 
                                    'Lunas'
                                )";
            
            $stmt_pembayaran = oci_parse($this->conn, $query_pembayaran);
            oci_bind_by_name($stmt_pembayaran, ':peminjaman_id', $peminjaman_id);
            oci_bind_by_name($stmt_pembayaran, ':jumlah_denda', $jumlah_denda);
            oci_bind_by_name($stmt_pembayaran, ':metode_pembayaran', $metode_pembayaran);
            oci_execute($stmt_pembayaran);
            
            // 2. Update status peminjaman menjadi Selesai
            $query_update_status = "UPDATE peminjaman 
                                    SET status_peminjaman = 'Selesai' 
                                    WHERE peminjaman_id = :peminjaman_id";
            $stmt_update = oci_parse($this->conn, $query_update_status);
            oci_bind_by_name($stmt_update, ':peminjaman_id', $peminjaman_id);
            oci_execute($stmt_update);
            
            // 3. Kembalikan alat ke stok tersedia
            $query_alat = "SELECT alat_id FROM detail_peminjaman 
                          WHERE peminjaman_id = :peminjaman_id";
            $stmt_alat = oci_parse($this->conn, $query_alat);
            oci_bind_by_name($stmt_alat, ':peminjaman_id', $peminjaman_id);
            oci_execute($stmt_alat);
            $alat_data = oci_fetch_assoc($stmt_alat);
            
            if ($alat_data) {
                $query_update_stok = "UPDATE alat_mendaki 
                                      SET jumlah_tersedia = jumlah_tersedia + 1 
                                      WHERE alat_id = :alat_id";
                $stmt_stok = oci_parse($this->conn, $query_update_stok);
                oci_bind_by_name($stmt_stok, ':alat_id', $alat_data['ALAT_ID']);
                oci_execute($stmt_stok);
            }
            
            // Commit transaksi jika semua operasi berhasil
            oci_commit($this->conn);
            return ['success' => true, 'message' => 'Pembayaran denda berhasil dilakukan.'];
            
        } catch (Exception $e) {
            // Rollback jika terjadi error
            oci_rollback($this->conn);
            return ['success' => false, 'message' => 'Terjadi kesalahan saat memproses pembayaran: ' . $e->getMessage()];
        }
    }
}

$database = new Database();
$dendaHandler = new DendaHandler($database);

// Variabel untuk pesan error/success
$message = '';
$success = false;

// Proses form pembayaran denda
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = $dendaHandler->processDendaPayment(
        $peminjaman_id,
        $user_id,
        $jumlah_denda
    );
    
    $success = $result['success'];
    $message = $result['message'];
    
    if ($success) {
        // Redirect ke halaman riwayat dengan pesan sukses
        header("Location: riwayat_peminjaman.php?status=success&message=" . urlencode($message));
        exit();
    }
}

// Validasi denda sebelum menampilkan form
$validation = $dendaHandler->validateDenda($peminjaman_id, $user_id);
if (!$validation['valid']) {
    header("Location: riwayat_peminjaman.php?status=error&message=" . urlencode($validation['message']));
    exit();
}

// Jika valid, tampilkan form pembayaran denda
$denda_data = $validation['data'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pembayaran Denda</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-100 to-green-50 min-h-screen">
    <?php include '../includes/header.php'; ?>
    
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white shadow-lg rounded-lg overflow-hidden border border-green-100">
            <div class="bg-gradient-to-r from-red-600 to-red-400 p-6">
                <h1 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-exclamation-triangle mr-4"></i>
                    Pembayaran Denda
                </h1>
            </div>
            
            <div class="p-6">
                <?php if (!empty($message) && !$success): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= $message ?>
                </div>
                <?php endif; ?>
                
                <div class="mb-6 bg-red-50 p-4 rounded-lg border border-red-100">
                    <h2 class="text-lg font-semibold text-red-700 mb-2">Informasi Denda</h2>
                    <p class="text-gray-700 mb-2">Anda memiliki denda keterlambatan pengembalian alat pendakian.</p>
                    <div class="flex items-center justify-between py-2 border-b border-gray-200">
                        <span class="text-gray-600">ID Peminjaman:</span>
                        <span class="font-semibold"><?= $peminjaman_id ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-200">
                        <span class="text-gray-600">Alat yang Dipinjam:</span>
                        <span class="font-semibold"><?= $denda_data['NAMA_ALAT'] ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-200">
                        <span class="text-gray-600">Tanggal Selesai Peminjaman:</span>
                        <span class="font-semibold"><?= date('d M Y', strtotime($denda_data['TANGGAL_SELESAI'])) ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-200">
                        <span class="text-gray-600">Jumlah Hari Keterlambatan:</span>
                        <?php $hari_terlambat = floor($denda_data['DENDA'] / 10000); ?>
                        <span class="font-semibold"><?= $hari_terlambat ?> hari</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-gray-200">
                        <span class="text-gray-600">Jumlah Denda:</span>
                        <span class="font-semibold text-red-600">Rp <?= number_format($jumlah_denda, 0, ',', '.') ?></span>
                    </div>
                </div>
                
                <form method="POST" action="" class="bg-white rounded-lg">
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6">
                        <h3 class="text-md font-semibold mb-2">Petunjuk Pembayaran</h3>
                        <p class="text-gray-600 mb-2">Metode pembayaran: <span class="font-semibold">Tunai (Cash)</span></p>
                        <p class="text-gray-600">Pembayaran denda dilakukan secara tunai pada saat pengembalian alat pendakian di toko kami.</p>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-gray-600 text-sm">Dengan melakukan pembayaran, Anda menyetujui bahwa denda keterlambatan telah dilunasi dan status peminjaman akan diselesaikan.</p>
                    </div>
                    
                    <div class="flex justify-between">
                        <a href="riwayat_peminjaman.php" class="bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600 transition">
                            <i class="fas fa-arrow-left mr-2"></i> Kembali
                        </a>
                        <button type="submit" class="bg-red-500 text-white py-2 px-4 rounded hover:bg-red-600 transition">
                            <i class="fas fa-money-bill-wave mr-2"></i> Konfirmasi Pembayaran Denda
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>