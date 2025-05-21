<!-- admin/components/sidebar.php -->
<!-- Hamburger Button (only visible on mobile) -->
<button id="hamburger-btn" class="fixed top-4 left-4 z-50 md:hidden bg-gray-800 text-white p-2 rounded-md shadow-lg">
    <svg id="hamburger-icon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
    <svg id="close-icon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
    </svg>
</button>

<!-- Overlay (only visible on mobile when sidebar is open) -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 md:hidden hidden"></div>

<!-- Sidebar -->
<div id="sidebar" class="fixed left-0 top-0 h-full w-64 bg-gray-800 text-white shadow-lg transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-40">
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
                <a href="manajemen_pembayaran.php" class="block py-3 px-4 hover:bg-gray-700 <?= (basename($_SERVER['PHP_SELF']) == 'manajemen_pembayaran.php') ? 'bg-gray-700' : '' ?>">
                    <i class="fas fa-credit-card mr-2"></i>Manajemen Pembayaran
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const hamburgerIcon = document.getElementById('hamburger-icon');
    const closeIcon = document.getElementById('close-icon');

    // Toggle sidebar function
    function toggleSidebar() {
        const isOpen = !sidebar.classList.contains('-translate-x-full');
        
        if (isOpen) {
            // Close sidebar
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            hamburgerIcon.classList.remove('hidden');
            closeIcon.classList.add('hidden');
        } else {
            // Open sidebar
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            hamburgerIcon.classList.add('hidden');
            closeIcon.classList.remove('hidden');
        }
    }

    // Event listeners
    hamburgerBtn.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);

    // Close sidebar when clicking on a navigation link (mobile only)
    const navLinks = sidebar.querySelectorAll('nav a');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 768) { // Only on mobile
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
                hamburgerIcon.classList.remove('hidden');
                closeIcon.classList.add('hidden');
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            // On desktop, always show sidebar and hide overlay
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.add('hidden');
            hamburgerIcon.classList.remove('hidden');
            closeIcon.classList.add('hidden');
        } else {
            // On mobile, start with sidebar hidden
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }
    });
});
</script>