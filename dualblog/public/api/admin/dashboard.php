<?php // dashboard.php

// Show all PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
checkUserStatus();

try {
    $conn = new PDO("mysql:host={$db_config['public_blog']['host']};dbname={$db_config['public_blog']['database']};charset=utf8", 
        $db_config['public_blog']['username'], 
        $db_config['public_blog']['password']
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    echo json_encode(['error' => 'Database connection failed. Please check the error log.']);
    exit();
}

// Fetch admin info
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Yönetim Paneli statistics
$stats = [
    'total_posts' => $conn->query("SELECT COUNT(*) FROM posts")->fetchColumn(),
    'published_posts' => $conn->query("SELECT COUNT(*) FROM posts WHERE status = 'published'")->fetchColumn(),
    'draft_posts' => $conn->query("SELECT COUNT(*) FROM posts WHERE status = 'draft'")->fetchColumn(),
    'total_views' => $conn->query("SELECT COALESCE(SUM(views_count), 0) FROM posts")->fetchColumn()
];

// Fetch Son Yazılar with view counts
$query = "SELECT p.*, a.full_name as author_name, 
          COALESCE(p.views_count, 0) as views_count 
          FROM posts p 
          LEFT JOIN admins a ON p.author_id = a.id 
          ORDER BY p.created_at DESC 
          LIMIT 10";
$stmt = $conn->query($query);
$recent_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$response = [
    'admin' => $admin,
    'stats' => $stats,
    'recent_posts' => $recent_posts
];

echo json_encode($response);
exit();
