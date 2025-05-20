<?php
// Fungsi untuk memvalidasi input
function validateInput($input, $type = 'string') {
    // Hapus whitespace di awal dan akhir
    $input = trim($input);
    
    // Cegah XSS
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    switch($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) ? $input : false;
        
        case 'integer':
            return filter_var($input, FILTER_VALIDATE_INT) !== false ? $input : false;
        
        case 'phone':
            // Validasi nomor telepon (hanya angka, minimal 10 digit)
            return preg_match('/^[0-9]{10,}$/', $input) ? $input : false;
        
        default:
            // Validasi string dasar
            return strlen($input) > 0 ? $input : false;
    }
}

// Fungsi untuk menghasilkan slug dari string
function generateSlug($string) {
    // Konversi ke lowercase
    $string = strtolower($string);
    
    // Hapus karakter non-alphanumeric
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    
    // Hapus tanda strip berulang
    $string = preg_replace('/-+/', '-', $string);
    
    // Hapus strip di awal dan akhir
    $string = trim($string, '-');
    
    return $string;
}

// Fungsi untuk membuat kode unik
function generateUniqueCode($prefix = '', $length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = $prefix;
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    return $code;
}

// Fungsi untuk mencatat log aktivitas
function logActivity($user_id, $activity) {
    // Dalam implementasi nyata, simpan ke database atau file log
    $log_entry = date('Y-m-d H:i:s') . " - User $user_id: $activity\n";
    file_put_contents('../logs/activity.log', $log_entry, FILE_APPEND);
}

// Fungsi untuk mengirim email sederhana
function sendEmail($to, $subject, $message) {
    $headers = "From: noreply@pendakirental.com\r\n";
    $headers .= "Reply-To: noreply@pendakirental.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    return mail($to, $subject, $message, $headers);
}

// Fungsi untuk format rupiah
function formatRupiah($nominal) {
    return 'Rp ' . number_format($nominal, 0, ',', '.');
}

// Fungsi untuk menghitung durasi
function hitungDurasi($tanggal_mulai, $tanggal_selesai) {
    $start = new DateTime($tanggal_mulai);
    $end = new DateTime($tanggal_selesai);
    return $start->diff($end)->days + 1;
}