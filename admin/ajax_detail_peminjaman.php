<?php
require_once '../config/database.php';

// Cek apakah user adalah admin
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo "Akses ditolak";
    exit();
}

$peminjamanId = $_GET['id'] ?? null;

if (!$peminjamanId) {
    echo "ID Peminjaman tidak valid";
    exit();
}

class ManajemenPeminjaman {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function getDetailPeminjaman($peminjamanId) {
        $query = "SELECT p.PEMINJAMAN_ID, p.USER_ID, p.TANGGAL_PINJAM, p.TANGGAL_KEMBALI, p.STATUS_PEMINJAMAN, 
                         u.NAMA_LENGKAP, u.EMAIL,
                         (SELECT LISTAGG(a.NAMA_ALAT, ', ') WITHIN GROUP (ORDER BY a.NAMA_ALAT) 
                          FROM detail_peminjaman dp 
                          JOIN alat_mendaki a ON dp.ALAT_ID = a.ALAT_ID 
                          WHERE dp.PEMINJAMAN_ID = p.PEMINJAMAN_ID) AS DAFTAR_ALAT,
                         (SELECT MAX(dp.TANGGAL_SELESAI) 
                          FROM detail_peminjaman dp 
                          WHERE dp.PEMINJAMAN_ID = p.PEMINJAMAN_ID) AS TANGGAL_SELESAI_DETAIL
                  FROM peminjaman p
                  JOIN users u ON p.USER_ID = u.USER_ID
                  WHERE p.PEMINJAMAN_ID = :peminjaman_id";

        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':peminjaman_id', $peminjamanId);

        // Define output variables explicitly to prevent OCI fetch errors
        oci_define_by_name($stmt, 'PEMINJAMAN_ID', $peminjaman_id);
        oci_define_by_name($stmt, 'NAMA_LENGKAP', $nama_lengkap);
        oci_define_by_name($stmt, 'EMAIL', $email);
        oci_define_by_name($stmt, 'TANGGAL_PINJAM', $tanggal_pinjam);
        oci_define_by_name($stmt, 'TANGGAL_KEMBALI', $tanggal_kembali);
        oci_define_by_name($stmt, 'STATUS_PEMINJAMAN', $status_peminjaman);
        oci_define_by_name($stmt, 'DAFTAR_ALAT', $daftar_alat);
        oci_define_by_name($stmt, 'TANGGAL_SELESAI_DETAIL', $tanggal_selesai_detail);

        if (!oci_execute($stmt)) {
            $error = oci_error($stmt);
            echo "Execution error: " . $error['message'];
            exit();
        }

        // Fetch the row manually
        if (oci_fetch($stmt)) {
            $detail = [
                'PEMINJAMAN_ID' => $peminjaman_id,
                'NAMA_LENGKAP' => $nama_lengkap,
                'EMAIL' => $email,
                'TANGGAL_PINJAM' => $tanggal_pinjam,
                'TANGGAL_KEMBALI' => $tanggal_kembali,
                'STATUS_PEMINJAMAN' => $status_peminjaman,
                'DAFTAR_ALAT' => $daftar_alat,
                'TANGGAL_SELESAI_DETAIL' => $tanggal_selesai_detail
            ];

            // If tanggal_kembali is null, use the tanggal_selesai from detail_peminjaman
            if (empty($detail['TANGGAL_KEMBALI']) && !empty($detail['TANGGAL_SELESAI_DETAIL'])) {
                $detail['TANGGAL_KEMBALI'] = $detail['TANGGAL_SELESAI_DETAIL'];
            }

            return $detail;
        }

        return null;
    }

    public function updateStatusPeminjaman($peminjamanId, $status) {
        $query = "UPDATE peminjaman SET STATUS_PEMINJAMAN = :status WHERE PEMINJAMAN_ID = :peminjaman_id";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':status', $status);
        oci_bind_by_name($stmt, ':peminjaman_id', $peminjamanId);

        $result = oci_execute($stmt);
        
        return [
            'result' => $result,
            'pesan' => $result ? 'Status peminjaman berhasil diupdate' : 'Gagal mengupdate status peminjaman'
        ];
    }
}

$database = new Database();
$manajemenPeminjaman = new ManajemenPeminjaman($database);

// Process update status if form is submitted
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

$detail = $manajemenPeminjaman->getDetailPeminjaman($peminjamanId);

if (!$detail) {
    echo "Detail peminjaman tidak ditemukan";
    exit();
}
?>

<div class="space-y-4">
    <?php if(!empty($pesan)): ?>
        <div class="<?= $result ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> p-4 rounded mb-4">
            <?= $pesan ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="font-semibold text-gray-600">Nama Peminjam</p>
            <p><?= htmlspecialchars($detail['NAMA_LENGKAP']) ?></p>
        </div>
        <div>
            <p class="font-semibold text-gray-600">Email</p>
            <p><?= htmlspecialchars($detail['EMAIL']) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="font-semibold text-gray-600">Tanggal Pinjam</p>
            <p><?= date('d M Y', strtotime($detail['TANGGAL_PINJAM'])) ?></p>
        </div>
        <div>
            <p class="font-semibold text-gray-600">Tanggal Kembali</p>
            <p><?= date('d M Y', strtotime($detail['TANGGAL_KEMBALI'])) ?></p>
        </div>
    </div>

    <div>
        <p class="font-semibold text-gray-600">Daftar Alat</p>
        <p><?= htmlspecialchars($detail['DAFTAR_ALAT']) ?></p>
    </div>

    <div>
        <p class="font-semibold text-gray-600">Status Peminjaman</p>
        <span class="<?= 
            $detail['STATUS_PEMINJAMAN'] == 'Diajukan' ? 'bg-yellow-100 text-yellow-800' : 
            ($detail['STATUS_PEMINJAMAN'] == 'Disetujui' ? 'bg-green-100 text-green-800' : 
            ($detail['STATUS_PEMINJAMAN'] == 'Ditolak' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'))
        ?> px-2 py-1 rounded-full text-xs">
            <?= $detail['STATUS_PEMINJAMAN'] ?>
        </span>
    </div>

    <form method="POST" class="mt-4 space-y-4">
        <input type="hidden" name="peminjaman_id" value="<?= $detail['PEMINJAMAN_ID'] ?>">
        <div>
            <label class="block text-gray-700 mb-2">Update Status</label>
            <select name="status_baru" class="w-full px-3 py-2 border rounded-md">
                <option value="Diajukan" <?= $detail['STATUS_PEMINJAMAN'] == 'Diajukan' ? 'selected' : '' ?>>Diajukan</option>
                <option value="Disetujui" <?= $detail['STATUS_PEMINJAMAN'] == 'Disetujui' ? 'selected' : '' ?>>Disetujui</option>
                <option value="Ditolak" <?= $detail['STATUS_PEMINJAMAN'] == 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                <option value="Selesai" <?= $detail['STATUS_PEMINJAMAN'] == 'Selesai' ? 'selected' : '' ?>>Selesai</option>
            </select>
        </div>
        <button type="submit" name="update_status" 
                class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition">
            Update Status
        </button>
    </form>
</div>