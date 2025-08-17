<?php
require_once '../includes/db_connect.php';
session_start();

// Verify login status
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$errors = [];
$claim_submitted = false;
$success_message = '';

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: invoices.php");
    exit;
}

$invoice_id = $_GET['id'];

try {
    // Get invoice details with patient info
    $sql = "SELECT i.*, p.unique_patient_code, p.first_name, p.last_name, p.phone, p.email, p.dob, p.address, p.insurance_info,
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
    
    // Check if patient has insurance
    if (empty($invoice['insurance_info'])) {
        $errors[] = "This patient does not have insurance information on record.";
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
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $insurance_provider = isset($_POST['insurance_provider']) ? trim($_POST['insurance_provider']) : '';
        $policy_number = isset($_POST['policy_number']) ? trim($_POST['policy_number']) : '';
        $claim_notes = isset($_POST['claim_notes']) ? trim($_POST['claim_notes']) : '';
        
        // Validation
        if (empty($insurance_provider)) {
            $errors[] = "Insurance provider is required";
        }
        
        if (empty($policy_number)) {
            $errors[] = "Policy number is required";
        }
        
        // If no errors, process the claim
        if (empty($errors)) {
            // In a real system, this would submit the claim to an insurance API
            // For the demo, we'll simulate a successful submission
            
            // Log the action
            $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
            $audit_stmt = $pdo->prepare($audit_sql);
            $audit_stmt->execute([
                $_SESSION['user_id'],
                'Submit Insurance Claim',
                'invoices',
                $invoice_id,
                "Submitted insurance claim for invoice #$invoice_id to provider: $insurance_provider, Policy: $policy_number"
            ]);
            
            $claim_submitted = true;
            $success_message = "Insurance claim has been submitted successfully. Claim Reference: CL" . date('Ymd') . $invoice_id;
        }
    }
    
    // Extract insurance info if available
    $insurance_details = [
        'provider' => '',
        'policy_number' => ''
    ];
    
    if (!empty($invoice['insurance_info'])) {
        // Try to parse the insurance info - format could be "Provider - Policy #12345"
        $parts = explode('-', $invoice['insurance_info']);
        if (count($parts) > 1) {
            $insurance_details['provider'] = trim($parts[0]);
            $policy_parts = explode('#', $parts[1]);
            if (count($policy_parts) > 1) {
                $insurance_details['policy_number'] = trim($policy_parts[1]);
            }
        }
    }
    
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    error_log("Insurance Claim Error: " . $e->getMessage());
}

