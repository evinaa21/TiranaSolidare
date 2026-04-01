<?php
/**
 * E2E test helper — clears the rate_limit_log for localhost to allow
 * test runs to log in repeatedly without hitting the rate limiter.
 *
 * SECURITY: Only callable from localhost (127.0.0.1 / ::1).
 * This file must never be deployed to production.
 */
$allowedIPs = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];
$remoteIP = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($remoteIP, $allowedIPs, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

require_once __DIR__ . '/../../../config/db.php';

try {
    // Only clear rate limits for localhost IPs (the Playwright browser uses 127.0.0.1)
    $stmt = $pdo->prepare("DELETE FROM rate_limit_log WHERE ip IN ('127.0.0.1','::1','::ffff:127.0.0.1')");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'deleted' => $deleted]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
