<?php
session_start();
require_once '../config/database.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Akses ditolak');
}

// Cek apakah ID disediakan
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('ID Peminjaman tidak valid');
}

$peminjamanId = $_GET['id'];
$database = new Database();
$conn = $database->getConnection();

// Process form submission for setting status to "Selesai"
if (isset($_POST['update_status']) && $_POST['update_status'] == 1) {
    // Status selalu diatur ke 'Selesai'
    $status_baru = 'Selesai';
    $peminjaman_id = $_POST['peminjaman_id'];
    
    // Periksa status saat ini untuk mencegah pengembalian berulang
    $check_status_query = "SELECT STATUS_PEMINJAMAN FROM peminjaman WHERE PEMINJAMAN_ID = :peminjaman_id";
    $stmt_check = oci_parse($conn, $check_status_query);
    oci_bind_by_name($stmt_check, ':peminjaman_id', $peminjaman_id);
    oci_execute($stmt_check);
    $current_status = oci_fetch_assoc($stmt_check);
    
    // Hanya proses jika status belum 'Selesai'
    if ($current_status && $current_status['STATUS_PEMINJAMAN'] !== 'Selesai') {
        // Persiapkan query update
        $update_query = "UPDATE peminjaman SET STATUS_PEMINJAMAN = :status";
        
        // Untuk status selesai, selalu set tanggal kembali ke hari ini
        $today = date('Y-m-d');
        $update_query .= ", TANGGAL_KEMBALI = TO_DATE(:tanggal, 'YYYY-MM-DD')";
        
        $update_query .= " WHERE PEMINJAMAN_ID = :peminjaman_id";
        
        $stmt_update = oci_parse($conn, $update_query);
        oci_bind_by_name($stmt_update, ':status', $status_baru);
        oci_bind_by_name($stmt_update, ':peminjaman_id', $peminjaman_id);
        oci_bind_by_name($stmt_update, ':tanggal', $today);
        
        $update_result = oci_execute($stmt_update);
        
        if ($update_result) {
            // Status selesai, kembalikan alat ke stok
            // Dapatkan semua detail peminjaman untuk mengembalikan ke stok
            $query_detail = "SELECT ALAT_ID, JUMLAH_PINJAM FROM detail_peminjaman WHERE PEMINJAMAN_ID = :peminjaman_id";
            $stmt_detail = oci_parse($conn, $query_detail);
            oci_bind_by_name($stmt_detail, ':peminjaman_id', $peminjaman_id);
            oci_execute($stmt_detail);
            
            while ($detail = oci_fetch_assoc($stmt_detail)) {
                $alat_id = $detail['ALAT_ID'];
                $jumlah_kembali = $detail['JUMLAH_PINJAM'];
                
                // PERBAIKAN: Tambahkan log untuk membantu debugging
                error_log("Mengembalikan alat ID: $alat_id, Jumlah: $jumlah_kembali");
                
                // Update stok alat - PERBAIKAN: Gunakan JUMLAH_TOTAL sebagai referensi
                $update_stok = "UPDATE alat_mendaki 
                               SET JUMLAH_TERSEDIA = JUMLAH_TERSEDIA + :jumlah_kembali 
                               WHERE ALAT_ID = :alat_id 
                               AND (JUMLAH_TERSEDIA + :jumlah_kembali) <= JUMLAH_TOTAL";
                               
                $stmt_stok = oci_parse($conn, $update_stok);
                oci_bind_by_name($stmt_stok, ':jumlah_kembali', $jumlah_kembali);
                oci_bind_by_name($stmt_stok, ':alat_id', $alat_id);
                $result_stok = oci_execute($stmt_stok);
                
                if (!$result_stok) {
                    $error = oci_error($stmt_stok);
                    error_log("Error updating stock for item ID $alat_id: " . $error['message']);
                }
            }
            
            // Refresh halaman untuk menampilkan perubahan
            header("Location: ?id=" . $peminjaman_id);
            exit;
        } else {
            $error = oci_error($stmt_update);
            $error_message = "Gagal mengupdate status: " . $error['message'];
        }
    } else {
        // Status sudah 'Selesai', tidak perlu update lagi
        header("Location: ?id=" . $peminjaman_id);
        exit;
    }
}

