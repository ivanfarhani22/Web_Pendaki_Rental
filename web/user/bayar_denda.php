<?php
session_start();
require_once '../config/database.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Pastikan parameter peminjaman_id dan jumlah_denda ada
if (!isset($_GET['peminjaman_id']) || !isset($_GET['jumlah_denda'])) {
    header('Location: riwayat_peminjaman.php');
    exit();
}

$peminjaman_id = $_GET['peminjaman_id'];
$jumlah_denda = $_GET['jumlah_denda'];

// Validasi peminjaman milik user yang login
$database = new Database();
$conn = $database->getConnection();

$query_validasi = "SELECT p.peminjaman_id, p.user_id, p.status_peminjaman, a.nama_alat,
                    CASE
                        WHEN p.status_peminjaman = 'Sedang Dipinjam' AND d.tanggal_selesai < CURRENT_DATE
                        THEN (CURRENT_DATE - d.tanggal_selesai) * 10000
                        ELSE 0
                    END as denda
                   FROM peminjaman p
                   JOIN detail_peminjaman d ON p.peminjaman_id = d.peminjaman_id
                   JOIN alat_mendaki a ON d.alat_id = a.alat_id
                   WHERE p.peminjaman_id = :peminjaman_id AND p.user_id = :user_id";

$stmt = oci_parse($conn, $query_validasi);
oci_bind_by_name($stmt, ':peminjaman_id', $peminjaman_id);
oci_bind_by_name($stmt, ':user_id', $_SESSION['user_id']);
oci_execute($stmt);

$data_peminjaman = oci_fetch_assoc($stmt);

// Jika data tidak ditemukan atau tidak ada denda, redirect
if (!$data_peminjaman || $data_peminjaman['DENDA'] <= 0) {
    header('Location: riwayat_peminjaman.php');
    exit();
}

