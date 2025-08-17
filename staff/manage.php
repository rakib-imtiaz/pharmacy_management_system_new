<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/db_connect.php';
session_start();

// Verify admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login.php");
    exit;
}

include_once '../includes/header.php';

// Handle staff actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_staff'])) {
            $pdo->beginTransaction();
            
            // Add new staff member
            $stmt = $pdo->prepare("
                INSERT INTO `user` (username, password, role) 
                VALUES (?, ?, ?)
            ");
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $_POST['username'],
                $password_hash,
                $_POST['role']
            ]);
            $user_id = $pdo->lastInsertId();

            // Add staff details based on role
            if ($_POST['role'] === 'Doctor') {
                $stmt = $pdo->prepare("
                    INSERT INTO `doctor` (user_id, name, specialization, contact_info, department_id) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $_POST['name'],
                    $_POST['specialization'],
                    $_POST['contact_number'],
                    $_POST['department_id']
                ]);
            } elseif ($_POST['role'] === 'Nurse') {
                $stmt = $pdo->prepare("
                    INSERT INTO `nurse` (user_id, name, contact_info, department_id) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $_POST['name'],
                    $_POST['contact_number'],
                    $_POST['department_id']
                ]);
            }
            
            $pdo->commit();
            $_SESSION['success'] = "Staff member added successfully";
            header("Location: manage.php");
            exit;
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error adding staff member: " . $e->getMessage();
        error_log($error);
    }
}

