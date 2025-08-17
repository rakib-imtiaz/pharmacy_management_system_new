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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $insurance_info = trim($_POST['insurance_info'] ?? '');

    // Validation
    if (empty($first_name)) {
        $errors[] = "First name is required";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $first_name)) {
        $errors[] = "First name can only contain letters and spaces";
    }

    if (empty($last_name)) {
        $errors[] = "Last name is required";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $last_name)) {
        $errors[] = "Last name can only contain letters and spaces";
    }

    if (!empty($dob)) {
        $dob_date = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dob_date || $dob_date->format('Y-m-d') !== $dob) {
            $errors[] = "Invalid date of birth format";
        }
    }

    if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
        $errors[] = "Phone number can only contain digits, spaces, hyphens, and parentheses";
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            // Generate unique patient code
            $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(unique_patient_code, 2) AS UNSIGNED)) FROM patients");
            $max_id = $stmt->fetchColumn() ?: 0;
            $next_id = $max_id + 1;
            $unique_patient_code = 'P' . str_pad($next_id, 4, '0', STR_PAD_LEFT);

            // Insert patient
            $sql = "INSERT INTO patients (unique_patient_code, first_name, last_name, dob, gender, phone, email, address, insurance_info) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $unique_patient_code,
                $first_name,
                $last_name,
                $dob ?: null,
                $gender,
                $phone ?: null,
                $email ?: null,
                $address ?: null,
                $insurance_info ?: null
            ]);

            $patient_id = $pdo->lastInsertId();

            // Log the action
            $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
            $audit_stmt = $pdo->prepare($audit_sql);
            $audit_stmt->execute([
                $_SESSION['user_id'],
                'Create Patient',
                'patients',
                $patient_id,
                "Registered patient $unique_patient_code: $first_name $last_name"
            ]);

            $success_message = "Patient registered successfully! Patient ID: $unique_patient_code";
            
            // Clear form data
            $first_name = $last_name = $dob = $gender = $phone = $email = $address = $insurance_info = '';

        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Patient Registration Error: " . $e->getMessage());
        }
    }
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
            <h1 class="text-3xl font-bold text-gray-800">Register New Patient</h1>
        </div>
        <p class="text-gray-600">Add a new patient to the system with complete information</p>
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

    <!-- Registration Form -->
    <div class="bg-white rounded-xl shadow-lg p-8 fade-in">
        <form method="POST" class="space-y-6">
            <!-- Personal Information -->
            <div class="border-b border-gray-200 pb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Personal Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            value="<?php echo htmlspecialchars($first_name ?? ''); ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                            pattern="[a-zA-Z\s]+"
                            title="Only letters and spaces allowed"
                        >
                    </div>
                    
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Last Name <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            value="<?php echo htmlspecialchars($last_name ?? ''); ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                            pattern="[a-zA-Z\s]+"
                            title="Only letters and spaces allowed"
                        >
                    </div>
                    
                    <div>
                        <label for="dob" class="block text-sm font-medium text-gray-700 mb-2">
                            Date of Birth
                        </label>
                        <input 
                            type="date" 
                            id="dob" 
                            name="dob" 
                            value="<?php echo htmlspecialchars($dob ?? ''); ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            max="<?php echo date('Y-m-d'); ?>"
                        >
                    </div>
                    
                    <div>
                        <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">
                            Gender
                        </label>
                        <select 
                            id="gender" 
                            name="gender" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo ($gender ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($gender ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($gender ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="border-b border-gray-200 pb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Contact Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Phone Number
                        </label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="(123) 456-7890"
                            pattern="[\d\s\-\+\(\)]+"
                            title="Phone number can contain digits, spaces, hyphens, and parentheses"
                        >
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($email ?? ''); ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="patient@example.com"
                        >
                    </div>
                </div>
                
                <div class="mt-6">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                        Address
                    </label>
                    <textarea 
                        id="address" 
                        name="address" 
                        rows="3"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Enter full address"
                    ><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Insurance Information -->
            <div class="border-b border-gray-200 pb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Insurance Information</h2>
                <div>
                    <label for="insurance_info" class="block text-sm font-medium text-gray-700 mb-2">
                        Insurance Provider & Policy Number
                    </label>
                    <input 
                        type="text" 
                        id="insurance_info" 
                        name="insurance_info" 
                        value="<?php echo htmlspecialchars($insurance_info ?? ''); ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="e.g., Blue Cross Blue Shield - Policy #123456789"
                    >
                    <p class="text-sm text-gray-500 mt-1">Leave blank if patient is self-paying</p>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex flex-col sm:flex-row gap-4 pt-6">
                <button 
                    type="submit" 
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center"
                >
                    <i class="fas fa-user-plus mr-2"></i>
                    Register Patient
                </button>
                <a 
                    href="patients.php" 
                    class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center"
                >
                    <i class="fas fa-times mr-2"></i>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Form validation and enhancement
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const requiredFields = form.querySelectorAll('[required]');
    
    // Real-time validation
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            validateField(this);
        });
        
        field.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateField(this);
            }
        });
    });
    
    function validateField(field) {
        const value = field.value.trim();
        const isValid = field.checkValidity();
        
        if (!isValid) {
            field.classList.add('border-red-500');
            field.classList.remove('border-gray-300');
        } else {
            field.classList.remove('border-red-500');
            field.classList.add('border-gray-300');
        }
    }
    
    // Auto-format phone number
    const phoneField = document.getElementById('phone');
    phoneField.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 6) {
            value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
        } else if (value.length >= 3) {
            value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
        }
        e.target.value = value;
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