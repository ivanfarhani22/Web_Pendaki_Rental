<?php
class Database {
    private $host = 'localhost';
    private $service_name = 'ORCLPDB';
    private $username = 'pendaki';
    private $password = 'password123';
    private $conn;

    public function getConnection() {
        try {
            // Koneksi Oracle menggunakan OCI8
            $this->conn = oci_connect(
                $this->username, 
                $this->password, 
                "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST={$this->host})(PORT=1521))(CONNECT_DATA=(SERVICE_NAME={$this->service_name})))"
            );
            
            if (!$this->conn) {
                $e = oci_error();
                throw new Exception('Koneksi database gagal: ' . $e['message']);
            }
            
            return $this->conn;
        } catch (Exception $e) {
            die("Kesalahan: " . $e->getMessage());
        }
    }

    public function closeConnection() {
        if ($this->conn) {
            oci_close($this->conn);
        }
    }
}

// Contoh penggunaan
$database = new Database();
$conn = $database->getConnection();
?>