include_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 fade-in">
        <div class="flex items-center mb-4">
            <a href="view_invoice.php?id=<?php echo $invoice_id; ?>" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Submit Insurance Claim</h1>
        </div>
        <p class="text-gray-600">Process an insurance claim for Invoice #<?php echo str_pad($invoice_id, 5, '0', STR_PAD_LEFT); ?></p>
    </div>

    <?php if ($claim_submitted): ?>
        <!-- Success Message -->
        <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-8 rounded-lg mb-8 text-center fade-in">
            <i class="fas fa-check-circle text-5xl mb-4 text-green-500"></i>
            <h2 class="text-2xl font-bold mb-2">Claim Submitted Successfully</h2>
            <p class="mb-4"><?php echo htmlspecialchars($success_message); ?></p>
            <div class="flex justify-center gap-4 mt-6">
                <a href="view_invoice.php?id=<?php echo $invoice_id; ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-300">
                    Return to Invoice
                </a>
                <a href="invoices.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-300">
                    All Invoices
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 fade-in">
                <div class="flex items-center mb-2">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span class="font-medium">Please correct the following errors:</span>
                </div>
                <ul class="list-disc list-inside ml-4">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Claim Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-bold text-gray-800">Claim Information</h2>
                        <p class="text-sm text-gray-600">Fill in the insurance claim details</p>
                    </div>

                    <div class="p-6">
                        <form method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="insurance_provider" class="block text-sm font-medium text-gray-700 mb-2">
                                        Insurance Provider <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="insurance_provider" 
                                        name="insurance_provider" 
                                        value="<?php echo htmlspecialchars($insurance_details['provider']); ?>"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <label for="policy_number" class="block text-sm font-medium text-gray-700 mb-2">
                                        Policy Number <span class="text-red-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="policy_number" 
                                        name="policy_number" 
                                        value="<?php echo htmlspecialchars($insurance_details['policy_number']); ?>"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        required
                                    >
                                </div>
                            </div>
                            
                            <div>
                                <label for="claim_notes" class="block text-sm font-medium text-gray-700 mb-2">
                                    Additional Notes for Claim
                                </label>
                                <textarea 
                                    id="claim_notes" 
                                    name="claim_notes" 
                                    rows="3"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Enter any additional notes for the insurance claim..."
                                ></textarea>
                            </div>
                            
                            <!-- Services Summary -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Services to Claim</h3>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <table class="w-full">
                                        <thead>
                                            <tr class="border-b border-gray-300">
                                                <th class="px-4 py-2 text-left">Service</th>
                                                <th class="px-4 py-2 text-right">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($invoice_items as $item): ?>
                                                <tr class="border-b border-gray-200">
                                                    <td class="px-4 py-2">
                                                        <?php echo htmlspecialchars($item['service_name']); ?>
                                                        <?php if (!empty($item['service_description'])): ?>
                                                            <span class="text-xs text-gray-500 block"><?php echo htmlspecialchars($item['service_description']); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2 text-right">
                                                        $<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="font-bold">
                                                <td class="px-4 py-2 text-right">Total Amount:</td>
                                                <td class="px-4 py-2 text-right">$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex flex-col sm:flex-row gap-4 pt-6">
                                <button 
                                    type="submit" 
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center"
                                >
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Submit Insurance Claim
                                </button>
                                <a 
                                    href="view_invoice.php?id=<?php echo $invoice_id; ?>" 
                                    class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center"
                                >
                                    <i class="fas fa-times mr-2"></i>
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Patient & Invoice Information -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-bold text-gray-800">Patient Information</h2>
                    </div>
                    <div class="p-6">
                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></p>
                        <p class="text-sm text-gray-600">ID: <?php echo htmlspecialchars($invoice['unique_patient_code']); ?></p>
                        
                        <?php if ($invoice['dob']): ?>
                            <p class="text-sm text-gray-600 mt-2">DOB: <?php echo date('F j, Y', strtotime($invoice['dob'])); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($invoice['phone']): ?>
                            <p class="text-sm text-gray-600 mt-1">
                                <i class="fas fa-phone mr-1"></i> <?php echo htmlspecialchars($invoice['phone']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($invoice['address']): ?>
                            <div class="mt-4">
                                <h3 class="text-sm font-medium text-gray-700">Address:</h3>
                                <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($invoice['address'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg overflow-hidden mt-6 fade-in">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-bold text-gray-800">Invoice Summary</h2>
                    </div>
                    <div class="p-6">
                        <p class="font-medium">Invoice #<?php echo str_pad($invoice_id, 5, '0', STR_PAD_LEFT); ?></p>
                        <p class="text-sm text-gray-600 mt-1">Date: <?php echo date('F j, Y', strtotime($invoice['invoice_date'])); ?></p>
                        <p class="text-sm text-gray-600 mt-1">Amount: $<?php echo number_format($invoice['total_amount'], 2); ?></p>
                        
                        <?php if ($invoice['visit_id']): ?>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <h3 class="text-sm font-medium text-gray-700">Related Visit:</h3>
                                <p class="text-sm text-gray-600">Date: <?php echo date('F j, Y', strtotime($invoice['visit_datetime'])); ?></p>
                                <p class="text-sm text-gray-600">Doctor: <?php echo htmlspecialchars($invoice['doctor_name'] ?? 'N/A'); ?></p>
                                
                                <?php if (!empty($invoice['diagnosis'])): ?>
                                    <div class="mt-2">
                                        <h3 class="text-sm font-medium text-gray-700">Diagnosis:</h3>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($invoice['diagnosis']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Insurance Information -->
                <?php if (!empty($invoice['insurance_info'])): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden mt-6 fade-in">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-bold text-gray-800">Insurance Information</h2>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-800"><?php echo htmlspecialchars($invoice['insurance_info']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Add staggered animation
document.addEventListener('DOMContentLoaded', function() {
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
    });
});
</script>

<?php include_once '../includes/footer.php'; ?> 