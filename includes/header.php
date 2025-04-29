<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['user_id']); // Cek apakah user sudah login
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaki Rental</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
</head>
<body class="bg-gray-50 text-gray-900">
    <!-- Header -->
    <header class="bg-gradient-to-r from-green-600 to-teal-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4 md:space-x-10">
                <!-- Logo -->
                <div class="flex justify-start lg:w-0 lg:flex-1">
                    <a href="../user/index.php" class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0H5a2 2 0 002-2v-3a2 2 0 114 0v3a2 2 0 002 2h2v-4a3 3 0 00-3-3H3a3 3 0 00-3 3v4h2zM12 8V6a4 4 0 10-8 0v2h8zM21 12v7a2 2 0 01-2 2h-6a2 2 0 01-2-2v-7a2 2 0 012-2h6a2 2 0 012 2z" />
                        </svg>
                        <span class="text-2xl font-bold text-white">Pendaki Rental</span>
                    </a>
                </div>

                <!-- Navigation -->
                <nav class="hidden md:flex space-x-6">
                    <?php if($is_logged_in): ?>
                        <a href="../user/index.php" class="text-white hover:bg-green-700 px-3 py-2 rounded-md transition">Dashboard</a>
                        <a href="../user/daftar_alat.php" class="text-white hover:bg-green-700 px-3 py-2 rounded-md transition">Daftar Alat</a>
                        <a href="../user/riwayat_peminjaman.php" class="text-white hover:bg-green-700 px-3 py-2 rounded-md transition">Riwayat Peminjaman</a>
                        <a href="../user/profile.php" class="text-white hover:bg-green-700 px-3 py-2 rounded-md transition">Profil</a>
                        <a href="../auth/logout.php" class="text-white bg-red-500 hover:bg-red-600 px-3 py-2 rounded-md transition">Logout</a>
                    <?php else: ?>
                        <a href="../auth/login.php" class="text-white hover:bg-green-700 px-3 py-2 rounded-md transition">Login</a>
                        <a href="../auth/register.php" class="text-white bg-teal-500 hover:bg-teal-600 px-3 py-2 rounded-md transition">Daftar</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content Placeholder -->
    <main>