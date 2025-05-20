<?php
session_start();
require_once '../config/database.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

class ManajemenAlat {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function getKategori() {
        $query = "SELECT KATEGORI_ID, NAMA_KATEGORI FROM kategori_alat";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);

        $kategori = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $kategori[] = $row;
        }
        return $kategori;
    }

    public function getDaftarAlat() {
        $query = "SELECT a.*, k.NAMA_KATEGORI 
                  FROM alat_mendaki a
                  JOIN kategori_alat k ON a.KATEGORI_ID = k.KATEGORI_ID";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);

        $daftarAlat = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $daftarAlat[] = $row;
        }
        return $daftarAlat;
    }

    public function tambahAlat($data) {
        $query = "INSERT INTO alat_mendaki 
                  (KATEGORI_ID, NAMA_ALAT, DESKRIPSI, JUMLAH_TOTAL, JUMLAH_TERSEDIA, KONDISI, HARGA_SEWA) 
                  VALUES 
                  (:kategori_id, :nama_alat, :deskripsi, :jumlah_total, :jumlah_total, :kondisi, :harga_sewa)";
        
        $stmt = oci_parse($this->conn, $query);
        
        oci_bind_by_name($stmt, ':kategori_id', $data['kategori_id']);
        oci_bind_by_name($stmt, ':nama_alat', $data['nama_alat']);
        oci_bind_by_name($stmt, ':deskripsi', $data['deskripsi']);
        oci_bind_by_name($stmt, ':jumlah_total', $data['jumlah_total']);
        oci_bind_by_name($stmt, ':kondisi', $data['kondisi']);
        oci_bind_by_name($stmt, ':harga_sewa', $data['harga_sewa']);

        $result = oci_execute($stmt);
        
        return [
            'result' => $result,
            'pesan' => $result ? 'Alat berhasil ditambahkan' : 'Gagal menambahkan alat'
        ];
    }

    // Fungsi untuk mengambil detail alat berdasarkan ID
    public function getAlatById($id) {
        $query = "SELECT * FROM alat_mendaki WHERE ALAT_ID = :id";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        oci_execute($stmt);

        return oci_fetch_assoc($stmt);
    }

    // Fungsi untuk mengupdate alat
    public function updateAlat($data) {
        $query = "UPDATE alat_mendaki 
                  SET KATEGORI_ID = :kategori_id, 
                      NAMA_ALAT = :nama_alat, 
                      DESKRIPSI = :deskripsi, 
                      JUMLAH_TOTAL = :jumlah_total, 
                      KONDISI = :kondisi, 
                      HARGA_SEWA = :harga_sewa
                  WHERE ALAT_ID = :alat_id";
        
        $stmt = oci_parse($this->conn, $query);
        
        oci_bind_by_name($stmt, ':kategori_id', $data['kategori_id']);
        oci_bind_by_name($stmt, ':nama_alat', $data['nama_alat']);
        oci_bind_by_name($stmt, ':deskripsi', $data['deskripsi']);
        oci_bind_by_name($stmt, ':jumlah_total', $data['jumlah_total']);
        oci_bind_by_name($stmt, ':kondisi', $data['kondisi']);
        oci_bind_by_name($stmt, ':harga_sewa', $data['harga_sewa']);
        oci_bind_by_name($stmt, ':alat_id', $data['alat_id']);

        $result = oci_execute($stmt);
        
        return [
            'result' => $result,
            'pesan' => $result ? 'Alat berhasil diperbarui' : 'Gagal memperbarui alat'
        ];
    }

    // Fungsi baru untuk menghapus alat
    public function hapusAlat($id) {
        $query = "DELETE FROM alat_mendaki WHERE ALAT_ID = :id";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        $result = oci_execute($stmt);
        
        return [
            'result' => $result,
            'pesan' => $result ? 'Alat berhasil dihapus' : 'Gagal menghapus alat'
        ];
    }
}

$database = new Database();
$manajemenAlat = new ManajemenAlat($database);

// Proses tambah alat jika form disubmit
$result = false;
$pesan = '';
$editMode = false;
$alatEdit = null;

