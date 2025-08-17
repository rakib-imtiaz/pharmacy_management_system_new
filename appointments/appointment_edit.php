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

// Check if appointment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: appointments.php");
    exit;
}

$appointment_id = $_GET['id'];

// Get doctors for dropdown
try {
    $doctors_query = "SELECT user_id, full_name FROM users WHERE role_id = 2 ORDER BY full_name";
    $stmt = $pdo->query($doctors_query);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Doctor Query Error: " . $e->getMessage());
}

// Get appointment details
try {
    $sql = "SELECT a.*, p.unique_patient_code, p.first_name, p.last_name, p.phone, 
                  u.full_name AS doctor_name
           FROM appointments a
           JOIN patients p ON a.patient_id = p.patient_id
           JOIN users u ON a.doctor_id = u.user_id
           WHERE a.appointment_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        header("Location: appointments.php");
        exit;
    }
    
    // Extract date and time from appointment_datetime
    $appointment_date = date('Y-m-d', strtotime($appointment['appointment_datetime']));
    $appointment_time = date('H:i', strtotime($appointment['appointment_datetime']));
    
} catch (PDOException $e) {
    error_log("Appointment Query Error: " . $e->getMessage());
    header("Location: appointments.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = isset($_POST['doctor_id']) ? trim($_POST['doctor_id']) : '';
    $appointment_date = isset($_POST['appointment_date']) ? trim($_POST['appointment_date']) : '';
    $appointment_time = isset($_POST['appointment_time']) ? trim($_POST['appointment_time']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    // Validation
    if (empty($doctor_id)) {
        $errors[] = "Doctor is required";
    }

    if (empty($appointment_date)) {
        $errors[] = "Appointment date is required";
    }

    if (empty($appointment_time)) {
        $errors[] = "Appointment time is required";
    }

    if (empty($status)) {
        $errors[] = "Status is required";
    }

    // Create appointment datetime
    $appointment_datetime = $appointment_date . ' ' . $appointment_time . ':00';

    // Check for double-booking (only if time or doctor changed and status is still scheduled)
    if (empty($errors) && $status === 'Scheduled' && 
        ($doctor_id != $appointment['doctor_id'] || $appointment_datetime != $appointment['appointment_datetime'])) {
        try {
            $check_sql = "SELECT COUNT(*) FROM appointments 
                         WHERE doctor_id = ? 
                         AND DATE(appointment_datetime) = ? 
                         AND TIME(appointment_datetime) = ?
                         AND status = 'Scheduled'
                         AND appointment_id != ?";
            $stmt = $pdo->prepare($check_sql);
            $stmt->execute([$doctor_id, $appointment_date, $appointment_time . ':00', $appointment_id]);
            $existing_count = $stmt->fetchColumn();

            if ($existing_count > 0) {
                $errors[] = "This time slot is already booked for the selected doctor. Please select another time.";
            }
        } catch (PDOException $e) {
            $errors[] = "Error checking availability: " . $e->getMessage();
            error_log("Appointment Check Error: " . $e->getMessage());
        }
    }

    // If no errors, proceed with update
    if (empty($errors)) {
        try {
            // Update appointment
            $sql = "UPDATE appointments 
                    SET doctor_id = ?, appointment_datetime = ?, status = ? 
                    WHERE appointment_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $doctor_id,
                $appointment_datetime,
                $status,
                $appointment_id
            ]);

            // Log the action
            $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
            $audit_stmt = $pdo->prepare($audit_sql);
            $audit_stmt->execute([
                $_SESSION['user_id'],
                'Update Appointment',
                'appointments',
                $appointment_id,
                "Updated appointment for patient {$appointment['unique_patient_code']}"
            ]);

            $success_message = "Appointment updated successfully!";
            
            // Refresh appointment data
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Extract date and time again
            $appointment_date = date('Y-m-d', strtotime($appointment_datetime));
            $appointment_time = date('H:i', strtotime($appointment_datetime));
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Appointment Update Error: " . $e->getMessage());
        }
    }
}

