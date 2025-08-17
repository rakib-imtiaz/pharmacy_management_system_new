<?php
require_once '../includes/db_connect.php';
session_start();

// Verify login status
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if visit ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: outpatient.php");
    exit;
}

$visit_id = $_GET['id'];

try {
    // Get visit details with patient and doctor info
    $sql = "SELECT ov.*, p.unique_patient_code, p.first_name, p.last_name, p.phone, p.email, p.insurance_info,
                  u.full_name AS doctor_name
           FROM outpatient_visits ov
           JOIN patients p ON ov.patient_id = p.patient_id
           JOIN users u ON ov.doctor_id = u.user_id
           WHERE ov.visit_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        header("Location: outpatient.php");
        exit;
    }
    
    // Check if there's an invoice for this visit
    $invoice_sql = "SELECT invoice_id, total_amount, paid FROM invoices WHERE visit_id = ? LIMIT 1";
    $invoice_stmt = $pdo->prepare($invoice_sql);
    $invoice_stmt->execute([$visit_id]);
    $invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get patient's previous visits (excluding current)
    $prev_visit_sql = "SELECT ov.*, u.full_name AS doctor_name 
                       FROM outpatient_visits ov 
                       JOIN users u ON ov.doctor_id = u.user_id
                       WHERE ov.patient_id = ? AND ov.visit_id != ? 
                       ORDER BY ov.visit_datetime DESC LIMIT 3";
    $prev_stmt = $pdo->prepare($prev_visit_sql);
    $prev_stmt->execute([$visit['patient_id'], $visit_id]);
    $previous_visits = $prev_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Visit View Error: " . $e->getMessage());
    header("Location: outpatient.php");
    exit;
}