// Query untuk mendapatkan detail peminjaman
$query = "SELECT p.PEMINJAMAN_ID, 
                 p.USER_ID, 
                 p.TANGGAL_PINJAM, 
                 p.TANGGAL_KEMBALI, 
                 p.STATUS_PEMINJAMAN, 
                 p.TOTAL_BIAYA, 
                 u.NAMA_LENGKAP, 
                 u.EMAIL, 
                 u.NO_TELEPON
          FROM peminjaman p
          JOIN users u ON p.USER_ID = u.USER_ID
          WHERE p.PEMINJAMAN_ID = :peminjaman_id";

$stmt = oci_parse($conn, $query);
oci_bind_by_name($stmt, ':peminjaman_id', $peminjamanId);
oci_execute($stmt);

$peminjaman = oci_fetch_assoc($stmt);

// Jika peminjaman tidak ditemukan, tampilkan pesan error
if (!$peminjaman) {
    echo "<div class='bg-red-100 text-red-700 p-4 rounded'>Data peminjaman tidak ditemukan</div>";
    exit;
}

// Query terpisah untuk mendapatkan daftar alat
$queryAlat = "SELECT a.NAMA_ALAT, dp.JUMLAH_PINJAM, dp.TANGGAL_MULAI, dp.TANGGAL_SELESAI, a.ALAT_ID, dp.DETAIL_ID
              FROM detail_peminjaman dp
              JOIN alat_mendaki a ON dp.ALAT_ID = a.ALAT_ID
              WHERE dp.PEMINJAMAN_ID = :peminjaman_id";

$stmtAlat = oci_parse($conn, $queryAlat);
oci_bind_by_name($stmtAlat, ':peminjaman_id', $peminjamanId);
oci_execute($stmtAlat);

$daftarAlat = [];
$tanggalSelesaiMax = null;
$detailInfo = []; // Untuk menyimpan info detail untuk tabel

while ($row = oci_fetch_assoc($stmtAlat)) {
    $daftarAlat[] = $row['NAMA_ALAT'] . ' (' . $row['JUMLAH_PINJAM'] . ' unit)';
    
    // Simpan untuk tampilan detail
    $detailInfo[] = [
        'alat_id' => $row['ALAT_ID'],
        'detail_id' => $row['DETAIL_ID'],
        'nama_alat' => $row['NAMA_ALAT'],
        'jumlah_pinjam' => $row['JUMLAH_PINJAM'],
        'tanggal_mulai' => $row['TANGGAL_MULAI'],
        'tanggal_selesai' => $row['TANGGAL_SELESAI']
    ];
    
    // Cek tanggal selesai paling akhir
    if ($tanggalSelesaiMax === null || strtotime($row['TANGGAL_SELESAI']) > strtotime($tanggalSelesaiMax)) {
        $tanggalSelesaiMax = $row['TANGGAL_SELESAI'];
    }
}

// Jika tanggal kembali kosong, gunakan tanggal selesai maksimum
if (empty($peminjaman['TANGGAL_KEMBALI']) && $tanggalSelesaiMax) {
    $peminjaman['TANGGAL_KEMBALI'] = $tanggalSelesaiMax;
}

// Format tanggal dengan error handling
$tanggal_pinjam = !empty($peminjaman['TANGGAL_PINJAM']) ? 
    date('d M Y', strtotime($peminjaman['TANGGAL_PINJAM'])) : 'Belum ditentukan';
    
$tanggal_kembali = !empty($peminjaman['TANGGAL_KEMBALI']) ? 
    date('d M Y', strtotime($peminjaman['TANGGAL_KEMBALI'])) : 'Belum ditentukan';

