<?php
require_once '../includes/db_connect.php';
session_start();

// Verify login status
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if appointment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: appointments.php");
    exit;
}

$appointment_id = $_GET['id'];

try {
    // Get appointment details
    $sql = "SELECT a.*, p.unique_patient_code, p.first_name, p.last_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            WHERE a.appointment_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        $_SESSION['error_message'] = "Appointment not found.";
        header("Location: appointments.php");
        exit;
    }
    
    // Check if appointment is already cancelled
    if ($appointment['status'] === 'Cancelled') {
        $_SESSION['error_message'] = "This appointment is already cancelled.";
        header("Location: appointments.php");
        exit;
    }
    
    // Cancel the appointment (update status)
    $update_sql = "UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$appointment_id]);
    
    // Log the action
    $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
    $audit_stmt = $pdo->prepare($audit_sql);
    $audit_stmt->execute([
        $_SESSION['user_id'],
        'Cancel Appointment',
        'appointments',
        $appointment_id,
        "Cancelled appointment for patient {$appointment['unique_patient_code']} ({$appointment['first_name']} {$appointment['last_name']})"
    ]);
    
    $_SESSION['success_message'] = "Appointment has been successfully cancelled.";
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error cancelling appointment: " . $e->getMessage();
    error_log("Appointment Cancellation Error: " . $e->getMessage());
}

// Redirect back to appointments list
header("Location: appointments.php");
exit;
?> 