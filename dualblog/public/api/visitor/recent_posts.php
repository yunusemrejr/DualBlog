<?php
// File: api/recent_posts.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); // Adjust as needed for security
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once('../../../config/config.php');

// Initialize response array
$response = [];

// Validate and retrieve the 'exclude_id' parameter (optional)
$exclude_id = isset($_GET['exclude_id']) && is_numeric($_GET['exclude_id']) && $_GET['exclude_id'] > 0 ? (int)$_GET['exclude_id'] : 0;

// Validate and retrieve the 'limit' parameter (optional, default to 5)
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 ? (int)$_GET['limit'] : 5;

// Validate and retrieve the 'page' parameter (optional, default to 1)
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

try {
    // Establish database connection
    $conn = new PDO(
        "mysql:host={$db_config['public_blog']['host']};dbname={$db_config['public_blog']['database']};charset=utf8", 
        $db_config['public_blog']['username'], 
        $db_config['public_blog']['password']
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Log the error internally
    error_log("Database connection failed: " . $e->getMessage(), 3, '../../../logs/error.log');

    http_response_code(500); // Internal Server Error
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed."
    ]);
    exit;
}

try {
    // Calculate the offset for pagination
    $offset = ($page - 1) * $limit;

    // Fetch total number of posts
    $totalQuery = "SELECT COUNT(*) FROM posts WHERE status = 'published'";
    if ($exclude_id > 0) {
        $totalQuery .= " AND id != :exclude_id";
    }
    $totalStmt = $conn->prepare($totalQuery);
    if ($exclude_id > 0) {
        $totalStmt->bindParam(':exclude_id', $exclude_id, PDO::PARAM_INT);
    }
    $totalStmt->execute();
    $totalPosts = $totalStmt->fetchColumn();

    // Fetch recent posts with additional fields
    $query = "SELECT 
                p.id, 
                p.title, 
                p.slug,
                p.published_at,
                IFNULL(p.featured_image, 'images/placeholder.webp') AS image_url, 
                a.full_name AS author,
                p.excerpt,
                p.content,
                p.category_id,
                p.views_count,
                p.meta_description,
                p.meta_keywords,
                p.created_at,
                p.updated_at
              FROM posts p 
              LEFT JOIN admins a ON p.author_id = a.id 
              WHERE p.status = 'published'";

    // Exclude a specific post if 'exclude_id' is provided
    if ($exclude_id > 0) {
        $query .= " AND p.id != :exclude_id";
    }

    $query .= " ORDER BY p.published_at DESC LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($query);

    if ($exclude_id > 0) {
        $stmt->bindParam(':exclude_id', $exclude_id, PDO::PARAM_INT);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $recent_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare the response
    $response = [
        "success" => true,
        "data" => $recent_posts ? array_map(function($post) {
            return [
                "id" => (int)$post['id'],
                "title" => $post['title'],
                "slug" => $post['slug'],
                "published_at" => $post['published_at'],
                "image_url" => $post['image_url'],
                "author" => $post['author'] ?: $GLOBALS['company_name'],
                "excerpt" => $post['excerpt'],
                "content" => $post['content'],
                "category_id" => isset($post['category_id']) ? (int)$post['category_id'] : null,
                "views_count" => isset($post['views_count']) ? (int)$post['views_count'] : 0,
                "meta_description" => $post['meta_description'],
                "meta_keywords" => $post['meta_keywords'],
                "created_at" => $post['created_at'],
                "updated_at" => $post['updated_at']
            ];
        }, $recent_posts) : [],
        "pagination" => [
            "current_page" => $page,
            "total_pages" => ceil($totalPosts / $limit),
            "total_posts" => (int)$totalPosts
        ],
        "message" => $recent_posts ? null : "No recent posts available."
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch(PDOException $e) {
    // Log the error internally
    error_log("Error fetching recent posts: " . $e->getMessage(), 3, '../../../logs/error.log');

    http_response_code(500); // Internal Server Error
    echo json_encode([
        "success" => false,
        "message" => "An error occurred while fetching recent posts."
    ]);
    exit;
}
?>
