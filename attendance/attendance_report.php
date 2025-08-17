<?php
// Start the session and include necessary files
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

// Check if admin role (attendance management is admin-only)
if (!hasRole('Admin')) {
    setFlashMessage('error', 'You do not have permission to access attendance reports.');
    header("Location: ../index.php");
    exit();
}

// Set up report filters
$reportType = $_GET['report_type'] ?? 'monthly';
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Prepare date conditions
$dateConditions = '';
$params = [];

switch ($reportType) {
    case 'daily':
        $dateConditions = "AND DATE(a.date) = ?";
        $params[] = $startDate;
        break;
    case 'weekly':
        $dateConditions = "AND DATE(a.date) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        break;
    case 'monthly':
    default:
        $dateConditions = "AND MONTH(a.date) = ? AND YEAR(a.date) = ?";
        $params[] = $month;
        $params[] = $year;
        break;
    case 'yearly':
        $dateConditions = "AND YEAR(a.date) = ?";
        $params[] = $year;
        break;
    case 'custom':
        $dateConditions = "AND DATE(a.date) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        break;
}

// Add user filter if provided
if ($userId > 0) {
    $dateConditions .= " AND a.user_id = ?";
    $params[] = $userId;
}

// Query for attendance summary
$summarySql = "
    SELECT 
        COUNT(DISTINCT a.user_id) as total_employees,
        COUNT(DISTINCT CASE WHEN a.check_in IS NOT NULL AND a.check_out IS NOT NULL THEN a.attendance_id ELSE NULL END) as complete_records,
        COUNT(DISTINCT CASE WHEN a.check_in IS NOT NULL AND a.check_out IS NULL THEN a.attendance_id ELSE NULL END) as incomplete_records,
        COUNT(DISTINCT CASE WHEN a.check_in > CONCAT(a.date, ' 09:15:00') THEN a.attendance_id ELSE NULL END) as late_entries,
        AVG(TIMESTAMPDIFF(HOUR, a.check_in, a.check_out)) as avg_hours
    FROM attendance a
    WHERE 1=1 $dateConditions
";

$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

// Query for department-wise attendance (using roles as departments)
$deptSql = "
    SELECT 
        r.role_name as department,
        COUNT(DISTINCT a.user_id) as employee_count,
        COUNT(DISTINCT a.attendance_id) as attendance_records,
        COUNT(DISTINCT CASE WHEN a.check_in > CONCAT(a.date, ' 09:15:00') THEN a.attendance_id ELSE NULL END) as late_entries
    FROM attendance a
    JOIN users u ON a.user_id = u.user_id
    JOIN roles r ON u.role_id = r.role_id
    WHERE 1=1 $dateConditions
    GROUP BY r.role_name
    ORDER BY employee_count DESC
";

$deptStmt = $pdo->prepare($deptSql);
$deptStmt->execute($params);
$departmentStats = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Get daily attendance data for charts (up to 31 days for monthly view)
$dailyChartSql = "
    SELECT 
        DATE(a.date) as attendance_date,
        COUNT(DISTINCT a.user_id) as employee_count,
        COUNT(DISTINCT CASE WHEN a.check_in > CONCAT(a.date, ' 09:15:00') THEN a.attendance_id ELSE NULL END) as late_entries
    FROM attendance a
    WHERE 1=1 $dateConditions
    GROUP BY DATE(a.date)
    ORDER BY DATE(a.date)
";

$dailyChartStmt = $pdo->prepare($dailyChartSql);
$dailyChartStmt->execute($params);
$dailyChartData = $dailyChartStmt->fetchAll(PDO::FETCH_ASSOC);

// Get employee list for filtering
$employeeSql = "SELECT u.user_id, u.username, u.full_name FROM users u ORDER BY u.full_name";
$employeeStmt = $pdo->query($employeeSql);
$employees = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);

