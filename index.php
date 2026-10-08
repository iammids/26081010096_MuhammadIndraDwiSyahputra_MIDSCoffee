<?php
/**
 * ISCOM Programming Day 3 — MIDS Coffee
 * Halaman untuk menampilkan data nyata dari 3 tabel relasional MySQL.
 */
declare(strict_types=1);

session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function e(mixed $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rupiah(int|float|string $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function tanggalIndonesia(string $date): string
{
    $months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    try {
        $dt = new DateTimeImmutable($date);
        return $dt->format('d') . ' ' . $months[(int) $dt->format('n')] . ' ' . $dt->format('Y');
    } catch (Exception) {
        return $date;
    }
}

$menu = [];
$pelanggan = [];
$pesanan = [];
$databaseError = false;

try {
    /** @var mysqli $conn */
    $conn = require __DIR__ . '/services/config.php';

    // Query SELECT #1: mengambil semua menu.
    $hasilMenu = $conn->query('SELECT id_menu, nama_menu, kategori, harga, tersedia FROM menu ORDER BY id_menu ASC');
    while ($row = $hasilMenu->fetch_assoc()) {
        $menu[] = $row;
    }
    $hasilMenu->free();

    // Query SELECT #2: mengambil semua pelanggan.
    $hasilPelanggan = $conn->query('SELECT id_pelanggan, nama_pelanggan, email, no_telepon, tanggal_daftar FROM pelanggan ORDER BY id_pelanggan ASC');
    while ($row = $hasilPelanggan->fetch_assoc()) {
        $pelanggan[] = $row;
    }
    $hasilPelanggan->free();

    // Query SELECT #3 dengan 2 JOIN: menampilkan nama pelanggan dan menu
    // dari foreign key pada tabel pesanan.
    $hasilPesanan = $conn->query(
        'SELECT p.id_pesanan, pl.nama_pelanggan, m.nama_menu, p.jumlah,
                p.harga_satuan, (p.jumlah * p.harga_satuan) AS total_harga,
                p.tanggal_pesanan, p.status
         FROM pesanan AS p
         INNER JOIN pelanggan AS pl ON pl.id_pelanggan = p.id_pelanggan
         INNER JOIN menu AS m ON m.id_menu = p.id_menu
         ORDER BY p.id_pesanan DESC'
    );
    while ($row = $hasilPesanan->fetch_assoc()) {
        $pesanan[] = $row;
    }
    $hasilPesanan->free();
    $conn->close();
} catch (Throwable $exception) {
    // Pesan error asli tidak diperlihatkan ke pengunjung agar tidak membocorkan konfigurasi.
    error_log('[MIDS COFFEE Database] ' . $exception->getMessage());
    $databaseError = true;
    http_response_code(503);
}

$pendapatan = 0;
$pesananSelesai = 0;
foreach ($pesanan as $p) {
    if ($p['status'] === 'Selesai') {
        $pendapatan += (float) $p['total_harga'];
        $pesananSelesai++;
    }
}
$totalMenuTersedia = count(array_filter($menu, fn(array $m): bool => (int) $m['tersedia'] === 1));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#a34a28">
    <meta name="description" content="Dashboard MIDS Coffee: data menu, pelanggan, dan pesanan dari MySQL secara dinamis menggunakan PHP mysqli.">
    <title>MIDS Coffee | Dashboard Database</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<a class="skip-link" href="#konten">Lewati navigasi</a>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="#atas" aria-label="MIDS Coffee, kembali ke atas">
            <span class="brand-icon" aria-hidden="true">
                <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h13v5a6 6 0 0 1-6 6h-1a6 6 0 0 1-6-6V8Z"/><path d="M17 9h2a3 3 0 0 1 0 6h-2"/><path d="M8 3v2M13 3v2"/></svg>
            </span>
            <span>MIDS<span class="brand-dot">.</span><small>COFFEE</small></span>
        </a>
        <nav class="top-links" aria-label="Navigasi utama">
            <a href="#menu">Menu</a>
            <a href="#pelanggan">Pelanggan</a>
            <a href="#pesanan">Pesanan</a>
        </nav>
        <span class="topbar-badge"><span class="live-dot" aria-hidden="true"></span> Database MySQL</span>
    </div>
</header>

<main id="konten">
    <div class="container" id="atas">
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-copy">
                <div class="eyebrow"><span class="eyebrow-line"></span> ISCOM / PROGRAMMING DAY 3</div>
                <h1 id="hero-title">Semua data MIDS Coffee,<br><em>dalam satu tempat.</em></h1>
                <p>Dashboard sederhana untuk memantau menu, pelanggan, dan pesanan yang tersimpan di database relasional.</p>
                <div class="hero-bottom">
                    <a href="#menu" class="button-primary">Jelajahi data <span aria-hidden="true">↗</span></a>
                    <span class="hero-caption">PHP <span aria-hidden="true">/</span> MySQL <span aria-hidden="true">/</span> mysqli</span>
                </div>
            </div>
            <div class="hero-art" aria-hidden="true">
                <div class="art-circle art-circle-lg"></div>
                <div class="art-circle art-circle-sm"></div>
                <div class="coffee-cup">
                    <div class="cup-top"><div class="latte-art"><span></span><span></span><span></span></div></div>
                    <div class="cup-handle"></div>
                </div>
                <span class="art-label">EST. 2026</span>
            </div>
        </section>

        <?php if ($databaseError): ?>
        <div class="alert" role="alert">
            <strong>Database belum terhubung.</strong>
            <p>Pastikan MySQL di Laragon aktif, jalankan <code>database/schema.sql</code> melalui DBeaver, lalu periksa <code>services/config.php</code> dan muat ulang halaman.</p>
        </div>
        <?php endif; ?>

        <?php if (is_array($flash) && isset($flash['text'])): ?>
        <div class="flash <?= ($flash['kind'] ?? '') === 'success' ? 'flash-success' : 'flash-error' ?>" role="status">
            <?= e($flash['text']) ?>
        </div>
        <?php endif; ?>

        <section class="overview" aria-labelledby="overview-title">
            <div class="section-heading overview-heading">
                <div><p class="overline">RINGKASAN</p><h2 id="overview-title">Sekilas MIDS Coffee</h2></div>
                <span class="section-note">Bersumber langsung dari database</span>
            </div>
            <div class="stats-grid">
                <article class="stat-card">
                    <div class="stat-top"><span>Total menu</span><span class="stat-symbol" aria-hidden="true">☕</span></div>
                    <strong><?= count($menu) ?></strong>
                    <small><?= $totalMenuTersedia ?> menu tersedia</small>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Total pelanggan</span><span class="stat-symbol" aria-hidden="true">♙</span></div>
                    <strong><?= count($pelanggan) ?></strong>
                    <small>Terdaftar di database</small>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Total pesanan</span><span class="stat-symbol" aria-hidden="true">▤</span></div>
                    <strong><?= count($pesanan) ?></strong>
                    <small><?= $pesananSelesai ?> pesanan selesai</small>
                </article>
                <article class="stat-card stat-card-highlight">
                    <div class="stat-top"><span>Omzet pesanan selesai</span><span class="stat-symbol" aria-hidden="true">↗</span></div>
                    <strong class="stat-currency"><?= e(rupiah($pendapatan)) ?></strong>
                    <small>Jumlah × harga saat transaksi</small>
                </article>
            </div>
        </section>

        <section class="data-section" id="menu" aria-labelledby="menu-title">
            <div class="section-heading">
                <div><p class="overline">TABEL 01 / MASTER</p><h2 id="menu-title">Daftar menu <span class="heading-count"><?= count($menu) ?></span></h2></div>
                <div class="section-actions"><p class="section-note">Informasi produk yang dijual MIDS Coffee</p><a class="add-button" href="manage.php?type=menu&amp;action=create">+ Tambah menu</a></div>
            </div>
            <div class="table-card"><div class="table-scroll">
                <table>
                    <caption class="sr-only">Daftar semua menu dari tabel menu</caption>
                    <thead><tr><th scope="col">ID</th><th scope="col">Nama menu</th><th scope="col">Kategori</th><th scope="col">Harga</th><th scope="col">Ketersediaan</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                    <?php if (count($menu) === 0): ?>
                        <tr><td class="empty-row" colspan="6">Belum ada data menu.</td></tr>
                    <?php else: foreach ($menu as $m): ?>
                        <tr>
                            <td class="id-cell">#<?= e($m['id_menu']) ?></td>
                            <td class="name-cell"><?= e($m['nama_menu']) ?></td>
                            <td><?= e($m['kategori']) ?></td>
                            <td class="number-cell"><?= e(rupiah($m['harga'])) ?></td>
                            <td><span class="pill <?= (int) $m['tersedia'] === 1 ? 'pill-available' : 'pill-muted' ?>"><span class="pill-dot"></span><?= (int) $m['tersedia'] === 1 ? 'Tersedia' : 'Habis' ?></span></td>
                            <td class="table-actions">
                                <a class="edit-button" href="manage.php?type=menu&amp;action=edit&amp;id=<?= (int) $m['id_menu'] ?>">Edit</a>
                                <form class="delete-form" method="post" action="manage.php" onsubmit="return confirm('Hapus menu ini? Menu yang pernah dipesan tidak dapat dihapus.');">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="type" value="menu"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id_menu'] ?>">
                                    <button class="delete-button" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div></div>
        </section>

        <section class="data-section" id="pelanggan" aria-labelledby="pelanggan-title">
            <div class="section-heading">
                <div><p class="overline">TABEL 02 / MASTER</p><h2 id="pelanggan-title">Data pelanggan <span class="heading-count"><?= count($pelanggan) ?></span></h2></div>
                <div class="section-actions"><p class="section-note">Pelanggan terdaftar beserta kontak</p><a class="add-button" href="manage.php?type=pelanggan&amp;action=create">+ Tambah pelanggan</a></div>
            </div>
            <div class="table-card"><div class="table-scroll">
                <table>
                    <caption class="sr-only">Daftar semua pelanggan dari tabel pelanggan</caption>
                    <thead><tr><th scope="col">ID</th><th scope="col">Nama pelanggan</th><th scope="col">Email</th><th scope="col">Nomor telepon</th><th scope="col">Terdaftar</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                    <?php if (count($pelanggan) === 0): ?>
                        <tr><td class="empty-row" colspan="6">Belum ada data pelanggan.</td></tr>
                    <?php else: foreach ($pelanggan as $pl): ?>
                        <tr>
                            <td class="id-cell">#<?= e($pl['id_pelanggan']) ?></td>
                            <td class="name-cell"><span class="avatar" aria-hidden="true"><?= e(mbFirstLetter($pl['nama_pelanggan'])) ?></span><?= e($pl['nama_pelanggan']) ?></td>
                            <td><?= e($pl['email']) ?></td>
                            <td class="number-cell"><?= e($pl['no_telepon']) ?></td>
                            <td><?= e(tanggalIndonesia($pl['tanggal_daftar'])) ?></td>
                            <td class="table-actions">
                                <a class="edit-button" href="manage.php?type=pelanggan&amp;action=edit&amp;id=<?= (int) $pl['id_pelanggan'] ?>">Edit</a>
                                <form class="delete-form" method="post" action="manage.php" onsubmit="return confirm('Hapus pelanggan ini? Pelanggan yang punya pesanan tidak dapat dihapus.');">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="type" value="pelanggan"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $pl['id_pelanggan'] ?>">
                                    <button class="delete-button" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div></div>
        </section>

        <section class="data-section data-section-last" id="pesanan" aria-labelledby="pesanan-title">
            <div class="section-heading">
                <div><p class="overline">TABEL 03 / TRANSAKSI</p><h2 id="pesanan-title">Riwayat pesanan <span class="heading-count"><?= count($pesanan) ?></span></h2></div>
                <div class="section-actions"><p class="section-note">Hasil JOIN dari pesanan, pelanggan, dan menu</p><a class="add-button" href="manage.php?type=pesanan&amp;action=create">+ Tambah pesanan</a></div>
            </div>
            <div class="table-card"><div class="table-scroll">
                <table>
                    <caption class="sr-only">Riwayat pesanan, nama pelanggan, dan nama menu melalui SQL JOIN</caption>
                    <thead><tr><th scope="col">ID</th><th scope="col">Pelanggan</th><th scope="col">Pesanan menu</th><th scope="col">Qty</th><th scope="col">Total harga</th><th scope="col">Status</th><th scope="col">Tanggal</th><th scope="col">Aksi</th></tr></thead>
                    <tbody>
                    <?php if (count($pesanan) === 0): ?>
                        <tr><td class="empty-row" colspan="8">Belum ada data pesanan.</td></tr>
                    <?php else: foreach ($pesanan as $p): ?>
                        <?php $pillClass = match ($p['status']) { 'Selesai' => 'pill-available', 'Diproses' => 'pill-pending', default => 'pill-muted' }; ?>
                        <tr>
                            <td class="id-cell">#<?= e($p['id_pesanan']) ?></td>
                            <td class="name-cell"><?= e($p['nama_pelanggan']) ?></td>
                            <td><?= e($p['nama_menu']) ?></td>
                            <td class="number-cell"><?= e($p['jumlah']) ?>x</td>
                            <td class="number-cell amount-cell"><?= e(rupiah($p['total_harga'])) ?></td>
                            <td><span class="pill <?= $pillClass ?>"><span class="pill-dot"></span><?= e($p['status']) ?></span></td>
                            <td class="number-cell"><?= e(tanggalIndonesia($p['tanggal_pesanan'])) ?></td>
                            <td class="table-actions">
                                <a class="edit-button" href="manage.php?type=pesanan&amp;action=edit&amp;id=<?= (int) $p['id_pesanan'] ?>">Edit</a>
                                <form class="delete-form" method="post" action="manage.php" onsubmit="return confirm('Yakin ingin menghapus pesanan ini secara permanen?');">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="type" value="pesanan"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id_pesanan'] ?>">
                                    <button class="delete-button" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div></div>
            <p class="table-hint">↗ Data tampil otomatis saat database MySQL diperbarui dan halaman dimuat ulang.</p>
        </section>
    </div>
</main>

<footer class="footer"><div class="container footer-inner"><span class="footer-logo">MIDS<span>.</span></span><p>Website Database Relasional · Mentoring ISCOM Day 3</p><span>2026 © MIDS Coffee</span></div></footer>
</body>
</html>
<?php
/** Ambil inisial tanpa perlu ekstensi mbstring, tetap aman untuk nama UTF-8 biasa. */
function mbFirstLetter(string $name): string
{
    return strtoupper(substr(trim($name), 0, 1));
}
