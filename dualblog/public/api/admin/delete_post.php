<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once('../../../config/config.php');
require_once('permissions.php');

try {
    $conn = new PDO("mysql:host={$db_config['host']};dbname={$db_config['database']}", 
        $db_config['username'], 
        $db_config['password']
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get post ID from POST data or JSON input
$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? $input['id'] : (isset($_POST['id']) ? $_POST['id'] : null);

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Post ID is required']);
    exit();
}

try {
    // First fetch the post to check permissions
    $stmt = $conn->prepare("SELECT * FROM posts WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
        exit();
    }

    // Check if user has permission to delete this post
    if (!canDeletePost($post)) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit();
    }

    // Delete the post
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Check if deletion was successful
    if ($stmt->rowCount() > 0) {
        // If post had a featured image, you might want to delete it here
        if (!empty($post['featured_image'])) {
            $image_path = '../../' . $post['featured_image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to delete post');
    }
} catch (Exception $e) {
    error_log("Delete failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete post']);
} 