<?php
session_start();
require_once '../config/database.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "Akses ditolak";
    exit();
}

// Validasi ID pembayaran
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ID Pembayaran tidak valid";
    exit();
}

$pembayaranId = $_GET['id'];
$database = new Database();
$conn = $database->getConnection();

// Process form submission if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $pembayaran_id = $_POST['pembayaran_id'];
    $status_baru = $_POST['status_baru'];
    
    // Get the peminjaman total if changing to "Lunas"
    if ($status_baru === 'Lunas') {
        $query_total = "SELECT p.TOTAL_BIAYA 
                      FROM pembayaran pb
                      JOIN peminjaman p ON pb.PEMINJAMAN_ID = p.PEMINJAMAN_ID
                      WHERE pb.PEMBAYARAN_ID = :pembayaran_id";
        $stmt_total = oci_parse($conn, $query_total);
        oci_bind_by_name($stmt_total, ':pembayaran_id', $pembayaran_id);
        oci_execute($stmt_total);
        $total_row = oci_fetch_assoc($stmt_total);
        $jumlahPembayaran = $total_row['TOTAL_BIAYA'];
        
        // Update the payment status and amount
        $query = "UPDATE pembayaran 
                SET STATUS_PEMBAYARAN = :status,
                    JUMLAH_PEMBAYARAN = :jumlah_pembayaran
                WHERE PEMBAYARAN_ID = :pembayaran_id";
                
        $stmt = oci_parse($conn, $query);
        oci_bind_by_name($stmt, ':status', $status_baru);
        oci_bind_by_name($stmt, ':jumlah_pembayaran', $jumlahPembayaran);
        oci_bind_by_name($stmt, ':pembayaran_id', $pembayaran_id);
    } else {
        // Just update status for other status changes
        $query = "UPDATE pembayaran 
                SET STATUS_PEMBAYARAN = :status
                WHERE PEMBAYARAN_ID = :pembayaran_id";
                
        $stmt = oci_parse($conn, $query);
        oci_bind_by_name($stmt, ':status', $status_baru);
        oci_bind_by_name($stmt, ':pembayaran_id', $pembayaran_id);
    }
    
    $result = oci_execute($stmt);
    
    if ($result) {
        echo '<div class="bg-green-100 text-green-700 p-3 rounded mb-4">
                Status pembayaran berhasil diupdate menjadi ' . $status_baru . '
              </div>';
    } else {
        $error = oci_error($stmt);
        echo '<div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                Error: ' . $error['message'] . '
              </div>';
    }
}

class PembayaranDetail {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
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
}

$pembayaranDetail = new PembayaranDetail($conn);
$detail = $pembayaranDetail->getDetailPembayaran($pembayaranId);

if (!$detail) {
    echo "Pembayaran tidak ditemukan";
    exit();
}
?>

<!-- Informasi Detail Pembayaran -->
<div class="space-y-3 text-sm">
    <!-- Informasi Pembayaran -->
    <div class="border-b pb-3">
        <h3 class="font-semibold text-gray-700 mb-2">Informasi Pembayaran</h3>
        <div class="grid grid-cols-2 gap-x-3 gap-y-2">
            <div class="col-span-1">
                <p class="text-gray-500">ID Pembayaran</p>
                <p class="font-medium"><?= $detail['PEMBAYARAN_ID'] ?></p>
            </div>
            <div class="col-span-1">
                <p class="text-gray-500">Tanggal Pembayaran</p>
                <p class="font-medium"><?= date('d M Y', strtotime($detail['TANGGAL_PEMBAYARAN'])) ?></p>
            </div>
            <div class="col-span-1">
                <p class="text-gray-500">Jumlah Pembayaran</p>
                <p class="font-medium">Rp <?= number_format($detail['JUMLAH_PEMBAYARAN'], 0, ',', '.') ?></p>
            </div>
            <div class="col-span-1">
                <p class="text-gray-500">Total Peminjaman</p>
                <p class="font-medium">Rp <?= number_format($detail['TOTAL_PEMINJAMAN'], 0, ',', '.') ?></p>
            </div>
            <div class="col-span-1">
                <p class="text-gray-500">Metode Pembayaran</p>
                <p class="font-medium"><?= $detail['METODE_PEMBAYARAN'] ?></p>
            </div>
            <div class="col-span-1">
                <p class="text-gray-500">Status Pembayaran</p>
                <p class="font-medium">
                    <span class="<?= 
                        $detail['STATUS_PEMBAYARAN'] == 'DP' ? 'bg-yellow-100 text-yellow-800' : 
                        'bg-green-100 text-green-800'
                    ?> px-2 py-0.5 rounded-full text-xs font-medium">
                        <?= $detail['STATUS_PEMBAYARAN'] ?>
                    </span>
                </p>
            </div>
            <?php if (!empty($detail['BUKTI_PEMBAYARAN'])): ?>
            <div class="col-span-2">
                <p class="text-gray-500">Bukti Pembayaran</p>
                <a href="../uploads/bukti_pembayaran/<?= $detail['BUKTI_PEMBAYARAN'] ?>" 
                   target="_blank" 
                   class="text-blue-600 hover:underline">
                    <i class="fas fa-file-image mr-1"></i>Lihat Bukti
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Informasi Lainnya -->
    <div class="border-b pb-3">
        <h3 class="font-semibold text-gray-700 mb-2">Informasi Peminjaman</h3>
        <div class="grid grid-cols-2 gap-x-3 gap-y-2">
            <div class="col-span-1">
                <p class="text-gray-500">ID Peminjaman</p>
                <p class="font-medium"><?= $detail['PEMINJAMAN_ID'] ?></p>
            </div>
            <div class="col-span-1">
                <p class="text-gray-500">Status Peminjaman</p>
                <p class="font-medium">
                    <span class="<?= 
                        $detail['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam' ? 'bg-green-100 text-green-800' : 
                        'bg-blue-100 text-blue-800'
                    ?> px-2 py-0.5 rounded-full text-xs font-medium">
                        <?= $detail['STATUS_PEMINJAMAN'] ?>
                    </span>
                </p>
            </div>
            <div class="col-span-1">
                <p class="text-gray-500">Peminjam</p>
                <p class="font-medium"><?= $detail['NAMA_LENGKAP'] ?></p>
            </div>
            <div class="col-span-1">
                <p class="text-gray-500">Kontak</p>
                <p class="font-medium"><?= $detail['NO_TELEPON'] ?></p>
            </div>
        </div>
    </div>
    
    <!-- Form Update Status Pembayaran - hanya jika status DP -->
    <?php if ($detail['STATUS_PEMBAYARAN'] == 'DP'): ?>
    <div>
        <h3 class="font-semibold text-gray-700 mb-2">Update Status Pembayaran</h3>
        <form method="POST" action="">
            <input type="hidden" name="pembayaran_id" value="<?= $detail['PEMBAYARAN_ID'] ?>">
            <input type="hidden" name="status_baru" value="Lunas">
            <input type="hidden" name="update_status" value="1">
            
            <button type="submit" 
                    class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg text-sm">
                <i class="fas fa-check mr-1"></i>Konfirmasi Pelunasan
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>