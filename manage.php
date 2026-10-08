<?php
/**
 * CRUD MIDS Coffee — satu halaman formulir untuk menu, pelanggan dan pesanan.
 * Semua perubahan database memakai prepared statements dan POST + CSRF token.
 */
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Jakarta');

function esc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function goHome(string $type): void
{
    header('Location: index.php#' . $type, true, 303);
    exit;
}

function positiveId(mixed $raw): int
{
    $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? 0 : (int) $id;
}

function oneRow(mysqli $db, string $sql, int $id): ?array
{
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function runStatement(mysqli $db, string $sql, string $types, mixed ...$args): int
{
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$args);
    $stmt->execute();
    $changed = $stmt->affected_rows;
    $stmt->close();
    return $changed;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$source = $isPost ? $_POST : $_GET;
$type = (string) ($source['type'] ?? '');
$action = (string) ($source['action'] ?? 'create');
$id = positiveId($source['id'] ?? 0);
$labels = ['menu' => 'Menu', 'pelanggan' => 'Pelanggan', 'pesanan' => 'Pesanan'];

if (!isset($labels[$type]) || !in_array($action, ['create', 'edit', 'delete'], true)) {
    http_response_code(400);
    exit('Permintaan tidak valid. <a href="index.php">Kembali ke dashboard</a>');
}
if ($action !== 'create' && $id < 1) {
    http_response_code(400);
    exit('ID tidak valid. <a href="index.php">Kembali ke dashboard</a>');
}
if (!$isPost && $action === 'delete') {
    http_response_code(405);
    exit('Hapus data hanya dapat dilakukan melalui tombol Hapus di dashboard.');
}

if ($isPost && !hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Sesi formulir tidak berlaku. Muat ulang halaman dan coba lagi.');
}

$errors = [];
$values = [];
$choicesMenu = [];
$choicesPelanggan = [];
$databaseError = false;

try {
    /** @var mysqli $db */
    $db = require __DIR__ . '/services/config.php';

    // Delete hanya POST. Foreign Key menghalangi penghapusan master yg masih digunakan.
    if ($isPost && $action === 'delete') {
        try {
            $pk = ['menu' => 'id_menu', 'pelanggan' => 'id_pelanggan', 'pesanan' => 'id_pesanan'][$type];
            $deleted = runStatement($db, "DELETE FROM {$type} WHERE {$pk} = ?", 'i', $id);
            $_SESSION['flash'] = $deleted > 0
                ? ['kind' => 'success', 'text' => ucfirst($type) . ' berhasil dihapus.']
                : ['kind' => 'error', 'text' => 'Data tidak ditemukan atau sudah dihapus.'];
        } catch (mysqli_sql_exception $ex) {
            if ((int) $ex->getCode() === 1451) {
                $_SESSION['flash'] = ['kind' => 'error', 'text' => 'Data ini masih digunakan oleh pesanan. Hapus pesanan terkait terlebih dahulu.'];
            } else {
                error_log('[MIDS CRUD] ' . $ex->getMessage());
                $_SESSION['flash'] = ['kind' => 'error', 'text' => 'Gagal menghapus data. Silakan coba kembali.'];
            }
        }
        $db->close();
        goHome($type);
    }

    if ($isPost) {
        // Nilai kiriman pengguna tidak pernah digunakan sebagai bagian dari sintaks SQL.
        $values = array_map(static fn($v) => is_string($v) ? trim($v) : '', $_POST);
        if ($action === 'edit' && $type !== 'pesanan') {
            $pk = $type === 'menu' ? 'id_menu' : 'id_pelanggan';
            if (!oneRow($db, "SELECT {$pk} FROM {$type} WHERE {$pk}=?", $id)) {
                $errors[] = 'Data yang ingin diedit tidak ditemukan.';
            }
        }

        if ($type === 'menu') {
            $name = (string) ($values['nama_menu'] ?? '');
            $category = (string) ($values['kategori'] ?? '');
            $priceRaw = (string) ($values['harga'] ?? '');
            $availableRaw = (string) ($values['tersedia'] ?? '');
            if ($name === '' || strlen($name) > 100) $errors[] = 'Nama menu wajib diisi (maksimal 100 karakter).';
            if (!in_array($category, ['Kopi', 'Non-Kopi', 'Makanan'], true)) $errors[] = 'Pilih kategori menu yang benar.';
            if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $priceRaw) || (float) $priceRaw <= 0) $errors[] = 'Harga harus lebih besar dari 0, maksimal 2 angka desimal.';
            if (!in_array($availableRaw, ['0', '1'], true)) $errors[] = 'Pilih status ketersediaan.';
            if (!$errors) {
                $price = (float) $priceRaw;
                $available = (int) $availableRaw;
                if ($action === 'create') {
                    runStatement($db, 'INSERT INTO menu (nama_menu, kategori, harga, tersedia) VALUES (?, ?, ?, ?)', 'ssdi', $name, $category, $price, $available);
                } else {
                    runStatement($db, 'UPDATE menu SET nama_menu=?, kategori=?, harga=?, tersedia=? WHERE id_menu=?', 'ssdii', $name, $category, $price, $available, $id);
                }
            }
        } elseif ($type === 'pelanggan') {
            $name = (string) ($values['nama_pelanggan'] ?? '');
            $email = (string) ($values['email'] ?? '');
            $phone = (string) ($values['no_telepon'] ?? '');
            $joined = (string) ($values['tanggal_daftar'] ?? '');
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $joined);
            if ($name === '' || strlen($name) > 100) $errors[] = 'Nama pelanggan wajib diisi (maksimal 100 karakter).';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $errors[] = 'Masukkan alamat email yang valid.';
            if (!preg_match('/^[0-9+()\s-]{8,20}$/D', $phone)) $errors[] = 'Nomor telepon harus 8–20 karakter (angka, spasi, +, atau -).';
            if (!$dt || $dt->format('Y-m-d') !== $joined) $errors[] = 'Tanggal daftar tidak valid.';
            if (!$errors) {
                if ($action === 'create') {
                    runStatement($db, 'INSERT INTO pelanggan (nama_pelanggan, email, no_telepon, tanggal_daftar) VALUES (?, ?, ?, ?)', 'ssss', $name, $email, $phone, $joined);
                } else {
                    runStatement($db, 'UPDATE pelanggan SET nama_pelanggan=?, email=?, no_telepon=?, tanggal_daftar=? WHERE id_pelanggan=?', 'ssssi', $name, $email, $phone, $joined, $id);
                }
            }
        } else {
            $customerId = positiveId($values['id_pelanggan'] ?? 0);
            $menuId = positiveId($values['id_menu'] ?? 0);
            $quantity = positiveId($values['jumlah'] ?? 0);
            $status = (string) ($values['status'] ?? '');
            $dateRaw = (string) ($values['tanggal_pesanan'] ?? '');
            $when = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $dateRaw);
            if ($customerId < 1 || !oneRow($db, 'SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan=?', $customerId)) $errors[] = 'Pilih pelanggan yang tersedia.';
            $selectedMenu = $menuId ? oneRow($db, 'SELECT id_menu, harga, tersedia FROM menu WHERE id_menu=?', $menuId) : null;
            if (!$selectedMenu) $errors[] = 'Pilih menu yang tersedia.';
            if ($quantity < 1 || $quantity > 1000) $errors[] = 'Jumlah pesanan harus antara 1 dan 1000.';
            if (!in_array($status, ['Diproses', 'Selesai', 'Dibatalkan'], true)) $errors[] = 'Pilih status pesanan.';
            if (!$when || $when->format('Y-m-d\TH:i') !== $dateRaw) $errors[] = 'Tanggal dan jam pesanan tidak valid.';
            $existing = $action === 'edit'
                ? oneRow($db, 'SELECT id_menu, harga_satuan FROM pesanan WHERE id_pesanan=?', $id)
                : null;
            if ($action === 'edit' && !$existing) $errors[] = 'Pesanan yang ingin diedit tidak ditemukan.';
            if ($selectedMenu && (int) $selectedMenu['tersedia'] === 0 && ($action === 'create' || (int) ($existing['id_menu'] ?? 0) !== $menuId)) {
                $errors[] = 'Menu habis tidak dapat dipilih untuk pesanan baru.';
            }
            if (!$errors) {
                // Harga pesanan adalah snapshot: mengedit status/jumlah tidak mengganti harga lama.
                $unitPrice = $existing && (int) $existing['id_menu'] === $menuId
                    ? (float) $existing['harga_satuan'] : (float) $selectedMenu['harga'];
                $date = $when->format('Y-m-d H:i:s');
                if ($action === 'create') {
                    runStatement($db, 'INSERT INTO pesanan (id_pelanggan, id_menu, jumlah, harga_satuan, tanggal_pesanan, status) VALUES (?, ?, ?, ?, ?, ?)', 'iiidss', $customerId, $menuId, $quantity, $unitPrice, $date, $status);
                } else {
                    runStatement($db, 'UPDATE pesanan SET id_pelanggan=?, id_menu=?, jumlah=?, harga_satuan=?, tanggal_pesanan=?, status=? WHERE id_pesanan=?', 'iiidssi', $customerId, $menuId, $quantity, $unitPrice, $date, $status, $id);
                }
            }
        }
        if (!$errors) {
            $_SESSION['flash'] = ['kind' => 'success', 'text' => ucfirst($type) . ($action === 'create' ? ' berhasil ditambahkan.' : ' berhasil diperbarui.')];
            $db->close();
            goHome($type);
        }
    } elseif ($action === 'edit') {
        $pk = ['menu' => 'id_menu', 'pelanggan' => 'id_pelanggan', 'pesanan' => 'id_pesanan'][$type];
        $values = oneRow($db, "SELECT * FROM {$type} WHERE {$pk} = ?", $id) ?? [];
        if (!$values) {
            $_SESSION['flash'] = ['kind' => 'error', 'text' => 'Data yang ingin diedit tidak ditemukan.'];
            $db->close();
            goHome($type);
        }
        if ($type === 'pesanan') {
            $values['tanggal_pesanan'] = date('Y-m-d\TH:i', strtotime((string) $values['tanggal_pesanan']));
        }
    } else {
        $values = [
            'kategori' => 'Kopi', 'tersedia' => '1',
            'tanggal_daftar' => date('Y-m-d'), 'tanggal_pesanan' => date('Y-m-d\TH:i'),
            'jumlah' => '1', 'status' => 'Diproses',
        ];
    }

    if ($type === 'pesanan') {
        $result = $db->query('SELECT id_pelanggan, nama_pelanggan FROM pelanggan ORDER BY nama_pelanggan');
        while ($row = $result->fetch_assoc()) $choicesPelanggan[] = $row;
        $result->free();
        $result = $db->query('SELECT id_menu, nama_menu, harga, tersedia FROM menu ORDER BY nama_menu');
        while ($row = $result->fetch_assoc()) $choicesMenu[] = $row;
        $result->free();
    }
    $db->close();
} catch (mysqli_sql_exception $ex) {
    error_log('[MIDS CRUD Database] ' . $ex->getMessage());
    if ((int) $ex->getCode() === 1062) {
        $errors[] = 'Email tersebut sudah digunakan pelanggan lain. Gunakan alamat email yang berbeda.';
    } elseif ((int) $ex->getCode() === 1451) {
        $errors[] = 'Data masih digunakan oleh pesanan lain dan tidak dapat dihapus.';
    } else {
        $errors[] = 'Perubahan belum disimpan. Periksa koneksi database dan coba lagi.';
    }
} catch (Throwable $ex) {
    error_log('[MIDS CRUD] ' . $ex->getMessage());
    $databaseError = true;
    $errors[] = 'Tidak dapat menghubungkan ke database. Periksa Laragon dan services/config.php.';
}

