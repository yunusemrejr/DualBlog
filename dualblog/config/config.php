<?php
// Enhanced Database Configuration
$db_config = [
    'public_blog' => [
        'host' => 'localhost',
        'database' => 'u6585514_opcblog',
        'username' => 'root',
        'password' => 'root',
    ],
    'private_blog' => [
        'host' => 'localhost',
        'database' => 'u6585514_yev_cms',
        'username' => 'root',
        'password' => 'root',
    ],
];

$private_blog_access_code = "Aspotomasyon.89*";

$main_folder = 'dualblog'; // Ensure this matches the main folder name for the engine

$domains = [
    'public_blog' => 'localhost',
    'private_blog' => 'localhost',
];

$user_accessible_folders_for_sections = [
    'public_blog' => 'dualblog/public', // Ensure this matches the folder name
    'private_blog' => 'dualblog/private', // Ensure this matches the folder name
];

// Base directory of this configuration file
$config_base_dir = __DIR__;

// File paths relative to this config file
$file_paths_relative_to_config = [
    'public_blog' => $user_accessible_folders_for_sections['public_blog'],  // Adjusted to main folder hierarchy
    'private_blog' => $user_accessible_folders_for_sections['private_blog'], // Adjusted to main folder hierarchy
    'company_logo' =>  'frontend/img/logo.png', // Adjusted to main folder hierarchy. Logo should be sized 600x103px. Don't change this path unless you know what you are doing.
    'company_favicon' =>   'frontend/img/favicon.png', // Adjusted to main folder hierarchy. Favicon should be sized 16x16px. Don't change this path unless you know what you are doing.
];

$company_name = 'OPCTurkey';
$company_url = 'https://opcturkey.com';
$company_email = 'info@opcturkey.com';
$company_phone = '+905326666666';
$company_address = 'Turkey';
$cms_language = 'tr';
$blog_language = 'tr';

$HTTPS = 0;

// Table definitions for both databases
$table_definitions = [
    'public_blog' => [
        'TABLE_ADMINS' => 'public_admins',
        'TABLE_CATEGORIES' => 'public_categories',
        'TABLE_MEDIA' => 'public_media',
        'TABLE_POSTS' => 'public_posts',
        'TABLE_TAGS' => 'public_tags',
        'TABLE_POSTS_TAGS' => 'public_posts_tags'
    ],
    'private_blog' => [
        'TABLE_ADMINS' => 'private_admins',
        'TABLE_CATEGORIES' => 'private_categories',
        'TABLE_MEDIA' => 'private_media',
        'TABLE_POSTS' => 'private_posts',
        'TABLE_TAGS' => 'private_tags',
        'TABLE_POSTS_TAGS' => 'private_posts_tags'
    ]
];

?>
