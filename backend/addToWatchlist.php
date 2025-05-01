<?php
/**
 * POST /backend/addToWatchlist.php
 * Adds a ticker to the authenticated user’s watch‑list.
 * Body: {"ticker":"AAPL"}
 * Response: {"success":true,"message":"Added to watchlist."}
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

// 2) Validate input
$body   = json_decode(file_get_contents('php://input'), true);
$ticker = strtoupper(trim($body['ticker'] ?? ''));
if (!$ticker) {
    http_response_code(400);
    echo json_encode(['error' => 'Ticker is required']);
    exit;
}

try {
    // 3) Ask RabbitMQ consumer
    $client   = new rabbitMQClient(
        __DIR__ . '/../testRabbitMQ_response.ini',
        'responseServer'
    );
    $response = $client->send_request([
        'action' => 'add_to_watchlist',
        'token'  => $token,
        'data'   => ['ticker' => $ticker]
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => 'Backend unavailable: '.$e->getMessage()]);
    exit;
}

// 4) Return result
if (!empty($response['status']) && $response['status'] === 'success') {
    echo json_encode(['success' => true, 'message' => $response['message']]);
} else {
    http_response_code(500);
    echo json_encode(['error' => $response['message'] ?? 'Could not add to watchlist']);
}
