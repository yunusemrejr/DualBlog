<?php
require 'config/config.php';
header('Content-Type: application/json');

$response = [];

if ($blog_language == 'tr') {
    $response['error_title'] = 'Hata! Bir şeyler ters gitti.';
    $response['error_message'] = 'Aradığınız sayfa kaldırılmış, adı değiştirilmiş veya geçici olarak kullanılamıyor olabilir.';
} else {
    $response['error_title'] = 'Oops! Something went wrong.';
    $response['error_message'] = 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.';
}

$response['company'] = [
    'name' => $company_name,
    'logo' => $file_paths_relative_to_config['company_logo'],
    'favicon' => 'media/B.png'
];

echo json_encode($response);
exit();