CREATE TABLE users (
    user_id NUMBER PRIMARY KEY,
    username VARCHAR2(50) UNIQUE NOT NULL,
    password VARCHAR2(255) NOT NULL,
    email VARCHAR2(100) UNIQUE NOT NULL,
    nama_lengkap VARCHAR2(100) NOT NULL,
    no_telepon VARCHAR2(15),
    role VARCHAR2(20) CHECK (role IN ('admin', 'peminjam')),
    tanggal_registrasi DATE DEFAULT SYSDATE
);
CREATE TABLE kategori_alat (
    kategori_id NUMBER PRIMARY KEY,
    nama_kategori VARCHAR2(100) NOT NULL,
    deskripsi VARCHAR2(255)
);
CREATE TABLE alat_mendaki (
    alat_id NUMBER PRIMARY KEY,
    kategori_id NUMBER REFERENCES kategori_alat(kategori_id),
    nama_alat VARCHAR2(100) NOT NULL,
    deskripsi VARCHAR2(255),
    jumlah_total NUMBER NOT NULL,
    jumlah_tersedia NUMBER NOT NULL,
    kondisi VARCHAR2(50) CHECK (kondisi IN ('Baru', 'Baik', 'Cukup', 'Rusak')),
    harga_sewa NUMBER(10,2)
);
CREATE TABLE peminjaman (
    peminjaman_id NUMBER PRIMARY KEY,
    user_id NUMBER REFERENCES users(user_id),
    tanggal_pinjam DATE DEFAULT SYSDATE,
    tanggal_kembali DATE,
    status_peminjaman VARCHAR2(20) CHECK (status_peminjaman IN ( 'Sedang Dipinjam', 'Selesai')),
    total_biaya NUMBER(10,2)
);
CREATE TABLE detail_peminjaman (
    detail_id NUMBER PRIMARY KEY,
    peminjaman_id NUMBER REFERENCES peminjaman(peminjaman_id),
    alat_id NUMBER REFERENCES alat_mendaki(alat_id),
    jumlah_pinjam NUMBER NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL
);
CREATE TABLE pembayaran (
    pembayaran_id NUMBER PRIMARY KEY,
    peminjaman_id NUMBER REFERENCES peminjaman(peminjaman_id),
    tanggal_pembayaran DATE DEFAULT SYSDATE,
    jumlah_pembayaran NUMBER(10,2),
    metode_pembayaran VARCHAR2(50),
    status_pembayaran VARCHAR2(20) CHECK (status_pembayaran IN ('DP', 'Lunas'))
);
ALTER TABLE pembayaran
ADD bukti_pembayaran VARCHAR2(255);

CREATE SEQUENCE seq_users START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_kategori_alat START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_alat_mendaki START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_peminjaman START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_detail_peminjaman START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_pembayaran START WITH 1 INCREMENT BY 1;
CREATE OR REPLACE TRIGGER trg_users_id
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    SELECT seq_users.NEXTVAL INTO :new.user_id FROM dual;
END;
/
CREATE OR REPLACE TRIGGER trg_kategori_id BEFORE INSERT ON kategori_alat
FOR EACH ROW BEGIN SELECT seq_kategori_alat.NEXTVAL INTO :new.kategori_id FROM dual; END;
/
CREATE OR REPLACE TRIGGER trg_alat_id BEFORE INSERT ON alat_mendaki
FOR EACH ROW BEGIN SELECT seq_alat_mendaki.NEXTVAL INTO :new.alat_id FROM dual; END;
/
CREATE OR REPLACE TRIGGER trg_peminjaman_id BEFORE INSERT ON peminjaman
FOR EACH ROW BEGIN SELECT seq_peminjaman.NEXTVAL INTO :new.peminjaman_id FROM dual; END;
/
CREATE OR REPLACE TRIGGER trg_detail_id BEFORE INSERT ON detail_peminjaman
FOR EACH ROW BEGIN SELECT seq_detail_peminjaman.NEXTVAL INTO :new.detail_id FROM dual; END;
/
CREATE OR REPLACE TRIGGER trg_pembayaran_id BEFORE INSERT ON pembayaran
FOR EACH ROW BEGIN SELECT seq_pembayaran.NEXTVAL INTO :new.pembayaran_id FROM dual; END;
/

