<?php
require_once '../includes/db_connect.php';
session_start();

// Verify login status
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: invoices.php");
    exit;
}

$invoice_id = $_GET['id'];

try {
    // Check if invoice exists and is not already paid
    $check_sql = "SELECT invoice_id, paid, patient_id FROM invoices WHERE invoice_id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$invoice_id]);
    $invoice = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        // Invoice not found
        $_SESSION['error_message'] = "Invoice not found.";
        header("Location: invoices.php");
        exit;
    }
    
    if ($invoice['paid']) {
        // Invoice already paid
        $_SESSION['info_message'] = "This invoice is already marked as paid.";
        header("Location: view_invoice.php?id=$invoice_id");
        exit;
    }
    
    // Update the invoice to mark as paid
    $update_sql = "UPDATE invoices SET paid = TRUE WHERE invoice_id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$invoice_id]);
    
    // Log the action
    $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
    $audit_stmt = $pdo->prepare($audit_sql);
    $audit_stmt->execute([
        $_SESSION['user_id'],
        'Mark Invoice Paid',
        'invoices',
        $invoice_id,
        "Marked invoice #$invoice_id as paid"
    ]);
    
    // Redirect back to invoice view with success message
    header("Location: view_invoice.php?id=$invoice_id&paid=1");
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error updating payment status: " . $e->getMessage();
    error_log("Invoice Payment Error: " . $e->getMessage());
    header("Location: view_invoice.php?id=$invoice_id");
    exit;
}
?> 