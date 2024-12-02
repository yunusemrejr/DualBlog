<?php
header('Content-Type: application/json');

$setup_config = @file_get_contents('data.important');
$setup_step_completed = false;

if ($setup_config !== false) {
    // Parse the data.important file
    foreach (explode("\n", $setup_config) as $line) {
        if (strpos($line, '@setup_step_completed=') === 0) {
            $setup_step_completed = (trim(str_replace('@setup_step_completed=', '', $line)) === 'true');
            break;
        }
    }
}  

if (!$setup_step_completed) {
    echo json_encode(['error' => 'Setup not completed', 'redirect' => 'setup.php']);
    exit;
}

require 'config/config.php';

$response = [];

if($blog_language == 'tr'){
    $response['language'] = 'tr';
    $response['messages'] = [
        'welcome' => 'İçerik Sayfası\'na Hoşgeldiniz',
        'general_content' => 'Genel İçerik',
        'private_content' => 'Özel İçerik',
        'visit_prompt' => 'Hangi içeriği ziyaret etmek istersiniz?',
        'welcome_opc' => 'OPCTurkey İçerik Sayfası\'na Hoşgeldiniz'
    ];
}else{
    $response['language'] = 'en';
    $response['messages'] = [
        'welcome' => 'Content Page',
        'general_content' => 'General Content',
        'private_content' => 'Private Content',
        'visit_prompt' => 'Which content would you like to visit?',
        'welcome_opc' => 'Welcome to OPCTurkey Content Page'
    ];
}

$response['links'] = [
    'public_blog' => $user_accessible_folders_for_sections['public_blog'],
    'private_blog' => $user_accessible_folders_for_sections['private_blog']
];

$response['company'] = [
    'name' => $company_name,
    'logo' => $file_paths_relative_to_config['company_logo'],
    'favicon' => $file_paths_relative_to_config['company_favicon']
];

echo json_encode($response);
exit;
