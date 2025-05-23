<?php
session_start();
require_once '../config/database.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

class RiwayatPeminjaman {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function getRiwayatPeminjaman($user_id) {
        $query = "SELECT 
                    p.peminjaman_id, 
                    p.tanggal_pinjam, 
                    p.status_peminjaman, 
                    p.total_biaya,
                    d.tanggal_mulai,
                    d.tanggal_selesai,
                    a.nama_alat,
                    a.alat_id,
                    (SELECT status_pembayaran FROM pembayaran WHERE peminjaman_id = p.peminjaman_id AND ROWNUM = 1) as status_pembayaran,
                    CASE
                        WHEN p.status_peminjaman = 'Sedang Dipinjam' AND d.tanggal_selesai + 1 <= CURRENT_DATE
                        THEN (CURRENT_DATE - (d.tanggal_selesai + 1) + 1) * 10000
                        ELSE 0
                    END as denda
                  FROM peminjaman p
                  JOIN detail_peminjaman d ON p.peminjaman_id = d.peminjaman_id
                  JOIN alat_mendaki a ON d.alat_id = a.alat_id
                  WHERE p.user_id = :user_id
                  ORDER BY p.tanggal_pinjam DESC";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':user_id', $user_id);
        oci_execute($stmt);

        $riwayat = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $riwayat[] = $row;
        }

        return $riwayat;
    }

  public function batalkanPeminjaman($peminjaman_id, $user_id) {
    try {
        // Start transaction
        oci_set_client_identifier($this->conn, 'PENDAKI_APP');
        
        // Cek apakah peminjaman milik user dan masih bisa dibatalkan
        $query_cek = "SELECT p.status_peminjaman, d.alat_id 
                      FROM peminjaman p
                      JOIN detail_peminjaman d ON p.peminjaman_id = d.peminjaman_id
                      WHERE p.peminjaman_id = :peminjaman_id AND p.user_id = :user_id";
        $stmt_cek = oci_parse($this->conn, $query_cek);
        oci_bind_by_name($stmt_cek, ':peminjaman_id', $peminjaman_id);
        oci_bind_by_name($stmt_cek, ':user_id', $user_id);
        oci_execute($stmt_cek);
        $data = oci_fetch_assoc($stmt_cek);

        if (!$data) {
            throw new Exception("Peminjaman tidak ditemukan");
        }
        
        if ($data['STATUS_PEMINJAMAN'] != 'Sedang Dipinjam') {
            throw new Exception("Hanya peminjaman dengan status 'Sedang Dipinjam' yang dapat dibatalkan");
        }

        // Update status peminjaman ke 'Dibatalkan' sesuai dengan constraint CHK_STATUS_PEMINJAMAN
        $status_baru = 'Dibatalkan';
        $query_batal = "UPDATE peminjaman 
                        SET status_peminjaman = :status_baru 
                        WHERE peminjaman_id = :peminjaman_id AND user_id = :user_id";
        $stmt_batal = oci_parse($this->conn, $query_batal);
        oci_bind_by_name($stmt_batal, ':status_baru', $status_baru);
        oci_bind_by_name($stmt_batal, ':peminjaman_id', $peminjaman_id);
        oci_bind_by_name($stmt_batal, ':user_id', $user_id);
        $result = oci_execute($stmt_batal);
        
        if (!$result) {
            $error = oci_error($stmt_batal);
            throw new Exception("Database error: " . $error['message']);
        }

        // Kembalikan alat ke stok
        $query_stok = "UPDATE alat_mendaki 
                       SET jumlah_tersedia = jumlah_tersedia + 1 
                       WHERE alat_id = :alat_id";
        $stmt_stok = oci_parse($this->conn, $query_stok);
        oci_bind_by_name($stmt_stok, ':alat_id', $data['ALAT_ID']);
        $result_stok = oci_execute($stmt_stok);
        
        if (!$result_stok) {
            $error = oci_error($stmt_stok);
            throw new Exception("Database error updating stock: " . $error['message']);
        }

        // Commit transaksi
        oci_commit($this->conn);
        return true;

    } catch (Exception $e) {
        // Rollback jika terjadi error
        oci_rollback($this->conn);
        error_log("Pembatalan error: " . $e->getMessage());
        return false;
    }
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
}

$database = new Database();
$riwayatPeminjaman = new RiwayatPeminjaman($database);

// Proses pembatalan peminjaman
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['batalkan_peminjaman'])) {
    $peminjaman_id = $_POST['peminjaman_id'];
    
    $hasil_pembatalan = $riwayatPeminjaman->batalkanPeminjaman(
        $peminjaman_id, 
        $_SESSION['user_id']
    );

    $pesan = $hasil_pembatalan ? 
        'Peminjaman berhasil dibatalkan.' : 
        'Gagal membatalkan peminjaman.';
}

