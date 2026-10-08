-- ================================================================
-- CONTOH QUERY SQL ISCOM Day 3 / CHALLENGE 1 - MIDS COFFEE
-- Jalankan setelah database/schema.sql selesai dijalankan.
-- Skrip ini MEMPERBARUI harga menu #2 dan MENGHAPUS pesanan #8.
-- Re-import schema.sql jika ingin mengembalikan data awal.
-- ================================================================
USE mids_coffee;

-- 1) SELECT: lihat seluruh menu.
SELECT id_menu, nama_menu, kategori, harga, tersedia
FROM menu
ORDER BY id_menu;

-- 2) SELECT dengan JOIN: tampilkan relasi 3 tabel.
SELECT
    p.id_pesanan,
    pl.nama_pelanggan,
    m.nama_menu,
    p.jumlah,
    p.harga_satuan,
    (p.jumlah * p.harga_satuan) AS total_harga,
    p.status,
    p.tanggal_pesanan
FROM pesanan AS p
INNER JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
INNER JOIN menu AS m ON m.id_menu = p.id_menu
ORDER BY p.id_pesanan;

-- 3) UPDATE: simulasi perubahan harga MINUMAN Es Kopi Susu Aren.
-- Nilai awal Rp 23.000; sesudah UPDATE menjadi Rp 25.000.
SELECT id_menu, nama_menu, harga FROM menu WHERE id_menu = 2;
UPDATE menu SET harga = 25000 WHERE id_menu = 2;
SELECT id_menu, nama_menu, harga FROM menu WHERE id_menu = 2;

-- Catatan: harga_satuan pada pesanan lama TIDAK ikut berubah.
-- Ini menjaga riwayat transaksi tetap benar.

-- 4) DELETE: hapus pesanan berstatus Dibatalkan (ID 8).
SELECT id_pesanan, id_pelanggan, id_menu, status
FROM pesanan WHERE id_pesanan = 8;
DELETE FROM pesanan
WHERE id_pesanan = 8 AND status = 'Dibatalkan';
SELECT id_pesanan, id_pelanggan, id_menu, status
FROM pesanan WHERE id_pesanan = 8;

-- 5) Pemeriksaan akhir (tetap >= 5 baris pada setiap tabel).
SELECT 'menu' AS nama_tabel, COUNT(*) AS total_baris FROM menu
UNION ALL
SELECT 'pelanggan', COUNT(*) FROM pelanggan
UNION ALL
SELECT 'pesanan', COUNT(*) FROM pesanan;
-- Hasil akhir yang diharapkan: menu=8; pelanggan=6; pesanan=7.
