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
$selected_patient = null;
$selected_visit = null;
$services = [];
$invoice_items = [];

// Add initial empty invoice item
$invoice_items[] = [
    'service_name' => '',
    'description' => '',
    'quantity' => 1,
    'unit_price' => 0
];

// Get services for dropdown
try {
    $services_query = "SELECT service_id, service_name, price FROM services ORDER BY service_name";
    $stmt = $pdo->query($services_query);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Services Query Error: " . $e->getMessage());
}

// Check if patient ID is provided
if (isset($_GET['patient_id']) && is_numeric($_GET['patient_id'])) {
    try {
        $patient_id = $_GET['patient_id'];
        $sql = "SELECT patient_id, unique_patient_code, first_name, last_name FROM patients WHERE patient_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$patient_id]);
        $selected_patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Patient Query Error: " . $e->getMessage());
    }
}

// Check if visit ID is provided
if (isset($_GET['visit_id']) && is_numeric($_GET['visit_id'])) {
    try {
        $visit_id = $_GET['visit_id'];
        $sql = "SELECT ov.*, p.unique_patient_code, p.first_name, p.last_name, p.patient_id,
                      u.full_name AS doctor_name
                FROM outpatient_visits ov
                JOIN patients p ON ov.patient_id = p.patient_id
                JOIN users u ON ov.doctor_id = u.user_id
                WHERE ov.visit_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$visit_id]);
        $selected_visit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($selected_visit) {
            $selected_patient = [
                'patient_id' => $selected_visit['patient_id'],
                'unique_patient_code' => $selected_visit['unique_patient_code'],
                'first_name' => $selected_visit['first_name'],
                'last_name' => $selected_visit['last_name']
            ];
            
            // Check if the visit already has an invoice
            $check_sql = "SELECT invoice_id FROM invoices WHERE visit_id = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$visit_id]);
            $existing_invoice = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_invoice) {
                $errors[] = "This visit already has an invoice (#" . $existing_invoice['invoice_id'] . "). Please edit the existing invoice.";
            } else {
                // Add consultation service by default for visits
                $consultation_service = null;
                foreach ($services as $service) {
                    if (strtolower($service['service_name']) === 'consultation') {
                        $consultation_service = $service;
                        break;
                    }
                }
                
                if ($consultation_service) {
                    $invoice_items[0] = [
                        'service_id' => $consultation_service['service_id'],
                        'service_name' => $consultation_service['service_name'],
                        'description' => 'Medical consultation',
                        'quantity' => 1,
                        'unit_price' => $consultation_service['price']
                    ];
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Visit Query Error: " . $e->getMessage());
    }
}

// Process patient search
$search_results = [];
$search_query = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
    try {
        $search_sql = "SELECT patient_id, unique_patient_code, first_name, last_name, phone 
                       FROM patients 
                       WHERE unique_patient_code LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?
                       ORDER BY last_name, first_name
                       LIMIT 10";
        $stmt = $pdo->prepare($search_sql);
        $search_param = "%$search_query%";
        $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Patient Search Error: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = isset($_POST['patient_id']) ? trim($_POST['patient_id']) : '';
    $invoice_date = isset($_POST['invoice_date']) ? trim($_POST['invoice_date']) : '';
    $visit_id = isset($_POST['visit_id']) ? trim($_POST['visit_id']) : null;
    $payment_status = isset($_POST['payment_status']) ? (trim($_POST['payment_status']) === 'paid') : false;
    
    // Get invoice items
    $service_ids = isset($_POST['service_id']) ? $_POST['service_id'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];
    $unit_prices = isset($_POST['unit_price']) ? $_POST['unit_price'] : [];
    
    // Validation
    if (empty($patient_id)) {
        $errors[] = "Patient is required";
    }

    if (empty($invoice_date)) {
        $errors[] = "Invoice date is required";
    }
    
    if (empty($service_ids) || count($service_ids) === 0) {
        $errors[] = "At least one service must be added to the invoice";
    }
    
    // Calculate total amount
    $total_amount = 0;
    $items_to_save = [];
    
    for ($i = 0; $i < count($service_ids); $i++) {
        if (empty($service_ids[$i])) continue;
        
        $quantity = isset($quantities[$i]) ? intval($quantities[$i]) : 1;
        $unit_price = isset($unit_prices[$i]) ? floatval($unit_prices[$i]) : 0;
        
        if ($quantity <= 0) {
            $errors[] = "Quantity must be greater than 0";
            continue;
        }
        
        if ($unit_price <= 0) {
            $errors[] = "Unit price must be greater than 0";
            continue;
        }
        
        $item_total = $quantity * $unit_price;
        $total_amount += $item_total;
        
        $items_to_save[] = [
            'service_id' => $service_ids[$i],
            'quantity' => $quantity,
            'unit_price' => $unit_price
        ];
    }
    
    if (empty($items_to_save)) {
        $errors[] = "At least one valid service item must be added to the invoice";
    }

    // If no errors, proceed with saving invoice
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert invoice
            $sql = "INSERT INTO invoices (patient_id, visit_id, invoice_date, total_amount, paid) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $patient_id,
                $visit_id ?: null,
                $invoice_date,
                $total_amount,
                $payment_status
            ]);

            $invoice_id = $pdo->lastInsertId();
            
            // Insert invoice items
            $item_sql = "INSERT INTO invoice_items (invoice_id, service_id, quantity, unit_price) 
                        VALUES (?, ?, ?, ?)";
            $item_stmt = $pdo->prepare($item_sql);
            
            foreach ($items_to_save as $item) {
                $item_stmt->execute([
                    $invoice_id,
                    $item['service_id'],
                    $item['quantity'],
                    $item['unit_price']
                ]);
            }
            
            // Log the action
            $audit_sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)";
            $audit_stmt = $pdo->prepare($audit_sql);
            $audit_stmt->execute([
                $_SESSION['user_id'],
                'Create Invoice',
                'invoices',
                $invoice_id,
                "Created invoice for patient_id=$patient_id with total amount $total_amount"
            ]);
            
            $pdo->commit();

            // Redirect to view the new invoice
            header("Location: view_invoice.php?id=$invoice_id&new=1");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Invoice Creation Error: " . $e->getMessage());
        }
    }
}

