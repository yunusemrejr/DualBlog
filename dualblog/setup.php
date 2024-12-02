<?php

echo "<!DOCTYPE html><html><body><br><img src='media/logo.png' width='300' alt='OPCTurkey Logo' class='logo'><br><br><style>body{font-family: Arial, sans-serif;font-size: 18px;color:gray;}</style>";

// Read setup status from data.important
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
} else {
    file_put_contents('data.important', '@setup_step_completed=false');
    echo "Setup waiting. You need to have a valid data.important file to continue.";
}

// Check if config file exists and has all variables filled
$config_valid = false;
if (file_exists('config/config.php')) {
    include 'config/config.php';

    $config_valid = !empty($db_config['public_blog']['host']) &&
                    !empty($db_config['public_blog']['database']) &&
                    !empty($db_config['public_blog']['username']) &&
                    !empty($db_config['public_blog']['password']) &&
                    !empty($db_config['private_blog']['host']) &&
                    !empty($db_config['private_blog']['database']) &&
                    !empty($db_config['private_blog']['username']) &&
                    !empty($db_config['private_blog']['password'])  ;
}
 

if ($config_valid) {
    try {
        // Step 1: Create databases and tables
        include 'config/create_tables.php';
        
        // Step 2: Create admin users
        include 'config/create_admin.php';
        
        // Step 3: Create initial posts
        include 'config/create_first_post.php';
        
        $db_connection_successful = true;
        
    } catch (Exception $e) {
        echo "Error during setup: " . $e->getMessage() . "<br>";
        $db_connection_successful = false;
    }
}

// After successfully setting up public_blog
if ($db_connection_successful) {
    // Attempt to connect to private_blog
    $private_db_success = false;
    try {
        $private_mysqli = new mysqli(
            $db_config['private_blog']['host'],
            $db_config['private_blog']['username'],
            $db_config['private_blog']['password'],
            $db_config['private_blog']['database']
        );
        $private_db_success = true;
        $private_mysqli->close();
    } catch (Exception $e) {
        echo "Private blog database connection failed: " . $e->getMessage() . "<br>";
    }

    if ($private_db_success) {
        // Both public and private blog connections are successful
        $setup_step_completed = true;
        file_put_contents('data.important', '@setup_step_completed=true');
    } else {
        $setup_step_completed = false;
        file_put_contents('data.important', '@setup_step_completed=false');
    }
}
else {
    file_put_contents('data.important', '@setup_step_completed=false');
}

if (!$setup_step_completed) {
    echo "<br><br><b>Setup waiting. You need to have a valid data.important file and config/config.php file to continue. These file names may differ in your system.</b><br><br><br><br><br><br><img src='media/logo.png' width='300' alt='OPCTurkey Logo' class='logo'>";
}

// Wait for user to click "finish" on setup screen
if ($setup_step_completed) {
    //change htaccess file to replace DirectoryIndex setup.php with DirectoryIndex index.php
    $htaccess_path = '.htaccess';
    $htaccess_content = file_get_contents($htaccess_path);
    $htaccess_content = str_replace('DirectoryIndex setup.php', 'DirectoryIndex index.php', $htaccess_content);
    file_put_contents($htaccess_path, $htaccess_content);

    echo "<script>
document.querySelectorAll('.success').forEach(function(element) {
    element.style.color = 'green';
});
document.querySelectorAll('.fail').forEach(function(element) {
    element.style.color = 'red';
});
    </script><br><br><form method='post' action='index.php'>
            <input style='font-size: 20px;padding: 10px;background-color: #004AAD;color: white;border: none;cursor: pointer;' type='submit' value='Finish Setup'>
          </form>
          ";
}

echo "</body></html>";
?>