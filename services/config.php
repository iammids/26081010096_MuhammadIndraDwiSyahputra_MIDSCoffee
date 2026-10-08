<?php
/**
 * Koneksi MySQL untuk lingkungan lokal Laragon.
 * Isi kredensial bisa diubah di sini atau melalui environment variable DB_*.
 * File ini mengembalikan objek mysqli kepada index.php.
 */
declare(strict_types=1);

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$password = '';
$database = 'mids_coffee';

// MYSQLI_REPORT_ERROR akan mengubah kegagalan query menjadi exception.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli($host, $user, $password, $database, $port);
$conn->set_charset('utf8mb4');

return $conn;