include_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 fade-in">
        <div class="flex items-center mb-4">
            <a href="invoices.php" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Create New Invoice</h1>
        </div>
        <p class="text-gray-600">Generate a new invoice for a patient</p>
    </div>

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Patient Selection -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Select Patient</h2>
                    <p class="text-sm text-gray-600">Choose a patient for this invoice</p>
                </div>

                <div class="p-6">
                    <?php if ($selected_patient): ?>
                        <!-- Selected Patient Display -->
                        <div class="mb-4">
                            <p class="text-sm text-gray-500 mb-1">Selected Patient:</p>
                            <div class="flex items-center p-4 border border-green-200 rounded-lg bg-green-50">
                                <div class="bg-green-100 p-2 rounded-full mr-3">
                                    <i class="fas fa-user-check text-green-600"></i>
                                </div>
                                <div class="flex-grow">
                                    <div class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($selected_patient['first_name'] . ' ' . $selected_patient['last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        ID: <?php echo htmlspecialchars($selected_patient['unique_patient_code']); ?>
                                    </div>
                                </div>
                                <a href="add_invoice.php" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Patient Search -->
                        <form action="add_invoice.php" method="GET" class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Search by Name, ID, or Phone
                            </label>
                            <div class="flex">
                                <input 
                                    type="text" 
                                    name="search" 
                                    value="<?php echo htmlspecialchars($search_query); ?>"
                                    class="flex-grow px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Enter name, ID, or phone number"
                                >
                                <button 
                                    type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r-lg transition-colors duration-300"
                                >
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <?php if (!empty($_GET) && !isset($_GET['patient_id']) && !isset($_GET['visit_id'])): ?>
                                <div class="mt-2">
                                    <a href="add_invoice.php" class="text-sm text-blue-600 hover:text-blue-800">
                                        Clear search
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>

                        <!-- Search Results -->
                        <?php if (!empty($search_results)): ?>
                            <div class="mt-4">
                                <h3 class="text-sm font-medium text-gray-700 mb-2">Search Results:</h3>
                                <div class="space-y-2">
                                    <?php foreach ($search_results as $patient): ?>
                                        <a 
                                            href="add_invoice.php?patient_id=<?php echo $patient['patient_id']; ?>" 
                                            class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-blue-50 transition-colors"
                                        >
                                            <div class="bg-blue-100 p-2 rounded-full mr-3">
                                                <i class="fas fa-user text-blue-600"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-600">
                                                    ID: <?php echo htmlspecialchars($patient['unique_patient_code']); ?>
                                                    <?php if (!empty($patient['phone'])): ?>
                                                        <span class="mx-1">|</span> 
                                                        <i class="fas fa-phone mr-1"></i> <?php echo htmlspecialchars($patient['phone']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php elseif (isset($_GET['search'])): ?>
                            <div class="mt-4 text-center py-4 border border-dashed border-gray-300 rounded-lg">
                                <i class="fas fa-user-slash text-gray-400 text-4xl mb-2"></i>
                                <p class="text-gray-600 mb-2">No patients found</p>
                                <a href="../patients/patient_add.php" class="text-blue-600 hover:text-blue-800">
                                    Register new patient
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($selected_visit): ?>
                        <!-- Visit Information -->
                        <div class="mt-6">
                            <h3 class="text-sm font-medium text-gray-700 mb-2">Visit Information:</h3>
                            <div class="p-4 border border-blue-200 rounded-lg bg-blue-50">
                                <div class="font-medium text-gray-900">
                                    Visit on <?php echo date('M j, Y', strtotime($selected_visit['visit_datetime'])); ?>
                                </div>
                                <div class="text-sm text-gray-600 mt-1">
                                    With Dr. <?php echo htmlspecialchars($selected_visit['doctor_name']); ?>
                                </div>
                                <?php if (!empty($selected_visit['diagnosis'])): ?>
                                    <div class="text-sm mt-3">
                                        <span class="font-medium">Diagnosis:</span> 
                                        <?php echo htmlspecialchars(substr($selected_visit['diagnosis'], 0, 100) . (strlen($selected_visit['diagnosis']) > 100 ? '...' : '')); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Invoice Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">Invoice Details</h2>
                    <p class="text-sm text-gray-600">Add services and charges</p>
                </div>

                <div class="p-6">
                    <form method="POST" id="invoiceForm" class="space-y-6">
                        <!-- Hidden Patient ID field -->
                        <?php if ($selected_patient): ?>
                            <input type="hidden" name="patient_id" value="<?php echo $selected_patient['patient_id']; ?>">
                        <?php endif; ?>
                        
                        <!-- Hidden Visit ID field -->
                        <?php if ($selected_visit): ?>
                            <input type="hidden" name="visit_id" value="<?php echo $selected_visit['visit_id']; ?>">
                        <?php endif; ?>
                        
                        <!-- Invoice Date and Status -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="invoice_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    Invoice Date <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="date" 
                                    id="invoice_date" 
                                    name="invoice_date" 
                                    value="<?php echo date('Y-m-d'); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    required
                                    <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                                >
                            </div>
                            
                            <div>
                                <label for="payment_status" class="block text-sm font-medium text-gray-700 mb-2">
                                    Payment Status
                                </label>
                                <select 
                                    id="payment_status" 
                                    name="payment_status" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                                >
                                    <option value="unpaid" selected>Unpaid</option>
                                    <option value="paid">Paid</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Invoice Items -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Invoice Items</h3>
                                <button 
                                    type="button" 
                                    id="addItemButton" 
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center"
                                    <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                                >
                                    <i class="fas fa-plus-circle mr-1"></i>
                                    Add Item
                                </button>
                            </div>
                            
                            <div id="invoiceItems" class="space-y-4">
                                <!-- Initial invoice item -->
                                <?php foreach ($invoice_items as $index => $item): ?>
                                <div class="invoice-item border border-gray-200 rounded-lg p-4 relative">
                                    <div class="grid grid-cols-12 gap-4">
                                        <div class="col-span-12 md:col-span-6">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Service <span class="text-red-500">*</span>
                                            </label>
                                            <select 
                                                name="service_id[]" 
                                                class="service-select w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                required
                                                <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                                                data-index="<?php echo $index; ?>"
                                            >
                                                <option value="">Select Service</option>
                                                <?php foreach ($services as $service): ?>
                                                    <option 
                                                        value="<?php echo $service['service_id']; ?>"
                                                        data-price="<?php echo $service['price']; ?>"
                                                        <?php echo (isset($item['service_id']) && $item['service_id'] == $service['service_id']) ? 'selected' : ''; ?>
                                                    >
                                                        <?php echo htmlspecialchars($service['service_name']); ?> - $<?php echo number_format($service['price'], 2); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-span-4 md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Quantity
                                            </label>
                                            <input 
                                                type="number" 
                                                name="quantity[]" 
                                                value="<?php echo $item['quantity'] ?? 1; ?>"
                                                min="1" 
                                                class="quantity-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                                                data-index="<?php echo $index; ?>"
                                            >
                                        </div>
                                        
                                        <div class="col-span-8 md:col-span-3">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Unit Price
                                            </label>
                                            <div class="relative">
                                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                                <input 
                                                    type="number" 
                                                    name="unit_price[]" 
                                                    value="<?php echo $item['unit_price'] ?? 0; ?>" 
                                                    min="0" 
                                                    step="0.01"
                                                    class="unit-price-input w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                    <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                                                    data-index="<?php echo $index; ?>"
                                                >
                                            </div>
                                        </div>
                                        
                                        <div class="col-span-11 md:col-span-1 flex items-end">
                                            <button 
                                                type="button" 
                                                class="remove-item text-red-600 hover:text-red-800 px-2 py-1"
                                                <?php echo empty($selected_patient) || count($invoice_items) <= 1 ? 'disabled' : ''; ?>
                                            >
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Total Amount -->
                            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-medium text-gray-700">Total Amount:</span>
                                    <span id="totalAmount" class="text-2xl font-bold text-blue-600">$0.00</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200">
                            <button 
                                type="submit" 
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center"
                                <?php echo empty($selected_patient) ? 'disabled' : ''; ?>
                            >
                                <i class="fas fa-save mr-2"></i>
                                Create Invoice
                            </button>
                            <a 
                                href="invoices.php" 
                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-300 flex items-center justify-center"
                            >
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addItemButton = document.getElementById('addItemButton');
    const invoiceItems = document.getElementById('invoiceItems');
    const totalAmountDisplay = document.getElementById('totalAmount');
    
    // Function to update item total when quantity or price changes
    function updateTotals() {
        let total = 0;
        
        // Get all service rows
        const quantityInputs = document.querySelectorAll('.quantity-input');
        const unitPriceInputs = document.querySelectorAll('.unit-price-input');
        
        // Calculate total
        for (let i = 0; i < quantityInputs.length; i++) {
            const quantity = parseFloat(quantityInputs[i].value) || 0;
            const unitPrice = parseFloat(unitPriceInputs[i].value) || 0;
            total += quantity * unitPrice;
        }
        
        // Update total display
        totalAmountDisplay.textContent = '$' + total.toFixed(2);
    }
    
    // Add event listener to quantity and price inputs
    function setupEventListeners() {
        document.querySelectorAll('.quantity-input, .unit-price-input').forEach(input => {
            input.addEventListener('input', updateTotals);
        });
        
        document.querySelectorAll('.service-select').forEach(select => {
            select.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const price = selectedOption.getAttribute('data-price') || 0;
                const index = this.getAttribute('data-index');
                const priceInput = document.querySelector(`.unit-price-input[data-index="${index}"]`);
                if (priceInput) {
                    priceInput.value = price;
                    updateTotals();
                }
            });
        });
        
        document.querySelectorAll('.remove-item').forEach(button => {
            button.addEventListener('click', function() {
                if (document.querySelectorAll('.invoice-item').length > 1) {
                    this.closest('.invoice-item').remove();
                    updateTotals();
                }
            });
        });
    }
    
    // Initialize event listeners
    setupEventListeners();
    
    // Calculate initial total
    updateTotals();
    
    // Add new item button
    addItemButton.addEventListener('click', function() {
        const newIndex = document.querySelectorAll('.invoice-item').length;
        const itemTemplate = `
            <div class="invoice-item border border-gray-200 rounded-lg p-4 relative">
                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12 md:col-span-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Service <span class="text-red-500">*</span>
                        </label>
                        <select 
                            name="service_id[]" 
                            class="service-select w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                            data-index="${newIndex}"
                        >
                            <option value="">Select Service</option>
                            ${Array.from(document.querySelector('.service-select').options)
                                .map(opt => {
                                    if (!opt.value) return `<option value="">${opt.textContent}</option>`;
                                    return `<option value="${opt.value}" data-price="${opt.getAttribute('data-price')}">${opt.textContent}</option>`;
                                })
                                .join('')
                            }
                        </select>
                    </div>
                    
                    <div class="col-span-4 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Quantity
                        </label>
                        <input 
                            type="number" 
                            name="quantity[]" 
                            value="1"
                            min="1" 
                            class="quantity-input w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            data-index="${newIndex}"
                        >
                    </div>
                    
                    <div class="col-span-8 md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Unit Price
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                            <input 
                                type="number" 
                                name="unit_price[]" 
                                value="0" 
                                min="0" 
                                step="0.01"
                                class="unit-price-input w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                data-index="${newIndex}"
                            >
                        </div>
                    </div>
                    
                    <div class="col-span-11 md:col-span-1 flex items-end">
                        <button 
                            type="button" 
                            class="remove-item text-red-600 hover:text-red-800 px-2 py-1"
                        >
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Add to DOM
        invoiceItems.insertAdjacentHTML('beforeend', itemTemplate);
        
        // Setup event listeners for new item
        setupEventListeners();
    });
    
    // Form validation
    const form = document.getElementById('invoiceForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate items
            const serviceSelects = document.querySelectorAll('select[name="service_id[]"]');
            for (let i = 0; i < serviceSelects.length; i++) {
                if (!serviceSelects[i].value) {
                    isValid = false;
                    alert('Please select a service for all invoice items');
                    break;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }
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