$pageTitle = ($action === 'create' ? 'Tambah ' : 'Edit ') . $labels[$type];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#a34a28">
  <title><?= esc($pageTitle) ?> | MIDS Coffee</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<a class="skip-link" href="#konten">Lewati navigasi</a>
<header class="topbar"><div class="container topbar-inner">
  <a class="brand" href="index.php" aria-label="Kembali ke dashboard MIDS Coffee">
    <span class="brand-icon" aria-hidden="true">☕</span><span>MIDS<span class="brand-dot">.</span><small>COFFEE</small></span>
  </a>
  <nav class="top-links"><a href="index.php#menu">Menu</a><a href="index.php#pelanggan">Pelanggan</a><a href="index.php#pesanan">Pesanan</a></nav>
  <span class="topbar-badge">Kelola Database</span>
</div></header>
<main id="konten" class="container crud-page">
  <a class="back-link" href="index.php#<?= esc($type) ?>">← Kembali ke dashboard</a>
  <div class="crud-header"><p class="overline">MIDS COFFEE / KELOLA DATA</p><h1><?= esc($pageTitle) ?></h1><p>Isi formulir berikut. Data akan langsung tersimpan di database <code>mids_coffee</code>.</p></div>
  <?php if ($errors): ?>
    <div class="alert" role="alert"><strong>Belum berhasil menyimpan data.</strong><ul><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form class="crud-card" method="post" action="manage.php" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="type" value="<?= esc($type) ?>">
    <input type="hidden" name="action" value="<?= esc($action) ?>">
    <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

    <?php if ($type === 'menu'): ?>
      <div class="form-grid">
        <label class="form-field form-wide">Nama menu
          <input name="nama_menu" type="text" required maxlength="100" placeholder="Contoh: Kopi Susu MIDS" value="<?= esc($values['nama_menu'] ?? '') ?>">
        </label>
        <label class="form-field">Kategori
          <select name="kategori" required>
            <?php foreach (['Kopi', 'Non-Kopi', 'Makanan'] as $category): ?>
              <option value="<?= esc($category) ?>" <?= ($values['kategori'] ?? '') === $category ? 'selected' : '' ?>><?= esc($category) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="form-field">Harga (Rupiah)
          <input name="harga" type="number" step="0.01" min="1" max="99999999" required value="<?= esc($values['harga'] ?? '') ?>" placeholder="23000">
        </label>
        <label class="form-field form-wide">Ketersediaan
          <select name="tersedia"><option value="1" <?= (string) ($values['tersedia'] ?? '1') === '1' ? 'selected' : '' ?>>Tersedia</option><option value="0" <?= (string) ($values['tersedia'] ?? '') === '0' ? 'selected' : '' ?>>Habis</option></select>
        </label>
      </div>
    <?php elseif ($type === 'pelanggan'): ?>
      <div class="form-grid">
        <label class="form-field form-wide">Nama lengkap
          <input type="text" name="nama_pelanggan" required maxlength="100" value="<?= esc($values['nama_pelanggan'] ?? '') ?>" placeholder="Nama pelanggan">
        </label>
        <label class="form-field">Email
          <input type="email" name="email" required maxlength="120" value="<?= esc($values['email'] ?? '') ?>" placeholder="nama@example.com">
        </label>
        <label class="form-field">Nomor telepon
          <input type="tel" name="no_telepon" required maxlength="20" value="<?= esc($values['no_telepon'] ?? '') ?>" placeholder="081234567890">
        </label>
        <label class="form-field form-wide">Tanggal daftar
          <input type="date" name="tanggal_daftar" required value="<?= esc($values['tanggal_daftar'] ?? '') ?>">
        </label>
      </div>
    <?php else: ?>
      <div class="form-grid">
        <label class="form-field">Pelanggan
          <select name="id_pelanggan" required>
            <option value="">Pilih pelanggan</option>
            <?php foreach ($choicesPelanggan as $pl): ?><option value="<?= (int) $pl['id_pelanggan'] ?>" <?= (string) ($values['id_pelanggan'] ?? '') === (string) $pl['id_pelanggan'] ? 'selected' : '' ?>><?= esc($pl['nama_pelanggan']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="form-field">Menu
          <select name="id_menu" required>
            <option value="">Pilih menu</option>
            <?php foreach ($choicesMenu as $m): ?>
              <?php $disabled = (int) $m['tersedia'] === 0 && (string) ($values['id_menu'] ?? '') !== (string) $m['id_menu']; ?>
              <option value="<?= (int) $m['id_menu'] ?>" <?= $disabled ? 'disabled' : '' ?> <?= (string) ($values['id_menu'] ?? '') === (string) $m['id_menu'] ? 'selected' : '' ?>><?= esc($m['nama_menu']) ?> · Rp <?= number_format((float) $m['harga'], 0, ',', '.') ?><?= (int) $m['tersedia'] === 0 ? ' (Habis)' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="form-field">Jumlah
          <input type="number" name="jumlah" min="1" max="1000" required value="<?= esc($values['jumlah'] ?? '1') ?>">
        </label>
        <label class="form-field">Status
          <select name="status" required>
            <?php foreach (['Diproses', 'Selesai', 'Dibatalkan'] as $status): ?><option value="<?= $status ?>" <?= ($values['status'] ?? 'Diproses') === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="form-field form-wide">Tanggal dan jam pesanan
          <input type="datetime-local" name="tanggal_pesanan" required value="<?= esc($values['tanggal_pesanan'] ?? '') ?>">
        </label>
      </div>
      <p class="form-note">Harga satuan diambil otomatis dari menu saat pesanan dibuat. Harga pesanan lama tidak berubah saat kamu hanya mengedit status/jumlah.</p>
    <?php endif; ?>
    <div class="form-actions"><a class="button-outline" href="index.php#<?= esc($type) ?>">Batal</a><button class="button-primary" type="submit" <?= $databaseError ? 'disabled' : '' ?>><?= $action === 'create' ? 'Tambah data' : 'Simpan perubahan' ?> →</button></div>
  </form>
</main>
<footer class="footer"><div class="container footer-inner"><span class="footer-logo">MIDS<span>.</span></span><p>Website Database Relasional · Mentoring ISCOM Day 3</p><span>2026 © MIDS Coffee</span></div></footer>
</body>
</html>
