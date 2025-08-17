<?php
define('ENVIRONMENT', 'development');
require_once 'includes/db_connect.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$debug_info = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $debug_info['username_entered'] = $username;
    $debug_info['password_length'] = strlen($password);

<<<<<<< HEAD
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
        $debug_info['error_type'] = 'empty_fields';
    } else {
        try {
            // Debug: Check database connection
            $debug_info['db_connected'] = true;
            
            // Query to get user with role information - using plain text password comparison
            $sql = "SELECT u.user_id, u.username, u.password_hash, u.full_name, r.role_name 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.role_id 
                    WHERE u.username = ? AND u.password_hash = ?";
            
            $debug_info['sql_query'] = $sql;
            $debug_info['username_param'] = $username;
            $debug_info['password_param'] = $password;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $password]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $debug_info['user_found'] = !empty($user);
            if ($user) {
                $debug_info['user_data'] = [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role_name' => $user['role_name'],
                    'password_hash_length' => strlen($user['password_hash']),
                    'password_hash_preview' => substr($user['password_hash'], 0, 10) . '...'
                ];
                
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role_name'];
=======
        // Get user from database
        $stmt = $pdo->prepare("SELECT user_id, username, password, role FROM `user` WHERE username = ? AND password = ? LIMIT 1");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch();

        // Check if user exists
        if (!$user) {
            throw new Exception("Invalid username or password.");
        }
>>>>>>> b9c44d5e7e4170886bd4bbc2857f9a4d72a84279

                $debug_info['session_set'] = true;
                $debug_info['session_data'] = [
                    'user_id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'role' => $_SESSION['role']
                ];

                // Log successful login
                try {
                    $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, details) VALUES (?, ?, ?, ?)";
                    $audit_stmt = $pdo->prepare($audit_sql);
                    $audit_stmt->execute([
                        $user['user_id'],
                        'Login',
                        'users',
                        "User {$user['username']} logged in successfully"
                    ]);
                    $debug_info['audit_logged'] = true;
                } catch (Exception $e) {
                    $debug_info['audit_error'] = $e->getMessage();
                }

                header("Location: index.php");
                exit;
            } else {
                $error = "Invalid username or password";
                $debug_info['error_type'] = 'invalid_credentials';
                
                // Log failed login attempt
                try {
                    $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, details) VALUES (?, ?, ?, ?)";
                    $audit_stmt = $pdo->prepare($audit_sql);
                    $audit_stmt->execute([
                        null,
                        'Failed Login',
                        'users',
                        "Failed login attempt for username: {$username}"
                    ]);
                    $debug_info['failed_audit_logged'] = true;
                } catch (Exception $e) {
                    $debug_info['failed_audit_error'] = $e->getMessage();
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            $debug_info['db_error'] = $e->getMessage();
            $debug_info['error_type'] = 'database_error';
            error_log("Login Error: " . $e->getMessage());
        }
    }
}

