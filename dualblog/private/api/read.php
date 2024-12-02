<?php
require_once '../../config/config.php';

session_start();
header('Content-Type: application/json');
// Redirect to HTTPS if not already using it
if ($HTTPS && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    http_response_code(301);
    echo json_encode(['error' => 'Please use HTTPS']);
    exit();
}
// Kullanıcının kimliğinin doğrulandığından emin olun
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $pdo = new PDO(
        "mysql:host={$db_config['private_blog']['host']};dbname={$db_config['private_blog']['database']};charset=utf8", 
        $db_config['private_blog']['username'], 
        $db_config['private_blog']['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Makale ID'si kontrolü
$articleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$mode = filter_input(INPUT_GET, 'mode', FILTER_DEFAULT, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
$search = filter_input(INPUT_GET, 'search', FILTER_DEFAULT, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);

// Pagination settings
$limit = 10; // Number of articles per page
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$offset = ($page - 1) * $limit;

$response = [];

if ($mode === 'read' && $articleId) {
    // Belirli bir makaleyi alın
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$articleId]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['articles'] = $article ? [$article] : [];
    $response['totalArticles'] = $article ? 1 : 0;
} else {
    if ($search) {
        // Arama terimi ile makaleleri alın
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE title LIKE :search OR content LIKE :search ORDER BY id DESC LIMIT :limit OFFSET :offset");
        $safeSearch = '%' . $search . '%';
        $stmt->bindParam(':search', $safeSearch, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $response['articles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total number of articles for pagination
        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE title LIKE :search OR content LIKE :search");
        $totalStmt->bindParam(':search', $safeSearch, PDO::PARAM_STR);
        $totalStmt->execute();
        $response['totalArticles'] = $totalStmt->fetchColumn();
    } else {
        // Tüm makaleleri alın
        $stmt = $pdo->prepare("SELECT * FROM articles ORDER BY id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $response['articles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total number of articles for pagination
        $totalStmt = $pdo->query("SELECT COUNT(*) FROM articles");
        $response['totalArticles'] = $totalStmt->fetchColumn();
    }
}

$response['totalPages'] = ceil($response['totalArticles'] / $limit);

echo json_encode($response);
exit();