// Tampilkan status peminjaman dengan warna sesuai (PERBAIKAN: disesuaikan dengan 2 status baru)
$status_class = '';
switch($peminjaman['STATUS_PEMINJAMAN']) {
    case 'Sedang Dipinjam':
        $status_class = 'bg-blue-100 text-blue-800';
        break;
    case 'Selesai':
        $status_class = 'bg-green-100 text-green-800';
        break;
    default:
        $status_class = 'bg-gray-100 text-gray-800';
}

// Cek apakah sudah melewati tanggal kembali
$sudah_lewat = !empty($peminjaman['TANGGAL_KEMBALI']) && 
               strtotime($peminjaman['TANGGAL_KEMBALI']) < strtotime(date('Y-m-d')) && 
               $peminjaman['STATUS_PEMINJAMAN'] != 'Selesai';

// Gabungkan daftar alat menjadi string
$daftar_alat_string = !empty($daftarAlat) ? implode(', ', $daftarAlat) : 'Tidak ada alat';

// Query untuk mendapatkan riwayat pembayaran jika ada
$queryPembayaran = "SELECT PEMBAYARAN_ID, TANGGAL_PEMBAYARAN, JUMLAH_PEMBAYARAN, 
                    METODE_PEMBAYARAN, STATUS_PEMBAYARAN
                    FROM pembayaran
                    WHERE PEMINJAMAN_ID = :peminjaman_id
                    ORDER BY TANGGAL_PEMBAYARAN DESC";

$stmtPembayaran = oci_parse($conn, $queryPembayaran);
oci_bind_by_name($stmtPembayaran, ':peminjaman_id', $peminjamanId);
oci_execute($stmtPembayaran);

