<?php
// Show all PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json');

require_once('../../../config/config.php');

$response = ['error' => ''];

if (!function_exists('random_bytes')) {
    function random_bytes($length) {
        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }
        return $bytes;
    }
}

if (!function_exists('bin2hex')) {
    function bin2hex($binary_data) {
        return implode('', array_map(function($byte) {
            return sprintf('%02x', ord($byte));
        }, str_split($binary_data)));
    }
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $response['error'] = 'Invalid request';
    } else {
        try {
            $conn = new PDO("mysql:host={$db_config['public_blog']['host']};dbname={$db_config['public_blog']['database']};charset=utf8", 
                $db_config['public_blog']['username'], 
                $db_config['public_blog']['password']
            );
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $username = trim($_POST['username']);
            $password = $_POST['password'];

            if (!$username) {
                throw new Exception('Invalid username format');
            }

            // Check for too many login attempts
            if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5 && 
                time() - $_SESSION['last_attempt'] < 300) {
                throw new Exception('Too many login attempts. Please try again in 5 minutes.');
            }

            $stmt = $conn->prepare("SELECT * FROM admins WHERE username = :username");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            // Change password verification to use SHA256
            $hashed_password = hash('sha256', $password);
            if ($admin && $admin['password_hash'] === $hashed_password) {
                // Reset login attempts on successful login
                unset($_SESSION['login_attempts']);
                unset($_SESSION['last_attempt']);

                if ($admin['status'] !== 'active') {
                    throw new Exception('Hesabınız etkin değil. Lütfen yöneticiyle iletişime geçin.');
                }

                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['user_role'] = $admin['role'];

                $_SESSION['is_active'] = ($admin['status'] === 'active');
                $_SESSION['last_activity'] = time();
                
                // Update last login time
                $stmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id = :id");
                $stmt->execute([':id' => $admin['id']]);

                // Set secure session cookie parameters
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);

                $response['success'] = true;
                $response['message'] = 'Login successful';
            } else {
                // Track failed login attempts
                $_SESSION['login_attempts'] = isset($_SESSION['login_attempts']) ? 
                    $_SESSION['login_attempts'] + 1 : 1;
                $_SESSION['last_attempt'] = time();
                
                $response['error'] = 'Geçersiz kullanıcı adı veya şifre';
            }
        } catch(Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $response['error'] = $e->getMessage();
        }
    }
}

echo json_encode($response);
?>
