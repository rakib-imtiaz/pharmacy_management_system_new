<?php
session_start();
require_once 'includes/db_connect.php';

if (isset($_SESSION['user_id'])) {
    try {
        // Verify user_id exists in USER table
        $stmt = $pdo->prepare("SELECT user_id FROM USER WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userExists = $stmt->fetch();

        if ($userExists) {
            // Log the logout action if user exists
            $stmt = $pdo->prepare("
                INSERT INTO AUDIT_LOG (user_id, timestamp, action, table_affected, record_id)
                VALUES (?, CURRENT_TIMESTAMP, 'LOGOUT', 'USER', ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        }
    } catch (PDOException $e) {
        // Log error message if needed
        error_log("Logout error: " . $e->getMessage());
    }
}

// Clear all session variables
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>

