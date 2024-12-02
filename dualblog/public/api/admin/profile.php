<?php
session_start();
header('Content-Type: application/json');

// Show all PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

$response = ['error' => '', 'success' => ''];

// Fetch admin info
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// If super admin, fetch all users
$all_users = [];
if (isSuperAdmin()) {
    $stmt = $conn->query("SELECT id, username, full_name, email, role, status FROM admins ORDER BY username");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // If super admin is managing another user
        if (isSuperAdmin() && !empty($_POST['manage_user_id'])) {
            $updates = [];
            $params = [];
            $target_user_id = $_POST['manage_user_id'];

            if (!empty($_POST['full_name'])) {
                $updates[] = "full_name = :full_name";
                $params[':full_name'] = $_POST['full_name'];
            }

            if (!empty($_POST['email'])) {
                if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Geçersiz email formatı');
                }
                
                $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
                $stmt->execute([$_POST['email'], $target_user_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Bu email adresi zaten kullanımda');
                }
                
                $updates[] = "email = :email";
                $params[':email'] = $_POST['email'];
            }

            if (!empty($_POST['new_password'])) {
                $updates[] = "password_hash = :password_hash";
                $params[':password_hash'] = hash('sha256', $_POST['new_password']);
            }

            if (!empty($_POST['role'])) {
                $updates[] = "role = :role";
                $params[':role'] = $_POST['role'];
            }

            if (isset($_POST['status'])) {
                $updates[] = "status = :status";
                $params[':status'] = $_POST['status'];
            }

            if (!empty($updates)) {
                $query = "UPDATE admins SET " . implode(", ", $updates) . " WHERE id = :id";
                $params[':id'] = $target_user_id;
                
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
                
                $response['success'] = 'Kullanıcı başarıyla güncellendi';
            }
        } else {
            // Original profile update logic for non-super admins
            $updates = [];
            $params = [];

            // Update basic info
            if (!empty($_POST['full_name'])) {
                $updates[] = "full_name = :full_name";
                $params[':full_name'] = $_POST['full_name'];
            }

            if (!empty($_POST['email'])) {
                // Validate email format
                if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Geçersiz email formatı');
                }
                
                // Check if email is already in use by another user
                $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
                $stmt->execute([$_POST['email'], $admin_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Bu email adresi zaten kullanımda');
                }
                
                $updates[] = "email = :email";
                $params[':email'] = $_POST['email'];
            }

            // Handle password update
            if (!empty($_POST['new_password'])) {
                if (empty($_POST['current_password'])) {
                    throw new Exception('Mevcut şifrenizi girmelisiniz');
                }
                
                // Verify current password
                $current_password_hash = hash('sha256', $_POST['current_password']);
                if ($current_password_hash !== $admin['password_hash']) {
                    throw new Exception('Mevcut şifre yanlış');
                }
                
                // Validate new password
                if (strlen($_POST['new_password']) < 8) {
                    throw new Exception('Yeni şifre en az 8 karakter olmalıdır');
                }
                
                $updates[] = "password_hash = :password_hash";
                $params[':password_hash'] = hash('sha256', $_POST['new_password']);
            }

            if (!empty($updates)) {
                $query = "UPDATE admins SET " . implode(", ", $updates) . " WHERE id = :id";
                $params[':id'] = $admin_id;
                
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
                
                $response['success'] = 'Profil başarıyla güncellendi';
                
                // Refresh admin data
                $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
                $stmt->execute([$admin_id]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }
}

$response['admin'] = $admin;
$response['all_users'] = $all_users;

echo json_encode($response);