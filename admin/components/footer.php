<!-- admin/components/footer.php -->
<footer class="bg-gray-800 text-white py-4 px-6 absolute bottom-0 right-0 left-64">
    <div class="container mx-auto flex justify-between items-center">
        <div>
            <p>&copy; <?= date('Y') ?> Sistem Peminjaman Alat Pendaki</p>
        </div>
        <div class="text-sm">
            <span>Logged in as: <?= $_SESSION['username'] ?></span>
            <span class="ml-4 text-gray-400">Admin Dashboard</span>
        </div>
    </div>
</footer>

<style>
    body {
        min-height: 100vh;
        position: relative;
        padding-bottom: 60px; /* Height of footer */
    }

    .content-wrapper {
        padding-bottom: 60px; /* Same as body padding-bottom */
    }
</style>