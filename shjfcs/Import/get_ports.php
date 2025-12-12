<?php
// get_ports.php
// Returns list of ports. Output format flexible (either array or {data: [...]})
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
try {
    $dbFile = realpath(__DIR__ . '/../db.php');
    if (!$dbFile || !file_exists($dbFile)) throw new Exception("db.php not found");
    require_once $dbFile;
    if (!isset($conn) || !($conn instanceof mysqli)) throw new Exception("DB connection not available");
    $sql = "SELECT id, port_name FROM ports ORDER BY port_name";
    $res = $conn->query($sql);
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    echo json_encode(['data' => $rows]);
    exit;
} catch (Throwable $ex) {
    error_log("get_ports.php error: " . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit;
}
?>
