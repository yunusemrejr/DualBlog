<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['upload'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit();
}

$file = $_FILES['upload'];
$upload_dir = '../../uploads/images/';
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

// Validate file type
if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit();
}

// Validate file size
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large']);
    exit();
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . time() . '.' . $extension;
$filepath = $upload_dir . $filename;

// Create upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Return the URL for CKEditor
    echo json_encode([
        'url' => '/uploads/images/' . $filename,
        'uploaded' => 1
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to upload file']);
} 