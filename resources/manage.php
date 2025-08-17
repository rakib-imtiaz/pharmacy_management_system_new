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

// Handle resource actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_resource'])) {
            // Add new resource
            $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;

            $stmt = $pdo->prepare("
                INSERT INTO `resource` (type, name, department_id, status, details) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['type'],
                $_POST['name'],
                $department_id,
                $_POST['status'],
                $_POST['details']
            ]);

            $_SESSION['success'] = "Resource added successfully";
            header("Location: manage.php");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete Action
if (isset($_POST['delete_resource'])) {
    try {
        $resource_id = $_POST['resource_id'];
        $stmt = $pdo->prepare("DELETE FROM `resource` WHERE resource_id = ?");
        $stmt->execute([$resource_id]);

        $_SESSION['success'] = "Resource deleted successfully";
        header("Location: manage.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error deleting resource: " . $e->getMessage();
    }
}

// Handle Edit Action
if (isset($_POST['edit_resource'])) {
    try {
        $resource_id = $_POST['resource_id'];
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;

        $stmt = $pdo->prepare("
            UPDATE `resource` 
            SET type = ?, 
                name = ?, 
                department_id = ?, 
                status = ?, 
                details = ?
            WHERE resource_id = ?
        ");
        $stmt->execute([
            $_POST['type'],
            $_POST['name'],
            $department_id,
            $_POST['status'],
            $_POST['details'],
            $resource_id
        ]);

        $_SESSION['success'] = "Resource updated successfully";
        header("Location: manage.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error updating resource: " . $e->getMessage();
    }
}

// Fetch all resources
try {
    $stmt = $pdo->query("
        SELECT r.resource_id, r.type, r.name, r.status, r.details, 
               dep.name as department_name
        FROM `resource` r
        LEFT JOIN `department` dep ON r.department_id = dep.department_id
        ORDER BY r.type, r.name
    ");
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching resources: " . $e->getMessage();
    $resources = [];
}

// Fetch departments for dropdown
try {
    $stmt = $pdo->query("SELECT department_id, name FROM department ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
    error_log("Error fetching departments: " . $e->getMessage());
}
?>

<div class="container mx-auto px-6 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Resource Management</h1>
        <button onclick="document.getElementById('addResourceModal').classList.remove('hidden')"
            class="bg-teal-500 hover:bg-teal-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300">
            <i class="fas fa-plus mr-2"></i>Add Resource
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

    <!-- Resource List -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($resources as $resource): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($resource['type']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($resource['name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($resource['department_name'] ?? 'N/A'); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($resource['status']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($resource['details']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="editResource('<?php echo $resource['resource_id']; ?>', 
                                                          '<?php echo htmlspecialchars($resource['type']); ?>', 
                                                          '<?php echo htmlspecialchars($resource['name']); ?>', 
                                                          '<?php echo $resource['department_id'] ?? ''; ?>', 
                                                          '<?php echo htmlspecialchars($resource['status']); ?>', 
                                                          '<?php echo htmlspecialchars($resource['details']); ?>')"
                                class="text-teal-600 hover:text-teal-900 mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteResource('<?php echo $resource['resource_id']; ?>')"
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

<!-- Add Resource Modal -->
<div id="addResourceModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add Resource</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_resource" value="1">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Type</label>
                    <select name="type" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="WARD">Ward</option>
                        <option value="ROOM">Room</option>
                        <option value="BED">Bed</option>
                        <option value="EQUIPMENT">Equipment</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Department</label>
                    <select name="department_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>">
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="AVAILABLE">Available</option>
                        <option value="OCCUPIED">Occupied</option>
                        <option value="MAINTENANCE">Maintenance</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Details</label>
                    <textarea name="details" rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('addResourceModal').classList.add('hidden')"
                        class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                        class="bg-teal-500 text-white px-4 py-2 rounded-md hover:bg-teal-600">
                        Add Resource
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Resource Modal -->
<div id="editResourceModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Edit Resource</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="edit_resource" value="1">
                <input type="hidden" name="resource_id" id="edit_resource_id">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Type</label>
                    <select name="type" id="edit_type" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="WARD">Ward</option>
                        <option value="ROOM">Room</option>
                        <option value="BED">Bed</option>
                        <option value="EQUIPMENT">Equipment</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" id="edit_name" required
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

                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="edit_status" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="AVAILABLE">Available</option>
                        <option value="OCCUPIED">Occupied</option>
                        <option value="MAINTENANCE">Maintenance</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Details</label>
                    <textarea name="details" id="edit_details" rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()"
                        class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                        class="bg-teal-500 text-white px-4 py-2 rounded-md hover:bg-teal-600">
                        Update Resource
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editResource(resourceId, type, name, departmentId, status, details) {
        document.getElementById('edit_resource_id').value = resourceId;
        document.getElementById('edit_type').value = type;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_department_id').value = departmentId || '';
        document.getElementById('edit_status').value = status;
        document.getElementById('edit_details').value = details;

        document.getElementById('editResourceModal').classList.remove('hidden');
    }

    function deleteResource(resourceId) {
        if (confirm('Are you sure you want to delete this resource?')) {
            document.getElementById('delete_resource_id').value = resourceId;
            document.getElementById('deleteResourceForm').submit();
        }
    }

    function closeEditModal() {
        document.getElementById('editResourceModal').classList.add('hidden');
    }
</script>

<form id="deleteResourceForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_resource" value="1">
    <input type="hidden" name="resource_id" id="delete_resource_id">
</form>

<?php include_once '../includes/footer.php'; ?>
ob_end_flush();
?>