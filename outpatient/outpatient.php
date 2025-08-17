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
$date_filter = isset($_GET['date']) ? trim($_GET['date']) : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.unique_patient_code LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(ov.visit_datetime) = ?";
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
        FROM outpatient_visits ov 
        JOIN patients p ON ov.patient_id = p.patient_id 
        JOIN users u ON ov.doctor_id = u.user_id 
        $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Get outpatient visits with pagination
    $sql = "
        SELECT ov.*, p.first_name, p.last_name, p.unique_patient_code, p.phone,
               u.full_name as doctor_name
        FROM outpatient_visits ov 
        JOIN patients p ON ov.patient_id = p.patient_id 
        JOIN users u ON ov.doctor_id = u.user_id 
        $where_clause
        ORDER BY ov.visit_datetime DESC 
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get today's visits count for stats
    $today_sql = "SELECT COUNT(*) FROM outpatient_visits WHERE DATE(visit_datetime) = CURRENT_DATE";
    $today_count = $pdo->query($today_sql)->fetchColumn();

    // Get this week's visits count
    $week_sql = "SELECT COUNT(*) FROM outpatient_visits WHERE YEARWEEK(visit_datetime) = YEARWEEK(CURRENT_DATE)";
    $week_count = $pdo->query($week_sql)->fetchColumn();

    // Get total visits count
    $total_visits_sql = "SELECT COUNT(*) FROM outpatient_visits";
    $total_visits_count = $pdo->query($total_visits_sql)->fetchColumn();

} catch (PDOException $e) {
    $visits = [];
    $total_pages = 0;
    $today_count = $week_count = $total_visits_count = 0;
    error_log("Outpatient Visits Query Error: " . $e->getMessage());
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 fade-in">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Outpatient Management</h1>
            <p class="text-gray-600">Manage patient visits and medical records</p>
        </div>
        <a href="visit_add.php" class="mt-4 md:mt-0 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-300 flex items-center">
            <i class="fas fa-notes-medical mr-2"></i>
            Record New Visit
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
            <div class="flex items-center">
                <div class="bg-blue-100 p-3 rounded-full mr-4">
                    <i class="fas fa-calendar-day text-blue-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Today's Visits</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $today_count; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
            <div class="flex items-center">
                <div class="bg-green-100 p-3 rounded-full mr-4">
                    <i class="fas fa-calendar-week text-green-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">This Week</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $week_count; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
            <div class="flex items-center">
                <div class="bg-purple-100 p-3 rounded-full mr-4">
                    <i class="fas fa-stethoscope text-purple-600 text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Visits</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $total_visits_count; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8 fade-in">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                <label class="block text-sm font-medium text-gray-700 mb-2">Visit Date</label>
                <input 
                    type="date" 
                    name="date" 
                    value="<?php echo htmlspecialchars($date_filter); ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>
            
            <div class="flex items-end space-x-2">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300">
                    Search
                </button>
                <a href="outpatient.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Outpatient Visits Table -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">Patient Visits</h2>
            <p class="text-gray-600 text-sm">Showing <?php echo count($visits); ?> of <?php echo $total_records; ?> visits</p>
        </div>

        <?php if (empty($visits)): ?>
            <div class="text-center py-12">
                <i class="fas fa-notes-medical text-gray-400 text-6xl mb-4"></i>
                <h3 class="text-xl font-medium text-gray-600 mb-2">No visits found</h3>
                <p class="text-gray-500 mb-6">
                    <?php echo (!empty($search) || !empty($date_filter)) ? 'Try adjusting your search criteria.' : 'Get started by recording your first patient visit.'; ?>
                </p>
                <?php if (empty($search) && empty($date_filter)): ?>
                    <a href="visit_add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-300">
                        Record First Visit
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visit Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diagnosis</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($visits as $visit): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="bg-green-100 p-2 rounded-full mr-3">
                                        <i class="fas fa-user text-green-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            ID: <?php echo htmlspecialchars($visit['unique_patient_code']); ?>
                                        </div>
                                        <?php if ($visit['phone']): ?>
                                            <div class="text-sm text-gray-500">
                                                <i class="fas fa-phone mr-1"></i>
                                                <?php echo htmlspecialchars($visit['phone']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($visit['doctor_name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo date('M j, Y', strtotime($visit['visit_datetime'])); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo date('g:i A', strtotime($visit['visit_datetime'])); ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 max-w-xs truncate">
                                    <?php echo htmlspecialchars($visit['diagnosis'] ?: 'No diagnosis recorded'); ?>
                                </div>
                                <?php if ($visit['lab_requests']): ?>
                                    <div class="text-xs text-blue-600 mt-1">
                                        <i class="fas fa-flask mr-1"></i>
                                        Lab tests requested
                                    </div>
                                <?php endif; ?>
                                <?php if ($visit['prescription']): ?>
                                    <div class="text-xs text-green-600 mt-1">
                                        <i class="fas fa-pills mr-1"></i>
                                        Prescription given
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="visit_view.php?id=<?php echo $visit['visit_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900 transition-colors" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="visit_edit.php?id=<?php echo $visit['visit_id']; ?>" 
                                       class="text-green-600 hover:text-green-900 transition-colors" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="deleteVisit(<?php echo $visit['visit_id']; ?>)" 
                                            class="text-red-600 hover:text-red-900 transition-colors" title="Delete">
                                        <i class="fas fa-trash"></i>
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
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo urlencode($date_filter); ?>" 
                               class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo urlencode($date_filter); ?>" 
                               class="px-3 py-2 text-sm font-medium rounded-md <?php echo $i == $page ? 'bg-blue-600 text-white' : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&date=<?php echo urlencode($date_filter); ?>" 
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
function deleteVisit(visitId) {
    if (confirm('Are you sure you want to delete this visit record? This action cannot be undone.')) {
        window.location.href = `visit_delete.php?id=${visitId}`;
    }
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