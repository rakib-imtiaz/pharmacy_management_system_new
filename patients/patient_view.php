<?php
require_once '../includes/db_connect.php';
session_start();

// Verify login status
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if patient ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: patients.php");
    exit;
}

$patient_id = $_GET['id'];

try {
    // Get patient details
    $sql = "SELECT * FROM patients WHERE patient_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        header("Location: patients.php");
        exit;
    }

    // Get patient's appointments
    $appt_sql = "SELECT a.*, u.full_name as doctor_name 
                 FROM appointments a 
                 JOIN users u ON a.doctor_id = u.user_id
                 WHERE a.patient_id = ? 
                 ORDER BY a.appointment_datetime DESC 
                 LIMIT 5";
    $appt_stmt = $pdo->prepare($appt_sql);
    $appt_stmt->execute([$patient_id]);
    $appointments = $appt_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get patient's visits
    $visit_sql = "SELECT ov.*, u.full_name as doctor_name 
                  FROM outpatient_visits ov 
                  JOIN users u ON ov.doctor_id = u.user_id
                  WHERE ov.patient_id = ? 
                  ORDER BY ov.visit_datetime DESC 
                  LIMIT 5";
    $visit_stmt = $pdo->prepare($visit_sql);
    $visit_stmt->execute([$patient_id]);
    $visits = $visit_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get patient's invoices
    $invoice_sql = "SELECT i.* 
                    FROM invoices i 
                    WHERE i.patient_id = ? 
                    ORDER BY i.invoice_date DESC 
                    LIMIT 5";
    $invoice_stmt = $pdo->prepare($invoice_sql);
    $invoice_stmt->execute([$patient_id]);
    $invoices = $invoice_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Patient View Error: " . $e->getMessage());
    header("Location: patients.php");
    exit;
}

