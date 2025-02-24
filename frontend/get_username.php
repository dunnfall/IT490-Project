<?php
session_start();
header('Content-Type: application/json');

$response = ['username' => isset($_SESSION['username']) ? $_SESSION['username'] : null];
echo json_encode($response);
?>