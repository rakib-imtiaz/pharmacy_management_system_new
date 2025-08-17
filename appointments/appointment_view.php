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
    // Get appointment details with patient and doctor info
    $sql = "SELECT a.*, p.unique_patient_code, p.first_name, p.last_name, p.phone, p.email,
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
    
    // Get patient's previous appointments (excluding current)
    $prev_appt_sql = "SELECT a.*, u.full_name AS doctor_name 
                      FROM appointments a 
                      JOIN users u ON a.doctor_id = u.user_id
                      WHERE a.patient_id = ? AND a.appointment_id != ? 
                      ORDER BY a.appointment_datetime DESC LIMIT 3";
    $prev_stmt = $pdo->prepare($prev_appt_sql);
    $prev_stmt->execute([$appointment['patient_id'], $appointment_id]);
    $previous_appointments = $prev_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if there's a visit record for this appointment
    $visit_sql = "SELECT visit_id FROM outpatient_visits 
                  WHERE patient_id = ? AND DATE(visit_datetime) = DATE(?)";
    $visit_stmt = $pdo->prepare($visit_sql);
    $visit_stmt->execute([$appointment['patient_id'], $appointment['appointment_datetime']]);
    $visit = $visit_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Appointment View Error: " . $e->getMessage());
    header("Location: appointments.php");
    exit;
}

include_once '../includes/header.php';