$pembayaran = [];
while ($row = oci_fetch_assoc($stmtPembayaran)) {
    $pembayaran[] = $row;
}
?>
<div class="space-y-0.5">
    <?php if(isset($error_message)): ?>
    <div class="bg-red-100 text-red-700 p-1 rounded-md mb-1">
        <div class="flex items-start space-x-1">
            <svg class="h-3 w-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            <div>
                <p class="font-medium text-xs">Error: <?= $error_message ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($sudah_lewat): ?>
    <div class="bg-red-100 text-red-700 p-1 rounded-md mb-1">
        <div class="flex items-start space-x-1">
            <svg class="h-3 w-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            <div>
                <p class="font-medium text-xs">Peringatan: Peminjaman sudah melewati tanggal pengembalian!</p>
                <p class="text-xs mt-0.5">Harap segera klik tombol "Selesaikan Peminjaman" untuk mengembalikan alat ke stok.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="flex justify-between items-center">
        <h3 class="text-sm font-semibold text-gray-800">Detail Peminjaman #<?= $peminjaman['PEMINJAMAN_ID'] ?></h3>
        <span class="<?= $status_class ?> px-1.5 py-0.5 rounded-full text-xs font-medium">
            <?= $peminjaman['STATUS_PEMINJAMAN'] ?>
        </span>
    </div>

    <!-- Informasi Peminjam & Tanggal -->
    <div class="grid grid-cols-2 gap-1">
        <div>
            <p class="text-gray-600 text-2xs">Peminjam:</p>
            <p class="font-semibold text-gray-800 text-xs"><?= !empty($peminjaman['NAMA_LENGKAP']) ? $peminjaman['NAMA_LENGKAP'] : 'Data tidak tersedia' ?></p>
            <p class="text-gray-600 text-2xs mt-0.5">Kontak:</p>
            <p class="text-gray-800 text-xs"><?= !empty($peminjaman['EMAIL']) ? $peminjaman['EMAIL'] : 'Email tidak tersedia' ?></p>
            <p class="text-gray-700 text-2xs"><?= !empty($peminjaman['NO_TELEPON']) ? $peminjaman['NO_TELEPON'] : 'No. telp tidak tersedia' ?></p>
        </div>
        <div>
            <p class="text-gray-600 text-2xs">Tanggal Pinjam:</p>
            <p class="font-semibold text-gray-800 text-xs"><?= $tanggal_pinjam ?></p>
            <p class="text-gray-600 text-2xs mt-0.5">Tanggal Kembali:</p>
            <p class="font-semibold <?= $sudah_lewat ? 'text-red-600' : 'text-gray-800' ?> text-xs">
                <?= $tanggal_kembali ?>
                <?= $sudah_lewat ? ' <span class="text-2xs text-red-600">(Terlambat)</span>' : '' ?>
            </p>
        </div>
    </div>

    <!-- Detail Alat - Desktop Table -->
    <div class="hidden lg:block">
        <p class="text-gray-600 text-xs mb-1">Alat yang Dipinjam:</p>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-md">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 text-xs">
                        <th class="py-1 px-2 text-left border-b">Nama Alat</th>
                        <th class="py-1 px-2 text-center border-b">Jumlah</th>
                        <th class="py-1 px-2 text-center border-b">Tanggal Mulai</th>
                        <th class="py-1 px-2 text-center border-b">Tanggal Selesai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($detailInfo as $detail): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-1 px-2 text-xs"><?= $detail['nama_alat'] ?></td>
                        <td class="py-1 px-2 text-center text-xs"><?= $detail['jumlah_pinjam'] ?> unit</td>
                        <td class="py-1 px-2 text-center text-xs">
                            <?= date('d M Y', strtotime($detail['tanggal_mulai'])) ?>
                        </td>
                        <td class="py-1 px-2 text-center text-xs 
                            <?= strtotime($detail['tanggal_selesai']) < strtotime(date('Y-m-d')) && $peminjaman['STATUS_PEMINJAMAN'] != 'Selesai' ? 'text-red-600' : '' ?>">
                            <?= date('d M Y', strtotime($detail['tanggal_selesai'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detail Alat - Mobile Cards -->
    <div class="lg:hidden">
        <p class="text-gray-600 text-2xs mb-0.5">Alat yang Dipinjam:</p>
        <div class="space-y-0.5">
            <?php foreach($detailInfo as $detail): ?>
            <div class="bg-gray-50 border border-gray-200 rounded-md p-1">
                <div class="space-y-0.5">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 text-xs"><?= $detail['nama_alat'] ?></p>
                            <p class="text-2xs text-gray-600">Jumlah: <span class="font-medium"><?= $detail['jumlah_pinjam'] ?> unit</span></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-1 text-2xs">
                        <div>
                            <p class="text-gray-500">Mulai:</p>
                            <p class="font-medium"><?= date('d M Y', strtotime($detail['tanggal_mulai'])) ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500">Selesai:</p>
                            <p class="font-medium <?= strtotime($detail['tanggal_selesai']) < strtotime(date('Y-m-d')) && $peminjaman['STATUS_PEMINJAMAN'] != 'Selesai' ? 'text-red-600' : '' ?>">
                                <?= date('d M Y', strtotime($detail['tanggal_selesai'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Informasi Pembayaran -->
    <div>
        <p class="text-gray-600 text-2xs">Total Biaya:</p>
        <p class="font-semibold text-green-600 text-xs">Rp <?= !empty($peminjaman['TOTAL_BIAYA']) ? number_format($peminjaman['TOTAL_BIAYA'], 0, ',', '.') : '0' ?></p>
    </div>

    <?php if(count($pembayaran) > 0): ?>
    <!-- Riwayat Pembayaran - Desktop Table -->
    <div class="hidden lg:block">
        <p class="text-gray-600 text-xs mb-1">Riwayat Pembayaran:</p>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-md">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 text-xs">
                        <th class="py-1 px-2 text-left border-b">Tanggal</th>
                        <th class="py-1 px-2 text-left border-b">Jumlah</th>
                        <th class="py-1 px-2 text-left border-b">Metode</th>
                        <th class="py-1 px-2 text-left border-b">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pembayaran as $bayar): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-1 px-2 text-xs"><?= date('d M Y', strtotime($bayar['TANGGAL_PEMBAYARAN'])) ?></td>
                        <td class="py-1 px-2 text-xs">Rp <?= number_format($bayar['JUMLAH_PEMBAYARAN'], 0, ',', '.') ?></td>
                        <td class="py-1 px-2 text-xs"><?= $bayar['METODE_PEMBAYARAN'] ?></td>
                        <td class="py-1 px-2">
                            <span class="<?= 
                                $bayar['STATUS_PEMBAYARAN'] == 'Lunas' ? 'bg-green-100 text-green-800' : 
                                ($bayar['STATUS_PEMBAYARAN'] == 'Menunggu' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')
                            ?> px-1 py-0.5 rounded-full text-2xs">
                                <?= $bayar['STATUS_PEMBAYARAN'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Riwayat Pembayaran - Mobile Cards -->
    <div class="lg:hidden">
        <p class="text-gray-600 text-2xs mb-0.5">Riwayat Pembayaran:</p>
        <div class="space-y-0.5">
            <?php foreach($pembayaran as $bayar): ?>
            <div class="bg-gray-50 border border-gray-200 rounded-md p-1">
                <div class="space-y-0.5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-gray-800 text-xs"><?= date('d M Y', strtotime($bayar['TANGGAL_PEMBAYARAN'])) ?></p>
                            <p class="text-2xs text-gray-600"><?= $bayar['METODE_PEMBAYARAN'] ?></p>
                        </div>
                        <span class="<?= 
                            $bayar['STATUS_PEMBAYARAN'] == 'Lunas' ? 'bg-green-100 text-green-800' : 
                            ($bayar['STATUS_PEMBAYARAN'] == 'Menunggu' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')
                        ?> px-1 py-0.5 rounded-full text-2xs">
                            <?= $bayar['STATUS_PEMBAYARAN'] ?>
                        </span>
                    </div>
                    <p class="font-semibold text-green-600 text-xs">Rp <?= number_format($bayar['JUMLAH_PEMBAYARAN'], 0, ',', '.') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form Update Status - Hanya untuk perubahan ke Selesai -->
    <?php if($peminjaman['STATUS_PEMINJAMAN'] != 'Selesai'): ?>
    <form method="POST" action="" class="pt-1 border-t border-gray-200">
        <input type="hidden" name="update_status" value="1">
        <input type="hidden" name="peminjaman_id" value="<?= $peminjaman['PEMINJAMAN_ID'] ?>">
        <input type="hidden" name="status_baru" value="Selesai">
        
        <div class="space-y-0.5">            
            <!-- Desktop Layout -->
            <div class="hidden sm:block">
                <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded-md hover:bg-blue-600 transition text-xs font-medium">
                    <i class="fas fa-check-circle mr-1"></i> Selesaikan Peminjaman
                </button>
            </div>
            
            <!-- Mobile Layout -->
            <div class="sm:hidden">
                <button type="submit" class="w-full bg-blue-500 text-white py-1 rounded-md hover:bg-blue-600 transition font-medium text-xs">
                    <i class="fas fa-check-circle mr-1"></i> Selesaikan Peminjaman
                </button>
            </div>
            
            <!-- Informasi -->
            <div class="space-y-0.5">
                <p class="text-2xs text-gray-500">
                    * Status akan diubah menjadi "Selesai", mengembalikan alat ke stok tersedia
                </p>
                <?php if($sudah_lewat): ?>
                <p class="text-2xs text-red-500">
                    * Perhatian: Peminjaman sudah melewati tanggal pengembalian
                </p>
                <?php endif; ?>
            </div>
        </div>
    </form>
    <?php else: ?>
    <div class="pt-1 border-t border-gray-200">
        <div class="bg-blue-50 border border-blue-200 rounded-md p-1 text-blue-800">
            <div class="flex items-center space-x-1">
                <svg class="h-3 w-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <div>
                    <p class="font-medium text-xs">Peminjaman telah selesai pada <?= $tanggal_kembali ?></p>
                    <p class="text-2xs">Semua alat telah dikembalikan ke stok tersedia</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>