// Handle Delete Action
if (isset($_POST['delete_staff'])) {
    try {
        $user_id = $_POST['user_id'];
        $pdo->beginTransaction();
        
        // Delete from user table (cascades to doctor/nurse)
        $stmt = $pdo->prepare("DELETE FROM `user` WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        $_SESSION['success'] = "Staff member deleted successfully";
        header("Location: manage.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error deleting staff member: " . $e->getMessage();
    }
}

// Handle Edit Action
if (isset($_POST['edit_staff'])) {
    try {
        $user_id = $_POST['user_id'];
        $role = $_POST['role'];
        $name = $_POST['name'];
        $contact_info = $_POST['contact_info'];
        $department_id = $_POST['department_id'] ?: null;
        
        $pdo->beginTransaction();
        
        if ($role === 'Doctor') {
            $stmt = $pdo->prepare("
                UPDATE `doctor` 
                SET name = ?, contact_info = ?, department_id = ?,
                    specialization = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$name, $contact_info, $department_id, $_POST['specialization'], $user_id]);
        } elseif ($role === 'Nurse') {
            $stmt = $pdo->prepare("
                UPDATE `nurse` 
                SET name = ?, contact_info = ?, department_id = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$name, $contact_info, $department_id, $user_id]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Staff member updated successfully";
        header("Location: manage.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error updating staff member: " . $e->getMessage();
    }
}

// Fetch all staff members
try {
    $stmt = $pdo->query("
        SELECT u.user_id, u.username, u.role, 
               COALESCE(d.name, n.name) as name,
               d.specialization, 
               COALESCE(d.department_id, n.department_id) as department_id,
               dep.name as department_name,
               COALESCE(d.contact_info, n.contact_info) as contact_info
        FROM `user` u
        LEFT JOIN `doctor` d ON u.user_id = d.user_id
        LEFT JOIN `nurse` n ON u.user_id = n.user_id
        LEFT JOIN `department` dep ON 
            CASE 
                WHEN d.department_id IS NOT NULL THEN d.department_id = dep.department_id
                WHEN n.department_id IS NOT NULL THEN n.department_id = dep.department_id
                ELSE FALSE
            END
        WHERE u.role IN ('Doctor', 'Nurse', 'Receptionist')
        ORDER BY u.role, name
    ");
    $staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug
    if (empty($staff_members)) {
        error_log("No staff members found in query");
    }
} catch (PDOException $e) {
    $error = "Error fetching staff members: " . $e->getMessage();
    error_log($error);
    $staff_members = [];
}

// Fetch departments for dropdown
try {
    $stmt = $pdo->query("SELECT department_id, name FROM department ORDER BY name");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $departments = [];
    error_log("Error fetching departments: " . $e->getMessage());
}
?>

<div class="container mx-auto px-6 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Staff Management</h1>
        <button onclick="document.getElementById('addStaffModal').classList.remove('hidden')"
                class="bg-teal-500 hover:bg-teal-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300">
            <i class="fas fa-plus mr-2"></i>Add Staff
        </button>
    </div>

    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['success']); ?></span>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Staff List -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Specialization/Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($staff_members as $staff): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($staff['name']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($staff['username']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $staff['role'] === 'Doctor' ? 'bg-green-100 text-green-800' : 
                                        ($staff['role'] === 'Nurse' ? 'bg-blue-100 text-blue-800' : 
                                        'bg-gray-100 text-gray-800'); ?>">
                                <?php echo htmlspecialchars($staff['role']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php 
                                if ($staff['role'] === 'Doctor') {
                                    echo htmlspecialchars($staff['specialization'] ?? 'N/A');
                                } else {
                                    echo htmlspecialchars($staff['department_name'] ?? 'N/A');
                                }
                            ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($staff['contact_info'] ?? 'N/A'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="editStaff('<?php echo $staff['user_id']; ?>', 
                                                      '<?php echo $staff['role']; ?>', 
                                                      '<?php echo htmlspecialchars($staff['name']); ?>', 
                                                      '<?php echo htmlspecialchars($staff['contact_info']); ?>', 
                                                      '<?php echo $staff['department_id'] ?? ''; ?>', 
                                                      '<?php echo htmlspecialchars($staff['specialization'] ?? ''); ?>')"
                                    class="text-teal-600 hover:text-teal-900 mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteStaff('<?php echo $staff['user_id']; ?>')"
                                    class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add New Staff Member</h3>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            <option value="Doctor">Doctor</option>
                            <option value="Nurse">Nurse</option>
                            <option value="Receptionist">Receptionist</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" name="name" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" name="username" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" name="password" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                        <input type="tel" name="contact_number" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    </div>

                    <div id="doctorFields" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Specialization</label>
                            <input type="text" name="specialization"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                    </div>

                    <div id="nurseFields" class="space-y-4 hidden">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Department</label>
                            <input type="text" name="department"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('addStaffModal').classList.add('hidden')"
                                class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" name="add_staff"
                                class="bg-teal-500 text-white px-4 py-2 rounded-md hover:bg-teal-600">
                            Add Staff
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Edit Modal -->
    <div id="editStaffModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Edit Staff Member</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="edit_staff" value="1">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <input type="hidden" name="role" id="edit_role">

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" name="name" id="edit_name" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Contact Info</label>
                        <input type="text" name="contact_info" id="edit_contact_info" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Department</label>
                        <select name="department_id" id="edit_department_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="editDoctorFields">
                        <label class="block text-sm font-medium text-gray-700">Specialization</label>
                        <input type="text" name="specialization" id="edit_specialization"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditModal()"
                                class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-teal-500 text-white px-4 py-2 rounded-md hover:bg-teal-600">
                            Update Staff
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Confirm Delete</h3>
                <p class="text-sm text-gray-500">Are you sure you want to delete this staff member? This action cannot be undone.</p>
                <form method="POST" class="mt-4">
                    <input type="hidden" name="delete_staff" value="1">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeDeleteModal()"
                                class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600">
                            Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelector('select[name="role"]').addEventListener('change', function(e) {
    const doctorFields = document.getElementById('doctorFields');
    const nurseFields = document.getElementById('nurseFields');
    
    if (e.target.value === 'Doctor') {
        doctorFields.classList.remove('hidden');
        nurseFields.classList.add('hidden');
    } else if (e.target.value === 'Nurse') {
        doctorFields.classList.add('hidden');
        nurseFields.classList.remove('hidden');
    } else {
        doctorFields.classList.add('hidden');
        nurseFields.classList.add('hidden');
    }
});

function editStaff(userId, role, name, contactInfo, departmentId, specialization) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_contact_info').value = contactInfo;
    document.getElementById('edit_department_id').value = departmentId || '';
    
    if (role === 'Doctor') {
        document.getElementById('edit_specialization').value = specialization || '';
        document.getElementById('editDoctorFields').classList.remove('hidden');
    } else {
        document.getElementById('editDoctorFields').classList.add('hidden');
    }
    
    document.getElementById('editStaffModal').classList.remove('hidden');
}

function deleteStaff(userId) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('deleteConfirmModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editStaffModal').classList.add('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').classList.add('hidden');
}

// Update the onclick handlers in your table
document.querySelectorAll('[data-edit]').forEach(button => {
    button.addEventListener('click', (e) => {
        const data = e.target.dataset;
        editStaff(
            data.userId,
            data.role,
            data.name,
            data.contactInfo,
            data.departmentId,
            data.specialization
        );
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>
<?php
ob_end_flush();
?> 