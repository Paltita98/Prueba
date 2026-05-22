<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'access_denied']);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

echo json_encode(['ok' => true, 'csrf' => $_SESSION['csrf_token']]);