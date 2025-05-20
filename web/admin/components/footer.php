<!-- admin/components/footer.php -->
<footer class="bg-gray-800 text-white py-4 px-6 absolute bottom-0 right-0 left-0 md:left-64">
    <div class="container mx-auto">
        <div class="flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0">
            <div class="text-center sm:text-left">
                <p class="text-sm sm:text-base">&copy; <?= date('Y') ?> Sistem Peminjaman Alat Pendaki</p>
            </div>
            <div class="text-sm text-center sm:text-right">
                <div class="flex flex-col sm:flex-row items-center space-y-1 sm:space-y-0">
                    <span class="text-gray-200">Logged in as: <span class="font-semibold"><?= $_SESSION['username'] ?></span></span>
                    <span class="sm:ml-4 text-gray-400">Admin Dashboard</span>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
    body {
        min-height: 100vh;
        position: relative;
        padding-bottom: 80px; /* Increased height for mobile */
    }

    .content-wrapper {
        padding-bottom: 80px; /* Same as body padding-bottom */
    }

    /* Responsive footer positioning */
    @media (min-width: 768px) {
        body {
            padding-bottom: 60px; /* Original height for desktop */
        }
        
        .content-wrapper {
            padding-bottom: 60px;
        }
    }

    /* Additional mobile optimizations */
    @media (max-width: 767px) {
        footer {
            padding: 1rem 1rem;
        }
        
        footer .container {
            padding: 0;
        }
    }
</style>