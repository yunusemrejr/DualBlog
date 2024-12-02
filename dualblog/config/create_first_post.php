<?php
require_once 'config.php';

function createPublicBlogFirstPost($pdo, $dbname) {
    try {
        $pdo->exec("USE `" . $dbname . "`;");
        
        // First check if posts already exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM posts");
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            echo "Posts already exist in $dbname. Skipping first post creation.<br>\n";
            return;
        }

        $pdo->beginTransaction();

        // Create first category if none exists
        $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
        if ($stmt->fetchColumn() == 0) {
            $sql = "INSERT INTO categories (name, slug, description) VALUES 
                   ('GENEL', 'GENEL', 'GENEL')";
            $pdo->exec($sql);
            $category_id = $pdo->lastInsertId();
        } else {
            $category_id = 1;
        }

        // Get first admin id
        $stmt = $pdo->query("SELECT id FROM admins WHERE username = 'yunus' LIMIT 1");
        $admin_id = $stmt->fetchColumn();

        // Initial blog post from database.sql
        $sql = "INSERT INTO posts (
            title, 
            slug, 
            content,
            excerpt,
            featured_image,
            category_id,
            author_id,
            status,
            meta_description,
            views_count
        ) VALUES (
            'OPC UA Nedir ve Neden OPC DA''dan Daha İyidir?',
            'opc-ua-nedir-ve-neden-opc-da-dan-daha-yidir',
            '<p>Günümüzde endüstriyel otomasyon sistemleri, veri entegrasyonu ve cihazlar arası iletişim açısından hızla gelişmektedir...</p>',
            'Günümüzde endüstriyel otomasyon sistemleri, veri entegrasyonu ve cihazlar arası iletişim açısından hızla gelişmektedir.',
            'images/posts/2.jpg',
            :category_id,
            :admin_id,
            'published',
            'Günümüzde endüstriyel otomasyon sistemleri, veri entegrasyonu ve cihazlar arası iletişim açısından hızla gelişmektedir.',
            1
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'category_id' => $category_id,
            'admin_id' => $admin_id
        ]);

        $pdo->commit();
        echo "First post created successfully in $dbname.<br>\n";
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function createPrivateBlogFirstPost($pdo, $dbname) {
    try {
        $pdo->exec("USE `" . $dbname . "`;");
        
        // First check if articles already exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM articles");
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            echo "Articles already exist in $dbname. Skipping first article creation.<br>\n";
            return;
        }

        // Initial article from database.sql
        $sql = "INSERT INTO articles (title, content) VALUES (
            'Placeholder Title',
            '<p>Placeholder content for the first article...</p>'
        )";
        
        $pdo->exec($sql);
        echo "First article created successfully in $dbname.<br>\n";
        
    } catch (PDOException $e) {
        throw $e;
    }
}

try {
    $pdo = new PDO(
        "mysql:host=" . $db_config['public_blog']['host'],
        $db_config['public_blog']['username'],
        $db_config['public_blog']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "Creating first post for public blog...<br>\n";
    createPublicBlogFirstPost($pdo, $db_config['public_blog']['database']);

    echo "Creating first article for private blog...<br>\n";
    createPrivateBlogFirstPost($pdo, $db_config['private_blog']['database']);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