<<<<<<< HEAD
// Debug: Check if database tables exist
try {
    $tables_check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    $debug_info['users_table_exists'] = !empty($tables_check);
    
    $roles_check = $pdo->query("SHOW TABLES LIKE 'roles'")->fetch();
    $debug_info['roles_table_exists'] = !empty($roles_check);
    
    if ($debug_info['users_table_exists']) {
        $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $debug_info['total_users'] = $user_count;
        
        // Get sample users for debugging
        $sample_users = $pdo->query("SELECT username, password_hash FROM users LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        $debug_info['sample_users'] = array_map(function($user) {
            return [
                'username' => $user['username'],
                'password_hash_length' => strlen($user['password_hash']),
                'password_hash_preview' => substr($user['password_hash'], 0, 10) . '...'
            ];
        }, $sample_users);
=======
        // Update last login time
        $pdo->prepare("UPDATE user SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?")->execute([$user['user_id']]);

        // Redirect to home page after login
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
>>>>>>> b9c44d5e7e4170886bd4bbc2857f9a4d72a84279
    }
} catch (Exception $e) {
    $debug_info['table_check_error'] = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<<<<<<< HEAD
    <title>Login - Bayside Surgical Centre</title>
=======
    <title>Login - Hospital Management System</title>
>>>>>>> b9c44d5e7e4170886bd4bbc2857f9a4d72a84279
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .login-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .pulse-glow {
            animation: pulseGlow 2s infinite;
        }
        
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(102, 126, 234, 0.3); }
            50% { box-shadow: 0 0 40px rgba(102, 126, 234, 0.6); }
        }
        
        .debug-panel {
            background: rgba(0, 0, 0, 0.8);
            color: #00ff00;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<<<<<<< HEAD
<body class="login-container min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Logo and Title -->
        <div class="text-center fade-in">
            <div class="mx-auto h-20 w-20 bg-white rounded-full flex items-center justify-center pulse-glow mb-6">
                <i class="fas fa-stethoscope text-3xl text-blue-600"></i>
            </div>
            <h2 class="text-3xl font-bold text-white mb-2">Bayside Surgical Centre</h2>
            <p class="text-blue-100 text-lg">Clinic Management System</p>
        </div>

        <!-- Login Form -->
        <div class="login-card rounded-2xl shadow-2xl p-8 fade-in" style="animation-delay: 0.2s;">
            <div class="text-center mb-8">
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Welcome Back</h3>
                <p class="text-gray-600">Sign in to your account</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 fade-in">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
=======
<body class="bg-gradient-to-br from-green-50 to-teal-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="bg-white/90 backdrop-blur-lg rounded-3xl shadow-2xl p-10 animate__animated animate__fadeIn">
            <div class="text-center mb-10">
                <div class="flex justify-center mb-6">
                    <div class="bg-gradient-to-r from-green-600 to-teal-600 p-5 rounded-full shadow-lg">
                        <i class="fas fa-hospital text-4xl text-white"></i>
                    </div>
                </div>
                <h1 class="text-4xl font-bold text-gray-800 mb-2">Welcome Back</h1>
                <p class="text-teal-600 font-medium">Hospital Management System</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 animate__animated animate__shake" role="alert">
                    <p class="font-medium">Login Error</p>
                    <p><?php echo htmlspecialchars($error); ?></p>
>>>>>>> b9c44d5e7e4170886bd4bbc2857f9a4d72a84279
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        Username
                    </label>
                    <div class="relative">
<<<<<<< HEAD
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input 
                            id="username" 
                            name="username" 
                            type="text" 
                            required 
                            value="<?php echo htmlspecialchars($username ?? ''); ?>"
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                            placeholder="Enter your username"
                        >
=======
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="username" name="username" required
                               class="w-full pl-10 pr-3 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition-colors"
                               placeholder="Enter your username">
>>>>>>> b9c44d5e7e4170886bd4bbc2857f9a4d72a84279
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Password
                    </label>
                    <div class="relative">
<<<<<<< HEAD
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input 
                            id="password" 
                            name="password" 
                            type="password" 
                            required
                            class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                            placeholder="Enter your password"
                        >
                    </div>
                </div>

                <div>
                    <button 
                        type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center"
                    >
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign In
                    </button>
                </div>
            </form>

            <!-- Demo Credentials -->
            <div class="mt-8 p-4 bg-blue-50 rounded-lg">
                <h4 class="text-sm font-medium text-blue-800 mb-2">Demo Credentials:</h4>
                <div class="text-xs text-blue-700 space-y-1">
                    <div><strong>Admin:</strong> admin / admin123</div>
                    <div><strong>Doctor:</strong> drjones / doctor123</div>
                    <div><strong>Staff:</strong> nurseamy / staff123</div>
=======
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" required
                               class="w-full pl-10 pr-3 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition-colors"
                               placeholder="Enter your password">
                    </div>
                </div>

                <button type="submit" 
                        class="w-full bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white font-semibold py-3 px-4 rounded-xl transition duration-300 transform hover:scale-[1.02] flex items-center justify-center space-x-2 shadow-lg">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </button>
            </form>

            <?php if (ENVIRONMENT === 'development'): ?>
            <div class="mt-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                <p class="text-sm text-gray-600 font-medium mb-2">Demo Credentials:</p>
                <div class="space-y-1 text-sm text-gray-500">
                    <p><span class="font-medium">Admin:</span> admin / password</p>
                    <p><span class="font-medium">Doctor:</span> doctor / password</p>
                    <p><span class="font-medium">Nurse:</span> nurse / password</p>
>>>>>>> b9c44d5e7e4170886bd4bbc2857f9a4d72a84279
                </div>
            </div>
            <?php endif; ?>

<<<<<<< HEAD
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account? 
                    <a href="signup.php" class="text-blue-600 hover:text-blue-800 font-medium">
                        Contact Administrator
=======
            <div class="mt-6 pt-6 border-t border-gray-200 text-center">
                <p class="text-gray-600 text-sm">
                    Don't have an account? 
                    <a href="signup.php" class="text-teal-600 hover:text-teal-700 font-semibold transition-colors">
                        Sign up here
>>>>>>> b9c44d5e7e4170886bd4bbc2857f9a4d72a84279
                    </a>
                </p>
            </div>
        </div>

        <!-- Debug Panel (only show if there's an error or debug info) -->
        <?php if (!empty($debug_info) || !empty($error)): ?>
        <div class="login-card rounded-2xl shadow-2xl p-4 fade-in">
            <h4 class="text-sm font-medium text-gray-800 mb-2">Debug Information:</h4>
            <div class="debug-panel p-3 rounded">
                <pre><?php echo htmlspecialchars(print_r($debug_info, true)); ?></pre>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center fade-in" style="animation-delay: 0.4s;">
            <p class="text-blue-200 text-sm">
                © 2024 Bayside Surgical Centre. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        // Add focus effects
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-blue-500');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-blue-500');
                });
            });
        });
    </script>
</body>
</html>
