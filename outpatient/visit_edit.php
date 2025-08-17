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

// Check if visit ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: outpatient.php");
    exit;
}

$visit_id = $_GET['id'];

// Get doctors for dropdown
try {
    $doctors_query = "SELECT user_id, full_name FROM users WHERE role_id = 2 ORDER BY full_name";
    $stmt = $pdo->query($doctors_query);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Doctor Query Error: " . $e->getMessage());
}

// Get visit details
try {
    $sql = "SELECT ov.*, p.unique_patient_code, p.first_name, p.last_name, 
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
    
    // Extract date and time from visit_datetime
    $visit_date = date('Y-m-d', strtotime($visit['visit_datetime']));
    $visit_time = date('H:i', strtotime($visit['visit_datetime']));
    
} catch (PDOException $e) {
    error_log("Visit Query Error: " . $e->getMessage());
    header("Location: outpatient.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = isset($_POST['doctor_id']) ? trim($_POST['doctor_id']) : '';
    $visit_date = isset($_POST['visit_date']) ? trim($_POST['visit_date']) : '';
    $visit_time = isset($_POST['visit_time']) ? trim($_POST['visit_time']) : '';
    $diagnosis = isset($_POST['diagnosis']) ? trim($_POST['diagnosis']) : '';
    $lab_requests = isset($_POST['lab_requests']) ? trim($_POST['lab_requests']) : '';
    $prescription = isset($_POST['prescription']) ? trim($_POST['prescription']) : '';

    // Validation
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

    // If no errors, proceed with update
    if (empty($errors)) {
        try {
            // Update visit
            $sql = "UPDATE outpatient_visits 
                    SET doctor_id = ?, visit_datetime = ?, diagnosis = ?, lab_requests = ?, prescription = ? 
                    WHERE visit_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $doctor_id,
                $visit_datetime,
                $diagnosis,
                $lab_requests,
                $prescription,
                $visit_id
            ]);

            // Log the action
            $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
            $audit_stmt = $pdo->prepare($audit_sql);
            $audit_stmt->execute([
                $_SESSION['user_id'],
                'Update Visit',
                'outpatient_visits',
                $visit_id,
                "Updated visit record for patient {$visit['unique_patient_code']}"
            ]);

            $success_message = "Visit record updated successfully!";
            
            // Refresh visit data
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$visit_id]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Extract date and time again
            $visit_date = date('Y-m-d', strtotime($visit_datetime));
            $visit_time = date('H:i', strtotime($visit_datetime));
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Visit Update Error: " . $e->getMessage());
        }
    }
}

include_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 fade-in">
        <div class="flex items-center mb-4">
            <a href="visit_view.php?id=<?php echo $visit_id; ?>" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Edit Visit Record</h1>
        </div>
        <p class="text-gray-600">Modify patient visit information</p>
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

                    <div class="mt-6">
                        <div class="text-sm text-gray-500 mb-2">Original Visit Record:</div>
                        <div class="font-medium">
                            <?php echo date('F j, Y', strtotime($visit['visit_datetime'])); ?>
                            at
                            <?php echo date('g:i A', strtotime($visit['visit_datetime'])); ?>
                        </div>
                        <div class="text-sm text-gray-600 mt-1">
                            with Dr. <?php echo htmlspecialchars($visit['doctor_name']); ?>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-6 mt-6">
                        <a href="../patients/patient_view.php?id=<?php echo $visit['patient_id']; ?>" class="w-full block text-center bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-4 rounded-lg transition-colors">
                            View Patient Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Visit Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Edit Visit Details</h2>
                    <p class="text-sm text-gray-600">Modify the visit information below</p>
                </div>

                <div class="p-6">
                    <form method="POST" class="space-y-6">
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
                            >
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['user_id']; ?>" 
                                            <?php echo $doctor['user_id'] == $visit['doctor_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($doctor['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                                    value="<?php echo htmlspecialchars($visit_date); ?>"
                                    max="<?php echo date('Y-m-d'); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
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
                                    value="<?php echo htmlspecialchars($visit_time); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
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
                            ><?php echo htmlspecialchars($visit['diagnosis'] ?? ''); ?></textarea>
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
                            ><?php echo htmlspecialchars($visit['lab_requests'] ?? ''); ?></textarea>
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
                            ><?php echo htmlspecialchars($visit['prescription'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex flex-col sm:flex-row gap-4 pt-6">
                            <button 
                                type="submit" 
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center"
                            >
                                <i class="fas fa-save mr-2"></i>
                                Save Changes
                            </button>
                            <a 
                                href="visit_view.php?id=<?php echo $visit_id; ?>" 
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