include_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 fade-in">
        <div class="flex items-center mb-4">
            <a href="outpatient.php" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Visit Record</h1>
        </div>
        <p class="text-gray-600">View detailed patient visit information</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Visit Details Card -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-800">Visit Information</h2>
                        <span class="text-sm text-gray-500">
                            Record #<?php echo $visit_id; ?>
                        </span>
                    </div>
                </div>

                <div class="p-6">
                    <div class="flex items-center mb-8">
                        <div class="bg-blue-100 p-4 rounded-full mr-6">
                            <i class="fas fa-notes-medical text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900">
                                <?php echo date('l, F j, Y', strtotime($visit['visit_datetime'])); ?>
                            </div>
                            <div class="text-xl font-medium text-gray-700">
                                <?php echo date('g:i A', strtotime($visit['visit_datetime'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <!-- Doctor Information -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Attending Doctor</h3>
                            <div class="flex items-center">
                                <div class="bg-green-100 p-3 rounded-full mr-4">
                                    <i class="fas fa-user-md text-green-600"></i>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">
                                        Dr. <?php echo htmlspecialchars($visit['doctor_name']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Visit Timestamp -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Record Details</h3>
                            <div class="space-y-2">
                                <div class="flex">
                                    <div class="w-1/2 text-gray-600">Created:</div>
                                    <div class="w-1/2">
                                        <?php echo date('M j, Y g:i A', strtotime($visit['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Medical Information -->
                    <div class="border-t border-gray-200 pt-6 mt-6 space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">Diagnosis</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <?php if (!empty($visit['diagnosis'])): ?>
                                    <p class="text-gray-800 whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($visit['diagnosis'])); ?></p>
                                <?php else: ?>
                                    <p class="text-gray-500 italic">No diagnosis recorded</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($visit['lab_requests'])): ?>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">Lab Requests</h3>
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <p class="text-gray-800 whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($visit['lab_requests'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($visit['prescription'])): ?>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">Prescription</h3>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <p class="text-gray-800 whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($visit['prescription'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions Buttons -->
                    <div class="border-t border-gray-200 pt-6 mt-6 flex flex-col sm:flex-row gap-4">
                        <a href="visit_edit.php?id=<?php echo $visit_id; ?>" 
                           class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center">
                            <i class="fas fa-edit mr-2"></i>
                            Edit Visit Record
                        </a>
                        
                        <?php if ($invoice): ?>
                            <a href="../invoices/view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" 
                               class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center">
                                <i class="fas fa-file-invoice-dollar mr-2"></i>
                                View Invoice
                            </a>
                        <?php else: ?>
                            <a href="../invoices/add_invoice.php?visit_id=<?php echo $visit_id; ?>" 
                               class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center">
                                <i class="fas fa-file-invoice-dollar mr-2"></i>
                                Create Invoice
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($previous_visits)): ?>
            <!-- Previous Visits -->
            <div class="mt-8 bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Previous Visits</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach($previous_visits as $prev): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex justify-between mb-2">
                                    <div>
                                        <span class="text-gray-900 font-medium">
                                            <?php echo date('F j, Y', strtotime($prev['visit_datetime'])); ?>
                                        </span>
                                        <span class="text-gray-600 ml-2">
                                            <?php echo date('g:i A', strtotime($prev['visit_datetime'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-600">
                                    Dr. <?php echo htmlspecialchars($prev['doctor_name']); ?>
                                </div>
                                <?php if (!empty($prev['diagnosis'])): ?>
                                    <div class="mt-2 text-sm">
                                        <span class="font-medium">Diagnosis:</span> 
                                        <?php echo htmlspecialchars(substr($prev['diagnosis'], 0, 100)) . (strlen($prev['diagnosis']) > 100 ? '...' : ''); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-2 text-right">
                                    <a href="visit_view.php?id=<?php echo $prev['visit_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($previous_visits) >= 3): ?>
                        <div class="mt-4 text-center">
                            <a href="outpatient.php?search=<?php echo $visit['unique_patient_code']; ?>" class="text-blue-600 hover:text-blue-800">
                                View All Visit Records
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
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
                                <?php echo htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']); ?>
                            </div>
                            <div class="text-sm text-gray-600">
                                ID: <?php echo htmlspecialchars($visit['unique_patient_code']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4 mt-4">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Contact Information</h3>
                        <div class="space-y-3">
                            <?php if (!empty($visit['phone'])): ?>
                                <div class="flex">
                                    <div class="w-1/4 flex-shrink-0">
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100">
                                            <i class="fas fa-phone text-blue-600"></i>
                                        </span>
                                    </div>
                                    <div class="w-3/4 flex items-center">
                                        <?php echo htmlspecialchars($visit['phone']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($visit['email'])): ?>
                                <div class="flex">
                                    <div class="w-1/4 flex-shrink-0">
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100">
                                            <i class="fas fa-envelope text-blue-600"></i>
                                        </span>
                                    </div>
                                    <div class="w-3/4 flex items-center">
                                        <?php echo htmlspecialchars($visit['email']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4 mt-4">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Insurance</h3>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <?php if (!empty($visit['insurance_info'])): ?>
                                <p class="text-gray-800"><?php echo htmlspecialchars($visit['insurance_info']); ?></p>
                            <?php else: ?>
                                <p class="text-orange-600">Self-Pay (No Insurance)</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-6 mt-6">
                        <a href="../patients/patient_view.php?id=<?php echo $visit['patient_id']; ?>" class="w-full block text-center bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-4 rounded-lg transition-colors">
                            View Patient Profile
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($invoice): ?>
            <!-- Invoice Information -->
            <div class="mt-8 bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-800">Billing Information</h2>
                </div>
                <div class="p-6">
                    <div class="flex justify-between mb-4">
                        <span class="text-gray-600">Invoice #<?php echo $invoice['invoice_id']; ?></span>
                        <span class="text-2xl font-bold text-gray-900">$<?php echo number_format($invoice['total_amount'], 2); ?></span>
                    </div>
                    <div class="mb-6">
                        <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold <?php echo $invoice['paid'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                            <?php echo $invoice['paid'] ? 'Paid' : 'Unpaid'; ?>
                        </span>
                    </div>
                    <a href="../invoices/view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="w-full block text-center bg-green-100 hover:bg-green-200 text-green-800 font-medium py-2 px-4 rounded-lg transition-colors">
                        View Full Invoice
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="mt-8 bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-800">Quick Actions</h2>
                </div>
                <div class="p-6 space-y-3">
                    <?php if (!$invoice): ?>
                    <a href="../invoices/add_invoice.php?visit_id=<?php echo $visit_id; ?>" class="w-full block text-center bg-green-100 hover:bg-green-200 text-green-800 font-medium py-2 px-4 rounded-lg transition-colors">
                        <i class="fas fa-file-invoice-dollar mr-2"></i>
                        Generate Invoice
                    </a>
                    <?php endif; ?>
                    
                    <a href="../prescriptions/create_new_prescription.php?patient_id=<?php echo $visit['patient_id']; ?>" class="w-full block text-center bg-blue-100 hover:bg-blue-200 text-blue-800 font-medium py-2 px-4 rounded-lg transition-colors">
                        <i class="fas fa-prescription mr-2"></i>
                        Create Prescription
                    </a>
                    
                    <a href="visit_add.php?patient_id=<?php echo $visit['patient_id']; ?>" class="w-full block text-center bg-purple-100 hover:bg-purple-200 text-purple-800 font-medium py-2 px-4 rounded-lg transition-colors">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Record New Visit
                    </a>
                </div>
            </div>
        </div>
    </div>
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