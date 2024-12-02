<?php
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

header('Content-Type: application/json');

try {
    $conn = new PDO("mysql:host={$db_config['public_blog']['host']};dbname={$db_config['public_blog']['database']};charset=utf8", 
        $db_config['public_blog']['username'], 
        $db_config['public_blog']['password']
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed. Please check the error log.']);
    exit();
}

$error = '';
$success = '';
$post = null;
$categories = [];

// Fetch categories for dropdown
try {
    $stmt = $conn->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Failed to fetch categories: " . $e->getMessage());
}

// Add this function after the database connection
function saveUploadedImage($file, $post_id) {
    $upload_dir = '../images/posts/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($file_extension, $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
    }

    $base_filename = $post_id;
    $new_filename = $base_filename . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    // If file exists, add random identifier
    if (file_exists($upload_path)) {
        $random_id = substr(md5(uniqid()), 0, 8); // 8 character random string
        $new_filename = $base_filename . '_' . $random_id . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
    }
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return 'images/posts/' . $new_filename;
    }
    
    throw new Exception('Failed to save image');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        
        // Handle image upload
        $featured_image = isset($_POST['featured_image']) ? $_POST['featured_image'] : '';
        
        if (isset($_FILES['image_upload']) && $_FILES['image_upload']['size'] > 0) {
            $featured_image = saveUploadedImage($_FILES['image_upload'], $id);
        }

        // Validate required fields
        if (empty($_POST['title']) || empty($_POST['content'])) {
            throw new Exception('Title and content are required');
        }

        $query = "UPDATE posts SET 
                    title = :title,
                    content = :content,
                    excerpt = :excerpt,
                    category_id = :category_id,
                    status = :status,
                    featured_image = :featured_image,
                    meta_description = :meta_description,
                    updated_at = NOW()
                 WHERE id = :id";
        
        // Only add author_id check for non-admin users
        if (!isAdmin()) {
            $query .= " AND author_id = :author_id";
        }
        
        $stmt = $conn->prepare($query);
        
        $params = [
            ':title' => $_POST['title'],
            ':content' => $_POST['content'],
            ':excerpt' => isset($_POST['excerpt']) ? $_POST['excerpt'] : '',
            ':category_id' => $_POST['category_id'] ? (int)$_POST['category_id'] : null,
            ':status' => $_POST['status'],
            ':featured_image' => $featured_image,
            ':meta_description' => isset($_POST['meta_description']) ? $_POST['meta_description'] : '',
            ':id' => $id
        ];

        // Only add author_id parameter for non-admin users
        if (!isAdmin()) {
            $params[':author_id'] = $_SESSION['admin_id'];
        }
        
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            $success = 'Post updated successfully';
            echo json_encode(['success' => $success]);
        } else {
            $error = 'No changes made or post not found';
            echo json_encode(['error' => $error]);
        }
    } catch (Exception $e) {
        error_log("Update failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update post: ' . $e->getMessage()]);
    }
    exit();
}

// Fetch post data for editing
if (isset($_GET['id'])) {
    try {
        $id = (int)$_GET['id'];
        $query = "SELECT * FROM posts WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':id' => $id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Post not found']);
            exit();
        }

        if (!canEditPost($post)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit();
        }

        echo json_encode(['post' => $post, 'categories' => $categories]);
    } catch(PDOException $e) {
        error_log("Failed to fetch post: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch post details']);
    }
    exit();
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request method']);