INSERT INTO users (user_id, username, password, email, nama_lengkap, no_telepon, role, tanggal_registrasi)
VALUES (seq_users.NEXTVAL, 'admin', 'admin123', 'admin@email.com', 'Admin Pendakian', '081234567890', 'admin', SYSDATE);

INSERT INTO users (nama_lengkap, email, no_telepon, username, password)
VALUES ('Ivan Ahmad', 'ivan@email.com', '08123456789', 'ivan123', 'hashed_password');

COMMIT;

--- 1. Trigger untuk menangani detail peminjaman
CREATE OR REPLACE TRIGGER trg_manage_detail_peminjaman
AFTER INSERT ON detail_peminjaman
FOR EACH ROW
DECLARE
    v_status VARCHAR2(20);
BEGIN
    -- Cek status peminjaman
    SELECT status_peminjaman INTO v_status
    FROM peminjaman
    WHERE peminjaman_id = :new.peminjaman_id;
    
    -- Jika statusnya 'Sedang Dipinjam', kurangi jumlah_tersedia
    IF v_status = 'Sedang Dipinjam' THEN
        -- Kurangi jumlah tersedia
        UPDATE alat_mendaki
        SET jumlah_tersedia = jumlah_tersedia - :new.jumlah_pinjam
        WHERE alat_id = :new.alat_id;
        
        -- Log aktivitas
        INSERT INTO log_sistem (keterangan, tanggal_log)
        VALUES (
            'Peminjaman: ID Alat: ' || :new.alat_id || 
            ', Jumlah Dipinjam: ' || :new.jumlah_pinjam || 
            ', Untuk Peminjaman ID: ' || :new.peminjaman_id || 
            ', Tanggal: ' || TO_CHAR(SYSDATE, 'DD-MON-YYYY HH24:MI:SS'),
            SYSDATE
        );
    END IF;
    
    -- Update tanggal_kembali di peminjaman jika perlu
    UPDATE peminjaman
    SET tanggal_kembali = 
        CASE
            WHEN tanggal_kembali IS NULL THEN :new.tanggal_selesai
            WHEN :new.tanggal_selesai > tanggal_kembali THEN :new.tanggal_selesai
            ELSE tanggal_kembali
        END
    WHERE peminjaman_id = :new.peminjaman_id;
END;
/

-- 2. Trigger untuk mengembalikan alat saat status berubah menjadi 'Selesai'
CREATE OR REPLACE TRIGGER trg_manage_peminjaman_status
AFTER UPDATE OF status_peminjaman ON peminjaman
FOR EACH ROW
DECLARE
    v_jumlah_total NUMBER;
BEGIN
    -- Hanya proses jika status berubah menjadi 'Selesai'
    IF :new.status_peminjaman = 'Selesai' AND :old.status_peminjaman = 'Sedang Dipinjam' THEN
        -- Log awal proses pengembalian
        INSERT INTO log_sistem (keterangan, tanggal_log)
        VALUES (
            'Mulai proses pengembalian untuk Peminjaman ID: ' || :new.peminjaman_id || 
            ', Status berubah dari ' || :old.status_peminjaman || 
            ' menjadi ' || :new.status_peminjaman,
            SYSDATE
        );
        
        -- Kembalikan semua alat dalam peminjaman ini
        FOR item IN (
            SELECT dp.alat_id, dp.jumlah_pinjam, am.jumlah_total, am.jumlah_tersedia
            FROM detail_peminjaman dp
            JOIN alat_mendaki am ON dp.alat_id = am.alat_id
            WHERE dp.peminjaman_id = :new.peminjaman_id
        )
        LOOP
            -- Log sebelum update
            INSERT INTO log_sistem (keterangan, tanggal_log)
            VALUES (
                'SEBELUM UPDATE - ID Alat: ' || item.alat_id || 
                ', Jumlah Total: ' || item.jumlah_total || 
                ', Jumlah Tersedia: ' || item.jumlah_tersedia || 
                ', Jumlah Kembali: ' || item.jumlah_pinjam,
                SYSDATE
            );
            
            -- Update dengan batasan agar tidak melebihi jumlah_total
            UPDATE alat_mendaki
            SET jumlah_tersedia = CASE
                WHEN jumlah_tersedia + item.jumlah_pinjam > jumlah_total THEN 
                    jumlah_total
                ELSE 
                    jumlah_tersedia + item.jumlah_pinjam
                END
            WHERE alat_id = item.alat_id;
            
            -- Ambil jumlah tersedia yang baru untuk logging
            SELECT jumlah_tersedia INTO v_jumlah_total
            FROM alat_mendaki
            WHERE alat_id = item.alat_id;
            
            -- Log setelah update
            INSERT INTO log_sistem (keterangan, tanggal_log)
            VALUES (
                'SETELAH UPDATE - ID Alat: ' || item.alat_id || 
                ', Jumlah Kembali: ' || item.jumlah_pinjam || 
                ', Jumlah Tersedia Baru: ' || v_jumlah_total,
                SYSDATE
            );
        END LOOP;
    END IF;
