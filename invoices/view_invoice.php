<?php
require_once '../includes/db_connect.php';
session_start();

// Verify login status
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$show_success = isset($_GET['new']) || isset($_GET['updated']) || isset($_GET['paid']);
$success_message = '';

if (isset($_GET['new'])) {
    $success_message = "Invoice created successfully!";
} elseif (isset($_GET['updated'])) {
    $success_message = "Invoice updated successfully!";
} elseif (isset($_GET['paid'])) {
    $success_message = "Invoice marked as paid!";
}

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: invoices.php");
    exit;
}

$invoice_id = $_GET['id'];

try {
    // Get invoice details with patient info
    $sql = "SELECT i.*, p.unique_patient_code, p.first_name, p.last_name, p.phone, p.email, p.address, p.insurance_info,
                  ov.visit_datetime, ov.visit_id, ov.diagnosis, ov.doctor_id,
                  u.full_name AS doctor_name
           FROM invoices i
           JOIN patients p ON i.patient_id = p.patient_id
           LEFT JOIN outpatient_visits ov ON i.visit_id = ov.visit_id
           LEFT JOIN users u ON ov.doctor_id = u.user_id
           WHERE i.invoice_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        header("Location: invoices.php");
        exit;
    }
    
    // Get invoice items
    $items_sql = "SELECT ii.*, s.service_name, s.service_description
                  FROM invoice_items ii
                  JOIN services s ON ii.service_id = s.service_id
                  WHERE ii.invoice_id = ?
                  ORDER BY ii.item_id";
    $items_stmt = $pdo->prepare($items_sql);
    $items_stmt->execute([$invoice_id]);
    $invoice_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Invoice View Error: " . $e->getMessage());
    header("Location: invoices.php");
    exit;
}