// Proses pembayaran denda
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Proses pembayaran
    $metode_pembayaran = $_POST['metode_pembayaran'];
    
    try {
        // Mulai transaksi
        
        // Tambahkan record pembayaran denda - fixed column name from jumlah_bayar to jumlah_pembayaran
        // Proses upload bukti pembayaran jika metode adalah Transfer Bank atau E-Wallet
        $bukti_pembayaran = null;
        if (($metode_pembayaran == 'Transfer Bank' || $metode_pembayaran == 'E-Wallet') && isset($_FILES['bukti_pembayaran'])) {
            $target_dir = "../uploads/bukti_pembayaran/";
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES["bukti_pembayaran"]["name"], PATHINFO_EXTENSION);
            $new_filename = "bukti_" . $peminjaman_id . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            // Check if file is an actual image
            $check = getimagesize($_FILES["bukti_pembayaran"]["tmp_name"]);
            if ($check === false) {
                throw new Exception("File yang diunggah bukan gambar.");
            }
            
            // Check file size (max 2MB)
            if ($_FILES["bukti_pembayaran"]["size"] > 2000000) {
                throw new Exception("Ukuran file terlalu besar. Maksimal 2MB.");
            }
            
            // Allow certain file formats
            if ($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg") {
                throw new Exception("Hanya file JPG, JPEG & PNG yang diperbolehkan.");
            }
            
            if (!move_uploaded_file($_FILES["bukti_pembayaran"]["tmp_name"], $target_file)) {
                throw new Exception("Gagal mengunggah file bukti pembayaran.");
            }
            
            $bukti_pembayaran = $new_filename;
        }
        
        // Query SQL sesuai dengan ada/tidaknya bukti pembayaran
        if ($bukti_pembayaran) {
            $query_bayar = "INSERT INTO pembayaran (peminjaman_id, jumlah_pembayaran, status_pembayaran, metode_pembayaran, tanggal_pembayaran, bukti_pembayaran)
                            VALUES (:peminjaman_id, :jumlah_pembayaran, 'Lunas', :metode_pembayaran, CURRENT_DATE, :bukti_pembayaran)";
        } else {
            $query_bayar = "INSERT INTO pembayaran (peminjaman_id, jumlah_pembayaran, status_pembayaran, metode_pembayaran, tanggal_pembayaran)
                            VALUES (:peminjaman_id, :jumlah_pembayaran, 'Lunas', :metode_pembayaran, CURRENT_DATE)";
        }
        
        $stmt_bayar = oci_parse($conn, $query_bayar);
        oci_bind_by_name($stmt_bayar, ':peminjaman_id', $peminjaman_id);
        oci_bind_by_name($stmt_bayar, ':jumlah_pembayaran', $jumlah_denda);
        oci_bind_by_name($stmt_bayar, ':metode_pembayaran', $metode_pembayaran);
        
        // Bind bukti pembayaran jika ada
        if ($bukti_pembayaran) {
            oci_bind_by_name($stmt_bayar, ':bukti_pembayaran', $bukti_pembayaran);
        }
        
        $result = oci_execute($stmt_bayar, OCI_DEFAULT);
        
        if (!$result) {
            $e = oci_error($stmt_bayar);
            throw new Exception($e['message']);
        }
        
        // Update status peminjaman menjadi 'Selesai' jika pembayaran denda berhasil
        // Karena denda sudah dibayar, peminjaman seharusnya dianggap selesai
        $query_update = "UPDATE peminjaman SET status_peminjaman = 'Selesai' 
                         WHERE peminjaman_id = :peminjaman_id";

        $stmt_update = oci_parse($conn, $query_update);
        oci_bind_by_name($stmt_update, ':peminjaman_id', $peminjaman_id);
        $result_update = oci_execute($stmt_update, OCI_DEFAULT);
        
        if (!$result_update) {
            $e = oci_error($stmt_update);
            throw new Exception($e['message']);
        }
        
        // Commit transaksi
        oci_commit($conn);
        
        $success_message = "Pembayaran denda berhasil diproses.";
    } catch (Exception $e) {
        // Rollback jika terjadi error
        oci_rollback($conn);
        $error_message = "Terjadi kesalahan saat memproses pembayaran denda: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bayar Denda</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
      <link rel="icon" href="/pendaki_gear/web/favicon.ico" type="image/x-icon">

</head>
<body class="bg-gradient-to-br from-gray-100 to-green-50 min-h-screen">
    <?php include '../includes/header.php'; ?>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="container mx-auto">
            <div class="bg-white shadow-lg rounded-lg overflow-hidden border border-green-100">
                <div class="bg-gradient-to-r from-green-900 to-green-900 p-6">
                    <h1 class="text-3xl font-bold text-white flex items-center">
                        <i class="fas fa-money-bill-wave mr-4"></i>
                        Pembayaran Denda
                    </h1>
                </div>

                <div class="p-6">
                    <?php if($success_message): ?>
                        <div class="bg-green-100 border-green-400 text-green-700 border p-4 rounded mb-6">
                            <?= $success_message ?>
                            <div class="mt-4">
                                <a href="riwayat_peminjaman.php" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                                    Kembali ke Riwayat Peminjaman
                                </a>
                            </div>
                        </div>
                    <?php elseif($error_message): ?>
                        <div class="bg-red-100 border-red-400 text-red-700 border p-4 rounded mb-6">
                            <?= $error_message ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-yellow-50 border-yellow-300 text-yellow-800 border p-4 rounded mb-6">
                            <h2 class="text-xl font-semibold mb-2">Informasi Denda</h2>
                            <p>Anda memiliki denda yang harus dibayarkan untuk peminjaman alat <strong><?= $data_peminjaman['NAMA_ALAT'] ?></strong> sebesar <strong>Rp <?= number_format($data_peminjaman['DENDA'], 0, ',', '.') ?></strong>.</p>
                            <p class="mt-2">Harap selesaikan pembayaran denda untuk menghindari penalti tambahan.</p>
                        </div>

                        <form method="POST" class="bg-white rounded-lg p-6 border border-gray-200" enctype="multipart/form-data">
                            <div class="mb-6">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="metode_pembayaran">
                                    Metode Pembayaran
                                </label>
                                <select name="metode_pembayaran" id="metode_pembayaran" required class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" onchange="toggleBuktiPembayaran()">
                                    <option value="">-- Pilih Metode Pembayaran --</option>
                                    <option value="Transfer Bank">Transfer Bank</option>
                                    <option value="E-Wallet">E-Wallet (DANA, OVO, GoPay)</option>
                                    <option value="Tunai">Tunai di Tempat</option>
                                </select>
                            </div>
                            
                            <div id="bukti_pembayaran_container" class="mb-6" style="display: none;">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="bukti_pembayaran">
                                    Unggah Bukti Pembayaran
                                </label>
                                <input type="file" name="bukti_pembayaran" id="bukti_pembayaran" 
                                       class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                       accept="image/jpeg,image/png,image/jpg">
                                <p class="text-gray-500 text-xs mt-1">Format yang didukung: JPG, JPEG, PNG. Ukuran maksimal: 2MB</p>
                            </div>

                            <div class="mb-6">
                                <p class="text-gray-700 font-semibold">Total Denda: <span class="text-red-600">Rp <?= number_format($data_peminjaman['DENDA'], 0, ',', '.') ?></span></p>
                            </div>

                            <div class="flex items-center justify-between">
                                <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                    Proses Pembayaran
                                </button>
                                <a href="riwayat_peminjaman.php" class="inline-block align-baseline font-bold text-sm text-green-500 hover:text-green-800">
                                    Kembali
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    
    <script>
        function toggleBuktiPembayaran() {
            var metode = document.getElementById('metode_pembayaran').value;
            var buktiContainer = document.getElementById('bukti_pembayaran_container');
            var buktiInput = document.getElementById('bukti_pembayaran');
            
            if (metode === 'Transfer Bank' || metode === 'E-Wallet') {
                buktiContainer.style.display = 'block';
                buktiInput.required = true;
            } else {
                buktiContainer.style.display = 'none';
                buktiInput.required = false;
            }
        }
        
        // Run on page load to handle pre-selected values
        document.addEventListener('DOMContentLoaded', function() {
            toggleBuktiPembayaran();
        });
    </script>
</body>
</html>