<?php
session_start();
require_once '../config/database.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

class AlatPendakian
{
    private $conn;

    public function __construct($database)
    {
        $this->conn = $database->getConnection();
    }

    public function getDaftarAlat($filter = [])
    {
        $query = "SELECT a.*, k.nama_kategori, k.deskripsi as kategori_deskripsi 
                  FROM alat_mendaki a
                  JOIN kategori_alat k ON a.kategori_id = k.kategori_id
                  WHERE a.jumlah_tersedia > 0";

        // Tambahkan filter jika ada
        if (!empty($filter['kategori'])) {
            $query .= " AND k.kategori_id = :kategori";
        }
        if (!empty($filter['kondisi'])) {
            $query .= " AND a.kondisi = :kondisi";
        }
        if (!empty($filter['search'])) {
            $query .= " AND (LOWER(a.nama_alat) LIKE LOWER('%' || :search || '%') 
                        OR LOWER(a.deskripsi) LIKE LOWER('%' || :search || '%'))";
        }

        $stmt = oci_parse($this->conn, $query);

        // Bind parameter filter
        if (!empty($filter['kategori'])) {
            oci_bind_by_name($stmt, ':kategori', $filter['kategori']);
        }
        if (!empty($filter['kondisi'])) {
            oci_bind_by_name($stmt, ':kondisi', $filter['kondisi']);
        }
        if (!empty($filter['search'])) {
            oci_bind_by_name($stmt, ':search', $filter['search']);
        }

        oci_execute($stmt);