include_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 fade-in">
        <div>
            <div class="flex items-center mb-4">
                <a href="invoices.php" class="text-blue-600 hover:text-blue-800 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <h1 class="text-3xl font-bold text-gray-800">Invoice #<?php echo str_pad($invoice_id, 5, '0', STR_PAD_LEFT); ?></h1>
            </div>
            <p class="text-gray-600">View invoice details and generate statements</p>
        </div>
        
        <div class="flex space-x-2 mt-4 md:mt-0">
            <button onclick="printInvoice()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center">
                <i class="fas fa-print mr-2"></i>
                Print
            </button>
            
            <button onclick="sendStatement()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center">
                <i class="fas fa-paper-plane mr-2"></i>
                Send Statement
            </button>
        </div>
    </div>

    <!-- Success Message -->
    <?php if ($show_success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 fade-in">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Invoice Details -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <!-- Invoice Header -->
                <div class="p-6 bg-gray-50 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">Invoice Details</h2>
                            <p class="text-gray-600">
                                Date: <?php echo date('F j, Y', strtotime($invoice['invoice_date'])); ?>
                            </p>
                        </div>
                        <div>
                            <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold 
                                <?php echo $invoice['paid'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                <?php echo $invoice['paid'] ? 'Paid' : 'Unpaid'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Invoice Content -->
                <div class="p-6">
                    <div class="mb-8">
                        <div class="flex justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 uppercase mb-2">Bill To</h3>
                                <p class="font-medium text-gray-800">
                                    <?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?>
                                </p>
                                <p class="text-gray-600">ID: <?php echo htmlspecialchars($invoice['unique_patient_code']); ?></p>
                                <?php if ($invoice['address']): ?>
                                    <p class="text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($invoice['address'])); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-right">
                                <h3 class="text-sm font-medium text-gray-500 uppercase mb-2">From</h3>
                                <p class="font-medium text-gray-800">Bayside Surgical Centre</p>
                                <p class="text-gray-600">123 Healthcare Ave</p>
                                <p class="text-gray-600">Medical District, MD 12345</p>
                                <p class="text-gray-600">Tel: (555) 123-4567</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($invoice['visit_id']): ?>
                    <!-- Visit Information -->
                    <div class="mb-8 p-4 bg-blue-50 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-700 mb-2">Visit Information:</h3>
                        <div class="flex justify-between">
                            <div>
                                <p class="font-medium text-gray-800">
                                    Date: <?php echo date('F j, Y', strtotime($invoice['visit_datetime'])); ?>
                                </p>
                                <p class="text-gray-600">Time: <?php echo date('g:i A', strtotime($invoice['visit_datetime'])); ?></p>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">Doctor:</p>
                                <p class="text-gray-600"><?php echo htmlspecialchars($invoice['doctor_name'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <?php if (!empty($invoice['diagnosis'])): ?>
                            <div class="mt-2 pt-2 border-t border-blue-100">
                                <p class="font-medium text-gray-800">Diagnosis:</p>
                                <p class="text-gray-600"><?php echo htmlspecialchars($invoice['diagnosis']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Invoice Items -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Services & Charges</h3>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                        <th class="px-4 py-2 border-b text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th class="px-4 py-2 border-b text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                                        <th class="px-4 py-2 border-b text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoice_items as $item): ?>
                                        <tr class="border-b">
                                            <td class="px-4 py-4">
                                                <div class="font-medium text-gray-800"><?php echo htmlspecialchars($item['service_name']); ?></div>
                                                <?php if (!empty($item['service_description'])): ?>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($item['service_description']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-4 text-right"><?php echo $item['quantity']; ?></td>
                                            <td class="px-4 py-4 text-right">$<?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td class="px-4 py-4 text-right">$<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50">
                                        <td colspan="3" class="px-4 py-3 text-right font-bold">Total</td>
                                        <td class="px-4 py-3 text-right font-bold">$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Payment Information -->
                    <div class="border-t border-gray-200 pt-6 flex justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase mb-2">Payment Status</h3>
                            <p class="font-medium <?php echo $invoice['paid'] ? 'text-green-600' : 'text-yellow-600'; ?>">
                                <?php echo $invoice['paid'] ? 'Paid' : 'Unpaid'; ?>
                            </p>
                            
                            <?php if (!$invoice['paid']): ?>
                                <button onclick="markAsPaid()" class="mt-2 bg-green-500 hover:bg-green-600 text-white px-4 py-1 text-sm rounded transition-colors duration-300">
                                    <i class="fas fa-check mr-1"></i> Mark as Paid
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-right">
                            <p class="text-sm text-gray-500 mb-1">Amount Due:</p>
                            <p class="text-2xl font-bold text-gray-800">$<?php echo number_format($invoice['total_amount'], 2); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="p-6 bg-gray-50 border-t border-gray-200">
                    <div class="flex flex-wrap gap-4">
                        <a href="edit_invoice.php?id=<?php echo $invoice_id; ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center">
                            <i class="fas fa-edit mr-2"></i>
                            Edit Invoice
                        </a>
                        
                        <?php if (!$invoice['paid']): ?>
                            <a href="invoice_claim.php?id=<?php echo $invoice_id; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center">
                                <i class="fas fa-file-medical mr-2"></i>
                                Insurance Claim
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patient Information -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Patient Information</h2>
                </div>
                <div class="p-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-user text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <div class="text-lg font-medium text-gray-900">
                                <?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?>
                            </div>
                            <div class="text-sm text-gray-600">
                                ID: <?php echo htmlspecialchars($invoice['unique_patient_code']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4 mt-4">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Contact Information</h3>
                        <div class="space-y-3">
                            <?php if (!empty($invoice['phone'])): ?>
                                <div class="flex">
                                    <div class="w-1/4 flex-shrink-0">
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100">
                                            <i class="fas fa-phone text-blue-600"></i>
                                        </span>
                                    </div>
                                    <div class="w-3/4 flex items-center">
                                        <?php echo htmlspecialchars($invoice['phone']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($invoice['email'])): ?>
                                <div class="flex">
                                    <div class="w-1/4 flex-shrink-0">
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100">
                                            <i class="fas fa-envelope text-blue-600"></i>
                                        </span>
                                    </div>
                                    <div class="w-3/4 flex items-center">
                                        <?php echo htmlspecialchars($invoice['email']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4 mt-4">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Insurance</h3>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <?php if (!empty($invoice['insurance_info'])): ?>
                                <p class="text-gray-800"><?php echo htmlspecialchars($invoice['insurance_info']); ?></p>
                            <?php else: ?>
                                <p class="text-orange-600">Self-Pay (No Insurance)</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-6 mt-6">
                        <a href="../patients/patient_view.php?id=<?php echo $invoice['patient_id']; ?>" class="w-full block text-center bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-4 rounded-lg transition-colors">
                            View Patient Profile
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mt-8 bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-800">Payment Options</h2>
                </div>
                <div class="p-6 space-y-3">
                    <?php if (!$invoice['paid']): ?>
                        <button onclick="processPayment()" class="w-full block text-center bg-green-100 hover:bg-green-200 text-green-800 font-medium py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-credit-card mr-2"></i>
                            Process Payment
                        </button>
                    <?php else: ?>
                        <div class="w-full block text-center bg-green-100 text-green-800 font-medium py-2 px-4 rounded-lg">
                            <i class="fas fa-check-circle mr-2"></i>
                            Payment Received
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Version (Hidden) -->
<div id="printable-invoice" class="hidden">
    <div style="max-width: 800px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
            <div>
                <h1 style="margin: 0; font-size: 24px;">INVOICE</h1>
                <p style="margin: 5px 0; color: #666;">#<?php echo str_pad($invoice_id, 5, '0', STR_PAD_LEFT); ?></p>
            </div>
            <div style="text-align: right;">
                <h2 style="margin: 0; font-size: 20px;">Bayside Surgical Centre</h2>
                <p style="margin: 5px 0;">123 Healthcare Ave</p>
                <p style="margin: 5px 0;">Medical District, MD 12345</p>
                <p style="margin: 5px 0;">Tel: (555) 123-4567</p>
            </div>
        </div>

        <hr style="border: 1px solid #ddd; margin: 20px 0;">

        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
            <div>
                <h3 style="margin: 0; font-size: 16px;">Bill To:</h3>
                <p style="margin: 5px 0;"><strong><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></strong></p>
                <p style="margin: 5px 0;">ID: <?php echo htmlspecialchars($invoice['unique_patient_code']); ?></p>
                <?php if ($invoice['address']): ?>
                    <p style="margin: 5px 0;"><?php echo nl2br(htmlspecialchars($invoice['address'])); ?></p>
                <?php endif; ?>
            </div>
            <div style="text-align: right;">
                <h3 style="margin: 0; font-size: 16px;">Invoice Details:</h3>
                <p style="margin: 5px 0;">Date: <?php echo date('F j, Y', strtotime($invoice['invoice_date'])); ?></p>
                <p style="margin: 5px 0;">Status: <?php echo $invoice['paid'] ? 'Paid' : 'Unpaid'; ?></p>
            </div>
        </div>

        <?php if ($invoice['visit_id']): ?>
        <div style="background-color: #f0f7ff; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 10px 0; font-size: 16px;">Visit Information:</h3>
            <p style="margin: 5px 0;">Date: <?php echo date('F j, Y', strtotime($invoice['visit_datetime'])); ?></p>
            <p style="margin: 5px 0;">Time: <?php echo date('g:i A', strtotime($invoice['visit_datetime'])); ?></p>
            <p style="margin: 5px 0;">Doctor: <?php echo htmlspecialchars($invoice['doctor_name'] ?? 'N/A'); ?></p>
            <?php if (!empty($invoice['diagnosis'])): ?>
                <p style="margin: 10px 0 5px 0;"><strong>Diagnosis:</strong> <?php echo htmlspecialchars($invoice['diagnosis']); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr style="background-color: #f3f4f6;">
                    <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Service</th>
                    <th style="border: 1px solid #ddd; padding: 10px; text-align: right;">Quantity</th>
                    <th style="border: 1px solid #ddd; padding: 10px; text-align: right;">Unit Price</th>
                    <th style="border: 1px solid #ddd; padding: 10px; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoice_items as $item): ?>
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 10px;">
                            <strong><?php echo htmlspecialchars($item['service_name']); ?></strong>
                            <?php if (!empty($item['service_description'])): ?>
                                <br><span style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($item['service_description']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right;"><?php echo $item['quantity']; ?></td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">$<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">$<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #f3f4f6;">
                    <td colspan="3" style="border: 1px solid #ddd; padding: 10px; text-align: right; font-weight: bold;">Total</td>
                    <td style="border: 1px solid #ddd; padding: 10px; text-align: right; font-weight: bold;">$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <?php if (!empty($invoice['insurance_info'])): ?>
        <div style="background-color: #f3f4f6; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <h3 style="margin: 0 0 10px 0; font-size: 16px;">Insurance Information:</h3>
            <p style="margin: 5px 0;"><?php echo htmlspecialchars($invoice['insurance_info']); ?></p>
        </div>
        <?php endif; ?>

        <div style="text-align: right; margin-top: 40px;">
            <h3 style="margin: 0; font-size: 16px;">Amount Due:</h3>
            <p style="margin: 5px 0; font-size: 24px; font-weight: bold;">$<?php echo number_format($invoice['total_amount'], 2); ?></p>
        </div>

        <hr style="border: 1px solid #ddd; margin: 30px 0 20px;">
        
        <div style="text-align: center; font-size: 12px; color: #666;">
            <p>Thank you for choosing Bayside Surgical Centre.</p>
            <p>For any questions regarding this invoice, please contact our billing department at (555) 123-4567.</p>
        </div>
    </div>
</div>

<script>
function markAsPaid() {
    if (confirm('Mark this invoice as paid? This action cannot be undone.')) {
        window.location.href = `invoice_mark_paid.php?id=<?php echo $invoice_id; ?>`;
    }
}

function processPayment() {
    alert('In a real system, this would open a payment gateway.\n\nFor demo purposes, you can use the "Mark as Paid" button.');
}

function printInvoice() {
    const printContents = document.getElementById('printable-invoice').innerHTML;
    const originalContents = document.body.innerHTML;
    
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    
    // Reload page to restore functionality after print
    window.location.reload();
}

function sendStatement() {
    // In a real system, this would send an email with the invoice PDF
    // For demo, just show a confirmation
    alert('Statement would be sent to <?php echo htmlspecialchars($invoice['email'] ?: "patient\'s email"); ?>\n\nThis is a simulated feature for the demo.');
}

// Add staggered animation
document.addEventListener('DOMContentLoaded', function() {
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>
