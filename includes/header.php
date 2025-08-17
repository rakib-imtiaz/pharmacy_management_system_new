<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify login status
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $base_url . "login.php");
    exit;
}

<<<<<<< HEAD
$is_admin = ($_SESSION['role'] === 'Admin');
$is_doctor = ($_SESSION['role'] === 'Doctor');
$is_staff = ($_SESSION['role'] === 'Staff');
=======
$is_admin = ($_SESSION['role'] === 'Administrator');
$is_doctor = ($_SESSION['role'] === 'Doctor');
$is_nurse = ($_SESSION['role'] === 'Nurse');
$is_receptionist = ($_SESSION['role'] === 'Receptionist');
$is_patient = ($_SESSION['role'] === 'Patient');
>>>>>>> b9c44d5e7e4170886bd4bbc2857f9a4d72a84279
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<<<<<<< HEAD
    <title>Bayside Surgical Centre - Clinic Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .nav-link {
            @apply flex items-center space-x-2 px-4 py-2 text-white hover:bg-blue-700 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105;
        }
        .nav-icon {
            @apply text-lg text-white;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .slide-in {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }
        
        .pulse-glow {
            animation: pulseGlow 2s infinite;
        }
        
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 5px rgba(59, 130, 246, 0.5); }
            50% { box-shadow: 0 0 20px rgba(59, 130, 246, 0.8); }
        }
        
        .mobile-menu {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .mobile-menu.open {
            transform: translateX(0);
        }
    </style>
</head>
<body class="bg-gray-50">
    <nav class="bg-gradient-to-r from-blue-600 to-blue-800 shadow-xl">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <!-- Logo Section -->
                <div class="flex-shrink-0">
                    <a href="<?php echo $base_url; ?>" class="flex items-center space-x-3 group">
                        <div class="bg-white p-2 rounded-lg group-hover:scale-110 transition-transform duration-300">
                            <i class="fas fa-stethoscope text-2xl text-blue-600"></i>
                        </div>
                        <div>
                            <span class="text-white text-xl font-bold">Bayside Surgical Centre</span>
                            <div class="text-blue-200 text-sm">Clinic Management System</div>
                        </div>
=======
    <title>HMS - Hospital Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <nav class="bg-teal-600 shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <a href="<?php echo $base_url; ?>" class="flex items-center space-x-2">
                    <i class="fas fa-hospital text-2xl text-white"></i>
                    <span class="text-white text-lg font-semibold">HMS</span>
                </a>

                <div class="flex space-x-4">
                    <a href="<?php echo $base_url; ?>" class="text-white hover:bg-teal-700 px-3 py-2 rounded-md">
                        <i class="fas fa-home mr-2"></i>Dashboard
>>>>>>> b9c44d5e7e4170886bd4bbc2857f9a4d72a84279
                    </a>

<<<<<<< HEAD
                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-4">
                    <a href="<?php echo $base_url; ?>" class="nav-link">
                        <i class="fas fa-home nav-icon"></i>
                        <span>Dashboard</span>
                    </a>

                    <a href="<?php echo $base_url; ?>patients/patients.php" class="nav-link">
                        <i class="fas fa-user-injured nav-icon"></i>
                        <span>Patients</span>
                    </a>

                    <a href="<?php echo $base_url; ?>appointments/appointments.php" class="nav-link">
                        <i class="fas fa-calendar-alt nav-icon"></i>
                        <span>Appointments</span>
                    </a>

                    <a href="<?php echo $base_url; ?>outpatient/outpatient.php" class="nav-link">
                        <i class="fas fa-notes-medical nav-icon"></i>
                        <span>Outpatient</span>
                    </a>

                    <a href="<?php echo $base_url; ?>invoices/invoices.php" class="nav-link">
                        <i class="fas fa-file-invoice-dollar nav-icon"></i>
                        <span>Billing</span>
                    </a>

                    <?php if ($is_admin): ?>
                        <a href="<?php echo $base_url; ?>reports/reports.php" class="nav-link">
                            <i class="fas fa-chart-bar nav-icon"></i>
                            <span>Reports</span>
                        </a>

                        <a href="<?php echo $base_url; ?>users/users.php" class="nav-link">
                            <i class="fas fa-users-cog nav-icon"></i>
                            <span>Users</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button id="mobile-menu-button" class="text-white hover:text-blue-200 focus:outline-none focus:text-blue-200">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>

                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <div class="hidden md:flex items-center space-x-2 text-white">
                        <i class="fas fa-user-circle text-lg"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <span class="text-blue-200">(<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
                    </div>
                    <a href="<?php echo $base_url; ?>logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors duration-300">
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        <span class="hidden md:inline">Logout</span>
=======
                    <?php if ($is_admin): ?>
                        <!-- Admin Management Dropdown -->
                        <div class="relative group">
                            <button class="text-white hover:bg-teal-700 px-3 py-2 rounded-md inline-flex items-center">
                                <i class="fas fa-cogs mr-2"></i>
                                <span>Management</span>
                                <i class="fas fa-chevron-down ml-2"></i>
                            </button>
                            <div class="hidden group-hover:block absolute left-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                <div class="py-1">
                                    <a href="<?php echo $base_url; ?>staff/manage.php"
                                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-teal-500 hover:text-white">
                                        <i class="fas fa-users mr-2"></i>Staff Management
                                    </a>
                                    <a href="<?php echo $base_url; ?>resources/manage.php"
                                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-teal-500 hover:text-white">
                                        <i class="fas fa-box mr-2"></i>Resources
                                    </a>
                                    <a href="<?php echo $base_url; ?>medicines/manage.php"
                                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-teal-500 hover:text-white">
                                        <i class="fas fa-pills mr-2"></i>Medicines
                                    </a>
                                    <a href="<?php echo $base_url; ?>bills/manage.php"
                                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-teal-500 hover:text-white">
                                        <i class="fas fa-file-invoice-dollar mr-2"></i>Bills
                                    </a>
                                    <a href="<?php echo $base_url; ?>departments/manage.php"
                                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-teal-500 hover:text-white">
                                        <i class="fas fa-hospital-alt mr-2"></i>Departments
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_doctor): ?>
                        <a href="<?php echo $base_url; ?>appointments/manage.php" class="text-white hover:bg-teal-700 px-3 py-2 rounded-md">
                            <i class="fas fa-calendar-check mr-2"></i>Appointments
                        </a>
                        <a href="<?php echo $base_url; ?>patients/manage.php" class="text-white hover:bg-teal-700 px-3 py-2 rounded-md">
                            <i class="fas fa-user-injured mr-2"></i>Patients
                        </a>
                        <a href="<?php echo $base_url; ?>prescriptions/manage.php" class="text-white hover:bg-teal-700 px-3 py-2 rounded-md">
                            <i class="fas fa-prescription mr-2"></i>Prescriptions
                        </a>
                    <?php endif; ?>

                    <?php if ($is_nurse): ?>
                        <a href="<?php echo $base_url; ?>patients/manage.php" class="text-white hover:bg-teal-700 px-3 py-2 rounded-md">
                            <i class="fas fa-user-injured mr-2"></i>Patients
                        </a>
                        <a href="<?php echo $base_url; ?>appointments/manage.php" class="text-white hover:bg-teal-700 px-3 py-2 rounded-md">
                            <i class="fas fa-calendar-check mr-2"></i>Appointments
                        </a>
                        <a href="<?php echo $base_url; ?>medicines/view.php" class="text-white hover:bg-teal-700 px-3 py-2 rounded-md">
                            <i class="fas fa-pills mr-2"></i>Medicines
                        </a>
                    <?php endif; ?>

                    <?php if ($is_patient): ?>
                        <a href="<?php echo $base_url; ?>appointments/book.php" class="text-white hover:bg-teal-700 px-3 py-2 rounded-md">
                            <i class="fas fa-calendar-plus mr-2"></i>Book Appointment
                        </a>
                        <a href="<?php echo $base_url; ?>appointments/my-appointments.php" class="text-white hover:bg-teal-700 px-3 py-2 rounded-md">
                            <i class="fas fa-calendar-check mr-2"></i>My Appointments
                        </a>
                        <a href="<?php echo $base_url; ?>prescriptions/my-prescriptions.php" class="text-white hover:bg-teal-700 px-3 py-2 rounded-md">
                            <i class="fas fa-prescription mr-2"></i>My Prescriptions
                        </a>
                        <a href="<?php echo $base_url; ?>bills/my-bills.php" class="text-white hover:bg-teal-700 px-3 py-2 rounded-md">
                            <i class="fas fa-file-invoice-dollar mr-2"></i>My Bills
                        </a>
                    <?php endif; ?>
                </div>

                <!-- User Info and Logout -->
                <div class="flex items-center space-x-4">
                    <span class="text-white">
                        <i class="fas fa-user-circle mr-2"></i>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    <a href="<?php echo $base_url; ?>logout.php"
                        class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md transition duration-300">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
>>>>>>> b9c44d5e7e4170886bd4bbc2857f9a4d72a84279
                    </a>
                </div>

            </div>
        </div>

        <!-- Mobile Navigation -->
        <div id="mobile-menu" class="mobile-menu md:hidden bg-blue-700 shadow-lg">
            <div class="px-4 py-2 space-y-2">
                <a href="<?php echo $base_url; ?>" class="nav-link">
                    <i class="fas fa-home nav-icon"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo $base_url; ?>patients/patients.php" class="nav-link">
                    <i class="fas fa-user-injured nav-icon"></i>
                    <span>Patients</span>
                </a>
                <a href="<?php echo $base_url; ?>appointments/appointments.php" class="nav-link">
                    <i class="fas fa-calendar-alt nav-icon"></i>
                    <span>Appointments</span>
                </a>
                <a href="<?php echo $base_url; ?>outpatient/outpatient.php" class="nav-link">
                    <i class="fas fa-notes-medical nav-icon"></i>
                    <span>Outpatient</span>
                </a>
                <a href="<?php echo $base_url; ?>invoices/invoices.php" class="nav-link">
                    <i class="fas fa-file-invoice-dollar nav-icon"></i>
                    <span>Billing</span>
                </a>
                <?php if ($is_admin): ?>
                    <a href="<?php echo $base_url; ?>reports/reports.php" class="nav-link">
                        <i class="fas fa-chart-bar nav-icon"></i>
                        <span>Reports</span>
                    </a>
                    <a href="<?php echo $base_url; ?>users/users.php" class="nav-link">
                        <i class="fas fa-users-cog nav-icon"></i>
                        <span>Users</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <script>
<<<<<<< HEAD
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu.classList.toggle('open');
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            
            if (!mobileMenu.contains(event.target) && !mobileMenuButton.contains(event.target)) {
                mobileMenu.classList.remove('open');
            }
        });
=======
        // Add event listeners for dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownButton = document.querySelector('.relative button');
            const dropdownMenu = document.querySelector('.relative .hidden');

            dropdownButton.addEventListener('click', function() {
                dropdownMenu.classList.toggle('hidden');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!dropdownButton.contains(event.target) && !dropdownMenu.contains(event.target)) {
                    dropdownMenu.classList.add('hidden');
                }
            });
        });
>>>>>>> b9c44d5e7e4170886bd4bbc2857f9a4d72a84279
    </script>
</body>

</html>