// Get detailed attendance records (limited to 100 for performance)
$detailedSql = "
    SELECT 
        u.full_name,
        r.role_name,
        DATE(a.date) as attendance_date,
        a.check_in,
        a.check_out,
        CASE 
            WHEN a.check_out IS NULL THEN NULL
            ELSE TIMESTAMPDIFF(HOUR, a.check_in, a.check_out) 
        END as hours_worked,
        CASE 
            WHEN a.check_in > CONCAT(a.date, ' 09:15:00') THEN 'Late'
            ELSE 'On Time'
        END as status
    FROM attendance a
    JOIN users u ON a.user_id = u.user_id
    JOIN roles r ON u.role_id = r.role_id
    WHERE 1=1 $dateConditions
    ORDER BY a.date DESC, u.full_name
    LIMIT 100
";

$detailedStmt = $pdo->prepare($detailedSql);
$detailedStmt->execute($params);
$detailedRecords = $detailedStmt->fetchAll(PDO::FETCH_ASSOC);

// Page title and include header
$pageTitle = "Attendance Reports";
include_once '../includes/header.php';

// Function to get month name
function getMonthName($month) {
    return date("F", mktime(0, 0, 0, $month, 1));
}

// Format the date range for display
function formatDateRangeForDisplay($reportType, $month, $year, $startDate, $endDate) {
    switch ($reportType) {
        case 'daily':
            return date('F j, Y', strtotime($startDate));
        case 'weekly':
        case 'custom':
            return date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate));
        case 'monthly':
            return getMonthName($month) . ' ' . $year;
        case 'yearly':
            return $year;
        default:
            return getMonthName($month) . ' ' . $year;
    }
}

