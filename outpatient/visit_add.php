<?php
require_once '../includes/db_connect.php';
session_start();

// Verify login status
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$errors = [];
$success_message = '';
$selected_patient = null;
$selected_doctor = null;
$doctors = [];
$default_date = date('Y-m-d');
$default_time = date('H:i');

// Get doctors for dropdown
try {
    $doctors_query = "SELECT user_id, full_name FROM users WHERE role_id = 2 ORDER BY full_name";
    $stmt = $pdo->query($doctors_query);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Doctor Query Error: " . $e->getMessage());
}

// Check if patient ID is provided
if (isset($_GET['patient_id']) && is_numeric($_GET['patient_id'])) {
    try {
        $patient_id = $_GET['patient_id'];
        $sql = "SELECT patient_id, unique_patient_code, first_name, last_name FROM patients WHERE patient_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$patient_id]);
        $selected_patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Patient Query Error: " . $e->getMessage());
    }
}

// Check if doctor ID is provided
if (isset($_GET['doctor_id']) && is_numeric($_GET['doctor_id'])) {
    try {
        $doctor_id = $_GET['doctor_id'];
        $sql = "SELECT user_id, full_name FROM users WHERE user_id = ? AND role_id = 2";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$doctor_id]);
        $selected_doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Doctor Query Error: " . $e->getMessage());
    }
}

// Check if date is provided (useful when coming from appointment)
if (isset($_GET['date']) && !empty($_GET['date'])) {
    $default_date = $_GET['date'];
}

