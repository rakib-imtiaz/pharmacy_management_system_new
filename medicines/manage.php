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

// Handle medicine actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_medicine'])) {
            $stmt = $pdo->prepare("
                INSERT INTO `medicine` (name, description, dosage_form, stock_quantity, category) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['dosage_form'],
                $_POST['stock_quantity'],
                $_POST['category']
            ]);

            $_SESSION['success'] = "Medicine added successfully";
            header("Location: manage.php");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete Action
if (isset($_POST['delete_medicine'])) {
    try {
        $medicine_id = $_POST['medicine_id'];
        $stmt = $pdo->prepare("DELETE FROM `medicine` WHERE medicine_id = ?");
        $stmt->execute([$medicine_id]);

        $_SESSION['success'] = "Medicine deleted successfully";
        header("Location: manage.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error deleting medicine: " . $e->getMessage();
    }
}

// Handle Edit Action
if (isset($_POST['edit_medicine'])) {
    try {
        $medicine_id = $_POST['medicine_id'];
        $stmt = $pdo->prepare("
            UPDATE `medicine` 
            SET name = ?, 
                description = ?, 
                dosage_form = ?, 
                stock_quantity = ?, 
                category = ?
            WHERE medicine_id = ?
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $_POST['dosage_form'],
            $_POST['stock_quantity'],
            $_POST['category'],
            $medicine_id
        ]);

        $_SESSION['success'] = "Medicine updated successfully";
        header("Location: manage.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error updating medicine: " . $e->getMessage();
    }
}

// Fetch all medicines
try {
    $stmt = $pdo->query("
        SELECT * FROM `medicine`
        ORDER BY name
    ");
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching medicines: " . $e->getMessage();
    $medicines = [];
}
?>

<div class="container mx-auto px-6 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Medicine Management</h1>
        <button onclick="document.getElementById('addMedicineModal').classList.remove('hidden')"
                class="bg-teal-500 hover:bg-teal-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300">
            <i class="fas fa-plus mr-2"></i>Add Medicine
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

    <!-- Medicine List -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dosage Form</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($medicines as $medicine): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($medicine['name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($medicine['description']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($medicine['dosage_form']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($medicine['stock_quantity']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($medicine['category']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="editMedicine(<?php echo htmlspecialchars(json_encode($medicine)); ?>)"
                                    class="text-teal-600 hover:text-teal-900 mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteMedicine('<?php echo $medicine['medicine_id']; ?>')"
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

<!-- Add Medicine Modal -->
<div id="addMedicineModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add Medicine</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_medicine" value="1">

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
                    <label class="block text-sm font-medium text-gray-700">Dosage Form</label>
                    <input type="text" name="dosage_form" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Stock Quantity</label>
                    <input type="number" name="stock_quantity" required min="0"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Category</label>
                    <input type="text" name="category" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('addMedicineModal').classList.add('hidden')"
                            class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-teal-500 text-white px-4 py-2 rounded-md hover:bg-teal-600">
                        Add Medicine
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Medicine Modal -->
<div id="editMedicineModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Edit Medicine</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="edit_medicine" value="1">
                <input type="hidden" name="medicine_id" id="edit_medicine_id">

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
                    <label class="block text-sm font-medium text-gray-700">Dosage Form</label>
                    <input type="text" name="dosage_form" id="edit_dosage_form" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Stock Quantity</label>
                    <input type="number" name="stock_quantity" id="edit_stock_quantity" required min="0"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Category</label>
                    <input type="text" name="category" id="edit_category" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()"
                            class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-teal-500 text-white px-4 py-2 rounded-md hover:bg-teal-600">
                        Update Medicine
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editMedicine(medicine) {
    document.getElementById('edit_medicine_id').value = medicine.medicine_id;
    document.getElementById('edit_name').value = medicine.name;
    document.getElementById('edit_description').value = medicine.description;
    document.getElementById('edit_dosage_form').value = medicine.dosage_form;
    document.getElementById('edit_stock_quantity').value = medicine.stock_quantity;
    document.getElementById('edit_category').value = medicine.category;
    
    document.getElementById('editMedicineModal').classList.remove('hidden');
}

function deleteMedicine(medicineId) {
    if (confirm('Are you sure you want to delete this medicine?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="delete_medicine" value="1">
            <input type="hidden" name="medicine_id" value="${medicineId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeEditModal() {
    document.getElementById('editMedicineModal').classList.add('hidden');
}
</script>

<?php 
include_once '../includes/footer.php';
ob_end_flush();
?> 