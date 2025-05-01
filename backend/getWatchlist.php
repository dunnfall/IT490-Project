<?php
/**
 * GET /backend/getWatchlist.php
 * Returns the authenticated user’s watch‑list as JSON.
 * Output: [ {"id":1,"stock_symbol":"AAPL"}, ... ]
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../rabbitMQLib.inc';

// 1) Authentication
$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    // 2) Ask the RabbitMQ consumer
    $client   = new rabbitMQClient(
        __DIR__ . '/../testRabbitMQ_response.ini',
        'responseServer'
    );
    $response = $client->send_request([
        'action' => 'get_watchlist',
        'token'  => $token
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => 'Backend unavailable: '.$e->getMessage()]);
    exit;
}

// 3) Return data
if (!empty($response['status']) && $response['status'] === 'success') {
    echo json_encode($response['data']);
} else {
    http_response_code(500);
    echo json_encode(['error' => $response['message'] ?? 'Could not load watchlist']);
}
