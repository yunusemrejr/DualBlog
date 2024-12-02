<?php
session_start(); // Start the session at the beginning
require_once '../../../config/config.php';
header('Content-Type: application/json');

// Function to update view count
function updateViewCount($post_id) {
    global $db_config;
    
    // Initialize viewed posts array in session if it doesn't exist
    if (!isset($_SESSION['viewed_posts'])) {
        $_SESSION['viewed_posts'] = array();
    }
    
    // Check if this post has already been viewed in this session
    if (in_array($post_id, $_SESSION['viewed_posts'])) {
        return true; // Post already viewed in this session
    }
    
    try {
        $conn = new PDO(
            "mysql:host={$db_config['public_blog']['host']};dbname={$db_config['public_blog']['database']};charset=utf8", 
            $db_config['public_blog']['username'], 
            $db_config['public_blog']['password']
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Update views_count in posts table
        $query = "UPDATE posts SET views_count = views_count + 1 WHERE id = :post_id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':post_id' => $post_id]);
        
        // Mark this post as viewed in this session
        $_SESSION['viewed_posts'][] = $post_id;
        
        return true;
    } catch(PDOException $e) {
        error_log("Failed to update view count: " . $e->getMessage());
        return false;
    }
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['post_id'])) {
    $post_id = filter_var($_GET['post_id'], FILTER_VALIDATE_INT);
    
    if ($post_id) {
        $success = updateViewCount($post_id);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'alreadyViewed' => isset($_SESSION['viewed_posts']) && in_array($post_id, $_SESSION['viewed_posts'])
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
    }
    exit;
}
else{
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>