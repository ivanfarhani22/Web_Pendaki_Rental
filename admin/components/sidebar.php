<!-- admin/components/sidebar.php -->
<div class="fixed left-0 top-0 h-full w-64 bg-gray-800 text-white shadow-lg">
    <div class="p-5 border-b border-gray-700">
        <h2 class="text-2xl font-bold text-center">Admin Panel</h2>
    </div>
    <nav class="mt-5">
        <ul>
            <li>
                <a href="index.php" class="block py-3 px-4 hover:bg-gray-700 <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'bg-gray-700' : '' ?>">
                    <i class="fas fa-home mr-2"></i>Dashboard
                </a>
            </li>
            <li>
                <a href="manajemen_alat.php" class="block py-3 px-4 hover:bg-gray-700 <?= (basename($_SERVER['PHP_SELF']) == 'manajemen_alat.php') ? 'bg-gray-700' : '' ?>">
                    <i class="fas fa-tools mr-2"></i>Manajemen Alat
                </a>
            </li>
            <li>
                <a href="manajemen_peminjaman.php" class="block py-3 px-4 hover:bg-gray-700 <?= (basename($_SERVER['PHP_SELF']) == 'manajemen_peminjaman.php') ? 'bg-gray-700' : '' ?>">
                    <i class="fas fa-exchange-alt mr-2"></i>Manajemen Peminjaman
                </a>
            </li>
            <li>
                <a href="laporan.php" class="block py-3 px-4 hover:bg-gray-700 <?= (basename($_SERVER['PHP_SELF']) == 'laporan.php') ? 'bg-gray-700' : '' ?>">
                    <i class="fas fa-file-alt mr-2"></i>Laporan
                </a>
            </li>
        </ul>
    </nav>
    <div class="absolute bottom-0 left-0 w-full p-4 border-t border-gray-700">
        <a href="../auth/logout.php" class="block w-full text-center bg-red-600 hover:bg-red-700 py-2 rounded">
            <i class="fas fa-sign-out-alt mr-2"></i>Logout
        </a>
    </div>
</div>