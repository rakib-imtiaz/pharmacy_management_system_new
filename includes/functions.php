<?php
// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to log audit
function log_audit($pdo, $user_id, $action, $table, $record_id) {
    $stmt = $pdo->prepare("INSERT INTO AUDIT_LOG (user_id, timestamp, action, table_affected, record_id) VALUES (?, NOW(), ?, ?, ?)");
    $stmt->execute([$user_id, $action, $table, $record_id]);
}
?> 