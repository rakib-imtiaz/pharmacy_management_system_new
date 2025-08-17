<?php
require_once '../includes/db_connect.php';
session_start();

// Verify login status
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include_once '../includes/header.php';

// Search and filter functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.unique_patient_code LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    if ($status_filter === 'paid') {
        $where_conditions[] = "i.paid = TRUE";
    } elseif ($status_filter === 'unpaid') {
        $where_conditions[] = "i.paid = FALSE";
    }
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(i.invoice_date) = ?";
    $params[] = $date_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Get total count
    $count_sql = "
        SELECT COUNT(*) 
        FROM invoices i 
        JOIN patients p ON i.patient_id = p.patient_id 
        $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Get invoices with pagination
    $sql = "
        SELECT i.*, p.first_name, p.last_name, p.unique_patient_code, p.phone,
               ov.visit_datetime, ov.diagnosis
        FROM invoices i 
        JOIN patients p ON i.patient_id = p.patient_id 
        LEFT JOIN outpatient_visits ov ON i.visit_id = ov.visit_id
        $where_clause
        ORDER BY i.invoice_date DESC, i.invoice_id DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get billing statistics
    $total_revenue_sql = "SELECT SUM(total_amount) FROM invoices WHERE paid = TRUE";
    $total_revenue = $pdo->query($total_revenue_sql)->fetchColumn() ?: 0;

    $pending_amount_sql = "SELECT SUM(total_amount) FROM invoices WHERE paid = FALSE";
    $pending_amount = $pdo->query($pending_amount_sql)->fetchColumn() ?: 0;

    $today_revenue_sql = "SELECT SUM(total_amount) FROM invoices WHERE paid = TRUE AND DATE(invoice_date) = CURRENT_DATE";
    $today_revenue = $pdo->query($today_revenue_sql)->fetchColumn() ?: 0;

    $total_invoices_sql = "SELECT COUNT(*) FROM invoices";
    $total_invoices = $pdo->query($total_invoices_sql)->fetchColumn() ?: 0;

} catch (PDOException $e) {
    $invoices = [];
    $total_pages = 0;
    $total_revenue = $pending_amount = $today_revenue = $total_invoices = 0;
    error_log("Invoices Query Error: " . $e->getMessage());
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 fade-in">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Billing Management</h1>
            <p class="text-gray-600">Manage invoices and payment tracking</p>
        </div>
        <a href="invoice_add.php" class="mt-4 md:mt-0 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-300 flex items-center">
            <i class="fas fa-file-invoice-dollar mr-2"></i>
            Create Invoice
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
            <div class="flex items-center">
                <div class="bg-green-100 p-3 rounded-full mr-4">
                    <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($total_revenue, 2); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
            <div class="flex items-center">
                <div class="bg-red-100 p-3 rounded-full mr-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Pending Amount</p>
                    <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($pending_amount, 2); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
            <div class="flex items-center">
                <div class="bg-blue-100 p-3 rounded-full mr-4">
                    <i class="fas fa-calendar-day text-blue-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Today's Revenue</p>
                    <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($today_revenue, 2); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
            <div class="flex items-center">
                <div class="bg-purple-100 p-3 rounded-full mr-4">
                    <i class="fas fa-file-invoice text-purple-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Invoices</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $total_invoices; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8 fade-in">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Patient</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Patient name or ID..." 
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Status</label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="unpaid" <?php echo $status_filter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Invoice Date</label>
                <input 
                    type="date" 
                    name="date" 
                    value="<?php echo htmlspecialchars($date_filter); ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>
            
            <div class="flex items-end space-x-2">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300">
                    Filter
                </button>
                <a href="invoices.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Invoices Table -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">Invoices</h2>
            <p class="text-gray-600 text-sm">Showing <?php echo count($invoices); ?> of <?php echo $total_records; ?> invoices</p>
        </div>

        <?php if (empty($invoices)): ?>
            <div class="text-center py-12">
                <i class="fas fa-file-invoice text-gray-400 text-6xl mb-4"></i>
                <h3 class="text-xl font-medium text-gray-600 mb-2">No invoices found</h3>
                <p class="text-gray-500 mb-6">
                    <?php echo (!empty($search) || !empty($status_filter) || !empty($date_filter)) ? 'Try adjusting your filters.' : 'Get started by creating your first invoice.'; ?>
                </p>
                <?php if (empty($search) && empty($status_filter) && empty($date_filter)): ?>
                    <a href="invoice_add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-300">
                        Create First Invoice
                    </a>
    <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="overflow-x-auto">
                <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visit Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($invoices as $invoice): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    #<?php echo str_pad($invoice['invoice_id'], 4, '0', STR_PAD_LEFT); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($invoice['invoice_date'])); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="bg-blue-100 p-2 rounded-full mr-3">
                                        <i class="fas fa-user text-blue-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            ID: <?php echo htmlspecialchars($invoice['unique_patient_code']); ?>
                                        </div>
                                        <?php if ($invoice['phone']): ?>
                                            <div class="text-sm text-gray-500">
                                                <i class="fas fa-phone mr-1"></i>
                                                <?php echo htmlspecialchars($invoice['phone']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($invoice['visit_datetime']): ?>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo date('M j, Y', strtotime($invoice['visit_datetime'])); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo date('g:i A', strtotime($invoice['visit_datetime'])); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-sm text-gray-500">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-lg font-bold text-gray-900">
                                    $<?php echo number_format($invoice['total_amount'], 2); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $invoice['paid'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $invoice['paid'] ? 'Paid' : 'Unpaid'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="invoice_view.php?id=<?php echo $invoice['invoice_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900 transition-colors" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="invoice_edit.php?id=<?php echo $invoice['invoice_id']; ?>" 
                                       class="text-green-600 hover:text-green-900 transition-colors" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php if (!$invoice['paid']): ?>
                                        <button onclick="markAsPaid(<?php echo $invoice['invoice_id']; ?>)" 
                                                class="text-green-600 hover:text-green-900 transition-colors" title="Mark as Paid">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="printInvoice(<?php echo $invoice['invoice_id']; ?>)" 
                                            class="text-purple-600 hover:text-purple-900 transition-colors" title="Print">
                                        <i class="fas fa-print"></i>
                                            </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-white px-6 py-3 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> results
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>" 
                               class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>" 
                               class="px-3 py-2 text-sm font-medium rounded-md <?php echo $i == $page ? 'bg-blue-600 text-white' : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&date=<?php echo urlencode($date_filter); ?>" 
                               class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function markAsPaid(invoiceId) {
    if (confirm('Mark this invoice as paid?')) {
        window.location.href = `invoice_mark_paid.php?id=${invoiceId}`;
    }
}

function printInvoice(invoiceId) {
    window.open(`invoice_print.php?id=${invoiceId}`, '_blank');
}

// Add staggered animation
document.addEventListener('DOMContentLoaded', function() {
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>
