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

// Handle bill actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_bill'])) {
            $stmt = $pdo->prepare("
                INSERT INTO `bill` (patient_id, amount, description, status) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['patient_id'],
                $_POST['amount'],
                $_POST['description'],
                $_POST['status']
            ]);

            $_SESSION['success'] = "Bill added successfully";
            header("Location: manage.php");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Delete Action
if (isset($_POST['delete_bill'])) {
    try {
        $bill_id = $_POST['bill_id'];
        $stmt = $pdo->prepare("DELETE FROM `bill` WHERE bill_id = ?");
        $stmt->execute([$bill_id]);

        $_SESSION['success'] = "Bill deleted successfully";
        header("Location: manage.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error deleting bill: " . $e->getMessage();
    }
}

// Handle Edit Action
if (isset($_POST['edit_bill'])) {
    try {
        $bill_id = $_POST['bill_id'];
        $stmt = $pdo->prepare("
            UPDATE `bill` 
            SET patient_id = ?,
                amount = ?,
                description = ?,
                status = ?
            WHERE bill_id = ?
        ");
        $stmt->execute([
            $_POST['patient_id'],
            $_POST['amount'],
            $_POST['description'],
            $_POST['status'],
            $bill_id
        ]);

        $_SESSION['success'] = "Bill updated successfully";
        header("Location: manage.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error updating bill: " . $e->getMessage();
    }
}

// Fetch all bills with patient names
try {
    $stmt = $pdo->query("
        SELECT b.*, p.name as patient_name 
        FROM `bill` b
        JOIN `patient` p ON b.patient_id = p.patient_id
        ORDER BY b.bill_date DESC
    ");
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching bills: " . $e->getMessage();
    $bills = [];
}

// Fetch patients for dropdown
try {
    $stmt = $pdo->query("SELECT patient_id, name FROM patient ORDER BY name");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $patients = [];
    error_log("Error fetching patients: " . $e->getMessage());
}
?>

<div class="container mx-auto px-6 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Bill Management</h1>
        <button onclick="document.getElementById('addBillModal').classList.remove('hidden')"
                class="bg-teal-500 hover:bg-teal-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300">
            <i class="fas fa-plus mr-2"></i>Add Bill
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

    <!-- Bill List -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($bills as $bill): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($bill['patient_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            $<?php echo number_format($bill['amount'], 2); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('Y-m-d H:i', strtotime($bill['bill_date'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $bill['status'] === 'Paid' ? 'bg-green-100 text-green-800' : 
                                        ($bill['status'] === 'Partial' ? 'bg-yellow-100 text-yellow-800' : 
                                        'bg-red-100 text-red-800'); ?>">
                                <?php echo htmlspecialchars($bill['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($bill['description']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="editBill(<?php echo htmlspecialchars(json_encode($bill)); ?>)"
                                    class="text-teal-600 hover:text-teal-900 mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteBill('<?php echo $bill['bill_id']; ?>')"
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

<!-- Add Bill Modal -->
<div id="addBillModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add Bill</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_bill" value="1">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Patient</label>
                    <select name="patient_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['patient_id']; ?>">
                                <?php echo htmlspecialchars($patient['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Amount</label>
                    <input type="number" name="amount" step="0.01" required min="0"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="Unpaid">Unpaid</option>
                        <option value="Partial">Partial</option>
                        <option value="Paid">Paid</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('addBillModal').classList.add('hidden')"
                            class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-teal-500 text-white px-4 py-2 rounded-md hover:bg-teal-600">
                        Add Bill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bill Modal -->
<div id="editBillModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Edit Bill</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="edit_bill" value="1">
                <input type="hidden" name="bill_id" id="edit_bill_id">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Patient</label>
                    <select name="patient_id" id="edit_patient_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['patient_id']; ?>">
                                <?php echo htmlspecialchars($patient['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Amount</label>
                    <input type="number" name="amount" id="edit_amount" step="0.01" required min="0"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="edit_description" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="edit_status" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="Unpaid">Unpaid</option>
                        <option value="Partial">Partial</option>
                        <option value="Paid">Paid</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()"
                            class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        Cancel
                    </button>
                    <button type="submit"
                            class="bg-teal-500 text-white px-4 py-2 rounded-md hover:bg-teal-600">
                        Update Bill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editBill(bill) {
    document.getElementById('edit_bill_id').value = bill.bill_id;
    document.getElementById('edit_patient_id').value = bill.patient_id;
    document.getElementById('edit_amount').value = bill.amount;
    document.getElementById('edit_description').value = bill.description;
    document.getElementById('edit_status').value = bill.status;
    
    document.getElementById('editBillModal').classList.remove('hidden');
}

function deleteBill(billId) {
    if (confirm('Are you sure you want to delete this bill?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="delete_bill" value="1">
            <input type="hidden" name="bill_id" value="${billId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeEditModal() {
    document.getElementById('editBillModal').classList.add('hidden');
}
</script>

<?php 
include_once '../includes/footer.php';
ob_end_flush();
?> 