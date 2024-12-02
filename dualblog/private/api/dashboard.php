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
    echo json_encode(['error' => 'Bir iç hata oluştu.']);
    exit();
}

// LimitRequestBody 10485760  # 10MB
if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 10485760) {
    http_response_code(413);
    echo json_encode(['error' => 'İstek boyutu 10MB\'yi aşamaz.']);
    exit();
}

// Form gönderimlerini işleyin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $content = $_POST['content']; // HTML içeriği
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    // Limit content length to 65535 characters (TEXT type in MySQL)
    if (strlen($content) > 65535) {
        http_response_code(400);
        echo json_encode(['error' => 'İçerik 65535 karakterden uzun olamaz.']);
        exit();
    }

    try {
        if (isset($_POST['add'])) {
            $stmt = $pdo->prepare("INSERT INTO articles (title, content) VALUES (?, ?)");
            $stmt->execute([$title, $content]);
            echo json_encode(['success' => 'Makale başarıyla eklendi.']);
        } elseif (isset($_POST['edit'])) {
            $stmt = $pdo->prepare("UPDATE articles SET title = ?, content = ? WHERE id = ?");
            $stmt->execute([$title, $content, $id]);
            echo json_encode(['success' => 'Makale başarıyla güncellendi.']);
        } elseif (isset($_POST['delete'])) {
            $stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => 'Makale başarıyla silindi.']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Bir iç hata oluştu.']);
    }
    exit();
}

// Pagination settings
$limit = 10; // Number of articles per page
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$offset = ($page - 1) * $limit;

// Makaleleri alın
try {
    $stmt = $pdo->prepare("SELECT * FROM articles ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total number of articles for pagination
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM articles");
    $totalArticles = $totalStmt->fetchColumn();
    $totalPages = ceil($totalArticles / $limit);

    echo json_encode([
        'articles' => $articles,
        'pagination' => [
            'totalArticles' => $totalArticles,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Bir iç hata oluştu.']);
}
exit();
?>