END;
/

-- 3. Tambahkan constraint untuk memastikan jumlah_tersedia tidak bisa melebihi jumlah_total
-- Ini adalah pengaman tambahan
ALTER TABLE alat_mendaki ADD CONSTRAINT chk_jumlah_tersedia 
CHECK (jumlah_tersedia <= jumlah_total);

-- 4. Tambahkan trigger untuk memastikan jumlah tersedia tidak bisa negatif
CREATE OR REPLACE TRIGGER trg_check_stok_alat
BEFORE UPDATE OF jumlah_tersedia ON alat_mendaki
FOR EACH ROW
BEGIN
    -- Pastikan jumlah_tersedia tidak negatif
    IF :new.jumlah_tersedia < 0 THEN
        :new.jumlah_tersedia := 0;
        
        INSERT INTO log_sistem (keterangan, tanggal_log)
        VALUES (
            'KOREKSI - ID Alat: ' || :new.alat_id || 
            ' mencoba update ke jumlah_tersedia negatif, dikoreksi menjadi 0',
            SYSDATE
        );
    END IF;
    
    -- Pastikan jumlah_tersedia tidak melebihi jumlah_total
    IF :new.jumlah_tersedia > :new.jumlah_total THEN
        :new.jumlah_tersedia := :new.jumlah_total;
        
        INSERT INTO log_sistem (keterangan, tanggal_log)
        VALUES (
            'KOREKSI - ID Alat: ' || :new.alat_id || 
            ' mencoba update ke jumlah_tersedia > jumlah_total, dikoreksi menjadi ' || :new.jumlah_total,
            SYSDATE
        );
    END IF;
END;
/

-- 5. Procedure untuk reset stok jika masih ada masalah
CREATE OR REPLACE PROCEDURE reset_stok_alat(p_alat_id IN NUMBER) AS
    v_jumlah_total NUMBER;
    v_jumlah_dipinjam NUMBER := 0;
BEGIN
    -- Dapatkan jumlah total alat
    SELECT jumlah_total INTO v_jumlah_total
    FROM alat_mendaki
    WHERE alat_id = p_alat_id;
    
    -- Hitung jumlah yang sedang dipinjam
    SELECT NVL(SUM(dp.jumlah_pinjam), 0) INTO v_jumlah_dipinjam
    FROM detail_peminjaman dp
    JOIN peminjaman p ON dp.peminjaman_id = p.peminjaman_id
    WHERE dp.alat_id = p_alat_id
    AND p.status_peminjaman = 'Sedang Dipinjam';
    
    -- Update jumlah_tersedia
    UPDATE alat_mendaki
    SET jumlah_tersedia = v_jumlah_total - v_jumlah_dipinjam
    WHERE alat_id = p_alat_id;
    
    -- Log aksi
    INSERT INTO log_sistem (keterangan, tanggal_log)
    VALUES (
        'Reset stok untuk Alat ID: ' || p_alat_id || 
        ', Total: ' || v_jumlah_total || 
        ', Dipinjam: ' || v_jumlah_dipinjam || 
        ', Tersedia diset ke: ' || (v_jumlah_total - v_jumlah_dipinjam),
        SYSDATE
    );
    
    COMMIT;
END;
/