// Get report title
$reportDateRange = formatDateRangeForDisplay($reportType, $month, $year, $startDate, $endDate);
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-6">Attendance Reports</h1>
    
    <!-- Flash Messages -->
    <?php displayFlashMessages(); ?>
    
    <!-- Report Controls -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form action="attendance_report.php" method="get" class="flex flex-wrap gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                <select name="report_type" id="report_type" class="border rounded px-3 py-2" onchange="toggleDateInputs(this.value)">
                    <option value="daily" <?= $reportType == 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="weekly" <?= $reportType == 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= $reportType == 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    <option value="yearly" <?= $reportType == 'yearly' ? 'selected' : '' ?>>Yearly</option>
                    <option value="custom" <?= $reportType == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>
            </div>
            
            <!-- Month Selector (for monthly) -->
            <div id="month_selector" class="<?= $reportType == 'monthly' ? 'block' : 'hidden' ?>">
                <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                <select name="month" class="border rounded px-3 py-2">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>" <?= $month == $i ? 'selected' : '' ?>>
                            <?= getMonthName($i) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <!-- Year Selector (for monthly and yearly) -->
            <div id="year_selector" class="<?= in_array($reportType, ['monthly', 'yearly']) ? 'block' : 'hidden' ?>">
                <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                <select name="year" class="border rounded px-3 py-2">
                    <?php for ($i = date('Y') - 5; $i <= date('Y') + 1; $i++): ?>
                        <option value="<?= $i ?>" <?= $year == $i ? 'selected' : '' ?>>
                            <?= $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <!-- Date Selectors (for daily, weekly, custom) -->
            <div id="date_selector" class="<?= in_array($reportType, ['daily', 'custom']) ? 'block' : 'hidden' ?>">
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date" name="start_date" value="<?= $startDate ?>" class="border rounded px-3 py-2">
            </div>
            
            <div id="end_date_selector" class="<?= in_array($reportType, ['weekly', 'custom']) ? 'block' : 'hidden' ?>">
                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" name="end_date" value="<?= $endDate ?>" class="border rounded px-3 py-2">
            </div>
            
            <!-- Employee Selector -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                <select name="user_id" class="border rounded px-3 py-2">
                    <option value="0">All Employees</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?= $employee['user_id'] ?>" <?= $userId == $employee['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($employee['full_name'] ?: $employee['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="self-end">
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Generate Report</button>
            </div>
        </form>
    </div>
    
    <!-- Report Header -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Attendance Report: <?= htmlspecialchars($reportDateRange) ?></h2>
            <p class="text-gray-600">
                <?= $userId > 0 ? 'Employee: ' . htmlspecialchars(array_values(array_filter($employees, function($e) use ($userId) { 
                    return $e['user_id'] == $userId; 
                }))[0]['full_name'] ?? 'Selected Employee') : 'All Employees' ?>
            </p>
        </div>
        <div>
            <button onclick="window.print()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                <i class="fas fa-print mr-1"></i> Print
            </button>
            <button onclick="exportTableToCSV('attendance_report_<?= $reportDateRange ?>.csv')" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 ml-2">
                <i class="fas fa-download mr-1"></i> Export to CSV
            </button>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Total Employees</h3>
            <p class="text-2xl font-bold text-blue-600"><?= $summary['total_employees'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Complete Records</h3>
            <p class="text-2xl font-bold text-green-600"><?= $summary['complete_records'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Missing Check-outs</h3>
            <p class="text-2xl font-bold text-yellow-600"><?= $summary['incomplete_records'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Late Entries</h3>
            <p class="text-2xl font-bold text-red-600"><?= $summary['late_entries'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Avg. Hours/Day</h3>
            <p class="text-2xl font-bold text-indigo-600"><?= number_format($summary['avg_hours'] ?? 0, 1) ?></p>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Daily Attendance Chart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">Daily Attendance Trends</h3>
            <canvas id="dailyAttendanceChart" height="300"></canvas>
        </div>
        
        <!-- Department Stats Chart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold mb-4">Department Attendance</h3>
            <?php if (count($departmentStats) > 0): ?>
                <canvas id="departmentChart" height="300"></canvas>
            <?php else: ?>
                <p class="text-gray-500 text-center py-12">No department data available for the selected period.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Department Stats Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <h3 class="text-lg font-semibold px-6 py-4 border-b">Department Statistics</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employees</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Records</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Late Entries</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Late %</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($departmentStats) > 0): ?>
                        <?php foreach ($departmentStats as $dept): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($dept['department']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= $dept['employee_count'] ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= $dept['attendance_records'] ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= $dept['late_entries'] ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                    $latePercentage = $dept['attendance_records'] > 0 
                                        ? ($dept['late_entries'] / $dept['attendance_records']) * 100 
                                        : 0;
                                    ?>
                                    <div class="text-sm text-gray-900"><?= number_format($latePercentage, 1) ?>%</div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No department data available for the selected period.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Detailed Records Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <h3 class="text-lg font-semibold px-6 py-4 border-b">Detailed Attendance Records</h3>
        <div class="overflow-x-auto">
            <table id="attendanceTable" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check In</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check Out</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hours</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($detailedRecords) > 0): ?>
                        <?php foreach ($detailedRecords as $record): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($record['full_name']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($record['role_name']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= date('M d, Y', strtotime($record['attendance_date'])) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : 'N/A' ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : 'N/A' ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= $record['hours_worked'] !== null ? number_format($record['hours_worked'], 1) : 'N/A' ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 <?= $record['status'] === 'Late' ? 'text-red-600' : 'text-green-600' ?>">
                                        <?= $record['status'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No detailed records found for the selected period.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($detailedRecords) >= 100): ?>
            <div class="px-6 py-3 bg-gray-50 text-center text-sm text-gray-500">
                Note: Showing first 100 records. Export to CSV for complete data.
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
<script>
// Toggle date input fields based on report type
function toggleDateInputs(reportType) {
    // Hide all selectors first
    document.getElementById('month_selector').style.display = 'none';
    document.getElementById('year_selector').style.display = 'none';
    document.getElementById('date_selector').style.display = 'none';
    document.getElementById('end_date_selector').style.display = 'none';
    
    // Show appropriate selectors based on report type
    switch (reportType) {
        case 'daily':
            document.getElementById('date_selector').style.display = 'block';
            break;
        case 'weekly':
            document.getElementById('date_selector').style.display = 'block';
            document.getElementById('end_date_selector').style.display = 'block';
            break;
        case 'monthly':
            document.getElementById('month_selector').style.display = 'block';
            document.getElementById('year_selector').style.display = 'block';
            break;
        case 'yearly':
            document.getElementById('year_selector').style.display = 'block';
            break;
        case 'custom':
            document.getElementById('date_selector').style.display = 'block';
            document.getElementById('end_date_selector').style.display = 'block';
            break;
    }
}

// Function to export table data to CSV
function exportTableToCSV(filename) {
    var csv = [];
    var rows = document.querySelectorAll("#attendanceTable tr");
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        
        for (var j = 0; j < cols.length; j++) {
            // Get text content and clean it up
            let cellText = cols[j].innerText.trim().replace(/\n/g, ' ');
            // Quote fields with commas
            if (cellText.includes(',')) {
                cellText = '"' + cellText + '"';
            }
            row.push(cellText);
        }
        
        csv.push(row.join(","));        
    }

    // Download CSV file
    downloadCSV(csv.join("\n"), filename);
}

function downloadCSV(csv, filename) {
    var csvFile;
    var downloadLink;

    // Create CSV file
    csvFile = new Blob([csv], {type: "text/csv"});

    // Create download link
    downloadLink = document.createElement("a");

    // Add file name
    downloadLink.download = filename;

    // Create object URL for the CSV file
    downloadLink.href = window.URL.createObjectURL(csvFile);

    // Hide link
    downloadLink.style.display = "none";

    // Add link to DOM
    document.body.appendChild(downloadLink);

    // Click download link
    downloadLink.click();
    
    // Clean up
    setTimeout(function() {
        document.body.removeChild(downloadLink);
        window.URL.revokeObjectURL(downloadLink.href);
    }, 500);
}

// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Daily Attendance Chart
    const dailyCtx = document.getElementById('dailyAttendanceChart').getContext('2d');
    
    // Parse data for the chart
    const dates = <?= json_encode(array_column($dailyChartData, 'attendance_date')) ?>;
    const attendance = <?= json_encode(array_column($dailyChartData, 'employee_count')) ?>;
    const lateEntries = <?= json_encode(array_column($dailyChartData, 'late_entries')) ?>;
    
    const dailyChart = new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Attendance',
                data: attendance,
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 2,
                tension: 0.1
            }, {
                label: 'Late Entries',
                data: lateEntries,
                backgroundColor: 'rgba(239, 68, 68, 0.2)',
                borderColor: 'rgba(239, 68, 68, 1)',
                borderWidth: 2,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Department Stats Chart
    <?php if (count($departmentStats) > 0): ?>
    const deptCtx = document.getElementById('departmentChart').getContext('2d');
    
    const departments = <?= json_encode(array_column($departmentStats, 'department')) ?>;
    const employeeCounts = <?= json_encode(array_column($departmentStats, 'employee_count')) ?>;
    const deptLateEntries = <?= json_encode(array_column($departmentStats, 'late_entries')) ?>;
    
    const deptChart = new Chart(deptCtx, {
        type: 'bar',
        data: {
            labels: departments,
            datasets: [{
                label: 'Employees',
                data: employeeCounts,
                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                borderWidth: 0
            }, {
                label: 'Late Entries',
                data: deptLateEntries,
                backgroundColor: 'rgba(239, 68, 68, 0.6)',
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<style>
@media print {
    .bg-white { background-color: white !important; }
    .shadow, .shadow-md { box-shadow: none !important; }
    button, form, .no-print { display: none !important; }
    body { font-size: 12pt; }
    table { page-break-inside: avoid; }
    a { text-decoration: none !important; color: black !important; }
}
</style>

<?php include_once '../includes/footer.php'; ?>
