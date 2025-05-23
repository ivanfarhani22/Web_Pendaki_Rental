<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

class HomePage {
    private $conn;

    public function __construct($database) {
        $this->conn = $database->getConnection();
    }

    public function getUserProfile($user_id) {
        $query = "SELECT 
                    nama_lengkap, 
                    email, 
                    no_telepon,
                    tanggal_registrasi
                  FROM users 
                  WHERE user_id = :user_id";
    
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':user_id', $user_id);
        oci_execute($stmt);
    
        return oci_fetch_assoc($stmt);
    }    

    public function getQuickStats($user_id) {
        $query = "SELECT 
                    COUNT(*) as total_peminjaman,
                    SUM(CASE WHEN status_peminjaman = 'Selesai' THEN 1 ELSE 0 END) as total_selesai,
                    SUM(CASE WHEN status_peminjaman = 'Sedang Dipinjam' THEN 1 ELSE 0 END) as sedang_dipinjam
                  FROM peminjaman
                  WHERE user_id = :user_id";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':user_id', $user_id);
        oci_execute($stmt);

        return oci_fetch_assoc($stmt);
    }

    public function getTotalEquipment() {
        $query = "SELECT COUNT(*) as total_alat FROM alat_mendaki WHERE kondisi != 'Rusak'";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        $result = oci_fetch_assoc($stmt);
        return $result['TOTAL_ALAT'];
    }

    public function getCategories() {
        $query = "SELECT COUNT(*) as total_kategori FROM kategori_alat";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        $result = oci_fetch_assoc($stmt);
        return $result['TOTAL_KATEGORI'];
    }
}

$database = new Database();
$homePage = new HomePage($database);

