<?php // posts.php

// Show all PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once('../../../config/config.php');
require_once('permissions.php');

try {
    $conn = new PDO("mysql:host={$db_config['public_blog']['host']};dbname={$db_config['public_blog']['database']};charset=utf8", 
        $db_config['public_blog']['username'], 
        $db_config['public_blog']['password']
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed. Please check the error log.']);
    exit();
}

// Pagination settings
$posts_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $posts_per_page;

// Build WHERE clause and parameters
$where_conditions = [];
$params = [];

// Search filter
if (!empty($_GET['search'])) {
    $where_conditions[] = "(p.title LIKE :search OR p.content LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

// Status filter
if (!empty($_GET['status']) && in_array($_GET['status'], ['published', 'draft', 'archived'])) {
    $where_conditions[] = "p.status = :status";
    $params[':status'] = $_GET['status'];
}

// Category filter
if (!empty($_GET['category'])) {
    $where_conditions[] = "p.category_id = :category_id";
    $params[':category_id'] = (int)$_GET['category'];
}

// Author filter - if not admin, only show their own posts
if (!isAdmin()) {
    $where_conditions[] = "p.author_id = :author_id";
    $params[':author_id'] = $_SESSION['admin_id'];
}

// Combine WHERE conditions
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total posts count for pagination
$count_query = "SELECT COUNT(*) FROM posts p $where_clause";
$stmt = $conn->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_posts = $stmt->fetchColumn();
$total_pages = ceil($total_posts / $posts_per_page);

// Fetch posts with pagination
$query = "SELECT p.*, a.full_name as author_name, c.name as category_name,
                 COALESCE(p.views_count, 0) as views_count
          FROM posts p 
          LEFT JOIN admins a ON p.author_id = a.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          $where_clause 
          ORDER BY p.created_at DESC 
          LIMIT :offset, :limit";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $posts_per_page, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'posts' => $posts,
    'total_pages' => $total_pages,
    'current_page' => $page
]);
