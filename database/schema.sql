-- ===========================================================
-- ISCOM Day 3: Database MIDS Coffee
-- DDL: CREATE DATABASE, CREATE TABLE dengan PK dan FK
-- DML: INSERT minimal 5 baris pada SETIAP tabel
-- Jalankan seluruh file ini di DBeaver (Alt + X).
-- PERHATIAN: DROP TABLE menghapus data lama saat script diulang.
-- ===========================================================

CREATE DATABASE IF NOT EXISTS mids_coffee
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE mids_coffee;

-- Urutan penghapusan memperhatikan hubungan Foreign Key.
DROP TABLE IF EXISTS pesanan;
DROP TABLE IF EXISTS pelanggan;
DROP TABLE IF EXISTS menu;

-- MASTER 1: 1 menu bisa muncul di banyak pesanan.
CREATE TABLE menu (
    id_menu INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_menu VARCHAR(100) NOT NULL,
    kategori ENUM('Kopi', 'Non-Kopi', 'Makanan') NOT NULL,
    harga DECIMAL(10,2) NOT NULL,
    tersedia TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- MASTER 2: 1 pelanggan dapat melakukan banyak pesanan.
CREATE TABLE pelanggan (
    id_pelanggan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_pelanggan VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    no_telepon VARCHAR(20) NOT NULL,
    tanggal_daftar DATE NOT NULL
) ENGINE=InnoDB;

-- TRANSAKSI: menghubungkan pelanggan dan menu melalui FK.
-- Satu baris pesanan berisi satu jenis menu dengan kuantitas tertentu.
-- harga_satuan menyimpan harga SAAT dipesan (riwayat tetap akurat
-- meskipun harga menu di tabel master diperbarui).
CREATE TABLE pesanan (
    id_pesanan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pelanggan INT UNSIGNED NOT NULL,
    id_menu INT UNSIGNED NOT NULL,
    jumlah INT UNSIGNED NOT NULL DEFAULT 1,
    harga_satuan DECIMAL(10,2) NOT NULL,
    tanggal_pesanan DATETIME NOT NULL,
    status ENUM('Diproses', 'Selesai', 'Dibatalkan') NOT NULL DEFAULT 'Diproses',
    CONSTRAINT fk_pesanan_pelanggan
        FOREIGN KEY (id_pelanggan) REFERENCES pelanggan (id_pelanggan)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pesanan_menu
        FOREIGN KEY (id_menu) REFERENCES menu (id_menu)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ===================== DATA AWAL =====================
-- 8 baris menu
INSERT INTO menu (id_menu, nama_menu, kategori, harga, tersedia) VALUES
(1, 'Espresso', 'Kopi', 18000, 1),
(2, 'Es Kopi Susu Aren', 'Kopi', 23000, 1),
(3, 'Cappuccino', 'Kopi', 26000, 1),
(4, 'Matcha Latte', 'Non-Kopi', 28000, 1),
(5, 'Chocolate Ice', 'Non-Kopi', 25000, 1),
(6, 'Butter Croissant', 'Makanan', 22000, 1),
(7, 'Nasi Ayam Sambal Matah', 'Makanan', 35000, 1),
(8, 'Kentang Goreng', 'Makanan', 20000, 0);

-- 6 baris pelanggan; alamat email contoh tidak ditujukan untuk penggunaan nyata
INSERT INTO pelanggan (id_pelanggan, nama_pelanggan, email, no_telepon, tanggal_daftar) VALUES
(1, 'Alya Putri', 'alya@example.com', '081234560001', '2026-09-25'),
(2, 'Raka Pratama', 'raka@example.com', '081234560002', '2026-09-26'),
(3, 'Nadia Azzahra', 'nadia@example.com', '081234560003', '2026-09-28'),
(4, 'Dimas Saputra', 'dimas@example.com', '081234560004', '2026-09-30'),
(5, 'Salsa Maharani', 'salsa@example.com', '081234560005', '2026-10-01'),
(6, 'Farhan Akbar', 'farhan@example.com', '081234560006', '2026-10-02');

-- 8 baris pesanan; ID 8 dibatalkan dan akan dihapus pada challenge DELETE.
INSERT INTO pesanan
(id_pesanan, id_pelanggan, id_menu, jumlah, harga_satuan, tanggal_pesanan, status) VALUES
(1, 1, 2, 2, 23000, '2026-10-02 09:20:00', 'Selesai'),
(2, 2, 4, 1, 28000, '2026-10-02 10:10:00', 'Selesai'),
(3, 3, 7, 1, 35000, '2026-10-03 12:35:00', 'Selesai'),
(4, 1, 6, 1, 22000, '2026-10-04 08:45:00', 'Selesai'),
(5, 4, 3, 2, 26000, '2026-10-05 13:15:00', 'Selesai'),
(6, 5, 8, 1, 20000, '2026-10-07 16:40:00', 'Diproses'),
(7, 6, 1, 3, 18000, '2026-10-08 09:05:00', 'Diproses'),
(8, 2, 5, 1, 25000, '2026-10-08 10:50:00', 'Dibatalkan');

-- Cek jumlah awal: menu=8, pelanggan=6, pesanan=8.
SELECT 'menu' AS nama_tabel, COUNT(*) AS total_baris FROM menu
UNION ALL
SELECT 'pelanggan', COUNT(*) FROM pelanggan
UNION ALL
SELECT 'pesanan', COUNT(*) FROM pesanan;
