<?php // new-single.php
// Show all PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
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
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed. Please check the error log.']);
    exit();
}

function saveUploadedImage($file, $post_id) {
    $upload_dir = '../images/posts/';
    
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
        $random_id = substr(md5(uniqid()), 0, 8);
        $new_filename = $base_filename . '_' . $random_id . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
    }
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return 'images/posts/' . $new_filename;
    }
    
    throw new Exception('Failed to save image');
}

function generateUniqueSlug($conn, $title) {
    // Convert title to lowercase and replace non-alphanumeric characters with dash
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    $slug = trim($slug, '-');
    
    // Check if slug exists
    $baseSlug = $slug;
    $counter = 1;
    
    do {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE slug = ?");
        $stmt->execute([$slug]);
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
    } while ($exists);
    
    return $slug;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Generate unique slug
        $slug = generateUniqueSlug($conn, $_POST['title']);

        // Validate required fields
        if (empty($_POST['title']) || empty($_POST['content'])) {
            throw new Exception('Title and content are required');
        }

        // First insert the post to get the ID
        $query = "INSERT INTO posts (
                    title, content, excerpt, category_id, 
                    status, meta_description, author_id, 
                    created_at, updated_at, slug
                ) VALUES (
                    :title, :content, :excerpt, :category_id,
                    :status, :meta_description, :author_id,
                    NOW(), NOW(), :slug
                )";
        
        $stmt = $conn->prepare($query);
        
        $stmt->execute([
            ':title' => $_POST['title'],
            ':content' => $_POST['content'],
            ':excerpt' => isset($_POST['excerpt']) ? $_POST['excerpt'] : '',
            ':category_id' => $_POST['category_id'] ? (int)$_POST['category_id'] : null,
            ':status' => $_POST['status'],
            ':meta_description' => isset($_POST['meta_description']) ? $_POST['meta_description'] : '',
            ':author_id' => $_SESSION['admin_id'],
            ':slug' => $slug
        ]);
        
        $post_id = $conn->lastInsertId();

        // Handle image upload if present
        $featured_image = '';
        if (isset($_FILES['image_upload']) && $_FILES['image_upload']['size'] > 0) {
            $featured_image = saveUploadedImage($_FILES['image_upload'], $post_id);
        } elseif (!empty($_POST['featured_image'])) {
            $featured_image = $_POST['featured_image'];
        }

        // Update the post with the featured image if we have one
        if ($featured_image) {
            $stmt = $conn->prepare("UPDATE posts SET featured_image = :featured_image WHERE id = :id");
            $stmt->execute([
                ':featured_image' => $featured_image,
                ':id' => $post_id
            ]);
        }

        http_response_code(201);
        echo json_encode(['success' => 'Post created successfully', 'post_id' => $post_id]);
        exit();

    } catch (Exception $e) {
        error_log("Create post failed: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => 'Failed to create post: ' . $e->getMessage()]);
        exit();
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}
?>
