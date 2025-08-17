<?php
require_once 'includes/db_connect.php';

echo "<h1>Database Debug Information</h1>";
echo "<pre>";

try {
    // Check database connection
    echo "=== DATABASE CONNECTION ===\n";
    echo "Database: " . $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) . "\n\n";
    
    // Check if tables exist
    echo "=== TABLE CHECK ===\n";
    $tables = ['users', 'roles', 'patients', 'appointments', 'outpatient_visits', 'invoices', 'audit_logs'];
    
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        echo "$table: " . ($result ? "EXISTS" : "MISSING") . "\n";
    }
    echo "\n";
    
    // Check roles table
    echo "=== ROLES TABLE ===\n";
    $roles = $pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($roles as $role) {
        echo "Role ID: {$role['role_id']}, Name: {$role['role_name']}\n";
    }
    echo "\n";
    
    // Check users table
    echo "=== USERS TABLE ===\n";
    $users = $pdo->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "User ID: {$user['user_id']}\n";
        echo "Username: {$user['username']}\n";
        echo "Full Name: {$user['full_name']}\n";
        echo "Role: {$user['role_name']}\n";
        echo "Password Hash: {$user['password_hash']}\n";
        echo "Password Hash Length: " . strlen($user['password_hash']) . "\n";
        echo "Is Properly Hashed: " . (password_get_info($user['password_hash'])['algoName'] !== 'unknown' ? 'YES' : 'NO') . "\n";
        echo "---\n";
    }
    echo "\n";
    
    // Test password verification
    echo "=== PASSWORD VERIFICATION TEST ===\n";
    $test_user = $pdo->query("SELECT * FROM users WHERE username = 'admin'")->fetch(PDO::FETCH_ASSOC);
    if ($test_user) {
        echo "Testing admin user:\n";
        echo "Stored hash: {$test_user['password_hash']}\n";
        echo "Testing 'admin123': " . (password_verify('admin123', $test_user['password_hash']) ? 'MATCH' : 'NO MATCH') . "\n";
        echo "Testing 'password': " . (password_verify('password', $test_user['password_hash']) ? 'MATCH' : 'NO MATCH') . "\n";
        echo "Testing 'wrong': " . (password_verify('wrong', $test_user['password_hash']) ? 'MATCH' : 'NO MATCH') . "\n";
    } else {
        echo "Admin user not found!\n";
    }
    echo "\n";
    
    // Check sample data
    echo "=== SAMPLE DATA CHECK ===\n";
    $patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    echo "Patients: $patients\n";
    
    $appointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    echo "Appointments: $appointments\n";
    
    $visits = $pdo->query("SELECT COUNT(*) FROM outpatient_visits")->fetchColumn();
    echo "Outpatient Visits: $visits\n";
    
    $invoices = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
    echo "Invoices: $invoices\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?> 