// Process patient search
$search_results = [];
$search_query = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
    try {
        $search_sql = "SELECT patient_id, unique_patient_code, first_name, last_name, phone 
                       FROM patients 
                       WHERE unique_patient_code LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?
                       ORDER BY last_name, first_name
                       LIMIT 10";
        $stmt = $pdo->prepare($search_sql);
        $search_param = "%$search_query%";
        $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Patient Search Error: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = isset($_POST['patient_id']) ? trim($_POST['patient_id']) : '';
    $doctor_id = isset($_POST['doctor_id']) ? trim($_POST['doctor_id']) : '';
    $visit_date = isset($_POST['visit_date']) ? trim($_POST['visit_date']) : '';
    $visit_time = isset($_POST['visit_time']) ? trim($_POST['visit_time']) : '';
    $diagnosis = isset($_POST['diagnosis']) ? trim($_POST['diagnosis']) : '';
    $lab_requests = isset($_POST['lab_requests']) ? trim($_POST['lab_requests']) : '';
    $prescription = isset($_POST['prescription']) ? trim($_POST['prescription']) : '';

    // Validation
    if (empty($patient_id)) {
        $errors[] = "Patient is required";
    }

    if (empty($doctor_id)) {
        $errors[] = "Doctor is required";
    }

    if (empty($visit_date)) {
        $errors[] = "Visit date is required";
    }

    if (empty($visit_time)) {
        $errors[] = "Visit time is required";
    }

    // Create visit datetime
    $visit_datetime = $visit_date . ' ' . $visit_time . ':00';

    // If no errors, proceed with saving visit
    if (empty($errors)) {
        try {
            // Insert visit
            $sql = "INSERT INTO outpatient_visits (patient_id, doctor_id, visit_datetime, diagnosis, lab_requests, prescription) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $patient_id,
                $doctor_id,
                $visit_datetime,
                $diagnosis,
                $lab_requests,
                $prescription
            ]);

            $visit_id = $pdo->lastInsertId();

            // Check if this visit corresponds to an appointment and update the appointment status if needed
            $check_appointment = "SELECT appointment_id FROM appointments 
                                 WHERE patient_id = ? AND doctor_id = ? 
                                 AND DATE(appointment_datetime) = ? 
                                 AND status = 'Scheduled'";
            $appt_stmt = $pdo->prepare($check_appointment);
            $appt_stmt->execute([$patient_id, $doctor_id, $visit_date]);
            $appointment = $appt_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($appointment) {
                $update_appt = "UPDATE appointments SET status = 'Completed' WHERE appointment_id = ?";
                $update_stmt = $pdo->prepare($update_appt);
                $update_stmt->execute([$appointment['appointment_id']]);
            }

            // Log the action
            $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
            $audit_stmt = $pdo->prepare($audit_sql);
            $audit_stmt->execute([
                $_SESSION['user_id'],
                'Create Visit',
                'outpatient_visits',
                $visit_id,
                "Recorded visit for patient_id=$patient_id with doctor_id=$doctor_id"
            ]);

            $success_message = "Patient visit recorded successfully!";
            
            // Get patient and doctor details for confirmation message
            $patient_sql = "SELECT first_name, last_name FROM patients WHERE patient_id = ?";
            $patient_stmt = $pdo->prepare($patient_sql);
            $patient_stmt->execute([$patient_id]);
            $patient_details = $patient_stmt->fetch(PDO::FETCH_ASSOC);
            
            $doctor_sql = "SELECT full_name FROM users WHERE user_id = ?";
            $doctor_stmt = $pdo->prepare($doctor_sql);
            $doctor_stmt->execute([$doctor_id]);
            $doctor_details = $doctor_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($patient_details && $doctor_details) {
                $visit_date_formatted = date('F j, Y', strtotime($visit_date));
                
                $success_message = "Visit record created successfully for {$patient_details['first_name']} {$patient_details['last_name']} with Dr. {$doctor_details['full_name']} on $visit_date_formatted.";
            }
            
            // Clear form data
            $selected_patient = null;
            $selected_doctor = null;
            $visit_date = '';
            $visit_time = '';
            $diagnosis = '';
            $lab_requests = '';
            $prescription = '';

        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Visit Creation Error: " . $e->getMessage());
        }
    }
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
            <h1 class="text-3xl font-bold text-gray-800">Record Patient Visit</h1>
        </div>
        <p class="text-gray-600">Document patient consultation and medical details</p>
    </div>

    <!-- Success Message -->
    <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 fade-in">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        </div>
    <?php endif; ?>

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
        <!-- Patient Selection -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Select Patient</h2>
                    <p class="text-sm text-gray-600">Search for an existing patient</p>
                </div>

                <div class="p-6">
                    <?php if ($selected_patient): ?>
                        <!-- Selected Patient Display -->
                        <div class="mb-4">
                            <p class="text-sm text-gray-500 mb-1">Selected Patient:</p>
                            <div class="flex items-center p-4 border border-green-200 rounded-lg bg-green-50">
                                <div class="bg-green-100 p-2 rounded-full mr-3">
                                    <i class="fas fa-user-check text-green-600"></i>
                                </div>
                                <div class="flex-grow">
                                    <div class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($selected_patient['first_name'] . ' ' . $selected_patient['last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        ID: <?php echo htmlspecialchars($selected_patient['unique_patient_code']); ?>
                                    </div>
                                </div>
                                <a href="visit_add.php" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Patient Search -->
                        <form action="visit_add.php" method="GET" class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Search by Name, ID, or Phone
                            </label>
                            <div class="flex">
                                <input 
                                    type="text" 
                                    name="search" 
                                    value="<?php echo htmlspecialchars($search_query); ?>"
                                    class="flex-grow px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Enter name, ID, or phone number"
                                >
                                <button 
                                    type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r-lg transition-colors duration-300"
                                >
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <?php if (!empty($_GET) && !isset($_GET['patient_id'])): ?>
                                <div class="mt-2">
                                    <a href="visit_add.php" class="text-sm text-blue-600 hover:text-blue-800">
                                        Clear search
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>

                        <!-- Search Results -->
                        <?php if (!empty($search_results)): ?>
                            <div class="mt-4">
                                <h3 class="text-sm font-medium text-gray-700 mb-2">Search Results:</h3>
                                <div class="space-y-2">
                                    <?php foreach ($search_results as $patient): ?>
                                        <a 
                                            href="visit_add.php?patient_id=<?php echo $patient['patient_id']; ?><?php echo isset($_GET['doctor_id']) ? '&doctor_id=' . $_GET['doctor_id'] : ''; ?>" 
                                            class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-blue-50 transition-colors"
                                        >
                                            <div class="bg-blue-100 p-2 rounded-full mr-3">
                                                <i class="fas fa-user text-blue-600"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-600">
                                                    ID: <?php echo htmlspecialchars($patient['unique_patient_code']); ?>
                                                    <?php if (!empty($patient['phone'])): ?>
                                                        <span class="mx-1">|</span> 
                                                        <i class="fas fa-phone mr-1"></i> <?php echo htmlspecialchars($patient['phone']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php elseif (isset($_GET['search'])): ?>
                            <div class="mt-4 text-center py-4 border border-dashed border-gray-300 rounded-lg">
                                <i class="fas fa-user-slash text-gray-400 text-4xl mb-2"></i>
                                <p class="text-gray-600 mb-2">No patients found</p>
                                <a href="../patients/patient_add.php" class="text-blue-600 hover:text-blue-800">
                                    Register new patient
                                </a>
                            </div>
                        <?php endif; ?>

                        <!-- Register New Patient Link -->
                        <div class="mt-6 text-center">
                            <a 
                                href="../patients/patient_add.php" 
                                class="inline-flex items-center text-blue-600 hover:text-blue-800"
                            >
                                <i class="fas fa-user-plus mr-2"></i>
                                Register New Patient
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Visit Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Visit Details</h2>
                    <p class="text-sm text-gray-600">Record consultation information</p>
                </div>

                <div class="p-6">
                    <form method="POST" class="space-y-6">
                        <!-- Hidden Patient ID field -->
                        <?php if ($selected_patient): ?>
                            <input type="hidden" name="patient_id" value="<?php echo $selected_patient['patient_id']; ?>">
                        <?php endif; ?>
                        
                        <!-- Doctor Selection -->
                        <div>
                            <label for="doctor_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Doctor <span class="text-red-500">*</span>
                            </label>
                            <select 
                                id="doctor_id" 
                                name="doctor_id" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required
                                <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                            >
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['user_id']; ?>" <?php echo (isset($selected_doctor) && $selected_doctor['user_id'] == $doctor['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($doctor['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($selected_patient)): ?>
                                <p class="mt-1 text-sm text-yellow-600">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Please select a patient first
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Date and Time -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="visit_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    Visit Date <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="date" 
                                    id="visit_date" 
                                    name="visit_date" 
                                    value="<?php echo htmlspecialchars($default_date); ?>"
                                    max="<?php echo date('Y-m-d'); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                    <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                                >
                            </div>
                            
                            <div>
                                <label for="visit_time" class="block text-sm font-medium text-gray-700 mb-2">
                                    Visit Time <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="time" 
                                    id="visit_time" 
                                    name="visit_time" 
                                    value="<?php echo htmlspecialchars($default_time); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                    <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                                >
                            </div>
                        </div>
                        
                        <!-- Diagnosis -->
                        <div>
                            <label for="diagnosis" class="block text-sm font-medium text-gray-700 mb-2">
                                Diagnosis
                            </label>
                            <textarea 
                                id="diagnosis" 
                                name="diagnosis" 
                                rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter diagnosis details..."
                                <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                            ></textarea>
                        </div>

                        <!-- Lab Requests -->
                        <div>
                            <label for="lab_requests" class="block text-sm font-medium text-gray-700 mb-2">
                                Lab Requests
                            </label>
                            <textarea 
                                id="lab_requests" 
                                name="lab_requests" 
                                rows="2"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter lab test requests..."
                                <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                            ></textarea>
                        </div>

                        <!-- Prescription -->
                        <div>
                            <label for="prescription" class="block text-sm font-medium text-gray-700 mb-2">
                                Prescription
                            </label>
                            <textarea 
                                id="prescription" 
                                name="prescription" 
                                rows="3"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter medication details..."
                                <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                            ></textarea>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex flex-col sm:flex-row gap-4 pt-6">
                            <button 
                                type="submit" 
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center"
                                <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                            >
                                <i class="fas fa-save mr-2"></i>
                                Save Visit Record
                            </button>
                            <a 
                                href="outpatient.php" 
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
    </div>
</div>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('visit_date');
    const timeInput = document.getElementById('visit_time');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Check if doctor is selected
            if (doctorSelect.value === '') {
                alert('Please select a doctor');
                isValid = false;
            }
            
            // Check if date is selected
            if (dateInput.value === '') {
                alert('Please select a visit date');
                isValid = false;
            }
            
            // Check if time is selected
            if (timeInput.value === '') {
                alert('Please select a visit time');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
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