<?php
require_once 'includes/db_connect.php';
session_start();
include_once 'includes/header.php';

// Fetch user details
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT a.appointment_id) as total_appointments,
           COUNT(DISTINCT mr.record_id) as total_medical_records,
           MAX(u.last_login) as last_login_date
    FROM `user` u
    LEFT JOIN `appointment` a ON u.user_id = a.patient_id
    LEFT JOIN `medical_record` mr ON u.user_id = mr.patient_id
    WHERE u.user_id = ?
    GROUP BY u.user_id
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_profile'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception("Current password is incorrect");
            }

            // Validate new password
            if (!empty($new_password)) {
                if (strlen($new_password) < 8) {
                    throw new Exception("New password must be at least 8 characters long");
                }
                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match");
                }
                
                // Update password
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE `user` SET password = ? WHERE user_id = ?");
                $stmt->execute([$password_hash, $_SESSION['user_id']]);
                
                // Log password change
                $stmt = $pdo->prepare("
                    INSERT INTO `audit_log` (user_id, timestamp, action, table_affected, record_id)
                    VALUES (?, CURRENT_TIMESTAMP, 'PASSWORD_CHANGE', 'user', ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
            }

            $_SESSION['success'] = "Profile updated successfully";
            header("Location: profile.php");
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container mx-auto px-6 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Profile Header -->
        <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
            <div class="flex items-center space-x-6">
                <div class="bg-teal-500 p-4 rounded-full">
                    <i class="fas fa-user-circle text-4xl text-white"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($user['username']); ?></h1>
                    <p class="text-gray-600"><?php echo htmlspecialchars($user['role']); ?></p>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 animate__animated animate__shake" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 animate__animated animate__fadeIn" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['success']); ?></span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- User Statistics -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Activity Overview</h2>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Appointments</span>
                        <span class="font-semibold"><?php echo number_format($user['total_appointments']); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Medical Records</span>
                        <span class="font-semibold"><?php echo number_format($user['total_medical_records']); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Last Login</span>
                        <span class="font-semibold">
                            <?php echo date('M d, Y H:i', strtotime($user['last_login_date'])); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Change Password Form -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Change Password</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Current Password
                        </label>
                        <input type="password" id="current_password" name="current_password" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                    </div>

                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                            New Password
                        </label>
                        <input type="password" id="new_password" name="new_password" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm New Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent">
                    </div>

                    <button type="submit" name="update_profile"
                            class="w-full bg-teal-500 hover:bg-teal-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300">
                        Update Password
                    </button>
                </form>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-xl shadow-lg p-6 md:col-span-2">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Recent Activity</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Action
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Details
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            $activity_stmt = $pdo->prepare("
                                SELECT * FROM `audit_log` 
                                WHERE user_id = ? 
                                ORDER BY timestamp DESC 
                                LIMIT 5
                            ");
                            $activity_stmt->execute([$_SESSION['user_id']]);
                            while ($activity = $activity_stmt->fetch()):
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo htmlspecialchars($activity['action']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo date('M d, Y H:i', strtotime($activity['timestamp'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo htmlspecialchars($activity['table_affected']); ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password strength validation
document.getElementById('new_password').addEventListener('input', function(e) {
    const password = e.target.value;
    const strength = {
        length: password.length >= 8,
        hasNumber: /\d/.test(password),
        hasUpper: /[A-Z]/.test(password),
        hasLower: /[a-z]/.test(password),
        hasSpecial: /[!@#$%^&*]/.test(password)
    };
    
    if (Object.values(strength).every(Boolean)) {
        e.target.classList.add('border-green-500');
        e.target.classList.remove('border-red-500');
    } else {
        e.target.classList.add('border-red-500');
        e.target.classList.remove('border-green-500');
    }
});

// Password match validation
document.getElementById('confirm_password').addEventListener('input', function(e) {
    const newPassword = document.getElementById('new_password').value;
    if (e.target.value === newPassword) {
        e.target.classList.add('border-green-500');
        e.target.classList.remove('border-red-500');
    } else {
        e.target.classList.add('border-red-500');
        e.target.classList.remove('border-green-500');
    }
});
</script>

<?php include_once 'includes/footer.php'; ?> 