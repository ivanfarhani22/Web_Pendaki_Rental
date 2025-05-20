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
    status_peminjaman VARCHAR2(20) CHECK (status_peminjaman IN ('Diajukan', 'Disetujui', 'Ditolak', 'Sedang Dipinjam', 'Selesai')),
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
    status_pembayaran VARCHAR2(20) CHECK (status_pembayaran IN ('Menunggu', 'Lunas', 'Gagal'))
);
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

-- Create a trigger to set tanggal_kembali based on the end date from detail_peminjaman
CREATE OR REPLACE TRIGGER trg_set_tanggal_kembali
AFTER INSERT ON detail_peminjaman
FOR EACH ROW
DECLARE
    v_current_end_date DATE;
    v_peminjaman_end_date DATE;
BEGIN
    -- Get current end date in peminjaman
    SELECT tanggal_kembali INTO v_peminjaman_end_date
    FROM peminjaman
    WHERE peminjaman_id = :new.peminjaman_id;
    
    -- If tanggal_kembali is NULL or the new end date is later, update it
    IF v_peminjaman_end_date IS NULL OR :new.tanggal_selesai > v_peminjaman_end_date THEN
        UPDATE peminjaman
        SET tanggal_kembali = :new.tanggal_selesai
        WHERE peminjaman_id = :new.peminjaman_id;
    END IF;
END;
/

-- Create a trigger to update equipment availability when peminjaman status changes
CREATE OR REPLACE TRIGGER trg_update_equipment_availability
AFTER UPDATE OF status_peminjaman ON peminjaman
FOR EACH ROW
BEGIN
    -- When status changes to "Selesai"
    IF :new.status_peminjaman = 'Selesai' AND :old.status_peminjaman != 'Selesai' THEN
        -- Update equipment availability for all items in this peminjaman
        FOR rec IN (
            SELECT alat_id, jumlah_pinjam
            FROM detail_peminjaman
            WHERE peminjaman_id = :new.peminjaman_id
        )
        LOOP
            -- Increase available count
            UPDATE alat_mendaki
            SET jumlah_tersedia = jumlah_tersedia + rec.jumlah_pinjam
            WHERE alat_id = rec.alat_id;
        END LOOP;
    -- When status changes to "Sedang Dipinjam" from "Disetujui"
    ELSIF :new.status_peminjaman = 'Sedang Dipinjam' AND :old.status_peminjaman = 'Disetujui' THEN
        -- Nothing to do here as the equipment is already marked as unavailable
        NULL;
    END IF;
END;
/

-- Create a trigger to update equipment availability when a peminjaman is approved
CREATE OR REPLACE TRIGGER trg_approve_peminjaman
AFTER UPDATE OF status_peminjaman ON peminjaman
FOR EACH ROW
BEGIN
    -- When status changes to "Disetujui" 
    IF :new.status_peminjaman = 'Disetujui' AND :old.status_peminjaman = 'Diajukan' THEN
        -- Update equipment availability for all items in this peminjaman
        FOR rec IN (
            SELECT alat_id, jumlah_pinjam
            FROM detail_peminjaman
            WHERE peminjaman_id = :new.peminjaman_id
        )
        LOOP
            -- Decrease available count because equipment is now reserved
            UPDATE alat_mendaki
            SET jumlah_tersedia = jumlah_tersedia - rec.jumlah_pinjam
            WHERE alat_id = rec.alat_id;
        END LOOP;
    END IF;
END;
/

-- Create a procedure that can be called to check for expired rentals
CREATE OR REPLACE PROCEDURE check_expired_rentals AS
BEGIN
    -- Update peminjaman status to "Selesai" when tanggal_kembali is reached
    UPDATE peminjaman
    SET status_peminjaman = 'Selesai'
    WHERE tanggal_kembali <= TRUNC(SYSDATE)
    AND status_peminjaman = 'Sedang Dipinjam';
    
    -- The trg_update_equipment_availability trigger will handle returning equipment to inventory
    
    COMMIT;
END;
/

-- Grant execute permission to the procedure (adjust as needed for your user)
GRANT EXECUTE ON check_expired_rentals TO pendaki;