<?php
ob_start();
require_once '../includes/db_connect.php';
session_start();

// Verify admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login.php");
    exit;
}

include_once '../includes/header.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle department actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_department'])) {
            $stmt = $pdo->prepare("
                INSERT INTO `department` (name, description, head_doctor_id) 
                VALUES (?, ?, ?)
            ");
            $head_doctor_id = !empty($_POST['head_doctor_id']) ? $_POST['head_doctor_id'] : null;
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $head_doctor_id
            ]);

            $_SESSION['success'] = "Department added successfully";
            header("Location: manage.php");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete Action
if (isset($_POST['delete_department'])) {
    try {
        $department_id = $_POST['department_id'];
        $stmt = $pdo->prepare("DELETE FROM `department` WHERE department_id = ?");
        $stmt->execute([$department_id]);

        $_SESSION['success'] = "Department deleted successfully";
        header("Location: manage.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error deleting department: " . $e->getMessage();
    }
}

// Handle Edit Action
if (isset($_POST['edit_department'])) {
    try {
        $department_id = $_POST['department_id'];
        $head_doctor_id = !empty($_POST['head_doctor_id']) ? $_POST['head_doctor_id'] : null;
        
        $stmt = $pdo->prepare("
            UPDATE `department` 
            SET name = ?,
                description = ?,
                head_doctor_id = ?
            WHERE department_id = ?
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $head_doctor_id,
            $department_id
        ]);

        $_SESSION['success'] = "Department updated successfully";
        header("Location: manage.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error updating department: " . $e->getMessage();
    }
}

// Fetch all departments with head doctor names
try {
    $stmt = $pdo->query("
        SELECT d.*, CONCAT(doc.name, ' (', doc.specialization, ')') as head_doctor_name
        FROM `department` d
        LEFT JOIN `doctor` doc ON d.head_doctor_id = doc.doctor_id
        ORDER BY d.name
    ");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching departments: " . $e->getMessage();
    $departments = [];
}

// Fetch doctors for dropdown
try {
    $stmt = $pdo->query("
        SELECT doctor_id, CONCAT(name, ' (', specialization, ')') as full_name 
        FROM doctor 
        ORDER BY name
    ");
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $doctors = [];
    error_log("Error fetching doctors: " . $e->getMessage());
}
?>

<div class="container mx-auto px-6 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Department Management</h1>
        <button onclick="document.getElementById('addDepartmentModal').classList.remove('hidden')"
                class="bg-teal-500 hover:bg-teal-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300">
            <i class="fas fa-plus mr-2"></i>Add Department
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

    <!-- Department List -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Head Doctor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($departments as $department): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($department['name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($department['description']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($department['head_doctor_name'] ?? 'Not Assigned'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="editDepartment(<?php echo htmlspecialchars(json_encode($department)); ?>)"
                                    class="text-teal-600 hover:text-teal-900 mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteDepartment('<?php echo $department['department_id']; ?>')"
                                    class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Department Modal -->
<div id="addDepartmentModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add Department</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_department" value="1">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Head Doctor</label>
                    <select name="head_doctor_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="">Select Head Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['doctor_id']; ?>">
                                <?php echo htmlspecialchars($doctor['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('addDepartmentModal').classList.add('hidden')"
                            class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-teal-500 text-white px-4 py-2 rounded-md hover:bg-teal-600">
                        Add Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div id="editDepartmentModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Edit Department</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="edit_department" value="1">
                <input type="hidden" name="department_id" id="edit_department_id">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" id="edit_name" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="edit_description" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Head Doctor</label>
                    <select name="head_doctor_id" id="edit_head_doctor_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="">Select Head Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['doctor_id']; ?>">
                                <?php echo htmlspecialchars($doctor['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()"
                            class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-teal-500 text-white px-4 py-2 rounded-md hover:bg-teal-600">
                        Update Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editDepartment(department) {
    document.getElementById('edit_department_id').value = department.department_id;
    document.getElementById('edit_name').value = department.name;
    document.getElementById('edit_description').value = department.description;
    document.getElementById('edit_head_doctor_id').value = department.head_doctor_id || '';
    
    document.getElementById('editDepartmentModal').classList.remove('hidden');
}

function deleteDepartment(departmentId) {
    if (confirm('Are you sure you want to delete this department?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="delete_department" value="1">
            <input type="hidden" name="department_id" value="${departmentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeEditModal() {
    document.getElementById('editDepartmentModal').classList.add('hidden');
}
</script>

<?php 
include_once '../includes/footer.php';
ob_end_flush();
?> 