$userProfile = $homePage->getUserProfile($_SESSION['user_id']);
$userStats = $homePage->getQuickStats($_SESSION['user_id']);
$totalEquipment = $homePage->getTotalEquipment();
$totalCategories = $homePage->getCategories();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PendakiGear Rental - Sewa Alat Mendaki Terpercaya</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="/pendaki_gear/web/favicon.ico" type="image/x-icon">

    <style>
        .hero-bg {
            background-image: url('https://st2.depositphotos.com/5991120/8867/v/450/depositphotos_88677448-stock-illustration-beautiful-green-mountains-summer-landscape.jpg');
            background-size: cover;
            background-position: center;
        }
        
        .flip-container {
            perspective: 1000px;
            width: 100%;
            height: 100vh;
            position: relative;
            overflow: hidden;
        }
        
        .flipper {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            transition: transform 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .flip-container.flipped .flipper {
            transform: rotateY(180deg);
        }
        
        .front, .back {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .front {
            z-index: 2;
        }
        
        .back {
            transform: rotateY(180deg);
            background: linear-gradient(135deg, #0f766e 0%, #065f46 100%);
            z-index: 1;
        }
        
        .dashboard-card {
            background: rgba(255, 255, 255, 0.48);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            max-width: 4xl;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .back-button {
            position: absolute;
            top: 2rem;
            left: 2rem;
            z-index: 10;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 12px 16px;
            border-radius: 50px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .back-button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
        }
        
        .floating-particles {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }
        
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }
        
        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }
        
        .dashboard-button {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .dashboard-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        /* Custom scrollbar for dashboard */
        .dashboard-card::-webkit-scrollbar {
            width: 6px;
        }
        
        .dashboard-card::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 3px;
        }
        
        .dashboard-card::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 3px;
        }
        
        .dashboard-card::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>

    <!-- Hero Cover Section with Flip Dashboard -->
    <section class="flip-container" id="heroContainer">
        <div class="flipper">
            <!-- Front Side - Hero -->
            <div class="front hero-bg relative">
                <div class="absolute inset-0 bg-gradient-to-br from-green-900/20 to-teal-700/20"></div>
                
                <!-- Floating Particles -->
                <div class="floating-particles">
                    <div class="particle w-2 h-2" style="left: 10%; animation-delay: 0s; animation-duration: 25s;"></div>
                    <div class="particle w-1 h-1" style="left: 20%; animation-delay: 5s; animation-duration: 30s;"></div>
                    <div class="particle w-3 h-3" style="left: 70%; animation-delay: 2s; animation-duration: 20s;"></div>
                    <div class="particle w-2 h-2" style="left: 80%; animation-delay: 8s; animation-duration: 35s;"></div>
                    <div class="particle w-1 h-1" style="left: 30%; animation-delay: 12s; animation-duration: 28s;"></div>
                </div>
                
                <div class="container mx-auto px-6 text-center relative z-10">
                    <div class="max-w-4xl mx-auto">
                        <h1 class="text-5xl md:text-7xl font-bold mb-6 leading-tight text-white">
                            Pendaki<span class="text-teal-300">Gear</span>
                            <br>
                            <span class="text-3xl md:text-4xl font-light">Rental</span>
                        </h1>
                        <p class="text-xl md:text-2xl mb-8 text-green-100 leading-relaxed">
                            Sewa alat mendaki berkualitas tinggi untuk petualangan tak terlupakan
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                            <a href="daftar_alat.php" class="bg-teal-500 hover:bg-teal-600 text-white px-8 py-4 rounded-full font-semibold text-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                                <i class="fas fa-mountain mr-2"></i>
                                Mulai Sewa Alat
                            </a>
                            <button onclick="flipToDashboard()" class="dashboard-button text-white px-8 py-4 rounded-full font-semibold text-lg">
                                <i class="fas fa-tachometer-alt mr-2"></i>
                                Dashboard Saya
                            </button>
                            <a href="#tentang" class="border-2 border-white text-white hover:bg-white hover:text-green-800 px-8 py-4 rounded-full font-semibold text-lg transition-all duration-300">
                                Pelajari Lebih Lanjut
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Scroll indicator -->
                <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
                    <i class="fas fa-chevron-down text-2xl text-white opacity-70"></i>
                </div>
            </div>

            <!-- Back Side - Dashboard -->
            <div class="back pt-20 pb-10">

                <!-- Floating Particles for Dashboard -->
                <div class="floating-particles">
                    <div class="particle w-2 h-2" style="left: 15%; animation-delay: 3s; animation-duration: 22s;"></div>
                    <div class="particle w-1 h-1" style="left: 85%; animation-delay: 7s; animation-duration: 18s;"></div>
                    <div class="particle w-3 h-3" style="left: 60%; animation-delay: 1s; animation-duration: 26s;"></div>
                </div>
                
                <div class="hero-bg min-h-screen container mx-auto px-4 sm:px-6 relative z-10 flex items-center justify-center">
                    <div class="dashboard-card p-4 sm:p-6 lg:p-8 mx-2 sm:mx-4 w-full max-w-4xl">
                        <div class="text-center mb-6 sm:mb-8">
                             <!-- Back Button -->
                            <button onclick="flipToHero()" class="back-button text-green-600 px-3 sm:px-4 py-2 rounded-full font-semibold text-base sm:text-lg mb-4">
                                <i class="fas fa-arrow-left mr-2 text-green-600"></i>
                                Kembali
                            </button>
                            <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-r from-teal-500 to-green-600 rounded-full mx-auto mb-4 flex items-center justify-center">
                                <i class="fas fa-user text-white text-xl sm:text-2xl"></i>
                            </div>
                            <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-2">Dashboard Anda</h2>
                            <p class="text-sm sm:text-base text-gray-600 px-4">Selamat datang kembali, <?= htmlspecialchars($userProfile['NAMA_LENGKAP']) ?>!</p>
                        </div>

                        <!-- Quick Stats -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
                            <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-4 sm:p-6 rounded-xl text-white text-center transform hover:scale-105 transition-transform">
                                <i class="fas fa-list-alt text-xl sm:text-2xl mb-2 sm:mb-3"></i>
                                <div class="text-xl sm:text-2xl font-bold mb-1"><?= $userStats['TOTAL_PEMINJAMAN'] ?: '0' ?></div>
                                <div class="text-green-100 text-xs sm:text-sm">Total Peminjaman</div>
                            </div>
                            <div class="bg-gradient-to-r from-blue-500 to-cyan-600 p-4 sm:p-6 rounded-xl text-white text-center transform hover:scale-105 transition-transform">
                                <i class="fas fa-hiking text-xl sm:text-2xl mb-2 sm:mb-3"></i>
                                <div class="text-xl sm:text-2xl font-bold mb-1"><?= $userStats['SEDANG_DIPINJAM'] ?: '0' ?></div>
                                <div class="text-blue-100 text-xs sm:text-sm">Sedang Dipinjam</div>
                            </div>
                            <div class="bg-gradient-to-r from-purple-500 to-pink-600 p-4 sm:p-6 rounded-xl text-white text-center transform hover:scale-105 transition-transform sm:col-span-2 lg:col-span-1">
                                <i class="fas fa-check-circle text-xl sm:text-2xl mb-2 sm:mb-3"></i>
                                <div class="text-xl sm:text-2xl font-bold mb-1"><?= $userStats['TOTAL_SELESAI'] ?: '0' ?></div>
                                <div class="text-purple-100 text-xs sm:text-sm">Selesai</div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                            <a href="daftar_alat.php" class="group bg-teal-50 hover:bg-teal-100 border border-teal-200 rounded-xl p-4 sm:p-6 transition-all duration-300 hover:shadow-lg">
                                <div class="flex items-center">
                                    <div class="bg-teal-500 p-2 sm:p-3 rounded-full mr-3 sm:mr-4 group-hover:scale-110 transition-transform flex-shrink-0">
                                        <i class="fas fa-shopping-cart text-white text-sm sm:text-base"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-semibold text-sm sm:text-base text-gray-800 group-hover:text-teal-700">Sewa Alat Baru</h3>
                                        <p class="text-xs sm:text-sm text-gray-600">Pilih peralatan untuk petualangan Anda</p>
                                    </div>
                                </div>
                            </a>
                            
                            <a href="riwayat_peminjaman.php" class="group bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-xl p-4 sm:p-6 transition-all duration-300 hover:shadow-lg">
                                <div class="flex items-center">
                                    <div class="bg-blue-500 p-2 sm:p-3 rounded-full mr-3 sm:mr-4 group-hover:scale-110 transition-transform flex-shrink-0">
                                        <i class="fas fa-history text-white text-sm sm:text-base"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-semibold text-sm sm:text-base text-gray-800 group-hover:text-blue-700">Riwayat Peminjaman</h3>
                                        <p class="text-xs sm:text-sm text-gray-600">Lihat aktivitas peminjaman Anda</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Company Section -->
    <section id="tentang" class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-800 mb-4">Tentang PendakiGear Rental</h2>
                    <div class="w-24 h-1 bg-teal-500 mx-auto mb-8"></div>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
                        Kami adalah penyedia layanan rental alat mendaki terpercaya yang telah melayani ribuan pendaki di seluruh Indonesia
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800 mb-6">Mengapa Memilih Kami?</h3>
                        <div class="space-y-6">
                            <div class="flex items-start">
                                <div class="bg-teal-100 p-3 rounded-full mr-4 flex-shrink-0">
                                    <i class="fas fa-shield-alt text-teal-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2">Alat Berkualitas Tinggi</h4>
                                    <p class="text-gray-600">Semua peralatan kami dipilih dari brand terpercaya dan rutin dirawat untuk memastikan keamanan Anda</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="bg-green-100 p-3 rounded-full mr-4 flex-shrink-0">
                                    <i class="fas fa-clock text-green-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2">Proses Cepat & Mudah</h4>
                                    <p class="text-gray-600">Sistem booking online yang simple dan proses pengambilan alat yang efisien</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div class="bg-blue-100 p-3 rounded-full mr-4 flex-shrink-0">
                                    <i class="fas fa-headset text-blue-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2">Dukungan 24/7</h4>
                                    <p class="text-gray-600">Tim customer service kami siap membantu Anda kapan saja sebelum dan selama perjalanan</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-6">
                        <div class="bg-gradient-to-br from-teal-500 to-green-600 p-6 rounded-xl text-white text-center">
                            <div class="text-3xl font-bold mb-2"><?= $totalEquipment ?></div>
                            <div class="text-teal-100">Alat Tersedia</div>
                        </div>
                        <div class="bg-gradient-to-br from-blue-500 to-teal-600 p-6 rounded-xl text-white text-center">
                            <div class="text-3xl font-bold mb-2"><?= $totalCategories ?></div>
                            <div class="text-blue-100">Kategori Alat</div>
                        </div>
                        <div class="bg-gradient-to-br from-green-500 to-teal-600 p-6 rounded-xl text-white text-center">
                            <div class="text-3xl font-bold mb-2">3+</div>
                            <div class="text-green-100">Tahun Pengalaman</div>
                        </div>
                        <div class="bg-gradient-to-br from-purple-500 to-blue-600 p-6 rounded-xl text-white text-center">
                            <div class="text-3xl font-bold mb-2">100%</div>
                            <div class="text-purple-100">Terpercaya</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    
    <!-- Syarat & Ketentuan Section -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-800 mb-4">Syarat & Ketentuan</h2>
                    <div class="w-24 h-1 bg-teal-500 mx-auto mb-6"></div>
                    <p class="text-lg text-gray-600">Harap dibaca dengan teliti sebelum melakukan peminjaman alat</p>
                </div>

                <div class="bg-gradient-to-br from-gray-50 to-white rounded-2xl shadow-lg p-8 border border-gray-100">
                    <div class="grid gap-8">
                        
                        <!-- Ketentuan Harga & Waktu -->
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded-r-lg">
                            <h3 class="text-xl font-semibold text-blue-800 mb-4 flex items-center">
                                <i class="fas fa-calculator mr-3"></i>
                                Ketentuan Harga & Waktu
                            </h3>
                            <ul class="space-y-3 text-gray-700">
                                <li class="flex items-start">
                                    <i class="fas fa-clock text-blue-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Harga terhitung dari tanggal mulai peminjaman hingga tanggal selesai</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-clock text-blue-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Bebas mengambil dan mengembalikan alat kapan saja dalam hari yang ditentukan</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-triangle text-red-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span><strong>Terlambat mengembalikan dikenakan denda Rp 10.000/hari</strong></span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-ban text-red-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Alat yang sudah dibawa tetap terhitung sewa meskipun tidak terpakai. Mengembalikan sebelum jadwal <strong>tidak ada refund</strong></span>
                                </li>
                            </ul>
                        </div>

                        <!-- Ketentuan Jaminan -->
                        <div class="bg-green-50 border-l-4 border-green-500 p-6 rounded-r-lg">
                            <h3 class="text-xl font-semibold text-green-800 mb-4 flex items-center">
                                <i class="fas fa-id-card mr-3"></i>
                                Ketentuan Jaminan & Identitas
                            </h3>
                            <ul class="space-y-3 text-gray-700">
                                <li class="flex items-start">
                                    <i class="fas fa-shield-alt text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Jaminan berupa <strong>KTP / SIM / KTM (Kartu Tanda Mahasiswa)</strong></span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-graduation-cap text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Pelajar atau mahasiswa yang belum punya KTP boleh menggunakan KTM</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-user-check text-green-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span><strong>Pengambil alat harus orang yang sama dengan identitas yang didaftarkan</strong></span>
                                </li>
                            </ul>
                        </div>

                        <!-- Ketentuan Booking & Pembayaran -->
                        <div class="bg-purple-50 border-l-4 border-purple-500 p-6 rounded-r-lg">
                            <h3 class="text-xl font-semibold text-purple-800 mb-4 flex items-center">
                                <i class="fas fa-credit-card mr-3"></i>
                                Ketentuan Booking & Pembayaran
                            </h3>
                            <ul class="space-y-3 text-gray-700">
                                <li class="flex items-start">
                                    <i class="fas fa-money-bill-wave text-purple-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Wajib melakukan <strong>DP (Down Payment)</strong> saat booking</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-ban text-red-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span><strong>DP tidak bisa direfund</strong> jika peminjam membatalkan booking</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-mobile-alt text-purple-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Booking via transfer dengan DP berapapun, tunjukkan bukti transfer saat booking melalui sistem</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Ketentuan Ketersediaan Stok -->
                        <div class="bg-orange-50 border-l-4 border-orange-500 p-6 rounded-r-lg">
                            <h3 class="text-xl font-semibold text-orange-800 mb-4 flex items-center">
                                <i class="fas fa-boxes mr-3"></i>
                                Ketentuan Ketersediaan Stok
                            </h3>
                            <ul class="space-y-3 text-gray-700">
                                <li class="flex items-start">
                                    <i class="fas fa-calendar-check text-orange-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span><strong>Untuk menghindari kehabisan stok, sangat disarankan untuk booking secara online terlebih dahulu</strong></span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-exclamation-circle text-red-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Jika alat yang diinginkan tidak tersedia, kami menyediakan alat lain yang tersedia</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-phone text-orange-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Untuk booking secara offline, silakan hubungi kami terlebih dahulu dan silahkan mengecek di website kami untuk melihat ketersediaan</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Ketentuan Kondisi Alat -->
                        <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-r-lg">
                            <h3 class="text-xl font-semibold text-red-800 mb-4 flex items-center">
                                <i class="fas fa-tools mr-3"></i>
                                Ketentuan Kondisi Alat
                            </h3>
                            <ul class="space-y-3 text-gray-700">
                                <li class="flex items-start">
                                    <i class="fas fa-search text-red-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Peminjam wajib mengecek kondisi alat saat pengambilan bersama petugas</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-hammer text-red-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span><strong>Alat rusak atau hilang menjadi tanggung jawab peminjam sepenuhnya</strong></span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-dollar-sign text-red-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Biaya ganti rugi sesuai dengan harga alat atau biaya perbaikan yang dikeluarkan</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-spray-can text-red-500 mt-1 mr-3 flex-shrink-0"></i>
                                    <span>Alat kotor masih bisa diterima, tapi jika terlalu kotor akan dikenakan biaya pembersihan</span>
                                </li>
                            </ul>
                        </div>

                    </div>

                    <!-- Call to Action -->
                    <div class="mt-8 text-center bg-gradient-to-r from-teal-500 to-green-600 rounded-xl p-6 text-white">
                        <h4 class="text-lg font-semibold mb-2">Sudah Memahami Syarat & Ketentuan?</h4>
                        <p class="mb-4 opacity-90">Mulai sewa alat mendaki impian Anda sekarang!</p>
                        <a href="daftar_alat.php" class="inline-block bg-white text-teal-600 px-8 py-3 rounded-full font-semibold hover:bg-gray-100 transition-colors">
                            <i class="fas fa-mountain mr-2"></i>
                            Mulai Sewa Sekarang
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-16 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-800 mb-4">Pertanyaan yang Sering Diajukan</h2>
                    <div class="w-24 h-1 bg-teal-500 mx-auto mb-6"></div>
                    <p class="text-lg text-gray-600">Temukan jawaban untuk pertanyaan umum seputar layanan kami</p>
                </div>

                <div class="space-y-4">
                    <div class="faq-item bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <button class="faq-question w-full text-left p-6 flex justify-between items-center hover:bg-gray-50 transition-colors" onclick="toggleFAQ(this)">
                            <span class="font-semibold text-gray-800">Bagaimana cara melakukan booking alat?</span>
                            <i class="fas fa-chevron-down text-teal-500 transform transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <div class="p-6 pt-0 text-gray-600">
                                <p>Anda dapat melakukan booking melalui website dengan langkah berikut:</p>
                                <ol class="list-decimal list-inside mt-2 space-y-1">
                                    <li>Pilih alat yang ingin disewa di halaman "Daftar Alat"</li>
                                    <li>Tentukan tanggal mulai dan selesai peminjaman</li>
                                    <li>Lakukan pembayaran DP melalui transfer bank</li>
                                    <li>Upload bukti transfer dan lengkapi data diri</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <button class="faq-question w-full text-left p-6 flex justify-between items-center hover:bg-gray-50 transition-colors" onclick="toggleFAQ(this)">
                            <span class="font-semibold text-gray-800">Berapa minimal DP yang harus dibayar?</span>
                            <i class="fas fa-chevron-down text-teal-500 transform transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <div class="p-6 pt-0 text-gray-600">
                                <p>Minimal DP adalah 20% dari total biaya sewa, namun Anda bisa membayar DP berapapun sesuai kemampuan. DP yang lebih besar akan mengurangi sisa pembayaran saat pengambilan alat.</p>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <button class="faq-question w-full text-left p-6 flex justify-between items-center hover:bg-gray-50 transition-colors" onclick="toggleFAQ(this)">
                            <span class="font-semibold text-gray-800">Apakah bisa mengambil alat di hari yang sama dengan booking?</span>
                            <i class="fas fa-chevron-down text-teal-500 transform transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <div class="p-6 pt-0 text-gray-600">
                                <p>Bisa.</p>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <button class="faq-question w-full text-left p-6 flex justify-between items-center hover:bg-gray-50 transition-colors" onclick="toggleFAQ(this)">
                            <span class="font-semibold text-gray-800">Jam berapa saja bisa mengambil dan mengembalikan alat?</span>
                            <i class="fas fa-chevron-down text-teal-500 transform transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <div class="p-6 pt-0 text-gray-600">
                                <p>Jam operasional kami adalah: 24 Jam</p>
                            </div>
                        </div>
                    </div>

                    <div class="faq-item bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <button class="faq-question w-full text-left p-6 flex justify-between items-center hover:bg-gray-50 transition-colors" onclick="toggleFAQ(this)">
                            <span class="font-semibold text-gray-800">Bagaimana jika alat rusak saat digunakan?</span>
                            <i class="fas fa-chevron-down text-teal-500 transform transition-transform"></i>
                        </button>
                        <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                            <div class="p-6 pt-0 text-gray-600">
                                <p>Jika alat mengalami kerusakan saat digunakan:</p>
                                <ul class="list-disc list-inside mt-2 space-y-1">
                                    <li>Segera hubungi kami untuk melaporkan kerusakan</li>
                                    <li>Peminjam bertanggung jawab atas biaya perbaikan atau penggantian</li>
                                    <li>Untuk kerusakan ringan akibat pemakaian normal, akan ada toleransi dari pihak kami</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-16 bg-gradient-to-br from-teal-500 to-green-600">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto text-center text-white">
                <h2 class="text-3xl font-bold mb-4">Masih Ada Pertanyaan?</h2>
                <p class="text-xl mb-8 opacity-90">Tim customer service kami siap membantu Anda 24/7</p>
                
                <div class="grid grid-cols-1 md:grid-cols-1 gap-8 "> 
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                        <div class="bg-white/20 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fab fa-whatsapp text-2xl"></i>
                        </div>
                        <h3 class="font-semibold mb-2">WhatsApp</h3>
                        <p class="opacity-90 text-sm mb-3">Chat langsung dengan admin</p>
                        <a href="https://wa.me/6282247219152" class="inline-block bg-white text-teal-600 px-4 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors">
                            Chat Sekarang
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

    <script>
        // Flip Dashboard Functions
        function flipToDashboard() {
            document.getElementById('heroContainer').classList.add('flipped');
        }
        
        function flipToHero() {
            document.getElementById('heroContainer').classList.remove('flipped');
        }

        // FAQ Toggle Function
        function toggleFAQ(button) {
            const faqItem = button.closest('.faq-item');
            const answer = faqItem.querySelector('.faq-answer');
            const icon = button.querySelector('i');
            
            // Close all other FAQs
            document.querySelectorAll('.faq-item').forEach(item => {
                if (item !== faqItem) {
                    item.querySelector('.faq-answer').style.maxHeight = '0px';
                    item.querySelector('.faq-question i').style.transform = 'rotate(0deg)';
                }
            });
            
            // Toggle current FAQ
            if (answer.style.maxHeight === '0px' || answer.style.maxHeight === '') {
                answer.style.maxHeight = answer.scrollHeight + 'px';
                icon.style.transform = 'rotate(180deg)';
            } else {
                answer.style.maxHeight = '0px';
                icon.style.transform = 'rotate(0deg)';
            }
        }

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll effect to header
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (header) {
                if (window.scrollY > 100) {
                    header.classList.add('bg-white', 'shadow-lg');
                    header.classList.remove('bg-transparent');
                } else {
                    header.classList.remove('bg-white', 'shadow-lg');
                    header.classList.add('bg-transparent');
                }
            }
        });

        // Initialize particles animation
        function createParticle() {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.width = particle.style.height = Math.random() * 10 + 5 + 'px';
            particle.style.animationDuration = Math.random() * 10 + 20 + 's';
            particle.style.animationDelay = Math.random() * 5 + 's';
            
            return particle;
        }

        // Add more particles dynamically
        document.querySelectorAll('.floating-particles').forEach(container => {
            for (let i = 0; i < 3; i++) {
                setTimeout(() => {
                    container.appendChild(createParticle());
                }, i * 2000);
            }
        });

        // Add loading animation
        window.addEventListener('load', function() {
            document.body.classList.add('loaded');
        });

        // Add intersection observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fadeInUp');
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.faq-item, .bg-gradient-to-br').forEach(el => {
            observer.observe(el);
        });
    </script>

    <style>
        /* Additional animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        /* FAQ smooth transitions */
        .faq-answer {
            transition: max-height 0.3s ease-out;
        }

        .faq-question i {
            transition: transform 0.3s ease;
        }

        /* Loading state */
        body:not(.loaded) {
            overflow: hidden;
        }

        body:not(.loaded) * {
            animation-play-state: paused !important;
        }

        /* Enhanced hover effects */
        .dashboard-button:hover {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(15px);
        }

        /* Responsive improvements */
        @media (max-width: 768px) {
            .flip-container {
                height: 100vh;
            }
            
            .dashboard-card {
                max-height: 85vh;
                margin: 1rem;
            }
            
            .back-button {
                top: 1rem;
                left: 1rem;
                padding: 8px 12px;
            }
        }

        /* Print styles */
        @media print {
            .flip-container,
            .floating-particles,
            .back-button {
                display: none !important;
            }
        }
    </style>
</body>
</html>