<?php
session_start();
require_once '../config/database.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

class AlatPendakian {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function getDaftarAlat($filter = []) {
        $query = "SELECT a.*, k.nama_kategori 
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

        $stmt = oci_parse($this->conn, $query);

        // Bind parameter filter
        if (!empty($filter['kategori'])) {
            oci_bind_by_name($stmt, ':kategori', $filter['kategori']);
        }
        if (!empty($filter['kondisi'])) {
            oci_bind_by_name($stmt, ':kondisi', $filter['kondisi']);
        }

        oci_execute($stmt);

        $alat = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $alat[] = $row;
        }

        return $alat;
    }

    public function getKategori() {
        $query = "SELECT * FROM kategori_alat ORDER BY nama_kategori";
        
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);

        $kategori = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $kategori[] = $row;
        }

        return $kategori;
    }

    public function mulaiPeminjaman($user_id, $alat_id, $tanggal_mulai, $tanggal_selesai) {
        try {
            // Cek ketersediaan alat
            $query_cek = "SELECT jumlah_tersedia, harga_sewa FROM alat_mendaki WHERE alat_id = :alat_id FOR UPDATE";
            $stmt_cek = oci_parse($this->conn, $query_cek);
            oci_bind_by_name($stmt_cek, ':alat_id', $alat_id);
            oci_execute($stmt_cek, OCI_NO_AUTO_COMMIT);
            $alat = oci_fetch_assoc($stmt_cek);

            if ($alat['JUMLAH_TERSEDIA'] <= 0) {
                throw new Exception("Alat tidak tersedia");
            }

            // Hitung durasi peminjaman
            $tgl_mulai = new DateTime($tanggal_mulai);
            $tgl_selesai = new DateTime($tanggal_selesai);
            $durasi = $tgl_mulai->diff($tgl_selesai)->days + 1;
            $total_biaya = $alat['HARGA_SEWA'] * $durasi;

            // Buat record peminjaman
            $query_pinjam = "INSERT INTO peminjaman 
                             (peminjaman_id, user_id, tanggal_pinjam, status_peminjaman, total_biaya) 
                             VALUES 
                             (SEQ_PEMINJAMAN.NEXTVAL, :user_id, SYSDATE, 'Diajukan', :total_biaya)
                             RETURNING peminjaman_id INTO :peminjaman_id";
            $stmt_pinjam = oci_parse($this->conn, $query_pinjam);
            $peminjaman_id = 0;
            oci_bind_by_name($stmt_pinjam, ':user_id', $user_id);
            oci_bind_by_name($stmt_pinjam, ':total_biaya', $total_biaya);
            oci_bind_by_name($stmt_pinjam, ':peminjaman_id', $peminjaman_id, -1);
            oci_execute($stmt_pinjam, OCI_NO_AUTO_COMMIT);

            // Buat detail peminjaman
            $query_detail = "INSERT INTO detail_peminjaman 
                             (peminjaman_id, alat_id, jumlah_pinjam, tanggal_mulai, tanggal_selesai) 
                             VALUES 
                             (:peminjaman_id, :alat_id, 1, TO_DATE(:tanggal_mulai, 'YYYY-MM-DD'), TO_DATE(:tanggal_selesai, 'YYYY-MM-DD'))";
            $stmt_detail = oci_parse($this->conn, $query_detail);
            oci_bind_by_name($stmt_detail, ':peminjaman_id', $peminjaman_id);
            oci_bind_by_name($stmt_detail, ':alat_id', $alat_id);
            oci_bind_by_name($stmt_detail, ':tanggal_mulai', $tanggal_mulai);
            oci_bind_by_name($stmt_detail, ':tanggal_selesai', $tanggal_selesai);
            oci_execute($stmt_detail, OCI_NO_AUTO_COMMIT);

            // Kurangi jumlah alat tersedia
            $query_update = "UPDATE alat_mendaki 
                             SET jumlah_tersedia = jumlah_tersedia - 1 
                             WHERE alat_id = :alat_id";
            $stmt_update = oci_parse($this->conn, $query_update);
            oci_bind_by_name($stmt_update, ':alat_id', $alat_id);
            oci_execute($stmt_update, OCI_NO_AUTO_COMMIT);

            // Commit transaksi
            oci_commit($this->conn);
            return $peminjaman_id;

        } catch (Exception $e) {
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

$daftarAlat = $alatPendakian->getDaftarAlat($filter);
$kategori = $alatPendakian->getKategori();

// Proses peminjaman
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pinjam_alat'])) {
    $alat_id = $_POST['alat_id'];
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];

    $hasil_peminjaman = $alatPendakian->mulaiPeminjaman(
        $_SESSION['user_id'], 
        $alat_id, 
        $tanggal_mulai, 
        $tanggal_selesai
    );

    $pesan = $hasil_peminjaman ? 
        'Peminjaman berhasil diajukan. Menunggu persetujuan admin.' : 
        'Gagal mengajukan peminjaman. Alat mungkin sudah tidak tersedia.';
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
</head>
<body class="bg-gradient-to-br from-green-50 via-teal-50 to-blue-100 min-h-screen">
    <?php include '../includes/header.php'; ?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-100">
            <div class="bg-gradient-to-r from-green-600 to-teal-600 px-6 py-8">
                <h1 class="text-3xl font-bold text-white text-center flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mr-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                    Daftar Alat Pendakian
                </h1>
            </div>

            <div class="p-6">
                <?php if(isset($pesan)): ?>
                    <div class="<?= $hasil_peminjaman ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700' ?> px-4 py-3 rounded relative mb-4" role="alert">
                        <?= $pesan ?>
                    </div>
                <?php endif; ?>

                <form method="GET" class="mb-8 bg-gray-50 p-6 rounded-lg shadow-inner">
                    <div class="grid md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">Kategori</label>
                            <select name="kategori" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                                <option value="">Semua Kategori</option>
                                <?php foreach($kategori as $kat): ?>
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
                            <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                </svg>
                                Filter
                            </button>
                        </div>
                    </div>
                </form>

                <div class="grid md:grid-cols-3 gap-6">
                    <?php foreach($daftarAlat as $alat): ?>
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden transform transition hover:scale-105 hover:shadow-xl">
                            <div class="p-6">
                                <h3 class="text-xl font-bold text-green-800 mb-3"><?= $alat['NAMA_ALAT'] ?></h3>
                                <div class="space-y-2 text-gray-600 mb-4">
                                    <p><strong>Kategori:</strong> <?= $alat['NAMA_KATEGORI'] ?></p>
                                    <p><strong>Kondisi:</strong> 
                                        <span class="<?= 
                                            $alat['KONDISI'] == 'Baru' ? 'text-green-600' : 
                                            ($alat['KONDISI'] == 'Baik' ? 'text-blue-600' : 'text-yellow-600')
                                        ?>">
                                            <?= $alat['KONDISI'] ?>
                                        </span>
                                    </p>
                                    <p><strong>Tersedia:</strong> <?= $alat['JUMLAH_TERSEDIA'] ?> unit</p>
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
                                        '<?= $alat['HARGA_SEWA'] ?>'
                                    )" 
                                    class="w-full bg-green-500 text-white py-2 rounded-md hover:bg-green-600 transition flex items-center justify-center"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    Pinjam
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Peminjaman -->
    <div id="pinjamModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-green-800">Pinjam Alat</h2>
                <button id="closeModal" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="pinjam_alat" value="1">
                <input type="hidden" id="modal-alat-id" name="alat_id">
                
                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-2">Nama Alat</label>
                    <input type="text" id="modal-nama-alat" readonly class="w-full px-3 py-2 bg-gray-100 rounded-md">
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-gray-700 font-bold mb-2">Estimasi Biaya</label>
                    <input type="text" id="modal-harga" readonly class="w-full px-3 py-2 bg-gray-100 rounded-md">
                </div>

                <button type="submit" class="w-full mt-6 bg-green-600 text-white py-3 rounded-md hover:bg-green-700 transition">
                    Ajukan Peminjaman
                </button>
            </form>
        </div>
    </div>

    <script>
    function showPinjamModal(alatId, namaAlat, hargaSewa) {
        document.getElementById('modal-alat-id').value = alatId;
        document.getElementById('modal-nama-alat').value = namaAlat;
        document.getElementById('modal-harga').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(hargaSewa) + '/hari';
        
        // Tambahkan event listener untuk hitung estimasi biaya
        const tanggalMulai = document.querySelector('input[name="tanggal_mulai"]');
        const tanggalSelesai = document.querySelector('input[name="tanggal_selesai"]');
        
        [tanggalMulai, tanggalSelesai].forEach(input => {
            input.addEventListener('change', hitungEstimasi);
        });

        function hitungEstimasi() {
            if (tanggalMulai.value && tanggalSelesai.value) {
                const mulai = new Date(tanggalMulai.value);
                const selesai = new Date(tanggalSelesai.value);
                const durasi = Math.ceil((selesai - mulai) / (1000 * 60 * 60 * 24)) + 1;
                const totalBiaya = durasi * hargaSewa;
                
                document.getElementById('modal-harga').value = 
                    'Rp ' + new Intl.NumberFormat('id-ID').format(totalBiaya) + 
                    ' ('+durasi+' hari)';
            }
        }

        document.getElementById('pinjamModal').classList.remove('hidden');
    }

    // Tutup modal
    document.getElementById('closeModal').onclick = function() {
        document.getElementById('pinjamModal').classList.add('hidden');
    }

    // Tutup modal jika diklik di luar modal
    window.onclick = function(event) {
        const modal = document.getElementById('pinjamModal');
        if (event.target == modal) {
            modal.classList.add('hidden');
        }
    }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>