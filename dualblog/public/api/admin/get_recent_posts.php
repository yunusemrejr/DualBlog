<?php
session_start();
require_once('../../../config/config.php');

 // Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $query = "SELECT p.*, c.name as category_name 
              FROM posts p 
              LEFT JOIN categories c ON p.category_id = c.id 
              ORDER BY p.created_at DESC 
              LIMIT 10";
    
    $stmt = $pdo->query($query);
    $posts = $stmt->fetchAll();
    
    // Format the data
    $posts = array_map(function($post) {
        $post['published_at'] = $post['published_at'] ? $post['published_at'] : null;
        return $post;
    }, $posts);
    
    header('Content-Type: application/json');
    echo json_encode($posts);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} 