include_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 fade-in">
        <div class="flex items-center mb-4">
            <a href="patients.php" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Patient Details</h1>
        </div>
        <p class="text-gray-600">View comprehensive patient information and medical history</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Patient Information Card -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-bold text-gray-800">Patient Information</h2>
                        <a href="patient_edit.php?id=<?php echo $patient_id; ?>" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Patient ID and Basic Info -->
                    <div class="flex items-center mb-6">
                        <div class="bg-blue-100 p-4 rounded-full mr-4">
                            <i class="fas fa-user-circle text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                            </h3>
                            <p class="text-gray-600">
                                ID: <?php echo htmlspecialchars($patient['unique_patient_code']); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Demographics -->
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Demographics</h4>
                        <div class="space-y-3">
                            <div class="flex">
                                <div class="w-1/3 text-gray-600">Date of Birth:</div>
                                <div class="w-2/3 font-medium">
                                    <?php echo $patient['dob'] ? date('F j, Y', strtotime($patient['dob'])) : 'Not recorded'; ?>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="w-1/3 text-gray-600">Gender:</div>
                                <div class="w-2/3 font-medium">
                                    <?php echo htmlspecialchars($patient['gender'] ?: 'Not specified'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Contact Information</h4>
                        <div class="space-y-3">
                            <div class="flex">
                                <div class="w-1/3 text-gray-600">Phone:</div>
                                <div class="w-2/3 font-medium">
                                    <?php echo htmlspecialchars($patient['phone'] ?: 'Not recorded'); ?>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="w-1/3 text-gray-600">Email:</div>
                                <div class="w-2/3 font-medium">
                                    <?php echo htmlspecialchars($patient['email'] ?: 'Not recorded'); ?>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="w-1/3 text-gray-600">Address:</div>
                                <div class="w-2/3 font-medium">
                                    <?php echo nl2br(htmlspecialchars($patient['address'] ?: 'Not recorded')); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Insurance Information -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Insurance Information</h4>
                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                            <div class="flex items-center mb-2">
                                <div class="mr-2">
                                    <i class="fas fa-id-card text-gray-600"></i>
                                </div>
                                <div class="font-medium">
                                    <?php if ($patient['insurance_info']): ?>
                                        <?php echo htmlspecialchars($patient['insurance_info']); ?>
                                    <?php else: ?>
                                        <span class="text-orange-600">Self-Pay (No Insurance)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registration Info -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mt-6 fade-in">
                <div class="p-4 bg-gray-50">
                    <p class="text-sm text-gray-600">
                        <i class="far fa-calendar-alt text-gray-500 mr-1"></i>
                        Registered on <?php echo date('F j, Y', strtotime($patient['created_at'])); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Medical History Tabs -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="border-b border-gray-200">
                    <nav class="flex" id="tabs">
                        <button class="tab-btn px-6 py-4 text-center text-gray-600 hover:text-blue-600 border-b-2 border-transparent active" data-target="visits">
                            <i class="fas fa-notes-medical mr-2"></i>
                            Clinical Visits
                        </button>
                        <button class="tab-btn px-6 py-4 text-center text-gray-600 hover:text-blue-600 border-b-2 border-transparent" data-target="appointments">
                            <i class="fas fa-calendar-check mr-2"></i>
                            Appointments
                        </button>
                        <button class="tab-btn px-6 py-4 text-center text-gray-600 hover:text-blue-600 border-b-2 border-transparent" data-target="billing">
                            <i class="fas fa-file-invoice-dollar mr-2"></i>
                            Billing
                        </button>
                    </nav>
                </div>

                <!-- Visits Tab -->
                <div id="visits-content" class="tab-content p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-800">Recent Clinical Visits</h3>
                        <a href="../outpatient/visit_add.php?patient_id=<?php echo $patient_id; ?>" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus mr-1"></i> Record New Visit
                        </a>
                    </div>

                    <?php if (empty($visits)): ?>
                        <div class="text-center py-8 border border-dashed border-gray-300 rounded-lg">
                            <i class="fas fa-notes-medical text-gray-400 text-4xl mb-3"></i>
                            <p class="text-gray-600 mb-2">No clinical visits recorded yet</p>
                            <a href="../outpatient/visit_add.php?patient_id=<?php echo $patient_id; ?>" class="text-blue-600 hover:text-blue-800">
                                Record first visit
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($visits as $visit): ?>
                                <div class="border border-gray-200 rounded-lg hover:bg-blue-50 transition-colors p-4">
                                    <div class="flex justify-between">
                                        <div>
                                            <div class="font-medium text-blue-900">
                                                <?php echo date('F j, Y', strtotime($visit['visit_datetime'])); ?> at <?php echo date('g:i A', strtotime($visit['visit_datetime'])); ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                Dr. <?php echo htmlspecialchars($visit['doctor_name']); ?>
                                            </div>
                                        </div>
                                        <a href="../outpatient/visit_view.php?id=<?php echo $visit['visit_id']; ?>" class="text-blue-600">
                                            View Details
                                        </a>
                                    </div>
                                    <div class="mt-2">
                                        <div class="text-sm">
                                            <span class="font-medium">Diagnosis:</span> 
                                            <?php echo htmlspecialchars($visit['diagnosis'] ?: 'Not recorded'); ?>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex space-x-3">
                                        <?php if ($visit['lab_requests']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-flask mr-1"></i> Lab Tests
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($visit['prescription']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-prescription mr-1"></i> Prescription
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($visits) >= 5): ?>
                                <div class="text-center pt-2">
                                    <a href="../outpatient/outpatient.php?search=<?php echo $patient['unique_patient_code']; ?>" class="text-blue-600 hover:text-blue-800">
                                        View All Visit Records
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Appointments Tab -->
                <div id="appointments-content" class="tab-content p-6 hidden">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-800">Appointments</h3>
                        <a href="../appointments/appointment_add.php?patient_id=<?php echo $patient_id; ?>" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus mr-1"></i> Schedule New
                        </a>
                    </div>

                    <?php if (empty($appointments)): ?>
                        <div class="text-center py-8 border border-dashed border-gray-300 rounded-lg">
                            <i class="fas fa-calendar-alt text-gray-400 text-4xl mb-3"></i>
                            <p class="text-gray-600 mb-2">No appointments scheduled</p>
                            <a href="../appointments/appointment_add.php?patient_id=<?php echo $patient_id; ?>" class="text-blue-600 hover:text-blue-800">
                                Schedule appointment
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($appointments as $appointment): ?>
                                <div class="border border-gray-200 rounded-lg hover:bg-blue-50 transition-colors p-4">
                                    <div class="flex justify-between">
                                        <div>
                                            <div class="font-medium text-blue-900">
                                                <?php echo date('F j, Y', strtotime($appointment['appointment_datetime'])); ?> at <?php echo date('g:i A', strtotime($appointment['appointment_datetime'])); ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                            </div>
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php echo $appointment['status'] === 'Completed' ? 'bg-green-100 text-green-800' : ($appointment['status'] === 'Cancelled' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'); ?>">
                                            <?php echo htmlspecialchars($appointment['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($appointments) >= 5): ?>
                                <div class="text-center pt-2">
                                    <a href="../appointments/appointments.php?search=<?php echo $patient['unique_patient_code']; ?>" class="text-blue-600 hover:text-blue-800">
                                        View All Appointments
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Billing Tab -->
                <div id="billing-content" class="tab-content p-6 hidden">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-gray-800">Billing & Invoices</h3>
                        <a href="../invoices/add_invoice.php?patient_id=<?php echo $patient_id; ?>" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus mr-1"></i> Create New Invoice
                        </a>
                    </div>

                    <?php if (empty($invoices)): ?>
                        <div class="text-center py-8 border border-dashed border-gray-300 rounded-lg">
                            <i class="fas fa-file-invoice-dollar text-gray-400 text-4xl mb-3"></i>
                            <p class="text-gray-600 mb-2">No billing records found</p>
                            <a href="../invoices/add_invoice.php?patient_id=<?php echo $patient_id; ?>" class="text-blue-600 hover:text-blue-800">
                                Create first invoice
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($invoices as $invoice): ?>
                                <div class="border border-gray-200 rounded-lg hover:bg-blue-50 transition-colors p-4">
                                    <div class="flex justify-between">
                                        <div>
                                            <div class="font-medium text-blue-900">
                                                Invoice #<?php echo htmlspecialchars($invoice['invoice_id']); ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                Date: <?php echo date('F j, Y', strtotime($invoice['invoice_date'])); ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-medium">
                                                $<?php echo number_format($invoice['total_amount'], 2); ?>
                                            </div>
                                            <div>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?php echo $invoice['paid'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                    <?php echo $invoice['paid'] ? 'Paid' : 'Unpaid'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-right">
                                        <a href="../invoices/view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>" class="text-blue-600">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($invoices) >= 5): ?>
                                <div class="text-center pt-2">
                                    <a href="../invoices/invoices.php?search=<?php echo $patient['unique_patient_code']; ?>" class="text-blue-600 hover:text-blue-800">
                                        View All Invoices
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Remove active class from all buttons
            tabButtons.forEach(btn => {
                btn.classList.remove('active');
                btn.classList.remove('text-blue-600');
                btn.classList.remove('border-blue-600');
                btn.classList.add('text-gray-600');
                btn.classList.add('border-transparent');
            });
            
            // Add active class to clicked button
            button.classList.add('active');
            button.classList.add('text-blue-600');
            button.classList.add('border-blue-600');
            button.classList.remove('text-gray-600');
            button.classList.remove('border-transparent');
            
            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Show the selected tab content
            const targetId = button.getAttribute('data-target') + '-content';
            document.getElementById(targetId).classList.remove('hidden');
        });
    });
});

// Add staggered animation
document.addEventListener('DOMContentLoaded', function() {
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
    });
});
</script>

<?php include_once '../includes/footer.php'; ?> 