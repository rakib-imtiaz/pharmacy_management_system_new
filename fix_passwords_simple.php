<?php
echo "<h1>Fixing Password Hashes</h1>";
echo "<pre>";

try {
    // Connect to database using mysqli for simplicity
    $mysqli = new mysqli('127.0.0.1', 'root', '', 'clinic_demo');
    
    if ($mysqli->connect_error) {
        die("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "Connected to database successfully!\n\n";
    
    // Get all users
    $result = $mysqli->query("SELECT user_id, username, password_hash FROM users");
    $users = $result->fetch_all(MYSQLI_ASSOC);
    
    echo "Found " . count($users) . " users\n\n";
    
    foreach ($users as $user) {
        echo "Processing user: {$user['username']}\n";
        echo "Current hash: {$user['password_hash']}\n";
        
        // Determine the correct password based on username
        $correct_password = '';
        switch ($user['username']) {
            case 'admin':
                $correct_password = 'admin123';
                break;
            case 'drjones':
                $correct_password = 'doctor123';
                break;
            case 'nurseamy':
                $correct_password = 'staff123';
                break;
            default:
                $correct_password = $user['password_hash']; // Use current value as password
        }
        
        // Hash the password properly
        $new_hash = password_hash($correct_password, PASSWORD_DEFAULT);
        
        // Update the database
        $update_sql = "UPDATE users SET password_hash = ? WHERE user_id = ?";
        $stmt = $mysqli->prepare($update_sql);
        $stmt->bind_param("si", $new_hash, $user['user_id']);
        $stmt->execute();
        
        echo "Updated password hash for {$user['username']}\n";
        echo "New hash: " . substr($new_hash, 0, 20) . "...\n";
        
        // Verify the new hash works
        $verify_result = password_verify($correct_password, $new_hash);
        echo "Verification test: " . ($verify_result ? 'PASS' : 'FAIL') . "\n";
        echo "---\n";
    }
    
    echo "\nPassword fix completed!\n";
    
    // Test login with the fixed passwords
    echo "\n=== TESTING LOGIN ===\n";
    $test_credentials = [
        ['admin', 'admin123'],
        ['drjones', 'doctor123'],
        ['nurseamy', 'staff123']
    ];
    
    foreach ($test_credentials as $cred) {
        $username = $cred[0];
        $password = $cred[1];
        
        $sql = "SELECT u.user_id, u.username, u.password_hash, u.full_name, r.role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                WHERE u.username = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            $login_success = password_verify($password, $user['password_hash']);
            echo "Login test for $username: " . ($login_success ? 'SUCCESS' : 'FAILED') . "\n";
        } else {
            echo "User $username not found!\n";
        }
    }
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?> 