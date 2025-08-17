<?php
require_once '../includes/db_connect.php';
session_start();

// Verify login status
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if the user has proper permissions (admin or staff role)
if (!in_array($_SESSION['role'], ['Admin', 'Staff'])) {
    $_SESSION['error_message'] = "You don't have permission to delete patients.";
    header("Location: patients.php");
    exit;
}

// Check if patient ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: patients.php");
    exit;
}

$patient_id = $_GET['id'];

try {
    // Get patient details for the log
    $sql = "SELECT unique_patient_code, first_name, last_name FROM patients WHERE patient_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        $_SESSION['error_message'] = "Patient not found.";
        header("Location: patients.php");
        exit;
    }
    
    // Start a transaction
    $pdo->beginTransaction();
    
    // First, check if patient has related records that would prevent deletion
    $check_appointments = "SELECT COUNT(*) FROM appointments WHERE patient_id = ?";
    $stmt = $pdo->prepare($check_appointments);
    $stmt->execute([$patient_id]);
    $has_appointments = $stmt->fetchColumn() > 0;
    
    $check_visits = "SELECT COUNT(*) FROM outpatient_visits WHERE patient_id = ?";
    $stmt = $pdo->prepare($check_visits);
    $stmt->execute([$patient_id]);
    $has_visits = $stmt->fetchColumn() > 0;
    
    $check_invoices = "SELECT COUNT(*) FROM invoices WHERE patient_id = ?";
    $stmt = $pdo->prepare($check_invoices);
    $stmt->execute([$patient_id]);
    $has_invoices = $stmt->fetchColumn() > 0;
    
    // If patient has related records, don't delete but inform the user
    if ($has_appointments || $has_visits || $has_invoices) {
        $pdo->rollBack();
        
        $_SESSION['error_message'] = "Cannot delete patient {$patient['unique_patient_code']} ({$patient['first_name']} {$patient['last_name']}) because they have ";
        
        $reasons = [];
        if ($has_appointments) $reasons[] = "appointments";
        if ($has_visits) $reasons[] = "visit records";
        if ($has_invoices) $reasons[] = "invoices";
        
        $_SESSION['error_message'] .= implode(", ", $reasons) . " associated with their account.";
        header("Location: patients.php");
        exit;
    }
    
    // If no related records, proceed with deletion
    $delete_sql = "DELETE FROM patients WHERE patient_id = ?";
    $stmt = $pdo->prepare($delete_sql);
    $stmt->execute([$patient_id]);
    
    // Log the action
    $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
    $audit_stmt = $pdo->prepare($audit_sql);
    $audit_stmt->execute([
        $_SESSION['user_id'],
        'Delete Patient',
        'patients',
        $patient_id,
        "Deleted patient {$patient['unique_patient_code']}: {$patient['first_name']} {$patient['last_name']}"
    ]);
    
    // Commit the transaction
    $pdo->commit();
    
    $_SESSION['success_message'] = "Patient {$patient['unique_patient_code']} ({$patient['first_name']} {$patient['last_name']}) has been successfully deleted.";
    
} catch (PDOException $e) {
    // Rollback in case of error
    $pdo->rollBack();
    
    $_SESSION['error_message'] = "Error deleting patient: " . $e->getMessage();
    error_log("Patient Delete Error: " . $e->getMessage());
}

// Redirect back to patients list
header("Location: patients.php");
exit;
?> 