include_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 fade-in">
        <div class="flex items-center mb-4">
            <a href="appointments.php" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Edit Appointment</h1>
        </div>
        <p class="text-gray-600">Modify appointment details</p>
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
                                <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                            </div>
                            <div class="text-sm text-gray-600">
                                ID: <?php echo htmlspecialchars($appointment['unique_patient_code']); ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($appointment['phone']): ?>
                        <div class="mb-4">
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-phone mr-2"></i>
                                <?php echo htmlspecialchars($appointment['phone']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-6">
                        <div class="text-sm text-gray-500 mb-2">Current Status:</div>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                            <?php 
                            if ($appointment['status'] === 'Scheduled') echo 'bg-blue-100 text-blue-800';
                            elseif ($appointment['status'] === 'Completed') echo 'bg-green-100 text-green-800';
                            else echo 'bg-red-100 text-red-800';
                            ?>">
                            <?php echo htmlspecialchars($appointment['status']); ?>
                        </span>
                    </div>

                    <div class="mt-6">
                        <div class="text-sm text-gray-500 mb-2">Originally Scheduled For:</div>
                        <div class="font-medium">
                            <?php echo date('F j, Y', strtotime($appointment['appointment_datetime'])); ?>
                            at
                            <?php echo date('g:i A', strtotime($appointment['appointment_datetime'])); ?>
                        </div>
                        <div class="text-sm text-gray-600 mt-1">
                            with Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                        </div>
                    </div>

                    <?php if ($appointment['status'] === 'Scheduled'): ?>
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <a href="appointment_cancel.php?id=<?php echo $appointment_id; ?>" 
                               class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 transition-colors"
                               onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                <i class="fas fa-times-circle mr-2"></i>
                                Cancel Appointment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Edit Appointment Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Edit Appointment Details</h2>
                    <p class="text-sm text-gray-600">Modify the appointment information below</p>
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
                                            <?php echo $doctor['user_id'] == $appointment['doctor_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($doctor['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Date and Time -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="appointment_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    Date <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="date" 
                                    id="appointment_date" 
                                    name="appointment_date" 
                                    value="<?php echo htmlspecialchars($appointment_date); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                >
                            </div>
                            
                            <div>
                                <label for="appointment_time" class="block text-sm font-medium text-gray-700 mb-2">
                                    Time <span class="text-red-500">*</span>
                                </label>
                                <select 
                                    id="appointment_time" 
                                    name="appointment_time" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                >
                                    <option value="">Select Time</option>
                                    <?php
                                    $start_time = strtotime('08:00');
                                    $end_time = strtotime('17:30');
                                    $interval = 30 * 60; // 30 minutes
                                    
                                    for ($time = $start_time; $time <= $end_time; $time += $interval) {
                                        $time_value = date('H:i', $time);
                                        $time_display = date('g:i A', $time);
                                        $selected = $appointment_time == $time_value ? 'selected' : '';
                                        echo "<option value=\"$time_value\" $selected>$time_display</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                Status <span class="text-red-500">*</span>
                            </label>
                            <select 
                                id="status" 
                                name="status" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required
                            >
                                <option value="Scheduled" <?php echo $appointment['status'] === 'Scheduled' ? 'selected' : ''; ?>>
                                    Scheduled
                                </option>
                                <option value="Completed" <?php echo $appointment['status'] === 'Completed' ? 'selected' : ''; ?>>
                                    Completed
                                </option>
                                <option value="Cancelled" <?php echo $appointment['status'] === 'Cancelled' ? 'selected' : ''; ?>>
                                    Cancelled
                                </option>
                            </select>
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
                                href="appointments.php" 
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
// Date and time validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSelect = document.getElementById('appointment_time');
    
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
                alert('Please select an appointment date');
                isValid = false;
            }
            
            // Check if time is selected
            if (timeSelect.value === '') {
                alert('Please select an appointment time');
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