// Proses hapus alat
if (isset($_GET['hapus'])) {
    $response = $manajemenAlat->hapusAlat($_GET['hapus']);
    $result = $response['result'];
    $pesan = $response['pesan'];
    
    // Redirect kembali ke halaman utama setelah hapus
    if ($result) {
        header('Location: manajemen_alat.php?msg=' . urlencode($pesan));
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cek apakah ini proses edit atau tambah
    if (isset($_POST['alat_id'])) {
        // Mode Edit
        $data = [
            'alat_id' => $_POST['alat_id'],
            'kategori_id' => $_POST['kategori_id'],
            'nama_alat' => $_POST['nama_alat'],
            'deskripsi' => $_POST['deskripsi'],
            'jumlah_total' => $_POST['jumlah_total'],
            'kondisi' => $_POST['kondisi'],
            'harga_sewa' => $_POST['harga_sewa']
        ];

        $response = $manajemenAlat->updateAlat($data);
    } else {
        // Mode Tambah
        $data = [
            'kategori_id' => $_POST['kategori_id'],
            'nama_alat' => $_POST['nama_alat'],
            'deskripsi' => $_POST['deskripsi'],
            'jumlah_total' => $_POST['jumlah_total'],
            'kondisi' => $_POST['kondisi'],
            'harga_sewa' => $_POST['harga_sewa']
        ];

        $response = $manajemenAlat->tambahAlat($data);
    }

    $result = $response['result'];
    $pesan = $response['pesan'];
}

// Cek apakah ada parameter edit
if (isset($_GET['edit'])) {
    $editMode = true;
    $alatEdit = $manajemenAlat->getAlatById($_GET['edit']);
}

// Cek apakah ada pesan dari redirect setelah hapus
if (isset($_GET['msg'])) {
    $pesan = $_GET['msg'];
    $result = true;
}

$kategori = $manajemenAlat->getKategori();
$daftarAlat = $manajemenAlat->getDaftarAlat();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Alat - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'components/sidebar.php'; ?>

    <!-- Main Content with responsive margin -->
    <div class="ml-0 md:ml-64 p-4 md:p-8 pt-16 md:pt-8">
        <div class="container mx-auto">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-4 md:mb-6">Manajemen Alat Pendakian</h1>

            <?php if(!empty($pesan)): ?>
                <div class="<?= $result ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> p-4 rounded mb-4 md:mb-6">
                    <?= $pesan ?>
                </div>
            <?php endif; ?>

            <!-- Responsive Layout: Stack on mobile, side-by-side on desktop -->
            <div class="space-y-6 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-6">
                <!-- Form Section -->
                <div class="lg:col-span-1 order-2 lg:order-1">
                    <div class="bg-white p-4 md:p-6 rounded-lg shadow-md">
                        <h2 class="text-lg md:text-xl font-semibold mb-4">
                            <?= $editMode ? 'Edit Alat' : 'Tambah Alat Baru' ?>
                        </h2>
                        <form method="POST" class="space-y-4">
                            <?php if ($editMode): ?>
                                <input type="hidden" name="alat_id" value="<?= $alatEdit['ALAT_ID'] ?>">
                            <?php endif; ?>

                            <div>
                                <label class="block text-gray-700 mb-2 text-sm md:text-base">Kategori Alat</label>
                                <select name="kategori_id" required class="w-full px-3 py-2 border rounded-md text-sm md:text-base">
                                    <?php foreach($kategori as $kat): ?>
                                        <option value="<?= $kat['KATEGORI_ID'] ?>" 
                                            <?= $editMode && $alatEdit['KATEGORI_ID'] == $kat['KATEGORI_ID'] ? 'selected' : '' ?>>
                                            <?= $kat['NAMA_KATEGORI'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2 text-sm md:text-base">Nama Alat</label>
                                <input type="text" name="nama_alat" required 
                                       class="w-full px-3 py-2 border rounded-md text-sm md:text-base"
                                       value="<?= $editMode ? $alatEdit['NAMA_ALAT'] : '' ?>">
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2 text-sm md:text-base">Deskripsi</label>
                                <textarea name="deskripsi" rows="3"
                                          class="w-full px-3 py-2 border rounded-md text-sm md:text-base"><?= $editMode ? $alatEdit['DESKRIPSI'] : '' ?></textarea>
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2 text-sm md:text-base">Jumlah Total</label>
                                <input type="number" name="jumlah_total" required 
                                       class="w-full px-3 py-2 border rounded-md text-sm md:text-base"
                                       value="<?= $editMode ? $alatEdit['JUMLAH_TOTAL'] : '' ?>">
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2 text-sm md:text-base">Kondisi</label>
                                <select name="kondisi" required class="w-full px-3 py-2 border rounded-md text-sm md:text-base">
                                    <option value="Baru" <?= $editMode && $alatEdit['KONDISI'] == 'Baru' ? 'selected' : '' ?>>Baru</option>
                                    <option value="Baik" <?= $editMode && $alatEdit['KONDISI'] == 'Baik' ? 'selected' : '' ?>>Baik</option>
                                    <option value="Cukup" <?= $editMode && $alatEdit['KONDISI'] == 'Cukup' ? 'selected' : '' ?>>Cukup</option>
                                    <option value="Rusak" <?= $editMode && $alatEdit['KONDISI'] == 'Rusak' ? 'selected' : '' ?>>Rusak</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2 text-sm md:text-base">Harga Sewa (Rp)</label>
                                <input type="number" name="harga_sewa" required 
                                       class="w-full px-3 py-2 border rounded-md text-sm md:text-base"
                                       value="<?= $editMode ? $alatEdit['HARGA_SEWA'] : '' ?>">
                            </div>

                            <button type="submit" 
                                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition text-sm md:text-base">
                                <?= $editMode ? 'Perbarui Alat' : 'Tambah Alat' ?>
                            </button>
                            
                            <?php if ($editMode): ?>
                                <a href="manajemen_alat.php" 
                                   class="w-full block text-center bg-gray-200 text-gray-700 py-2 px-4 rounded-md mt-2 hover:bg-gray-300 transition text-sm md:text-base">
                                    Batalkan Edit
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Table Section -->
                <div class="lg:col-span-2 order-1 lg:order-2">
                    <div class="bg-white p-4 md:p-6 rounded-lg shadow-md">
                        <h2 class="text-lg md:text-xl font-semibold mb-4">Daftar Alat</h2>
                        
                        <!-- Mobile View (Card Layout) -->
                        <div class="lg:hidden space-y-4">
                            <?php foreach($daftarAlat as $alat): ?>
                            <div class="bg-gray-50 p-4 rounded-lg border">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="font-semibold text-gray-800"><?= $alat['NAMA_ALAT'] ?></h3>
                                    <div class="flex space-x-2">
                                        <a href="?edit=<?= $alat['ALAT_ID'] ?>" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-edit text-sm"></i>
                                        </a>
                                        <a href="#" onclick="konfirmasiHapus(<?= $alat['ALAT_ID'] ?>, '<?= $alat['NAMA_ALAT'] ?>')" 
                                           class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash text-sm"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <div><span class="font-medium">Kategori:</span> <?= $alat['NAMA_KATEGORI'] ?></div>
                                    <div class="flex justify-between">
                                        <span><span class="font-medium">Total:</span> <?= $alat['JUMLAH_TOTAL'] ?></span>
                                        <span><span class="font-medium">Tersedia:</span> <?= $alat['JUMLAH_TERSEDIA'] ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="<?= 
                                            $alat['KONDISI'] == 'Baru' ? 'bg-green-100 text-green-800' : 
                                            ($alat['KONDISI'] == 'Baik' ? 'bg-blue-100 text-blue-800' : 
                                            ($alat['KONDISI'] == 'Cukup' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'))
                                        ?> px-2 py-1 rounded-full text-xs">
                                            <?= $alat['KONDISI'] ?>
                                        </span>
                                        <span class="font-semibold text-green-600">
                                            Rp <?= number_format($alat['HARGA_SEWA'], 0, ',', '.') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Desktop View (Table Layout) -->
                        <div class="hidden lg:block overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-100 text-gray-600">
                                        <th class="py-2 px-4 text-left">Nama Alat</th>
                                        <th class="py-2 px-4 text-left">Kategori</th>
                                        <th class="py-2 px-4 text-left">Jumlah Total</th>
                                        <th class="py-2 px-4 text-left">Tersedia</th>
                                        <th class="py-2 px-4 text-left">Kondisi</th>
                                        <th class="py-2 px-4 text-left">Harga Sewa</th>
                                        <th class="py-2 px-4 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($daftarAlat as $alat): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="py-3 px-4"><?= $alat['NAMA_ALAT'] ?></td>
                                        <td class="py-3 px-4"><?= $alat['NAMA_KATEGORI'] ?></td>
                                        <td class="py-3 px-4"><?= $alat['JUMLAH_TOTAL'] ?></td>
                                        <td class="py-3 px-4"><?= $alat['JUMLAH_TERSEDIA'] ?></td>
                                        <td class="py-3 px-4">
                                            <span class="<?= 
                                                $alat['KONDISI'] == 'Baru' ? 'bg-green-100 text-green-800' : 
                                                ($alat['KONDISI'] == 'Baik' ? 'bg-blue-100 text-blue-800' : 
                                                ($alat['KONDISI'] == 'Cukup' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'))
                                            ?> px-2 py-1 rounded-full text-xs">
                                                <?= $alat['KONDISI'] ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            Rp <?= number_format($alat['HARGA_SEWA'], 0, ',', '.') ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <a href="?edit=<?= $alat['ALAT_ID'] ?>" 
                                               class="text-blue-600 hover:text-blue-800 mx-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" onclick="konfirmasiHapus(<?= $alat['ALAT_ID'] ?>, '<?= $alat['NAMA_ALAT'] ?>')" 
                                               class="text-red-600 hover:text-red-800 mx-1">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div id="modalHapus" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white p-4 md:p-6 rounded-lg shadow-lg max-w-md w-full">
            <h3 class="text-lg md:text-xl font-bold mb-4">Konfirmasi Hapus</h3>
            <p id="pesanKonfirmasi" class="mb-6 text-sm md:text-base">Apakah Anda yakin ingin menghapus alat ini?</p>
            <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-4">
                <button onclick="tutupModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-sm md:text-base">
                    Batal
                </button>
                <a id="btnHapus" href="#" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-center text-sm md:text-base">
                    Hapus
                </a>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>

    <script>
        function konfirmasiHapus(id, nama) {
            document.getElementById('modalHapus').classList.remove('hidden');
            document.getElementById('pesanKonfirmasi').textContent = `Apakah Anda yakin ingin menghapus alat "${nama}"?`;
            document.getElementById('btnHapus').setAttribute('href', `?hapus=${id}`);
        }

        function tutupModal() {
            document.getElementById('modalHapus').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('modalHapus').addEventListener('click', function(e) {
            if (e.target === this) {
                tutupModal();
            }
        });
    </script>
</body>
</html>