// Function to get status color classes
function getStatusColor($status) {
    switch ($status) {
        case 'Scheduled':
            return 'bg-blue-100 text-blue-800';
        case 'Completed':
            return 'bg-green-100 text-green-800';
        case 'Cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 fade-in">
        <div class="flex items-center mb-4">
            <a href="appointments.php" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Appointment Details</h1>
        </div>
        <p class="text-gray-600">View complete appointment information</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Appointment Details Card -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-bold text-gray-800">Appointment Information</h2>
                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?php echo getStatusColor($appointment['status']); ?>">
                            <?php echo htmlspecialchars($appointment['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="p-6">
                    <div class="flex items-center mb-8">
                        <div class="bg-blue-100 p-4 rounded-full mr-6">
                            <i class="fas fa-calendar-check text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900">
                                <?php echo date('l, F j, Y', strtotime($appointment['appointment_datetime'])); ?>
                            </div>
                            <div class="text-xl font-medium text-gray-700">
                                <?php echo date('g:i A', strtotime($appointment['appointment_datetime'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                        <!-- Doctor Information -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Doctor</h3>
                            <div class="flex items-center">
                                <div class="bg-green-100 p-3 rounded-full mr-4">
                                    <i class="fas fa-user-md text-green-600"></i>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">
                                        Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Timing Information -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Schedule Details</h3>
                            <div class="space-y-2">
                                <div class="flex">
                                    <div class="w-1/2 text-gray-600">Created:</div>
                                    <div class="w-1/2">
                                        <?php echo date('M j, Y', strtotime($appointment['created_at'])); ?>
                                    </div>
                                </div>
                                <?php if ($appointment['status'] === 'Scheduled'): ?>
                                    <div class="flex">
                                        <div class="w-1/2 text-gray-600">Time until appointment:</div>
                                        <div class="w-1/2">
                                            <?php 
                                            $now = new DateTime();
                                            $apptTime = new DateTime($appointment['appointment_datetime']);
                                            $diff = $now->diff($apptTime);
                                            
                                            if ($apptTime < $now) {
                                                echo '<span class="text-red-600">Overdue</span>';
                                            } else {
                                                if ($diff->days > 0) {
                                                    echo $diff->format('%d days, %h hours');
                                                } else {
                                                    echo $diff->format('%h hours, %i minutes');
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Buttons -->
                    <div class="border-t border-gray-200 pt-6 mt-6 flex flex-col sm:flex-row gap-4">
                        <?php if ($appointment['status'] === 'Scheduled'): ?>
                            <a href="appointment_edit.php?id=<?php echo $appointment_id; ?>" 
                               class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center">
                                <i class="fas fa-edit mr-2"></i>
                                Edit Appointment
                            </a>
                            <a href="appointment_cancel.php?id=<?php echo $appointment_id; ?>" 
                               class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center"
                               onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                <i class="fas fa-times-circle mr-2"></i>
                                Cancel Appointment
                            </a>
                        <?php elseif ($appointment['status'] === 'Completed' && !$visit): ?>
                            <a href="../outpatient/visit_add.php?patient_id=<?php echo $appointment['patient_id']; ?>&date=<?php echo date('Y-m-d', strtotime($appointment['appointment_datetime'])); ?>" 
                               class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center">
                                <i class="fas fa-notes-medical mr-2"></i>
                                Record Visit
                            </a>
                        <?php elseif ($visit): ?>
                            <a href="../outpatient/visit_view.php?id=<?php echo $visit['visit_id']; ?>" 
                               class="flex-1 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center">
                                <i class="fas fa-file-medical mr-2"></i>
                                View Visit Record
                            </a>
                        <?php endif; ?>

                        <?php if ($appointment['status'] === 'Cancelled'): ?>
                            <a href="appointment_add.php?patient_id=<?php echo $appointment['patient_id']; ?>" 
                               class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center">
                                <i class="fas fa-calendar-plus mr-2"></i>
                                Reschedule
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($previous_appointments)): ?>
            <!-- Previous Appointments -->
            <div class="mt-8 bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Previous Appointments</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php foreach($previous_appointments as $prev): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex justify-between mb-2">
                                    <div>
                                        <span class="text-gray-900 font-medium">
                                            <?php echo date('F j, Y', strtotime($prev['appointment_datetime'])); ?>
                                        </span>
                                        <span class="text-gray-600 ml-2">
                                            <?php echo date('g:i A', strtotime($prev['appointment_datetime'])); ?>
                                        </span>
                                    </div>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo getStatusColor($prev['status']); ?>">
                                        <?php echo htmlspecialchars($prev['status']); ?>
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600">
                                    Dr. <?php echo htmlspecialchars($prev['doctor_name']); ?>
                                </div>
                                <div class="mt-2 text-right">
                                    <a href="appointment_view.php?id=<?php echo $prev['appointment_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($previous_appointments) >= 3): ?>
                        <div class="mt-4 text-center">
                            <a href="appointments.php?search=<?php echo $appointment['unique_patient_code']; ?>" class="text-blue-600 hover:text-blue-800">
                                View All Appointments
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
                                <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                            </div>
                            <div class="text-sm text-gray-600">
                                ID: <?php echo htmlspecialchars($appointment['unique_patient_code']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4 mt-4">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">Contact Information</h3>
                        <div class="space-y-3">
                            <?php if (!empty($appointment['phone'])): ?>
                                <div class="flex">
                                    <div class="w-1/4 flex-shrink-0">
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100">
                                            <i class="fas fa-phone text-blue-600"></i>
                                        </span>
                                    </div>
                                    <div class="w-3/4 flex items-center">
                                        <?php echo htmlspecialchars($appointment['phone']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($appointment['email'])): ?>
                                <div class="flex">
                                    <div class="w-1/4 flex-shrink-0">
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100">
                                            <i class="fas fa-envelope text-blue-600"></i>
                                        </span>
                                    </div>
                                    <div class="w-3/4 flex items-center">
                                        <?php echo htmlspecialchars($appointment['email']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-6 mt-6">
                        <a href="../patients/patient_view.php?id=<?php echo $appointment['patient_id']; ?>" class="w-full block text-center bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-4 rounded-lg transition-colors">
                            View Patient Profile
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($appointment['status'] === 'Scheduled'): ?>
                <!-- Quick Actions -->
                <div class="mt-8 bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-800">Quick Actions</h2>
                    </div>
                    <div class="p-6">
                        <?php if ($appointment['status'] === 'Scheduled'): ?>
                            <!-- Mark Completed -->
                            <form method="POST" action="appointment_edit.php?id=<?php echo $appointment_id; ?>" class="mb-4">
                                <input type="hidden" name="doctor_id" value="<?php echo $appointment['doctor_id']; ?>">
                                <input type="hidden" name="appointment_date" value="<?php echo date('Y-m-d', strtotime($appointment['appointment_datetime'])); ?>">
                                <input type="hidden" name="appointment_time" value="<?php echo date('H:i', strtotime($appointment['appointment_datetime'])); ?>">
                                <input type="hidden" name="status" value="Completed">
                                <button type="submit" 
                                    class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center"
                                    onclick="return confirm('Mark this appointment as completed?');">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    Mark as Completed
                                </button>
                            </form>
                            
                            <!-- Send Reminder (Simulated) -->
                            <button onclick="alert('Reminder sent to patient!')" 
                                class="w-full bg-blue-100 hover:bg-blue-200 text-blue-700 px-4 py-2 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center">
                                <i class="fas fa-bell mr-2"></i>
                                Send Reminder
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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