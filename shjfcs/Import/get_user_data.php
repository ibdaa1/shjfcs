<?php
// get_user_data.php
// Returns all active users and the current logged-in user (from session) as { data: [...], current: {...} }
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
try {
    $dbFile = realpath(__DIR__ . '/../db.php');
    if (!$dbFile || !file_exists($dbFile)) {
        throw new Exception("db.php not found");
    }
    require_once $dbFile;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection not available");
    }
    if (session_status() === PHP_SESSION_NONE) session_start();
    // fetch active users
    $sql = "SELECT EmpID, EmpName, JobTitle, Active FROM Users WHERE Active = 1 ORDER BY EmpName";
    $res = $conn->query($sql);
    $users = [];
    while ($r = $res->fetch_assoc()) $users[] = $r;

    // current user info from session (if present)
    $current = null;
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $empid = intval($_SESSION['user']['EmpID'] ?? 0);
        if ($empid > 0) {
            $stmt = $conn->prepare("SELECT EmpID, EmpName, JobTitle FROM Users WHERE EmpID = ? LIMIT 1");
            $stmt->bind_param('i', $empid);
            $stmt->execute();
            $current = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
        }
    }

    echo json_encode(['data' => $users, 'current' => $current]);
    exit;
} catch (Throwable $ex) {
    error_log("get_user_data.php error: " . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit;
}
?>