$riwayat = $riwayatPeminjaman->getRiwayatPeminjaman($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Peminjaman</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-100 to-green-50 min-h-screen">
    <?php include '../includes/header.php'; ?>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 pt-32">
        <div class="container mx-auto">
            <div class="bg-white shadow-lg rounded-lg overflow-hidden border border-green-100">
                <div class="bg-gradient-to-r from-green-900 to-green-900 p-6">
                    <h1 class="text-3xl font-bold text-white flex items-center">
                        <i class="fas fa-history mr-4"></i>
                        Riwayat Peminjaman
                    </h1>
                </div>

                <div class="p-6">
                    <?php if(isset($pesan)): ?>
                        <div class="<?= (isset($hasil_pembatalan) && $hasil_pembatalan) ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700' ?> border p-4 rounded mb-6">
                            <?= $pesan ?>
                        </div>
                    <?php endif; ?>

                    <?php if(empty($riwayat)): ?>
                        <div class="text-center py-12 bg-gray-50 rounded-lg">
                            <i class="fas fa-mountain text-6xl text-green-500 mb-4"></i>
                            <p class="text-gray-600 text-xl">Anda belum memiliki riwayat peminjaman.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-green-50 border-b border-green-200">
                                        <th class="py-3 px-4 text-left text-green-700">Alat</th>
                                        <th class="py-3 px-4 text-left text-green-700">Tanggal Pinjam</th>
                                        <th class="py-3 px-4 text-left text-green-700">Periode</th>
                                        <th class="py-3 px-4 text-left text-green-700">Total Biaya</th>
                                        <th class="py-3 px-4 text-left text-green-700">Status</th>
                                        <th class="py-3 px-4 text-left text-green-700">Pembayaran</th>
                                        <th class="py-3 px-4 text-left text-green-700">Denda</th>
                                        <th class="py-3 px-4 text-left text-green-700">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($riwayat as $item): ?>
                                    <tr class="border-b border-gray-200 hover:bg-green-50 transition">
                                        <td class="py-4 px-4 flex items-center">
                                            <i class="fas fa-hiking text-green-600 mr-3"></i>
                                            <?= $item['NAMA_ALAT'] ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <?= date('d M Y', strtotime($item['TANGGAL_PINJAM'])) ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <?= date('d M Y', strtotime($item['TANGGAL_MULAI'])) ?> - 
                                            <?= date('d M Y', strtotime($item['TANGGAL_SELESAI'])) ?>
                                        </td>
                                        <td class="py-4 px-4 font-semibold text-green-800">
                                            Rp <?= number_format($item['TOTAL_BIAYA'], 0, ',', '.') ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <span class="<?= 
                                                $item['STATUS_PEMINJAMAN'] == 'Selesai' ? 'bg-green-100 text-green-800' : 
                                                ($item['STATUS_PEMINJAMAN'] == 'Dibatalkan' ? 'bg-gray-100 text-gray-800' : 
                                                ($item['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam' && $item['DENDA'] > 0 ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) 
                                            ?> px-2 py-1 rounded-full text-xs">
                                                <?= $item['STATUS_PEMINJAMAN'] ?>
                                                <?= ($item['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam' && $item['DENDA'] > 0) ? ' (Terlambat)' : '' ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4">
                                            <?php if($item['STATUS_PEMBAYARAN']): ?>
                                                <span class="<?= 
                                                    $item['STATUS_PEMBAYARAN'] == 'Lunas' ? 'bg-green-100 text-green-800' : 
                                                    ($item['STATUS_PEMBAYARAN'] == 'DP' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')
                                                ?> px-2 py-1 rounded-full text-xs">
                                                    <?= $item['STATUS_PEMBAYARAN'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full text-xs">
                                                    Belum Bayar
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <?php if($item['DENDA'] > 0): ?>
                                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs">
                                                    Rp <?= number_format($item['DENDA'], 0, ',', '.') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                                                    Tidak Ada
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4">
                                            <?php if($item['STATUS_PEMINJAMAN'] == 'Sedang Dipinjam'): ?>
                                                <form method="POST" onsubmit="return confirm('Yakin ingin membatalkan peminjaman?');">
                                                    <input type="hidden" name="batalkan_peminjaman" value="1">
                                                    <input type="hidden" name="peminjaman_id" value="<?= $item['PEMINJAMAN_ID'] ?>">
                                                    <button type="submit" class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition text-sm">
                                                        <i class="fas fa-times mr-1"></i> Batalkan
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if($item['DENDA'] > 0): ?>
                                                <a href="bayar_denda.php?peminjaman_id=<?= $item['PEMINJAMAN_ID'] ?>&jumlah_denda=<?= $item['DENDA'] ?>" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition text-sm mt-2 inline-block">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i> Bayar Denda
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script>
        // Optional: Add some interactivity
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (form.querySelector('input[name="batalkan_peminjaman"]')) {
                    const confirmed = confirm('Yakin ingin membatalkan peminjaman?');
                    if (!confirmed) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>