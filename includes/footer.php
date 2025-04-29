</main>

<!-- Footer -->
<footer class="bg-gray-100 mt-8">
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- About Section -->
            <div>
                <h4 class="text-xl font-bold text-green-800 mb-4 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Pendaki Rental
                </h4>
                <p class="text-gray-600">Solusi penyewaan peralatan pendakian terlengkap untuk petualangan Anda</p>
            </div>

            <!-- Contact Section -->
            <div>
                <h4 class="text-xl font-bold text-green-800 mb-4 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Kontak
                </h4>
                <p class="text-gray-600 mb-2">Email: info@pendakirental.com</p>
                <p class="text-gray-600">Telepon: (021) 1234-5678</p>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-xl font-bold text-green-800 mb-4 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    Tautan Cepat
                </h4>
                <div class="space-y-2">
                    <a href="../user/index.php" class="text-gray-600 hover:text-green-700 transition">Dashboard</a><br>
                    <a href="../user/daftar_alat.php" class="text-gray-600 hover:text-green-700 transition">Daftar Alat</a><br>
                    <a href="../user/riwayat_peminjaman.php" class="text-gray-600 hover:text-green-700 transition">Riwayat Peminjaman</a>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="mt-8 pt-8 border-t border-gray-200 text-center">
            <p class="text-gray-500">&copy; <?= date('Y') ?> Pendaki Rental. All Rights Reserved.</p>
        </div>
    </div>
</footer>

<script>
    // Fungsi umum untuk menampilkan pesan konfirmasi
    function konfirmasiAksi(pesan) {
        return confirm(pesan);
    }

    // Initialize Feather Icons
    feather.replace();
</script>
</body>
</html>