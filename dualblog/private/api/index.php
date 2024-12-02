<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

// Redirect to HTTPS if not already using it
if ($HTTPS && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    http_response_code(301);
    echo json_encode(['error' => 'Please use HTTPS']);
    exit();
}

session_start();

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

if (!isset($_SESSION['attempts'])) {
    $_SESSION['attempts'] = 0;
}

$response = ['message' => '', 'status' => ''];

if ($_SESSION['attempts'] >= 13) {
    $response['message'] = "Too many attempts. Please try again later.";
    $response['status'] = "error";
} else {
    $accessCode = filter_input(INPUT_GET, 'accessCode', FILTER_DEFAULT);
    $accessCode = $accessCode !== null ? htmlspecialchars($accessCode, ENT_QUOTES, 'UTF-8') : null;
    if ($accessCode !== null) {
        if ($accessCode === $GLOBALS['private_blog_access_code']) {
            $_SESSION['authenticated'] = true;
            $response['message'] = "Access granted";
            $response['status'] = "success";
        } else {
            $_SESSION['attempts']++;
            $response['message'] = "Access denied";
            $response['status'] = "error";
        }
    } else {
        $response['message'] = "Access code required";
        $response['status'] = "error";
    }
}

echo json_encode($response);
exit();