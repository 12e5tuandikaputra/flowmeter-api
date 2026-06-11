<?php
// ============================================================
// insert_main_data.php
// Endpoint: POST /api/insert_main_data.php
// Menerima data dari ESP32 (HTTP POST form-encoded)
// dan menyimpannya ke tabel main_data
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// ── Konfigurasi Database ─────────────────────────────────────
define('DB_HOST', 'musql.railway.internal');
define('DB_NAME', 'railway');
define('DB_USER', 'root');
define('DB_PASS', 'flowmeterengspt');
define('DB_PORT', 3306);
// ─────────────────────────────────────────────────────────────

// Hanya terima method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// ── Helper: ambil nilai POST, kembalikan null jika tidak ada ─
function getFloat($key) {
    return isset($_POST[$key]) && $_POST[$key] !== '' ? (float) $_POST[$key] : null;
}
function getInt($key) {
    return isset($_POST[$key]) && $_POST[$key] !== '' ? (int) $_POST[$key] : null;
}
function getString($key, $maxLen = 12) {
    return isset($_POST[$key]) && $_POST[$key] !== ''
        ? substr(trim($_POST[$key]), 0, $maxLen)
        : null;
}

// ── Ambil semua field dari POST ──────────────────────────────
$flow_rate           = getFloat('flow_rate');
$energy_flow_rate    = getFloat('energy_flow_rate');
$velocity            = getFloat('velocity');
$fluid_sound_speed   = getFloat('fluid_sound_speed');
$pos_accumulator     = getFloat('pos_accumulator');
$net_accumulator     = getFloat('net_accumulator');
$temp_inlet          = getFloat('temp_inlet');
$temp_outlet         = getFloat('temp_outlet');
$flow_today          = getFloat('flow_today');
$flow_this_month     = getFloat('flow_this_month');
$flow_this_year      = getFloat('flow_this_year');
$signal_quality      = getInt('signal_quality');
$working_timer       = getString('working_timer', 12);
$total_work_time     = getString('total_work_time', 12);
$upstream_strength   = getInt('upstream_strength');
$downstream_strength = getInt('downstream_strength');

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

// ── INSERT ke tabel main_data ────────────────────────────────
$sql = "
    INSERT INTO main_data (
        flow_rate, energy_flow_rate, velocity, fluid_sound_speed,
        pos_accumulator, net_accumulator, temp_inlet, temp_outlet,
        flow_today, flow_this_month, flow_this_year,
        signal_quality, working_timer, total_work_time,
        upstream_strength, downstream_strength
    ) VALUES (
        :flow_rate, :energy_flow_rate, :velocity, :fluid_sound_speed,
        :pos_accumulator, :net_accumulator, :temp_inlet, :temp_outlet,
        :flow_today, :flow_this_month, :flow_this_year,
        :signal_quality, :working_timer, :total_work_time,
        :upstream_strength, :downstream_strength
    )
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':flow_rate'           => $flow_rate,
        ':energy_flow_rate'    => $energy_flow_rate,
        ':velocity'            => $velocity,
        ':fluid_sound_speed'   => $fluid_sound_speed,
        ':pos_accumulator'     => $pos_accumulator,
        ':net_accumulator'     => $net_accumulator,
        ':temp_inlet'          => $temp_inlet,
        ':temp_outlet'         => $temp_outlet,
        ':flow_today'          => $flow_today,
        ':flow_this_month'     => $flow_this_month,
        ':flow_this_year'      => $flow_this_year,
        ':signal_quality'      => $signal_quality,
        ':working_timer'       => $working_timer,
        ':total_work_time'     => $total_work_time,
        ':upstream_strength'   => $upstream_strength,
        ':downstream_strength' => $downstream_strength,
    ]);

    $insertedId = $pdo->lastInsertId();

    http_response_code(200);
    echo json_encode([
        'status'      => 'ok',
        'message'     => 'Data berhasil disimpan.',
        'inserted_id' => (int) $insertedId,
        'table'       => 'main_data',
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
