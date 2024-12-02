<?php // categories.php

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

// Check if user has permission to manage categories
if (!isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

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

function generateUniqueSlug($conn, $name, $current_id = null) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $slug = trim($slug, '-');
    
    $baseSlug = $slug;
    $counter = 1;
    
    do {
        if ($current_id) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $current_id]);
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
            $stmt->execute([$slug]);
        }
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
    } while ($exists);
    
    return $slug;
}

header('Content-Type: application/json');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create') {
            if (empty($_POST['name'])) {
                throw new Exception('Kategori adı gereklidir');
            }

            $slug = generateUniqueSlug($conn, $_POST['name']);
            
            $stmt = $conn->prepare("INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :description)");
            $stmt->execute([
                ':name' => $_POST['name'],
                ':slug' => $slug,
                ':description' => isset($_POST['description']) ? $_POST['description'] : '',
            ]);
            $success = 'Kategori başarıyla oluşturuldu';

        } elseif ($_POST['action'] === 'update') {
            if (empty($_POST['name']) || empty($_POST['category_id'])) {
                throw new Exception('Kategori adı ve ID gereklidir');
            }

            $slug = generateUniqueSlug($conn, $_POST['name'], $_POST['category_id']);
            
            $stmt = $conn->prepare("UPDATE categories SET name = :name, slug = :slug, description = :description WHERE id = :id");
            $stmt->execute([
                ':name' => $_POST['name'],
                ':slug' => $slug,
                ':description' => isset($_POST['description']) ? $_POST['description'] : '',
                ':id' => $_POST['category_id']
            ]);
            $success = 'Kategori başarıyla güncellendi';

        } elseif ($_POST['action'] === 'delete') {
            if (empty($_POST['category_id'])) {
                throw new Exception('Kategori ID gereklidir');
            }

            $stmt = $conn->prepare("UPDATE posts SET category_id = NULL WHERE category_id = :category_id");
            $stmt->execute([':category_id' => $_POST['category_id']]);

            $stmt = $conn->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute([':id' => $_POST['category_id']]);
            $success = 'Kategori başarıyla silindi';
        }
    } catch (Exception $e) {
        error_log("Category operation failed: " . $e->getMessage());
        echo json_encode(['error' => 'İşlem başarısız: ' . $e->getMessage()]);
        exit();
    }
    echo json_encode(['success' => $success]);
    exit();
}

$query = "SELECT c.id, c.name, c.slug, c.description, COUNT(p.id) as post_count 
          FROM categories c 
          LEFT JOIN posts p ON c.id = p.category_id 
          GROUP BY c.id, c.name, c.slug, c.description 
          ORDER BY c.name";
$categories = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['categories' => $categories]);