        $alat = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $alat[] = $row;
        }

        return $alat;
    }

    public function getKategori()
    {
        $query = "SELECT * FROM kategori_alat ORDER BY nama_kategori";

        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);

        $kategori = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $kategori[] = $row;
        }

        return $kategori;
    }

    public function getAlatById($alat_id)
    {
        $query = "SELECT a.*, k.nama_kategori, k.deskripsi as kategori_deskripsi 
                  FROM alat_mendaki a
                  JOIN kategori_alat k ON a.kategori_id = k.kategori_id
                  WHERE a.alat_id = :alat_id";

        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':alat_id', $alat_id);
        oci_execute($stmt);

        return oci_fetch_assoc($stmt);
    }

    public function mulaiPeminjaman($user_id, $alat_id, $jumlah_pinjam, $tanggal_mulai, $tanggal_selesai, $metode_pembayaran, $jumlah_pembayaran, $foto_bukti)
    {
        try {
            // Mulai transaksi
            oci_execute(oci_parse($this->conn, "BEGIN SAVEPOINT transaction_start; END;"));

            // Cek ketersediaan alat
            $query_cek = "SELECT jumlah_tersedia, harga_sewa FROM alat_mendaki WHERE alat_id = :alat_id FOR UPDATE";
            $stmt_cek = oci_parse($this->conn, $query_cek);
            oci_bind_by_name($stmt_cek, ':alat_id', $alat_id);
            oci_execute($stmt_cek, OCI_NO_AUTO_COMMIT);
            $alat = oci_fetch_assoc($stmt_cek);

            if ($alat['JUMLAH_TERSEDIA'] < $jumlah_pinjam) {
                throw new Exception("Jumlah yang diminta melebihi stok tersedia");
            }

            // Hitung durasi peminjaman
            $tgl_mulai = new DateTime($tanggal_mulai);
            $tgl_selesai = new DateTime($tanggal_selesai);
            $durasi = $tgl_mulai->diff($tgl_selesai)->days + 1;
            $total_biaya = $alat['HARGA_SEWA'] * $durasi * $jumlah_pinjam;

            // Buat record peminjaman dengan status 'Sedang Dipinjam'
            $query_pinjam = "INSERT INTO peminjaman 
             (user_id, tanggal_pinjam, status_peminjaman, total_biaya) 
             VALUES 
             (:user_id, SYSDATE, 'Sedang Dipinjam', :total_biaya)
             RETURNING peminjaman_id INTO :peminjaman_id";
            $stmt_pinjam = oci_parse($this->conn, $query_pinjam);
            $peminjaman_id = 0;
            oci_bind_by_name($stmt_pinjam, ':user_id', $user_id);
            oci_bind_by_name($stmt_pinjam, ':total_biaya', $total_biaya);
            oci_bind_by_name($stmt_pinjam, ':peminjaman_id', $peminjaman_id, -1, SQLT_INT);
            oci_execute($stmt_pinjam, OCI_NO_AUTO_COMMIT);

            // Buat detail peminjaman
            $query_detail = "INSERT INTO detail_peminjaman 
                         (peminjaman_id, alat_id, jumlah_pinjam, tanggal_mulai, tanggal_selesai) 
                         VALUES 
                         (:peminjaman_id, :alat_id, :jumlah_pinjam, TO_DATE(:tanggal_mulai, 'YYYY-MM-DD'), TO_DATE(:tanggal_selesai, 'YYYY-MM-DD'))";
            $stmt_detail = oci_parse($this->conn, $query_detail);
            oci_bind_by_name($stmt_detail, ':peminjaman_id', $peminjaman_id);
            oci_bind_by_name($stmt_detail, ':alat_id', $alat_id);
            oci_bind_by_name($stmt_detail, ':jumlah_pinjam', $jumlah_pinjam);
            oci_bind_by_name($stmt_detail, ':tanggal_mulai', $tanggal_mulai);
            oci_bind_by_name($stmt_detail, ':tanggal_selesai', $tanggal_selesai);
            oci_execute($stmt_detail, OCI_NO_AUTO_COMMIT);

            // Kurangi jumlah stok tersedia
            $jumlah_tersedia_baru = $alat['JUMLAH_TERSEDIA'] - $jumlah_pinjam;
            $query_update_stok = "UPDATE alat_mendaki 
                             SET jumlah_tersedia = :jumlah_tersedia_baru 
                             WHERE alat_id = :alat_id";
            $stmt_update_stok = oci_parse($this->conn, $query_update_stok);
            oci_bind_by_name($stmt_update_stok, ':jumlah_tersedia_baru', $jumlah_tersedia_baru);
            oci_bind_by_name($stmt_update_stok, ':alat_id', $alat_id);
            oci_execute($stmt_update_stok, OCI_NO_AUTO_COMMIT);

            // Create payment record - Changed status from 'Menunggu' to 'DP'
            $query_payment = "INSERT INTO pembayaran 
                         (peminjaman_id, tanggal_pembayaran, jumlah_pembayaran, 
                          metode_pembayaran, status_pembayaran, bukti_pembayaran) 
                         VALUES 
                         (:peminjaman_id, SYSDATE, :jumlah_pembayaran, 
                          :metode_pembayaran, 'DP', :bukti_pembayaran)";
            $stmt_payment = oci_parse($this->conn, $query_payment);
            oci_bind_by_name($stmt_payment, ':peminjaman_id', $peminjaman_id);
            oci_bind_by_name($stmt_payment, ':jumlah_pembayaran', $jumlah_pembayaran);
            oci_bind_by_name($stmt_payment, ':metode_pembayaran', $metode_pembayaran);
            oci_bind_by_name($stmt_payment, ':bukti_pembayaran', $foto_bukti);
            oci_execute($stmt_payment, OCI_NO_AUTO_COMMIT);

            // Commit transaksi
            oci_commit($this->conn);
            return $peminjaman_id;
        } catch (Exception $e) {
            // Rollback transaksi jika terjadi error
            oci_rollback($this->conn);
            return false;
        }
    }
}

$database = new Database();
$alatPendakian = new AlatPendakian($database);

// Proses filter
$filter = [];
if (isset($_GET['kategori']) && !empty($_GET['kategori'])) {
    $filter['kategori'] = $_GET['kategori'];
}
if (isset($_GET['kondisi']) && !empty($_GET['kondisi'])) {
    $filter['kondisi'] = $_GET['kondisi'];
}
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filter['search'] = $_GET['search'];
}

$daftarAlat = $alatPendakian->getDaftarAlat($filter);
$kategori = $alatPendakian->getKategori();

