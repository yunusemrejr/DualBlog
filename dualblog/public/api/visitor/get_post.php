<?php
// File: api/post.php (or get_post.php)

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); // Adjust as needed for security
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once('../../../config/config.php');

// Initialize response array
$response = [];

// Validate and retrieve the 'id' parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || $_GET['id'] <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode([
        "success" => false,
        "message" => "Invalid or missing 'id' parameter."
    ]);
    exit;
}

$post_id = (int)$_GET['id'];

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
    // Fetch the specific post with additional fields
    $query = "SELECT 
                p.id, 
                p.title, 
                p.slug,
                p.content, 
                p.excerpt, 
                IFNULL(p.featured_image, 'images/placeholder.webp') AS image_url, 
                p.created_at, 
                p.updated_at,
                p.published_at,
                p.category_id,
                p.views_count,
                p.meta_description,
                p.meta_keywords,
                a.full_name AS author 
              FROM posts p 
              LEFT JOIN admins a ON p.author_id = a.id 
              WHERE p.id = :id AND p.status = 'published'";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $post_id, PDO::PARAM_INT);
    $stmt->execute();
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($post) {
        // Initialize IntlDateFormatter for Turkish locale
        $formatter = new IntlDateFormatter(
            'tr_TR', 
            IntlDateFormatter::LONG, 
            IntlDateFormatter::NONE, 
            'Europe/Istanbul', // Timezone
            IntlDateFormatter::TRADITIONAL
        );

        // Determine which date to format: updated_at or created_at
        if (!empty($post['updated_at']) && strtotime($post['updated_at'])) {
            $dateToFormat = $post['updated_at'];
        } elseif (!empty($post['created_at']) && strtotime($post['created_at'])) {
            $dateToFormat = $post['created_at'];
        } else {
            $dateToFormat = null;
        }

        // Format the date in Turkish
        if ($dateToFormat) {
            $date = $formatter->format(new DateTime($dateToFormat));
            // Convert to lowercase first letter as per Turkish typographic conventions
            $date = mb_convert_case($date, MB_CASE_TITLE, "UTF-8");
        } else {
            $date = 'Tarih mevcut değil'; // Default message
        }

        // Check if the featured image exists
        $featured_image = $post['image_url'];
        if (!@getimagesize($featured_image)) {
            $featured_image = 'images/placeholder.webp';
        }

        // Decode the content to handle HTML entities
        $content = html_entity_decode($post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Prepare the response with additional fields
        $response = [
            "success" => true,
            "data" => [
                "id" => (int)$post['id'],
                "title" => $post['title'],
                "slug" => $post['slug'],
                "excerpt" => $post['excerpt'],
                "content" => $content,
                "image_url" => $featured_image,
                "published_at" => $post['published_at'],
                "created_at" => $post['created_at'],
                "updated_at" => $post['updated_at'],
                "formatted_date" => $date,
                "author" => $post['author'] ?: $GLOBALS['company_name'],
                "category_id" => isset($post['category_id']) ? (int)$post['category_id'] : null,
                "views_count" => isset($post['views_count']) ? (int)$post['views_count'] : 0,
                "meta_description" => $post['meta_description'],
                "meta_keywords" => $post['meta_keywords']
                // Add more fields here if needed, such as comments, tags, etc.
            ]
        ];
    } else {
        http_response_code(404); // Not Found
        $response = [
            "success" => false,
            "message" => "Post not found."
        ];
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch(PDOException $e) {
    // Log the error internally
    error_log("Error fetching post: " . $e->getMessage(), 3, '../../../logs/error.log');

    http_response_code(500); // Internal Server Error
    echo json_encode([
        "success" => false,
        "message" => "An error occurred while fetching the post."
    ]);
    exit;
}
?>
