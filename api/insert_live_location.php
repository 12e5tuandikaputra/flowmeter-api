<?php
// ============================================================
// insert_live_location.php
// Endpoint: POST /api/insert_live_location.php
// Menerima data GPS dari ESP32 (HTTP POST form-encoded)
// dan menyimpannya ke tabel live_location
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// ── Konfigurasi Database ─────────────────────────────────────
define('DB_HOST', 'mysql.railway.internal');
define('DB_NAME', 'railway');
define('DB_USER', 'root');         
define('DB_PASS', 'KpzJpzjTbZQtWPFBgwYbhMTisCCuWkUV');             
define('DB_PORT', 3306);
// ─────────────────────────────────────────────────────────────

// Hanya terima method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// ── Helper: ambil nilai POST ─────────────────────────────────
function getFloat($key) {
    return isset($_POST[$key]) && $_POST[$key] !== '' ? (float) $_POST[$key] : null;
}
function getInt($key) {
    return isset($_POST[$key]) && $_POST[$key] !== '' ? (int) $_POST[$key] : null;
}

// ── Ambil field dari POST ────────────────────────────────────
$actual_locations = getInt('actual_locations');   // 1 = true, 0 = false

// date_time: normalisasi format "YYYY-MM-DD HH:MM:SS"
$date_time_raw = isset($_POST['date_time']) ? trim($_POST['date_time']) : null;
$date_time     = null;
if ($date_time_raw !== null && $date_time_raw !== '') {
    // Ganti '+' dengan spasi jika browser/form encode spasi sebagai '+'
    $date_time_raw = str_replace('+', ' ', $date_time_raw);
    // Validasi format datetime
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $date_time_raw);
    $date_time = ($dt !== false) ? $dt->format('Y-m-d H:i:s') : null;
}

$lat = getFloat('lat');
$lon = getFloat('lon');
$alt = getFloat('alt');
$hdg = getFloat('hdg');

// ── Validasi minimal: harus ada koordinat ───────────────────
if ($lat === null || $lon === null) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Field lat dan lon wajib diisi.',
    ]);
    exit;
}

// ── Koneksi ke database ──────────────────────────────────────
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database connection failed.',
        'detail'  => $e->getMessage()
    ]);
    exit;
}

// ── INSERT ke tabel live_location ────────────────────────────
$sql = "
    INSERT INTO live_location (
        actual_locations, date_time, lat, lon, alt, hdg
    ) VALUES (
        :actual_locations, :date_time, :lat, :lon, :alt, :hdg
    )
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':actual_locations' => $actual_locations ?? 0,
        ':date_time'        => $date_time,
        ':lat'              => $lat,
        ':lon'              => $lon,
        ':alt'              => $alt,
        ':hdg'              => $hdg,
    ]);

    $insertedId = $pdo->lastInsertId();

    http_response_code(200);
    echo json_encode([
        'status'      => 'ok',
        'message'     => 'Lokasi berhasil disimpan.',
        'inserted_id' => (int) $insertedId,
        'table'       => 'live_location',
        'timestamp'   => date('Y-m-d H:i:s'),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal menyimpan data.',
        'detail'  => $e->getMessage()
    ]);
}
?>