// Proses peminjaman
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pinjam_alat'])) {
    $alat_id = $_POST['alat_id'];
    $jumlah_pinjam = $_POST['jumlah_pinjam'];
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $jumlah_pembayaran = $_POST['total_biaya'];

    // Validasi input
    if ($jumlah_pinjam < 1) {
        $pesan = 'Jumlah peminjaman minimal 1 unit';
        $hasil_peminjaman = false;
    } else if (strtotime($tanggal_mulai) > strtotime($tanggal_selesai)) {
        $pesan = 'Tanggal selesai harus setelah tanggal mulai';
        $hasil_peminjaman = false;
    } else if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] != 0) {
        $pesan = 'Bukti pembayaran wajib diunggah';
        $hasil_peminjaman = false;
    } else {
        // Proses upload bukti pembayaran
        $foto_bukti = null;
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (in_array($_FILES['bukti_pembayaran']['type'], $allowed_types) && $_FILES['bukti_pembayaran']['size'] <= $max_size) {
            $temp_file = $_FILES['bukti_pembayaran']['tmp_name'];
            $upload_dir = '../uploads/bukti_pembayaran/';

            // Create directory if not exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_name = time() . '_' . $_SESSION['user_id'] . '_' . $_FILES['bukti_pembayaran']['name'];
            $destination = $upload_dir . $file_name;

            if (move_uploaded_file($temp_file, $destination)) {
                $foto_bukti = $file_name;
            } else {
                $pesan = 'Gagal mengunggah bukti pembayaran';
                $hasil_peminjaman = false;
            }
        } else {
            $pesan = 'File harus berupa JPG/PNG dengan ukuran maksimal 2MB';
            $hasil_peminjaman = false;
        }

        if ($foto_bukti) {
            $hasil_peminjaman = $alatPendakian->mulaiPeminjaman(
                $_SESSION['user_id'],
                $alat_id,
                $jumlah_pinjam,
                $tanggal_mulai,
                $tanggal_selesai,
                $metode_pembayaran,
                $jumlah_pembayaran,
                $foto_bukti
            );

            $pesan = $hasil_peminjaman ?
                'Peminjaman berhasil dan peralatan sedang dipinjam. Pembayaran DP telah tercatat.' :
                'Gagal mengajukan peminjaman. Jumlah yang diminta mungkin melebihi stok tersedia.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Alat Pendakian</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
      <link rel="icon" href="/pendaki_gear/web/favicon.ico" type="image/x-icon">

</head>
<body class="bg-gradient-to-br from-green-50 via-teal-50 to-blue-100 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 pt-32">
        <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-100">
            <div class="bg-gradient-to-r from-green-900 to-teal-900 px-6 py-8">
                <h1 class="text-3xl font-bold text-white text-center flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mr-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                    Daftar Alat Pendakian
                </h1>
            </div>

            <div class="p-6">
                <?php if (isset($pesan)): ?>
                    <div class="<?= $hasil_peminjaman ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700' ?> px-4 py-3 rounded relative mb-4" role="alert">
                        <?= $pesan ?>
                    </div>
                <?php endif; ?>

                <form method="GET" class="mb-8 bg-gray-50 p-6 rounded-lg shadow-inner">
                    <div class="grid md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">Pencarian</label>
                            <input type="text" name="search" value="<?= isset($filter['search']) ? $filter['search'] : '' ?>" placeholder="Cari alat pendakian..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 font-bold mb-2">Kategori</label>
                            <select name="kategori" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                                <option value="">Semua Kategori</option>
                                <?php foreach ($kategori as $kat): ?>
                                    <option value="<?= $kat['KATEGORI_ID'] ?>"
                                        <?= isset($filter['kategori']) && $filter['kategori'] == $kat['KATEGORI_ID'] ? 'selected' : '' ?>>
                                        <?= $kat['NAMA_KATEGORI'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 font-bold mb-2">Kondisi</label>
                            <select name="kondisi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                                <option value="">Semua Kondisi</option>
                                <option value="Baru" <?= isset($filter['kondisi']) && $filter['kondisi'] == 'Baru' ? 'selected' : '' ?>>Baru</option>
                                <option value="Baik" <?= isset($filter['kondisi']) && $filter['kondisi'] == 'Baik' ? 'selected' : '' ?>>Baik</option>
                                <option value="Cukup" <?= isset($filter['kondisi']) && $filter['kondisi'] == 'Cukup' ? 'selected' : '' ?>>Cukup</option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-green-700 text-white py-2 px-4 rounded-md hover:bg-green-900 transition flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                </svg>
                                Filter
                            </button>
                        </div>
                    </div>
                </form>

                <div class="grid md:grid-cols-3 gap-6">
                    <?php foreach ($daftarAlat as $alat): ?>
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden transform transition hover:scale-105 hover:shadow-xl">
                            <div class="p-6">
                                <h3 class="text-xl font-bold text-green-800 mb-1"><?= $alat['NAMA_ALAT'] ?></h3>
                                <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded mb-3">
                                    <?= $alat['NAMA_KATEGORI'] ?>
                                </span>
                                <div class="text-gray-500 text-sm mb-3">
                                    <?= !empty($alat['DESKRIPSI']) ? $alat['DESKRIPSI'] : 'Tidak ada deskripsi' ?>
                                </div>
                                <div class="space-y-2 text-gray-600 mb-4">
                                    <p><strong>Kondisi:</strong>
                                        <span class="<?=
                                                        $alat['KONDISI'] == 'Baru' ? 'text-green-600' : ($alat['KONDISI'] == 'Baik' ? 'text-blue-600' : ($alat['KONDISI'] == 'Cukup' ? 'text-yellow-600' : 'text-red-600'))
                                                        ?>">
                                            <?= $alat['KONDISI'] ?>
                                        </span>
                                    </p>
                                    <p><strong>Total Unit:</strong> <?= $alat['JUMLAH_TOTAL'] ?></p>
                                    <p><strong>Tersedia:</strong>
                                        <span class="<?= $alat['JUMLAH_TERSEDIA'] < 5 ? 'text-orange-600 font-bold' : 'text-green-600' ?>">
                                            <?= $alat['JUMLAH_TERSEDIA'] ?> unit
                                        </span>
                                    </p>
                                    <p><strong>Harga Sewa:</strong>
                                        <span class="text-green-700 font-bold">
                                            Rp <?= number_format($alat['HARGA_SEWA'], 0, ',', '.') ?>/hari
                                        </span>
                                    </p>
                                </div>
                                <button
                                    onclick="showPinjamModal(
                                        '<?= $alat['ALAT_ID'] ?>', 
                                        '<?= $alat['NAMA_ALAT'] ?>',
                                        '<?= $alat['HARGA_SEWA'] ?>',
                                        '<?= $alat['JUMLAH_TERSEDIA'] ?>'
                                    )"
                                    class="w-full bg-green-700 text-white py-2 rounded-md hover:bg-green-900 transition flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    Pinjam
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($daftarAlat)): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center text-yellow-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-3 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="text-lg font-medium mb-1">Tidak ada alat yang tersedia</p>
                        <p class="text-sm">Silakan coba dengan filter yang berbeda</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Peminjaman -->
    <div id="pinjamModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-start justify-center">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 p-6 mt-[40px] max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-green-800">Pinjam Alat</h2>
                <button id="closeModal" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="pinjam_alat" value="1">
                <input type="hidden" id="modal-alat-id" name="alat_id">
                <input type="hidden" id="total-biaya" name="total_biaya" value="0">

                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Nama Alat</label>
                    <input type="text" id="modal-nama-alat" readonly class="w-full px-3 py-2 bg-gray-100 rounded-md">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Jumlah Pinjam</label>
                    <div class="flex items-center">
                        <button type="button" onclick="decreaseAmount()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-1 rounded-l-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                            </svg>
                        </button>
                        <input type="number" id="jumlahPinjam" name="jumlah_pinjam" value="1" min="1" class="w-full text-center border-y border-gray-300 py-1 focus:outline-none focus:ring-2 focus:ring-green-500">
                        <button type="button" onclick="increaseAmount()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-1 rounded-r-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Tersedia: <span id="stokTersedia">0</span> unit</p>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Tanggal Mulai</label>
                        <input type="date" id="tanggalMulai" name="tanggal_mulai" required min="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Tanggal Selesai</label>
                        <input type="date" id="tanggalSelesai" name="tanggal_selesai" required min="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-gray-700 font-bold mb-2">Estimasi Biaya</label>
                    <input type="text" id="modal-harga" readonly class="w-full px-3 py-2 bg-gray-100 rounded-md">
                </div>

                <div class="mt-4">
                    <label class="block text-gray-700 font-bold mb-2">Metode Pembayaran</label>
                    <select name="metode_pembayaran" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">-- Pilih Metode Pembayaran --</option>
                        <option value="Transfer Bank">Transfer Bank (Mandiri - 1780004013)</option>
                        <option value="E-Wallet">E-Wallet (DANA, OVO, GoPay, ShopeePay - 082247219152)</option>
                    </select>
                    <p class="text-sm text-gray-700 italic">* Catatan: Pembayaran dapat dilakukan dengan uang muka (DP) atau pelunasan langsung.</p>
                </div>

                <div class="mt-4">
                    <label class="block text-gray-700 font-bold mb-2">Upload Bukti Pembayaran</label>
                    <div class="flex items-center justify-center w-full">
                        <label class="flex flex-col w-full h-32 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="mb-1 text-sm text-gray-500">Klik untuk pilih file</p>
                                <p class="text-xs text-gray-500">Gambar JPG/PNG (Maks. 2MB)</p>
                            </div>
                            <input type="file" name="bukti_pembayaran" class="hidden" accept="image/jpeg,image/png,image/jpg" required>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex items-center space-x-3">
                    <button type="submit" class="flex-1 bg-green-700 text-white py-3 rounded-md hover:bg-green-900 transition flex items-center justify-center">
                        Konfirmasi Peminjaman
                    </button>
                    <button type="button" id="batalkanBtn" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-md hover:bg-gray-300 transition">
                        Batalkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        let hargaSewaPerHari = 0;
        let maxJumlah = 0;

        function showPinjamModal(alatId, namaAlat, hargaSewa, stokTersedia) {
            document.getElementById('pinjamModal').classList.remove('hidden');
            document.getElementById('modal-alat-id').value = alatId;
            document.getElementById('modal-nama-alat').value = namaAlat;
            document.getElementById('stokTersedia').textContent = stokTersedia;
            document.getElementById('jumlahPinjam').value = 1;
            document.getElementById('jumlahPinjam').max = stokTersedia;

            // Setup tanggal
            const today = new Date();
            const tomorrow = new Date();
            tomorrow.setDate(today.getDate() + 1);

            document.getElementById('tanggalMulai').value = formatDate(today);
            document.getElementById('tanggalSelesai').value = formatDate(tomorrow);

            // Set harga
            hargaSewaPerHari = parseInt(hargaSewa);
            maxJumlah = parseInt(stokTersedia);
            updateHarga();
        }

        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function closeModal() {
            document.getElementById('pinjamModal').classList.add('hidden');
        }

        document.getElementById('closeModal').addEventListener('click', closeModal);
        document.getElementById('batalkanBtn').addEventListener('click', closeModal);

        function decreaseAmount() {
            const input = document.getElementById('jumlahPinjam');
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                updateHarga();
            }
        }

        function increaseAmount() {
            const input = document.getElementById('jumlahPinjam');
            if (parseInt(input.value) < maxJumlah) {
                input.value = parseInt(input.value) + 1;
                updateHarga();
            }
        }

        document.getElementById('jumlahPinjam').addEventListener('input', function() {
            if (parseInt(this.value) < 1) this.value = 1;
            if (parseInt(this.value) > maxJumlah) this.value = maxJumlah;
            updateHarga();
        });

        function updateHarga() {
            const tanggalMulai = new Date(document.getElementById('tanggalMulai').value);
            const tanggalSelesai = new Date(document.getElementById('tanggalSelesai').value);
            const jumlahPinjam = parseInt(document.getElementById('jumlahPinjam').value);

            // Validasi tanggal
            if (isNaN(tanggalMulai.getTime()) || isNaN(tanggalSelesai.getTime())) {
                document.getElementById('modal-harga').value = "Mohon isi tanggal dengan benar";
                return;
            }

            // Hitung selisih hari
            const diffTime = Math.abs(tanggalSelesai - tanggalMulai);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 karena menghitung termasuk hari awal

            // Hitung total harga
            const totalHarga = hargaSewaPerHari * diffDays * jumlahPinjam;
            document.getElementById('modal-harga').value = `Rp ${totalHarga.toLocaleString('id-ID')} (${jumlahPinjam} unit × ${diffDays} hari × Rp ${hargaSewaPerHari.toLocaleString('id-ID')})`;
            document.getElementById('total-biaya').value = totalHarga;
        }

        // Update harga saat tanggal berubah
        document.getElementById('tanggalMulai').addEventListener('change', updateHarga);
        document.getElementById('tanggalSelesai').addEventListener('change', updateHarga);

        // Initialize feather icons
        feather.replace();

        // Show file name when selected
        document.querySelector('input[type="file"]').addEventListener('change', function(e) {
            const fileName = e.target.files[0].name;
            const fileSize = e.target.files[0].size / (1024 * 1024); // Convert to MB

            if (fileSize > 2) {
                alert('Ukuran file terlalu besar. Maksimal 2MB');
                e.target.value = '';
                return;
            }

            const parent = e.target.parentElement;
            const textDiv = parent.querySelector('div');

            textDiv.innerHTML = `
                <svg class="w-8 h-8 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <p class="mb-1 text-sm text-gray-700">${fileName}</p>
                <p class="text-xs text-gray-500">${fileSize.toFixed(2)} MB</p>
            `;
        });
    </script>
</body>

</html>