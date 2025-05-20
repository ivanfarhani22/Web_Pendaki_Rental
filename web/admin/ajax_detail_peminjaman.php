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

// Tampilkan status peminjaman dengan warna sesuai
$status_class = '';
switch($peminjaman['STATUS_PEMINJAMAN']) {
    case 'Diajukan':
        $status_class = 'bg-yellow-100 text-yellow-800';
        break;
    case 'Disetujui':
        $status_class = 'bg-green-100 text-green-800';
        break;
    case 'Ditolak':
        $status_class = 'bg-red-100 text-red-800';
        break;
    case 'Selesai':
        $status_class = 'bg-blue-100 text-blue-800';
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
<div class="space-y-0 md:space-y-0 lg:space-y-0">
    <?php if($sudah_lewat): ?>
    <div class="bg-red-100 text-red-700 p-1.5 md:p-3 lg:p-4 rounded-md mb-1.5 md:mb-3 lg:mb-4">
        <div class="flex items-start space-x-1.5 md:space-x-3">
            <svg class="h-3 w-3 md:h-5 md:w-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
            <div>
                <p class="font-medium text-xs md:text-sm lg:text-base">Peringatan: Peminjaman sudah melewati tanggal pengembalian!</p>
                <p class="text-xs mt-0.5 md:mt-1">Harap segera ubah status menjadi "Selesai" untuk mengembalikan alat ke stok.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-0.5 sm:gap-0">
        <h3 class="text-sm md:text-lg lg:text-xl font-semibold text-gray-800">Detail Peminjaman #<?= $peminjaman['PEMINJAMAN_ID'] ?></h3>
        
        <span class="<?= $status_class ?> px-1.5 py-0.5 md:px-3 md:py-1 rounded-full text-xs font-medium self-start sm:self-auto">
            <?= $peminjaman['STATUS_PEMINJAMAN'] ?>
        </span>
    </div>

    <!-- Informasi Peminjam -->
    <div class="grid grid-cols-1 gap-1 md:gap-4">
        <div class="space-y-1 md:space-y-3">
            <div>
                <p class="text-gray-600 text-2xs md:text-sm">Peminjam:</p>
                <p class="font-semibold text-gray-800 text-xs md:text-base"><?= !empty($peminjaman['NAMA_LENGKAP']) ? $peminjaman['NAMA_LENGKAP'] : 'Data tidak tersedia' ?></p>
            </div>
            <div>
                <p class="text-gray-600 text-2xs md:text-sm">Kontak:</p>
                <p class="font-semibold text-gray-800 text-xs md:text-base"><?= !empty($peminjaman['EMAIL']) ? $peminjaman['EMAIL'] : 'Email tidak tersedia' ?></p>
                <p class="text-gray-700 text-2xs md:text-sm"><?= !empty($peminjaman['NO_TELEPON']) ? $peminjaman['NO_TELEPON'] : 'No. telp tidak tersedia' ?></p>
            </div>
        </div>
    </div>

    <!-- Tanggal -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 md:gap-4">
        <div>
            <p class="text-gray-600 text-2xs md:text-sm">Tanggal Pinjam:</p>
            <p class="font-semibold text-gray-800 text-xs md:text-base"><?= $tanggal_pinjam ?></p>
        </div>
        <div>
            <p class="text-gray-600 text-2xs md:text-sm">Tanggal Kembali:</p>
            <p class="font-semibold <?= $sudah_lewat ? 'text-red-600' : 'text-gray-800' ?> text-xs md:text-base">
                <?= $tanggal_kembali ?>
                <?= $sudah_lewat ? ' <span class="text-2xs text-red-600">(Terlambat)</span>' : '' ?>
            </p>
        </div>
    </div>

    <!-- Detail Alat - Desktop Table -->
    <div class="hidden lg:block">
        <p class="text-gray-600 text-sm mb-2">Alat yang Dipinjam:</p>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-md">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 text-xs">
                        <th class="py-2 px-4 text-left border-b">Nama Alat</th>
                        <th class="py-2 px-4 text-center border-b">Jumlah</th>
                        <th class="py-2 px-4 text-center border-b">Tanggal Mulai</th>
                        <th class="py-2 px-4 text-center border-b">Tanggal Selesai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($detailInfo as $detail): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-2 px-4"><?= $detail['nama_alat'] ?></td>
                        <td class="py-2 px-4 text-center"><?= $detail['jumlah_pinjam'] ?> unit</td>
                        <td class="py-2 px-4 text-center">
                            <?= date('d M Y', strtotime($detail['tanggal_mulai'])) ?>
                        </td>
                        <td class="py-2 px-4 text-center 
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
        <p class="text-gray-600 text-2xs md:text-sm mb-1">Alat yang Dipinjam:</p>
        <div class="space-y-1 md:space-y-3">
            <?php foreach($detailInfo as $detail): ?>
            <div class="bg-gray-50 border border-gray-200 rounded-md p-1.5 md:p-3">
                <div class="space-y-0.5 md:space-y-2">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 text-xs md:text-base"><?= $detail['nama_alat'] ?></p>
                            <p class="text-2xs text-gray-600">Jumlah: <span class="font-medium"><?= $detail['jumlah_pinjam'] ?> unit</span></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-1 md:gap-2 text-2xs md:text-sm">
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
        <p class="text-gray-600 text-2xs md:text-sm">Total Biaya:</p>
        <p class="font-semibold text-green-600 text-sm md:text-lg">Rp <?= !empty($peminjaman['TOTAL_BIAYA']) ? number_format($peminjaman['TOTAL_BIAYA'], 0, ',', '.') : '0' ?></p>
    </div>

    <?php if(count($pembayaran) > 0): ?>
    <!-- Riwayat Pembayaran - Desktop Table -->
    <div class="hidden lg:block">
        <p class="text-gray-600 text-sm mb-2">Riwayat Pembayaran:</p>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-md">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 text-xs">
                        <th class="py-2 px-4 text-left border-b">Tanggal</th>
                        <th class="py-2 px-4 text-left border-b">Jumlah</th>
                        <th class="py-2 px-4 text-left border-b">Metode</th>
                        <th class="py-2 px-4 text-left border-b">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pembayaran as $bayar): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-2 px-4"><?= date('d M Y', strtotime($bayar['TANGGAL_PEMBAYARAN'])) ?></td>
                        <td class="py-2 px-4">Rp <?= number_format($bayar['JUMLAH_PEMBAYARAN'], 0, ',', '.') ?></td>
                        <td class="py-2 px-4"><?= $bayar['METODE_PEMBAYARAN'] ?></td>
                        <td class="py-2 px-4">
                            <span class="<?= 
                                $bayar['STATUS_PEMBAYARAN'] == 'Lunas' ? 'bg-green-100 text-green-800' : 
                                ($bayar['STATUS_PEMBAYARAN'] == 'Menunggu' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')
                            ?> px-2 py-1 rounded-full text-xs">
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
        <p class="text-gray-600 text-2xs md:text-sm mb-1">Riwayat Pembayaran:</p>
        <div class="space-y-1 md:space-y-3">
            <?php foreach($pembayaran as $bayar): ?>
            <div class="bg-gray-50 border border-gray-200 rounded-md p-1.5 md:p-3">
                <div class="space-y-0.5 md:space-y-2">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-gray-800 text-xs md:text-base"><?= date('d M Y', strtotime($bayar['TANGGAL_PEMBAYARAN'])) ?></p>
                            <p class="text-2xs text-gray-600"><?= $bayar['METODE_PEMBAYARAN'] ?></p>
                        </div>
                        <span class="<?= 
                            $bayar['STATUS_PEMBAYARAN'] == 'Lunas' ? 'bg-green-100 text-green-800' : 
                            ($bayar['STATUS_PEMBAYARAN'] == 'Menunggu' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')
                        ?> px-1.5 py-0.5 rounded-full text-2xs">
                            <?= $bayar['STATUS_PEMBAYARAN'] ?>
                        </span>
                    </div>
                    <p class="font-semibold text-green-600 text-xs md:text-base">Rp <?= number_format($bayar['JUMLAH_PEMBAYARAN'], 0, ',', '.') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form Update Status -->
    <form method="POST" action="" class="pt-2 md:pt-4 border-t border-gray-200">
        <input type="hidden" name="update_status" value="1">
        <input type="hidden" name="peminjaman_id" value="<?= $peminjaman['PEMINJAMAN_ID'] ?>">
        
        <div class="space-y-1.5 md:space-y-3">
            <label class="block text-gray-600 text-2xs md:text-sm font-medium">Update Status:</label>
            
            <!-- Desktop Layout -->
            <div class="hidden sm:flex sm:space-x-3">
                <select name="status_baru" class="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="" disabled>Pilih Status</option>
                    <option value="Diajukan" <?= $peminjaman['STATUS_PEMINJAMAN'] == 'Diajukan' ? 'selected' : '' ?>>Diajukan</option>
                    <option value="Disetujui" <?= $peminjaman['STATUS_PEMINJAMAN'] == 'Disetujui' ? 'selected' : '' ?>>Disetujui</option>
                    <option value="Ditolak" <?= $peminjaman['STATUS_PEMINJAMAN'] == 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                    <option value="Selesai" <?= $peminjaman['STATUS_PEMINJAMAN'] == 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                </select>
                <button type="submit" class="bg-green-500 text-white px-4 md:px-6 py-2 rounded-md hover:bg-green-600 transition text-sm">
                    Update
                </button>
            </div>
            
            <!-- Mobile Layout -->
            <div class="sm:hidden space-y-1.5">
                <select name="status_baru" class="w-full border border-gray-300 rounded-md px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs">
                    <option value="" disabled>Pilih Status</option>
                    <option value="Diajukan" <?= $peminjaman['STATUS_PEMINJAMAN'] == 'Diajukan' ? 'selected' : '' ?>>Diajukan</option>
                    <option value="Disetujui" <?= $peminjaman['STATUS_PEMINJAMAN'] == 'Disetujui' ? 'selected' : '' ?>>Disetujui</option>
                    <option value="Ditolak" <?= $peminjaman['STATUS_PEMINJAMAN'] == 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                    <option value="Selesai" <?= $peminjaman['STATUS_PEMINJAMAN'] == 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                </select>
                <button type="submit" class="w-full bg-green-500 text-white py-1.5 rounded-md hover:bg-green-600 transition font-medium text-xs">
                    <i class="fas fa-sync-alt mr-1"></i>Update Status
                </button>
            </div>
            
            <!-- Informasi -->
            <div class="space-y-0.5 md:space-y-1">
                <p class="text-2xs text-gray-500">
                    * Status "Selesai" akan mengembalikan alat ke stok tersedia secara otomatis dan mengatur tanggal kembali ke hari ini
                </p>
                <?php if($sudah_lewat): ?>
                <p class="text-2xs text-red-500">
                    * Perhatian: Peminjaman sudah melewati tanggal pengembalian yang ditentukan
                </p>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>