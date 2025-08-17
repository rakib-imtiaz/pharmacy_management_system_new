<?php
require_once 'includes/db_connect.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug: Print all POST data
        error_log("POST Data: " . print_r($_POST, true));

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = 'Patient'; // Default role for new signups
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact_info = trim($_POST['phone'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $blood_group = $_POST['blood_group'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');

        // Detailed validation
        $missing_fields = [];
        if (empty($username)) $missing_fields[] = 'Username';
        if (empty($password)) $missing_fields[] = 'Password';
        if (empty($confirm_password)) $missing_fields[] = 'Confirm Password';
        if (empty($full_name)) $missing_fields[] = 'Full Name';
        if (empty($email)) $missing_fields[] = 'Email';
        if (empty($contact_info)) $missing_fields[] = 'Phone';
        if (empty($date_of_birth)) $missing_fields[] = 'Date of Birth';
        if (empty($gender)) $missing_fields[] = 'Gender';

        if (!empty($missing_fields)) {
            throw new Exception("The following required fields are missing: " . implode(', ', $missing_fields));
        }

        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match");
        }

        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check if username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user` WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Username already exists");
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Create new user
        $stmt = $pdo->prepare("
            INSERT INTO `user` (username, password, role) 
            VALUES (?, ?, ?)
        ");
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt->execute([$username, $password_hash, $role]);
        $user_id = $pdo->lastInsertId();

        // Create patient record
        $stmt = $pdo->prepare("
            INSERT INTO `patient` (user_id, name, date_of_birth, gender, blood_group, 
                                contact_info, address, emergency_contact) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, 
            $full_name, 
            $date_of_birth, 
            $gender,
            $blood_group,
            $contact_info,
            $address,
            $emergency_contact
        ]);

        $pdo->commit();

        // Set success message
        $_SESSION['success'] = "Account created successfully. Please log in.";
        header("Location: login.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Hospital Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-green-50 to-teal-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full">
        <div class="bg-white/90 backdrop-blur-lg rounded-3xl shadow-2xl p-8 animate__animated animate__fadeIn">
            <!-- Logo and Title -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-6">
                    <div class="bg-gradient-to-r from-green-600 to-teal-600 p-4 rounded-2xl shadow-lg">
                        <i class="fas fa-hospital-user text-3xl text-white"></i>
                    </div>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Patient Registration</h1>
                <p class="text-teal-600 font-medium">Hospital Management System</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 animate__animated animate__shake">
                    <p class="font-medium">Registration Error</p>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Account Information -->
                <div class="md:col-span-2">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Account Information</h2>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" name="username" required
                               class="w-full pl-10 pr-3 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                               placeholder="Choose a username">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="password" required
                               class="w-full pl-10 pr-3 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                               placeholder="Create a password">
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="md:col-span-2">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4 mt-4">Personal Information</h2>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <input type="text" name="full_name" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Blood Group</label>
                    <select name="blood_group" required
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                        <option value="">Select Blood Group</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                    <input type="date" name="date_of_birth" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                    <select name="gender" required
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <!-- Contact Information -->
                <div class="md:col-span-2">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4 mt-4">Contact Information</h2>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" name="phone" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <textarea name="address" rows="2"
                              class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500"></textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact</label>
                    <input type="text" name="emergency_contact"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500"
                           placeholder="Name: Contact Number">
                </div>

                <div class="md:col-span-2">
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white font-semibold py-3 px-4 rounded-xl transition duration-300 flex items-center justify-center space-x-2">
                        <i class="fas fa-user-plus"></i>
                        <span>Register as Patient</span>
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center text-sm text-gray-600">
                <p>Already have an account? 
                    <a href="login.php" class="text-teal-600 hover:text-teal-700 font-semibold">
                        Login here
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html> 