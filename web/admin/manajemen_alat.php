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
        // PERBAIKAN: Pastikan format data benar untuk DB Oracle
        // Konversi nilai-nilai numerik yang mungkin diterima sebagai string
        $jumlah_total = (int)$data['jumlah_total'];
        $harga_sewa = (float)$data['harga_sewa'];
        
        $query = "INSERT INTO alat_mendaki 
                  (KATEGORI_ID, NAMA_ALAT, DESKRIPSI, JUMLAH_TOTAL, JUMLAH_TERSEDIA, KONDISI, HARGA_SEWA) 
                  VALUES 
                  (:kategori_id, :nama_alat, :deskripsi, :jumlah_total, :jumlah_total, :kondisi, :harga_sewa)";
        
        $stmt = oci_parse($this->conn, $query);
        
        oci_bind_by_name($stmt, ':kategori_id', $data['kategori_id']);
        oci_bind_by_name($stmt, ':nama_alat', $data['nama_alat']);
        oci_bind_by_name($stmt, ':deskripsi', $data['deskripsi']);
        oci_bind_by_name($stmt, ':jumlah_total', $jumlah_total);
        oci_bind_by_name($stmt, ':kondisi', $data['kondisi']);
        oci_bind_by_name($stmt, ':harga_sewa', $harga_sewa);

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

    // PERBAIKAN: Fungsi updateAlat diperbarui untuk menangani jumlah stok dengan benar
    public function updateAlat($data) {
        // Konversi nilai-nilai numerik yang mungkin diterima sebagai string
        $jumlah_total_baru = (int)$data['jumlah_total'];
        $harga_sewa = (float)$data['harga_sewa'];
        
        // Get current data
        $query_check = "SELECT JUMLAH_TERSEDIA, JUMLAH_TOTAL FROM alat_mendaki WHERE ALAT_ID = :alat_id";
        $stmt_check = oci_parse($this->conn, $query_check);
        oci_bind_by_name($stmt_check, ':alat_id', $data['alat_id']);
        oci_execute($stmt_check);
        $current_data = oci_fetch_assoc($stmt_check);
        
        $jumlah_total_lama = (int)$current_data['JUMLAH_TOTAL'];
        $jumlah_tersedia_lama = (int)$current_data['JUMLAH_TERSEDIA'];
        
        // Hitung berapa banyak alat yang sedang dipinjam
        $sedang_dipinjam = $jumlah_total_lama - $jumlah_tersedia_lama;
        
        // Tentukan jumlah yang tersedia baru
        // PERBAIKAN: Pastikan jumlah tersedia tidak pernah melebihi jumlah total
        $jumlah_tersedia_baru = $jumlah_total_baru - $sedang_dipinjam;
        
        // Validasi untuk memastikan stok tersedia tidak negatif
        if ($jumlah_tersedia_baru < 0) {
            return [
                'result' => false,
                'pesan' => 'Gagal memperbarui alat. Jumlah total tidak boleh kurang dari jumlah yang sedang dipinjam (' . $sedang_dipinjam . ').'
            ];
        }
        
        // Log debugging
        error_log("Update Alat ID: " . $data['alat_id']);
        error_log("Jumlah total lama: $jumlah_total_lama, Jumlah tersedia lama: $jumlah_tersedia_lama");
        error_log("Jumlah total baru: $jumlah_total_baru, Jumlah tersedia baru: $jumlah_tersedia_baru");
        error_log("Sedang dipinjam: $sedang_dipinjam");
        
        $query = "UPDATE alat_mendaki 
                  SET KATEGORI_ID = :kategori_id, 
                      NAMA_ALAT = :nama_alat, 
                      DESKRIPSI = :deskripsi, 
                      JUMLAH_TOTAL = :jumlah_total,
                      JUMLAH_TERSEDIA = :jumlah_tersedia,
                      KONDISI = :kondisi, 
                      HARGA_SEWA = :harga_sewa
                  WHERE ALAT_ID = :alat_id";
        
        $stmt = oci_parse($this->conn, $query);
        
        oci_bind_by_name($stmt, ':kategori_id', $data['kategori_id']);
        oci_bind_by_name($stmt, ':nama_alat', $data['nama_alat']);
        oci_bind_by_name($stmt, ':deskripsi', $data['deskripsi']);
        oci_bind_by_name($stmt, ':jumlah_total', $jumlah_total_baru);
        oci_bind_by_name($stmt, ':jumlah_tersedia', $jumlah_tersedia_baru);
        oci_bind_by_name($stmt, ':kondisi', $data['kondisi']);
        oci_bind_by_name($stmt, ':harga_sewa', $harga_sewa);
        oci_bind_by_name($stmt, ':alat_id', $data['alat_id']);

        $result = oci_execute($stmt);
        
        return [
            'result' => $result,
            'pesan' => $result ? 'Alat berhasil diperbarui' : 'Gagal memperbarui alat'
        ];
    }

    // Force delete the equipment regardless of dependencies
    public function hapusAlat($id) {
        // First, check if there are dependencies in detail_peminjaman
        $query_check = "SELECT COUNT(*) as TOTAL FROM detail_peminjaman WHERE ALAT_ID = :id";
        $stmt_check = oci_parse($this->conn, $query_check);
        oci_bind_by_name($stmt_check, ':id', $id);
        oci_execute($stmt_check);
        $result_check = oci_fetch_assoc($stmt_check);
        
        // If there are dependencies, delete them first
        if ($result_check['TOTAL'] > 0) {
            $query_delete_dependencies = "DELETE FROM detail_peminjaman WHERE ALAT_ID = :id";
            $stmt_delete_dependencies = oci_parse($this->conn, $query_delete_dependencies);
            oci_bind_by_name($stmt_delete_dependencies, ':id', $id);
            oci_execute($stmt_delete_dependencies);
        }
        
        // Now delete the equipment
        $query = "DELETE FROM alat_mendaki WHERE ALAT_ID = :id";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        $result = oci_execute($stmt);
        
        return [
            'result' => $result,
            'pesan' => $result ? 'Alat berhasil dihapus' : 'Gagal menghapus alat'
        ];
    }
    
    // Fungsi untuk menonaktifkan alat sebagai alternatif penghapusan
    public function nonaktifkanAlat($id) {
        $query = "UPDATE alat_mendaki SET JUMLAH_TERSEDIA = 0 WHERE ALAT_ID = :id";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        $result = oci_execute($stmt);
        
        return [
            'result' => $result,
            'pesan' => $result ? 'Alat berhasil dinonaktifkan (stok tersedia = 0)' : 'Gagal menonaktifkan alat'
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

// Proses nonaktifkan alat 
if (isset($_GET['nonaktifkan'])) {
    $response = $manajemenAlat->nonaktifkanAlat($_GET['nonaktifkan']);
    $result = $response['result'];
    $pesan = $response['pesan'];
    
    // Redirect kembali ke halaman utama
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
    <div class="ml-0 md:ml-64 p-4 md:p-8 pt-16 md:pt-8 transition-all duration-300">
        <div class="container mx-auto">
            <!-- Header with stats overview -->
            <div class="bg-gradient-to-r from-gray-800 to-gray-800 rounded-lg shadow-lg p-6 mb-6 text-white">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold mb-2">Manajemen Alat Pendakian</h1>
                        <p class="opacity-80">Kelola inventaris alat pendakian dengan mudah</p>
                    </div>
                    <div class="mt-4 md:mt-0 grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div class="bg-white bg-opacity-20 p-3 rounded-lg text-center">
                            <p class="text-xs uppercase tracking-wider">Total Alat</p>
                            <p class="text-2xl font-bold"><?= count($daftarAlat) ?></p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-lg text-center">
                            <p class="text-xs uppercase tracking-wider">Tersedia</p>
                            <p class="text-2xl font-bold"><?php 
                                $tersedia = 0;
                                foreach($daftarAlat as $alat) {
                                    $tersedia += $alat['JUMLAH_TERSEDIA'];
                                }
                                echo $tersedia;
                            ?></p>
                        </div>
                        <div class="hidden md:block bg-white bg-opacity-20 p-3 rounded-lg text-center">
                            <p class="text-xs uppercase tracking-wider">Kategori</p>
                            <p class="text-2xl font-bold"><?= count($kategori) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if(!empty($pesan)): ?>
                <div class="<?= $result ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> p-4 rounded mb-6 shadow-md border-l-4 <?= $result ? 'border-green-500' : 'border-red-500' ?>">
                    <div class="flex items-center">
                        <i class="fas <?= $result ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                        <p><?= $pesan ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Search and Filter Bar -->
            <div class="bg-white p-4 rounded-lg shadow-md mb-6">
                <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4">
                    <!-- Search -->
                    <div class="flex-grow">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Cari alat..." 
                                   class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- Filter by Category -->
                    <div class="w-full md:w-1/4">
                        <select id="kategoriFilter" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Kategori</option>
                            <?php foreach($kategori as $kat): ?>
                                <option value="<?= $kat['NAMA_KATEGORI'] ?>"><?= $kat['NAMA_KATEGORI'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Filter by Condition -->
                    <div class="w-full md:w-1/4">
                        <select id="kondisiFilter" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Kondisi</option>
                            <option value="Baru">Baru</option>
                            <option value="Baik">Baik</option>
                            <option value="Cukup">Cukup</option>
                            <option value="Rusak">Rusak</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Responsive Layout: Stack on mobile, side-by-side on desktop -->
            <div class="space-y-6 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-6">
                <!-- Table Section -->
                <div class="lg:col-span-2 order-1">
                    <div class="bg-white p-4 md:p-6 rounded-lg shadow-md">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg md:text-xl font-semibold">Daftar Alat</h2>
                            <div class="flex space-x-2">
                                <button id="viewToggle" class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600">
                                    <i class="fas fa-th-list"></i>
                                </button>
                                <button class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600" onclick="sortItems()">
                                    <i class="fas fa-sort-amount-down"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Card View -->
                        <div id="cardView" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php foreach($daftarAlat as $alat): ?>
                            <div class="bg-white p-4 rounded-lg border hover:shadow-md transition-all duration-200 alat-item"
                                 data-nama="<?= strtolower($alat['NAMA_ALAT']) ?>" 
                                 data-kategori="<?= strtolower($alat['NAMA_KATEGORI']) ?>" 
                                 data-kondisi="<?= strtolower($alat['KONDISI']) ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="font-semibold text-gray-800"><?= $alat['NAMA_ALAT'] ?></h3>
                                    <div class="flex space-x-2">
                                        <a href="?edit=<?= $alat['ALAT_ID'] ?>" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" onclick="konfirmasiHapus(<?= $alat['ALAT_ID'] ?>, '<?= $alat['NAMA_ALAT'] ?>')" 
                                           class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-600 space-y-2">
                                    <div class="inline-block bg-blue-50 text-blue-700 rounded-full px-3 py-1 text-xs">
                                        <?= $alat['NAMA_KATEGORI'] ?>
                                    </div>
                                    <div class="flex justify-between mt-2">
                                        <div class="flex items-center">
                                            <i class="fas fa-box-open mr-1 text-gray-500"></i>
                                            <span><?= $alat['JUMLAH_TOTAL'] ?> total</span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-check-circle mr-1 <?= $alat['JUMLAH_TERSEDIA'] > 0 ? 'text-green-500' : 'text-red-500' ?>"></i>
                                            <span><?= $alat['JUMLAH_TERSEDIA'] ?> tersedia</span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center mt-2">
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

                        <!-- Table View (hidden by default) -->
                        <div id="tableView" class="hidden overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-100 text-gray-600 border-b-2 border-gray-200">
                                        <th class="py-3 px-4 text-left">Nama Alat</th>
                                        <th class="py-3 px-4 text-left">Kategori</th>
                                        <th class="py-3 px-4 text-center">Jumlah</th>
                                        <th class="py-3 px-4 text-center">Kondisi</th>
                                        <th class="py-3 px-4 text-right">Harga Sewa</th>
                                        <th class="py-3 px-4 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($daftarAlat as $alat): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50 alat-item"
                                        data-nama="<?= strtolower($alat['NAMA_ALAT']) ?>" 
                                        data-kategori="<?= strtolower($alat['NAMA_KATEGORI']) ?>" 
                                        data-kondisi="<?= strtolower($alat['KONDISI']) ?>">
                                        <td class="py-3 px-4 font-medium"><?= $alat['NAMA_ALAT'] ?></td>
                                        <td class="py-3 px-4"><?= $alat['NAMA_KATEGORI'] ?></td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="text-gray-700"><?= $alat['JUMLAH_TOTAL'] ?></span> 
                                            <span class="text-sm text-gray-500">(<?= $alat['JUMLAH_TERSEDIA'] ?> tersedia)</span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="<?= 
                                                $alat['KONDISI'] == 'Baru' ? 'bg-green-100 text-green-800' : 
                                                ($alat['KONDISI'] == 'Baik' ? 'bg-blue-100 text-blue-800' : 
                                                ($alat['KONDISI'] == 'Cukup' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'))
                                            ?> px-2 py-1 rounded-full text-xs">
                                                <?= $alat['KONDISI'] ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right font-semibold text-green-600">
                                            Rp <?= number_format($alat['HARGA_SEWA'], 0, ',', '.') ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <a href="?edit=<?= $alat['ALAT_ID'] ?>" 
                                               class="bg-blue-100 text-blue-600 hover:bg-blue-200 p-1 rounded inline-block mx-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" onclick="konfirmasiHapus(<?= $alat['ALAT_ID'] ?>, '<?= $alat['NAMA_ALAT'] ?>')" 
                                               class="bg-red-100 text-red-600 hover:bg-red-200 p-1 rounded inline-block mx-1">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty state when no results found -->
                        <div id="emptyState" class="hidden py-8 text-center">
                            <i class="fas fa-search text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">Tidak ada alat yang ditemukan</p>
                            <p class="text-gray-400 text-sm">Coba ubah filter atau kata kunci pencarian</p>
                        </div>
                    </div>
                </div>

                <!-- Form Section -->
                <div class="lg:col-span-1 order-2">
                    <div class="bg-white p-4 md:p-6 rounded-lg shadow-md sticky top-4">
                        <div class="flex items-center mb-4">
                            <div class="bg-blue-100 text-blue-800 p-2 rounded-lg mr-3">
                                <i class="fas <?= $editMode ? 'fa-edit' : 'fa-plus' ?>"></i>
                            </div>
                            <h2 class="text-lg md:text-xl font-semibold">
                                <?= $editMode ? 'Edit Alat' : 'Tambah Alat Baru' ?>
                            </h2>
                        </div>
                        
                        <form method="POST" class="space-y-4">
                            <?php if ($editMode): ?>
                                <input type="hidden" name="alat_id" value="<?= $alatEdit['ALAT_ID'] ?>">
                            <?php endif; ?>

                            <div>
                                <label class="block text-gray-700 mb-2 text-sm md:text-base font-medium">Kategori Alat</label>
                                <select name="kategori_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm md:text-base focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <?php foreach($kategori as $kat): ?>
                                        <option value="<?= $kat['KATEGORI_ID'] ?>" 
                                            <?= $editMode && $alatEdit['KATEGORI_ID'] == $kat['KATEGORI_ID'] ? 'selected' : '' ?>>
                                            <?= $kat['NAMA_KATEGORI'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2 text-sm md:text-base font-medium">Nama Alat</label>
                                <input type="text" name="nama_alat" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm md:text-base focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="<?= $editMode ? $alatEdit['NAMA_ALAT'] : '' ?>">
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2 text-sm md:text-base font-medium">Deskripsi</label>
                                <textarea name="deskripsi" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm md:text-base focus:outline-none focus:ring-2 focus:ring-blue-500"><?= $editMode ? $alatEdit['DESKRIPSI'] : '' ?></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm md:text-base font-medium">Jumlah Total</label>
                                    <input type="number" name="jumlah_total" required 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm md:text-base focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           value="<?= $editMode ? $alatEdit['JUMLAH_TOTAL'] : '' ?>">
                                </div>

                                <div>
                                    <label class="block text-gray-700 mb-2 text-sm md:text-base font-medium">Kondisi</label>
                                    <select name="kondisi" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm md:text-base focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="Baru" <?= $editMode && $alatEdit['KONDISI'] == 'Baru' ? 'selected' : '' ?>>Baru</option>
                                        <option value="Baik" <?= $editMode && $alatEdit['KONDISI'] == 'Baik' ? 'selected' : '' ?>>Baik</option>
                                        <option value="Cukup" <?= $editMode && $alatEdit['KONDISI'] == 'Cukup' ? 'selected' : '' ?>>Cukup</option>
                                        <option value="Rusak" <?= $editMode && $alatEdit['KONDISI'] == 'Rusak' ? 'selected' : '' ?>>Rusak</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2 text-sm md:text-base font-medium">Harga Sewa (Rp)</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">Rp</span>
                                    <input type="number" name="harga_sewa" required 
                                           class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-md text-sm md:text-base focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           value="<?= $editMode ? $alatEdit['HARGA_SEWA'] : '' ?>">
                                </div>
                            </div>

                            <button type="submit" 
                                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition text-sm md:text-base flex justify-center items-center">
                                <i class="fas <?= $editMode ? 'fa-save' : 'fa-plus' ?> mr-2"></i>
                                <?= $editMode ? 'Perbarui Alat' : 'Tambah Alat' ?>
                            </button>
                            
                            <?php if ($editMode): ?>
                                <a href="manajemen_alat.php" 
                                   class="w-full block text-center bg-gray-200 text-gray-700 py-2 px-4 rounded-md mt-2 hover:bg-gray-300 transition text-sm md:text-base">
                                    <i class="fas fa-times mr-2"></i> Batalkan Edit
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div id="modalHapus" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full transform transition-all">
            <div class="text-center mb-4">
                <div class="bg-red-100 inline-flex p-3 rounded-full text-red-500 mb-4">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Konfirmasi Hapus</h3>
                <p id="pesanKonfirmasi" class="text-gray-600">Apakah Anda yakin ingin menghapus alat ini?</p>
            </div>
            <div class="flex flex-col sm:flex-row justify-center space-y-2 sm:space-y-0 sm:space-x-4">
                <button onclick="tutupModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-sm md:text-base">
                    <i class="fas fa-times mr-2"></i> Batal
                </button>
                <a id="btnHapus" href="#" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-center text-sm md:text-base">
                    <i class="fas fa-trash mr-2"></i> Hapus
                </a>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>

    <script>
        // Toggle view (card/table)
        const viewToggle = document.getElementById('viewToggle');
        const cardView = document.getElementById('cardView');
        const tableView = document.getElementById('tableView');
        
        viewToggle.addEventListener('click', function() {
            if (cardView.classList.contains('hidden')) {
                cardView.classList.remove('hidden');
                tableView.classList.add('hidden');
                viewToggle.innerHTML = '<i class="fas fa-th-list"></i>';
            } else {
                cardView.classList.add('hidden');
                tableView.classList.remove('hidden');
                viewToggle.innerHTML = '<i class="fas fa-th"></i>';
            }
        });
        
        // Search & Filter functionality
        const searchInput = document.getElementById('searchInput');
        const kategoriFilter = document.getElementById('kategoriFilter');
        const kondisiFilter = document.getElementById('kondisiFilter');
        const alatItems = document.querySelectorAll('.alat-item');
        const emptyState = document.getElementById('emptyState');
        
        function filterItems() {
            const searchTerm = searchInput.value.toLowerCase();
            const kategori = kategoriFilter.value.toLowerCase();
            const kondisi = kondisiFilter.value.toLowerCase();
            
            let visibleCount = 0;
            
            alatItems.forEach(item => {
                const matchesSearch = item.dataset.nama.includes(searchTerm);
                const matchesKategori = kategori === '' || item.dataset.kategori === kategori;
                const matchesKondisi = kondisi === '' || item.dataset.kondisi === kondisi;
                
                if (matchesSearch && matchesKategori && matchesKondisi) {
                    item.classList.remove('hidden');
                    visibleCount++;
                } else {
                    item.classList.add('hidden');
                }
            });
            
            // Show empty state if no results
            if (visibleCount === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
            }
        }
        
        searchInput.addEventListener('input', filterItems);
        kategoriFilter.addEventListener('change', filterItems);
        kondisiFilter.addEventListener('change', filterItems);
        
        // Sort functionality (toggle between price ascending/descending)
        let sortAscending = true;
        
        function sortItems() {
            const itemsArray = Array.from(alatItems);
            const sortedItems = itemsArray.sort((a, b) => {
                // For simplicity, we're sorting just on name
                // You could enhance this to sort by various properties
                const aName = a.dataset.nama;
                const bName = b.dataset.nama;
                
                if (sortAscending) {
                    return aName.localeCompare(bName);
                } else {
                    return bName.localeCompare(aName);
                }
            });
            
            sortAscending = !sortAscending;
            
            // Update the DOM
            const cardViewList = document.getElementById('cardView');
            const tableViewList = document.querySelector('#tableView tbody');
            
            cardViewList.innerHTML = '';
            tableViewList.innerHTML = '';
            
            sortedItems.forEach(item => {
                if (item.tagName === 'DIV') {
                    cardViewList.appendChild(item.cloneNode(true));
                } else {
                    tableViewList.appendChild(item.cloneNode(true));
                }
            });
        }
        
        // Modal functions
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
        
        // Add animation to newly added items
        function highlightNewItem() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                const newItems = document.querySelectorAll('.alat-item');
                if (newItems.length > 0) {
                    const latestItem = newItems[0];
                    latestItem.classList.add('animate-pulse', 'bg-green-50');
                    setTimeout(() => {
                        latestItem.classList.remove('animate-pulse', 'bg-green-50');
                    }, 3000);
                }
            }
        }
        
        // Run on page load
        document.addEventListener('DOMContentLoaded', highlightNewItem);
    </script>
</body>
</html>