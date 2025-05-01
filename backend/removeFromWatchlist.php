<?php
/**
 * POST /backend/removeFromWatchlist.php
 * Removes one entry from the authenticated user’s watch‑list.
 * Body: {"id":123}
 * Response: {"success":true,"message":"Removed from watchlist."}
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
$body  = json_decode(file_get_contents('php://input'), true);
$rowId = isset($body['id']) ? (int)$body['id'] : 0;
if ($rowId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

try {
    // 3) Ask RabbitMQ consumer to delete
    $client   = new rabbitMQClient(
        __DIR__ . '/../testRabbitMQ_response.ini',
        'responseServer'
    );
    $response = $client->send_request([
        'action' => 'remove_from_watchlist',
        'token'  => $token,
        'data'   => ['id' => $rowId]
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => 'Backend unavailable: ' . $e->getMessage()]);
    exit;
}

// 4) Return result
if (!empty($response['status']) && $response['status'] === 'success') {
    echo json_encode(['success' => true, 'message' => $response['message']]);
} else {
    http_response_code(500);
    echo json_encode(['error' => $response['message'] ?? 'Could not remove from watchlist']);
}
