<?php
require_once 'config.php';

function createPublicBlogAdmin($pdo, $dbname) {
    try {
        $pdo->exec("USE `" . $dbname . "`;");
        
        // First check if admins already exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            echo "Admins already exist in $dbname. Skipping admin creation.<br>\n";
            return;
        }

        $pdo->beginTransaction();

        // Initial admin users with hashed passwords
        $admins = [
            [
                'username' => 'john.doe',
                'password' => '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918', // Hashed version of actual password
                'email' => 'john.doe@test.com',
                'full_name' => 'John Doe',
                'role' => 'super_admin'
            ],
            [
                'username' => 'john.doe.admin',
                'password' => '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918',
                'email' => 'john.doe.admin@test.com',
                'full_name' => 'John Doe Admin',
                'role' => 'admin'
            ],
            [
                'username' => 'john.doe.author',
                'password' => '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918',
                'email' => 'john.doe.author@test.com',
                'full_name' => 'John Doe Author',
                'role' => 'author'
            ],
            [
                'username' => 'john.doe.team',
                'password' => '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918',
                'email' => 'john.doe.team@test.com',
                'full_name' => 'John Doe Team',
                'role' => 'author'
            ]
        ];

        $sql = "INSERT INTO admins (
            username,
            password_hash,
            email,
            full_name,
            role,
            status,
            created_at
        ) VALUES (
            :username,
            :password_hash,
            :email,
            :full_name,
            :role,
            'active',
            CURRENT_TIMESTAMP
        )";

        $stmt = $pdo->prepare($sql);

        foreach ($admins as $admin) {
            $stmt->execute([
                'username' => $admin['username'],
                'password_hash' => $admin['password'],  // Already hashed in the array
                'email' => $admin['email'],
                'full_name' => $admin['full_name'],
                'role' => $admin['role']
            ]);
            
            echo "Created admin user: " . $admin['username'] . " (" . $admin['role'] . ") in $dbname\n";
        }

        $pdo->commit();
        echo "All admin users created successfully in $dbname.<br>\n";
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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
    
    echo "Creating admins for public blog...<br>\n";
    createPublicBlogAdmin($pdo, $db_config['public_blog']['database']);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
