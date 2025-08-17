<?php
require_once 'includes/db_connect.php';

echo "<h1>Fixing Password Hashes</h1>";
echo "<pre>";

try {
    // Get all users
    $users = $pdo->query("SELECT user_id, username, password_hash FROM users")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($users) . " users\n\n";
    
    foreach ($users as $user) {
        echo "Processing user: {$user['username']}\n";
        echo "Current hash: {$user['password_hash']}\n";
        
        // Check if password is already properly hashed
        $hash_info = password_get_info($user['password_hash']);
        $is_properly_hashed = $hash_info['algoName'] !== 'unknown';
        
        echo "Is properly hashed: " . ($is_properly_hashed ? 'YES' : 'NO') . "\n";
        
        if (!$is_properly_hashed) {
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
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([$new_hash, $user['user_id']]);
            
            echo "Updated password hash for {$user['username']}\n";
            echo "New hash: " . substr($new_hash, 0, 20) . "...\n";
            
            // Verify the new hash works
            $verify_result = password_verify($correct_password, $new_hash);
            echo "Verification test: " . ($verify_result ? 'PASS' : 'FAIL') . "\n";
        } else {
            echo "Password already properly hashed, skipping...\n";
        }
        
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
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $login_success = password_verify($password, $user['password_hash']);
            echo "Login test for $username: " . ($login_success ? 'SUCCESS' : 'FAILED') . "\n";
        } else {
            echo "User $username not found!\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?> 