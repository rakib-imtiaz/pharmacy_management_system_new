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
    setFlashMessage('error', 'You do not have permission to access the attendance management system.');
    header("Location: ../index.php");
    exit();
}

// Handle check-in/check-out actions
if (isset($_POST['action'])) {
    $user_id = $_POST['user_id'] ?? $_SESSION['user_id'];
    $date = date('Y-m-d');
    $currentTime = date('Y-m-d H:i:s');
    
    try {
        if ($_POST['action'] === 'check_in') {
            // Check if already checked in
            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
            $stmt->execute([$user_id, $date]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                setFlashMessage('error', 'Already checked in for today.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, check_in) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $date, $currentTime]);
                
                // Log the action
                logAction('Check In', 'attendance', $pdo->lastInsertId(), "User ID $user_id checked in at $currentTime");
                setFlashMessage('success', 'Check-in recorded successfully.');
            }
        } elseif ($_POST['action'] === 'check_out') {
            // Find today's record and update check_out
            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
            $stmt->execute([$user_id, $date]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$record) {
                setFlashMessage('error', 'No check-in record found for today.');
            } elseif ($record['check_out'] !== null) {
                setFlashMessage('error', 'Already checked out for today.');
            } else {
                $stmt = $pdo->prepare("UPDATE attendance SET check_out = ? WHERE attendance_id = ?");
                $stmt->execute([$currentTime, $record['attendance_id']]);
                
                // Log the action
                logAction('Check Out', 'attendance', $record['attendance_id'], "User ID $user_id checked out at $currentTime");
                setFlashMessage('success', 'Check-out recorded successfully.');
            }
        }
    } catch (PDOException $e) {
        setFlashMessage('error', 'Database error: ' . $e->getMessage());
        error_log('Attendance error: ' . $e->getMessage());
    }
    
    // Redirect to prevent form resubmission
    header("Location: attendance.php");
    exit();
}

// Prepare date filters
$dateFilter = $_GET['date_filter'] ?? 'today';
$customStartDate = $_GET['start_date'] ?? '';
$customEndDate = $_GET['end_date'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$whereClause = '';
$params = [];

// Build the date filter SQL
switch ($dateFilter) {
    case 'today':
        $whereClause .= " AND a.date = ?";
        $params[] = date('Y-m-d');
        break;
    case 'yesterday':
        $whereClause .= " AND a.date = ?";
        $params[] = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'this_week':
        $whereClause .= " AND a.date >= ? AND a.date <= ?";
        $params[] = date('Y-m-d', strtotime('monday this week'));
        $params[] = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'last_week':
        $whereClause .= " AND a.date >= ? AND a.date <= ?";
        $params[] = date('Y-m-d', strtotime('monday last week'));
        $params[] = date('Y-m-d', strtotime('sunday last week'));
        break;
    case 'this_month':
        $whereClause .= " AND a.date >= ? AND a.date <= ?";
        $params[] = date('Y-m-01');
        $params[] = date('Y-m-t');
        break;
    case 'custom':
        if (!empty($customStartDate) && !empty($customEndDate)) {
            $whereClause .= " AND a.date >= ? AND a.date <= ?";
            $params[] = $customStartDate;
            $params[] = $customEndDate;
        }
        break;
}

// Add search query filter
if (!empty($searchQuery)) {
    $whereClause .= " AND (u.full_name LIKE ? OR u.username LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Count total records for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM attendance a
    JOIN users u ON a.user_id = u.user_id
    WHERE 1=1 $whereClause
");
$countStmt->execute($params);
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Fetch attendance records with pagination
$sql = "
    SELECT a.*, u.full_name, u.username 
    FROM attendance a
    JOIN users u ON a.user_id = u.user_id
    WHERE 1=1 $whereClause
    ORDER BY a.date DESC, u.full_name ASC
    LIMIT $offset, $recordsPerPage
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate late/missing entries statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN a.check_in > CONCAT(a.date, ' 09:15:00') THEN 1 ELSE 0 END) as late_entries,
        SUM(CASE WHEN a.check_out IS NULL THEN 1 ELSE 0 END) as missing_checkouts
    FROM attendance a
    WHERE 1=1 $whereClause
");
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Fetch users for the check-in/out form
$userStmt = $pdo->query("SELECT user_id, username, full_name FROM users ORDER BY full_name");
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

// Check if current user has already checked in/out today
$currentDate = date('Y-m-d');
$currentUserStmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$currentUserStmt->execute([$_SESSION['user_id'], $currentDate]);
$currentUserAttendance = $currentUserStmt->fetch(PDO::FETCH_ASSOC);

// Page title and include header
$pageTitle = "Attendance Management";
include_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-6">Attendance Management</h1>
    
    <!-- Flash Messages -->
    <?php displayFlashMessages(); ?>
    
    <!-- Check-In/Check-Out Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Staff Check-In/Check-Out</h2>
        
        <?php if (!$currentUserAttendance): ?>
            <form action="attendance.php" method="post" class="inline-block">
                <input type="hidden" name="action" value="check_in">
                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    Check In Now
                </button>
            </form>
        <?php elseif ($currentUserAttendance && !$currentUserAttendance['check_out']): ?>
            <form action="attendance.php" method="post" class="inline-block">
                <input type="hidden" name="action" value="check_out">
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Check Out Now
                </button>
            </form>
            <p class="text-sm text-gray-600 mt-2">
                You checked in at: <?= date('h:i A', strtotime($currentUserAttendance['check_in'])) ?>
            </p>
        <?php else: ?>
            <div class="bg-gray-100 p-4 rounded">
                <p>You have already completed your attendance for today.</p>
                <p class="text-sm text-gray-600">
                    Check-in: <?= date('h:i A', strtotime($currentUserAttendance['check_in'])) ?> |
                    Check-out: <?= date('h:i A', strtotime($currentUserAttendance['check_out'])) ?>
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Admin: Record attendance for other staff -->
        <div class="mt-6 border-t pt-4">
            <h3 class="font-medium mb-3">Record Attendance for Staff Member</h3>
            <form action="attendance.php" method="post" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Staff</label>
                    <select name="user_id" required class="border rounded px-3 py-2 w-48">
                        <option value="">-- Select Staff --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['user_id'] ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                    <div class="flex gap-2">
                        <button type="submit" name="action" value="check_in" class="bg-green-500 text-white px-3 py-2 rounded text-sm hover:bg-green-600">
                            Check In
                        </button>
                        <button type="submit" name="action" value="check_out" class="bg-blue-500 text-white px-3 py-2 rounded text-sm hover:bg-blue-600">
                            Check Out
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Total Records</h3>
            <p class="text-3xl font-bold text-blue-600"><?= $stats['total_records'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Late Entries</h3>
            <p class="text-3xl font-bold text-yellow-600"><?= $stats['late_entries'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-700">Missing Check-outs</h3>
            <p class="text-3xl font-bold text-red-600"><?= $stats['missing_checkouts'] ?? 0 ?></p>
        </div>
    </div>
    
    <!-- Search and Filter Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form action="attendance.php" method="get" class="flex flex-wrap gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date Filter</label>
                <select name="date_filter" class="border rounded px-3 py-2" onchange="toggleCustomDateInputs(this.value)">
                    <option value="today" <?= $dateFilter == 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= $dateFilter == 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="this_week" <?= $dateFilter == 'this_week' ? 'selected' : '' ?>>This Week</option>
                    <option value="last_week" <?= $dateFilter == 'last_week' ? 'selected' : '' ?>>Last Week</option>
                    <option value="this_month" <?= $dateFilter == 'this_month' ? 'selected' : '' ?>>This Month</option>
                    <option value="custom" <?= $dateFilter == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>
            </div>
            
            <div id="custom_date_inputs" class="<?= $dateFilter === 'custom' ? 'flex' : 'hidden' ?> gap-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($customStartDate) ?>" class="border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($customEndDate) ?>" class="border rounded px-3 py-2">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search Staff</label>
                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search by name..." class="border rounded px-3 py-2">
            </div>
            
            <div class="self-end">
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Apply Filters</button>
                <a href="attendance.php" class="text-gray-600 ml-2 hover:underline">Reset</a>
            </div>
        </form>
    </div>
    
    <!-- Attendance Records Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check In</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check Out</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (count($attendanceRecords) > 0): ?>
                    <?php foreach ($attendanceRecords as $record): ?>
                        <?php
                        // Calculate duration if both check-in and check-out exist
                        $duration = '';
                        if (!empty($record['check_in']) && !empty($record['check_out'])) {
                            $checkIn = new DateTime($record['check_in']);
                            $checkOut = new DateTime($record['check_out']);
                            $interval = $checkIn->diff($checkOut);
                            $duration = $interval->format('%h hrs, %i mins');
                        }
                        
                        // Determine status classes
                        $statusClass = '';
                        $statusText = '';
                        
                        if (empty($record['check_out'])) {
                            $statusClass = 'text-yellow-600';
                            $statusText = 'No Check-out';
                        } elseif (strtotime($record['check_in']) > strtotime($record['date'] . ' 09:15:00')) {
                            $statusClass = 'text-red-600';
                            $statusText = 'Late';
                        } else {
                            $statusClass = 'text-green-600';
                            $statusText = 'Complete';
                        }
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($record['full_name']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($record['username']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= date('M d, Y', strtotime($record['date'])) ?></div>
                                <div class="text-sm text-gray-500"><?= date('l', strtotime($record['date'])) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (!empty($record['check_in'])): ?>
                                    <div class="text-sm text-gray-900"><?= date('h:i A', strtotime($record['check_in'])) ?></div>
                                <?php else: ?>
                                    <span class="text-sm text-gray-500">Not recorded</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (!empty($record['check_out'])): ?>
                                    <div class="text-sm text-gray-900"><?= date('h:i A', strtotime($record['check_out'])) ?></div>
                                <?php else: ?>
                                    <span class="text-sm text-gray-500">Not recorded</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $duration ?: 'N/A' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 <?= $statusClass ?>">
                                    <?= $statusText ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No attendance records found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center mt-6">
        <nav class="inline-flex rounded-md shadow">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&date_filter=<?= $dateFilter ?>&search=<?= urlencode($searchQuery) ?><?= $dateFilter === 'custom' ? '&start_date=' . $customStartDate . '&end_date=' . $customEndDate : '' ?>"
                   class="px-4 py-2 border <?= $page === $i ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </nav>
    </div>
    <?php endif; ?>
    
    <!-- Reports Link -->
    <div class="mt-6 text-center">
        <a href="attendance_report.php" class="inline-block bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600">
            View Detailed Attendance Reports
        </a>
    </div>
</div>

<script>
// Function to toggle custom date inputs
function toggleCustomDateInputs(value) {
    const customInputs = document.getElementById('custom_date_inputs');
    if (value === 'custom') {
        customInputs.classList.remove('hidden');
        customInputs.classList.add('flex');
    } else {
        customInputs.classList.add('hidden');
        customInputs.classList.remove('flex');
    }
}
</script>

<?php include_once '../includes/footer.php'; ?>
