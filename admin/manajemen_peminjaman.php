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
                         MAX(dp.TANGGAL_SELESAI) AS TANGGAL_SELESAI_DETAIL
                  FROM peminjaman p
                  JOIN users u ON p.USER_ID = u.USER_ID
                  JOIN detail_peminjaman dp ON p.PEMINJAMAN_ID = dp.PEMINJAMAN_ID
                  JOIN alat_mendaki a ON dp.ALAT_ID = a.ALAT_ID
                  WHERE p.PEMINJAMAN_ID = :peminjaman_id
                  GROUP BY p.PEMINJAMAN_ID, u.NAMA_LENGKAP, u.EMAIL, u.NOMOR_TELEPON";

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
    <title>Manajemen Peminjaman - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'components/sidebar.php'; ?>

    <div class="ml-64 p-8">
        <div class="container mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Peminjaman</h1>

            <?php if(!empty($pesan)): ?>
                <div class="<?= $result ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> p-4 rounded mb-6">
                    <?= $pesan ?>
                </div>
            <?php endif; ?>

            <div class="flex space-x-2 mb-6">
                <?php 
                $statuses = [
                    null => 'Semua',
                    'Diajukan' => 'Diajukan',
                    'Disetujui' => 'Disetujui', 
                    'Ditolak' => 'Ditolak', 
                    'Selesai' => 'Selesai'
                ];
                ?>
                <?php foreach($statuses as $status => $label): ?>
                    <a href="?status=<?= $status ?? '' ?>" 
                       class="px-4 py-2 rounded-md <?= 
                           $status_filter === $status ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                       ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100 text-gray-600">
                                <th class="py-2 px-4 text-left">ID Peminjaman</th>
                                <th class="py-2 px-4 text-left">Nama Peminjam</th>
                                <th class="py-2 px-4 text-left">Tanggal Pinjam</th>
                                <th class="py-2 px-4 text-left">Tanggal Kembali</th>
                                <th class="py-2 px-4 text-left">Status</th>
                                <th class="py-2 px-4 text-left">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($daftarPeminjaman as $peminjaman): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-4"><?= $peminjaman['PEMINJAMAN_ID'] ?></td>
                                <td class="py-3 px-4"><?= $peminjaman['NAMA_LENGKAP'] ?></td>
                                <td class="py-3 px-4"><?= date('d M Y', strtotime($peminjaman['TANGGAL_PINJAM'])) ?></td>
                                <td class="py-3 px-4"><?= date('d M Y', strtotime($peminjaman['TANGGAL_KEMBALI'])) ?></td>
                                <td class="py-3 px-4">
                                    <span class="<?= 
                                        $peminjaman['STATUS_PEMINJAMAN'] == 'Diajukan' ? 'bg-yellow-100 text-yellow-800' : 
                                        ($peminjaman['STATUS_PEMINJAMAN'] == 'Disetujui' ? 'bg-green-100 text-green-800' : 
                                        ($peminjaman['STATUS_PEMINJAMAN'] == 'Ditolak' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'))
                                    ?> px-2 py-1 rounded-full text-xs">
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
            </div>
        </div>

        <!-- Modal Detail Peminjaman -->
        <div id="modal-detail-peminjaman" 
             class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
                <div class="p-6">
                    <div class="flex justify-between items-center border-b pb-3 mb-4">
                        <h2 class="text-xl font-semibold">Detail Peminjaman</h2>
                        <button onclick="closeDetailModal()" 
                                class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-2xl"></i>
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
    </script>
</body>
</html>