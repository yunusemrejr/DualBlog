<?php
require_once 'config.php';

// First establish connection and create databases
try {
    // Root connection for creating databases
    $pdo = new PDO(
        "mysql:host=" . $db_config['public_blog']['host'],
        $db_config['public_blog']['username'],
        $db_config['public_blog']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create databases first (outside of any transaction)
    $sql = "CREATE DATABASE IF NOT EXISTS `" . $db_config['public_blog']['database'] . "` 
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
    $pdo->exec($sql);
    echo "Ensured public blog database exists.<br>\n";
    
    $sql = "CREATE DATABASE IF NOT EXISTS `" . $db_config['private_blog']['database'] . "` 
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
    $pdo->exec($sql);
    echo "Ensured private blog database exists.<br>\n";

} catch (PDOException $e) {
    die("Error creating databases: " . $e->getMessage() . "<br>\n");
}

function createPrivateBlogTables($pdo, $dbname) {
    try {
        $pdo->exec("USE `" . $dbname . "`;");
        
        // Create only the articles table for private blog
        $sql = "CREATE TABLE IF NOT EXISTS `articles` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `content` text NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        
        $pdo->exec($sql);
        echo "Created 'articles' table in private blog database.<br>\n";
        
        return true;
    } catch (PDOException $e) {
        throw new PDOException("Error in private database: " . $e->getMessage() . "<br>\n");
    }
}

function createPublicBlogTables($pdo, $dbname) {
    try {
        $pdo->exec("USE `" . $dbname . "`;");

        // Create tables one by one without transaction
        /**
         * Create 'articles' Table
         */
        $sql = "CREATE TABLE IF NOT EXISTS `articles` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `content` text NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        
        $pdo->exec($sql);
        echo "Created 'articles' table in $dbname.<br>\n";

        /**
         * Create 'admins' Table
         */
        $sql = "CREATE TABLE IF NOT EXISTS `admins` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
            `full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `role` enum('super_admin','admin','author') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'author',
            `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_login` timestamp NULL DEFAULT NULL,
            `is_active` tinyint(1) DEFAULT '1',
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql);
        echo "Created 'admins' table in $dbname.<br>    \n";

        /**
         * Create 'categories' Table
         */
        $sql = "CREATE TABLE IF NOT EXISTS `categories` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
            `description` text COLLATE utf8mb4_unicode_ci,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql);
        echo "Created 'categories' table in $dbname.<br>\n";

        /**
         * Create 'media' Table
         */
        $sql = "CREATE TABLE IF NOT EXISTS `media` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `file_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `file_size` int(11) DEFAULT NULL,
            `uploaded_by` int(11) DEFAULT NULL,
            `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`uploaded_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql);
        echo "Created 'media' table in $dbname.<br>\n";

        /**
         * Create 'posts' Table
         */
        $sql = "CREATE TABLE IF NOT EXISTS `posts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `excerpt` text COLLATE utf8mb4_unicode_ci,
            `featured_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `category_id` int(11) DEFAULT NULL,
            `author_id` int(11) DEFAULT NULL,
            `status` enum('draft','published','archived') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
            `published_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            `meta_description` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `meta_keywords` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `views_count` int(11) DEFAULT '0',
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`),
            FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`author_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql);
        echo "Created 'posts' table in $dbname.<br>\n";

        /**
         * Create 'tags' Table
         */
        $sql = "CREATE TABLE IF NOT EXISTS `tags` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql);
        echo "Created 'tags' table in $dbname.<br>\n";

        /**
         * Create 'posts_tags' Junction Table
         */
        $sql = "CREATE TABLE IF NOT EXISTS `posts_tags` (
            `post_id` int(11) NOT NULL,
            `tag_id` int(11) NOT NULL,
            PRIMARY KEY (`post_id`,`tag_id`),
            FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`tag_id`) REFERENCES `tags`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql);
        echo "Created 'posts_tags' table in $dbname.<br>\n";

        echo "<span class='success'>All tables created successfully in $dbname.</span><br>\n";
        return true;
    } catch (PDOException $e) {
        throw new PDOException("Error in database '$dbname': " . $e->getMessage());
    }
}

// Now create the appropriate tables for each database
try {
    // Create tables for private blog (just articles table)
    echo "Setting up private blog database...<br>\n";
    createPrivateBlogTables($pdo, $db_config['private_blog']['database']);

    // Create tables for public blog (full set of tables)
    echo "Setting up public blog database...<br>\n";
    createPublicBlogTables($pdo, $db_config['public_blog']['database']);

} catch (PDOException $e) {
    die("Error creating tables: " . $e->getMessage() . "<br>\n");
}
?>
