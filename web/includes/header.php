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
    <!-- Header - Changed to fixed position -->
    <header class="bg-gradient-to-r from-teal-900 to-green-900 shadow-lg fixed top-0 left-0 right-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <!-- Logo -->
                <div class="flex justify-start lg:w-0 lg:flex-1">
                    <a href="../user/index.php" class="flex items-center">
                        <img src="https://images.squarespace-cdn.com/content/v1/66b9939f8309ad3bb59cd88f/1732152083816-0YAKHS96UHIJOUMKZ89D/generalists-icon-hiking.png" alt="Pendaki Rental Logo" class="h-10 w-10 mr-2">
                        <span class="text-2xl font-bold text-white">Pendaki Gear</span>
                    </a>
                </div>

                <!-- Hamburger Menu Button -->
                <div class="md:hidden">
                    <button id="hamburger-button" class="text-white hover:text-gray-200 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex space-x-6">
                    <?php if($is_logged_in): ?>
                        <a href="../user/index.php" class="text-white hover:bg-green-700 px-3 py-2 rounded-md transition">Home</a>
                        <a href="../user/daftar_alat.php" class="text-white hover:bg-green-700 px-3 py-2 rounded-md transition">Daftar Alat</a>
                        <a href="../user/riwayat_peminjaman.php" class="text-white hover:bg-green-700 px-3 py-2 rounded-md transition">Riwayat Peminjaman</a>
                        <a href="../user/profile.php" class="text-white hover:bg-green-700 px-3 py-2 rounded-md transition">Profil</a>
                        <a href="../auth/logout.php" class="text-white bg-red-700 hover:bg-red-900 px-3 py-2 rounded-md transition">Logout</a>
                    <?php else: ?>
                        <a href="../auth/login.php" class="text-white hover:bg-green-700 px-3 py-2 rounded-md transition">Login</a>
                        <a href="../auth/register.php" class="text-white bg-teal-500 hover:bg-teal-600 px-3 py-2 rounded-md transition">Daftar</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobile-menu" class="fixed inset-0 bg-gray-900 bg-opacity-80 z-50 hidden md:hidden" onclick="closeMobileMenu()">
            <div class="flex justify-end p-4">
                <button id="close-menu-button" class="text-white focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="flex flex-col items-center justify-center h-full" onclick="event.stopPropagation()">
                <nav class="flex flex-col space-y-4 w-full max-w-xs">
                    <?php if($is_logged_in): ?>
                        <a href="../user/index.php" class="text-white text-center hover:bg-green-700 px-4 py-3 text-lg rounded-md transition">Dashboard</a>
                        <a href="../user/daftar_alat.php" class="text-white text-center hover:bg-green-700 px-4 py-3 text-lg rounded-md transition">Daftar Alat</a>
                        <a href="../user/riwayat_peminjaman.php" class="text-white text-center hover:bg-green-700 px-4 py-3 text-lg rounded-md transition">Riwayat Peminjaman</a>
                        <a href="../user/profile.php" class="text-white text-center hover:bg-green-700 px-4 py-3 text-lg rounded-md transition">Profil</a>
                        <a href="../auth/logout.php" class="text-white text-center bg-red-700 hover:bg-red-900 px-4 py-3 text-lg rounded-md transition">Logout</a>
                    <?php else: ?>
                        <a href="../auth/login.php" class="text-white text-center hover:bg-green-700 px-4 py-3 text-lg rounded-md transition">Login</a>
                        <a href="../auth/register.php" class="text-white text-center bg-teal-500 hover:bg-teal-600 px-4 py-3 text-lg rounded-md transition">Daftar</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>

    <!-- Added padding to push content below the fixed header -->
    <main>
        <!-- Your page content goes here -->
    </main>

    <!-- JavaScript for the mobile menu functionality -->
    <script>
        // Initialize Feather icons
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
        });

        // Mobile menu functionality
        const hamburgerButton = document.getElementById('hamburger-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const closeMenuButton = document.getElementById('close-menu-button');

        hamburgerButton.addEventListener('click', function() {
            mobileMenu.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent scrolling when menu is open
        });

        function closeMobileMenu() {
            mobileMenu.classList.add('hidden');
            document.body.style.overflow = 'auto'; // Re-enable scrolling
        }

        closeMenuButton.addEventListener('click', closeMobileMenu);

        // Close mobile menu when clicking on a link
        const mobileMenuLinks = mobileMenu.querySelectorAll('a');
        mobileMenuLinks.forEach(link => {
            link.addEventListener('click', closeMobileMenu);
        });

        // Close mobile menu when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !mobileMenu.classList.contains('hidden')) {
                closeMobileMenu();
            }
        });
    </script>
</body>
</html>