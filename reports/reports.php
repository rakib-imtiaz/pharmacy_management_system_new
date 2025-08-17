<?php
require_once '../includes/db_connect.php';
session_start();

// Verify login status
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include_once '../includes/header.php';

// Get date range from request or default to last 30 days
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'appointments';

try {
    $data = [];
    $chart_data = [];
    $total_count = 0;
    $total_amount = 0;
    $daily_data = [];
    
    // Generate dates between start and end dates for chart labels
    $period = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1D'),
        (new DateTime($end_date))->modify('+1 day')
    );
    
    foreach ($period as $date) {
        $dateKey = $date->format('Y-m-d');
        $daily_data[$dateKey] = 0;
    }

    // Different reports based on report type
    switch ($report_type) {
        case 'appointments':
            // Fetch appointments summary
            $query = "
                SELECT 
                    DATE(appointment_datetime) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled
                FROM appointments 
                WHERE appointment_datetime BETWEEN ? AND ?
                GROUP BY DATE(appointment_datetime)
                ORDER BY date ASC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$start_date, $end_date . ' 23:59:59']);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Count total appointments
            $count_query = "SELECT COUNT(*) FROM appointments WHERE appointment_datetime BETWEEN ? AND ?";
            $count_stmt = $pdo->prepare($count_query);
            $count_stmt->execute([$start_date, $end_date . ' 23:59:59']);
            $total_count = $count_stmt->fetchColumn();
            
            // Prepare chart data
            foreach ($data as $row) {
                $daily_data[$row['date']] = intval($row['total']);
            }
            
            // Prepare chart series
            $chart_data = [
                'labels' => array_keys($daily_data),
                'datasets' => [
                    [
                        'label' => 'Appointments',
                        'data' => array_values($daily_data),
                        'borderColor' => 'rgb(59, 130, 246)',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                        'fill' => true
                    ]
                ]
            ];
            break;
            
        case 'revenue':
            // Fetch revenue summary
            $query = "
                SELECT 
                    DATE(invoice_date) as date,
                    SUM(total_amount) as total_revenue,
                    COUNT(*) as invoice_count,
                    SUM(CASE WHEN paid = TRUE THEN total_amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN paid = FALSE THEN total_amount ELSE 0 END) as unpaid_amount
                FROM invoices 
                WHERE invoice_date BETWEEN ? AND ?
                GROUP BY DATE(invoice_date)
                ORDER BY date ASC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$start_date, $end_date]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate total revenue
            $total_query = "SELECT SUM(total_amount) FROM invoices WHERE invoice_date BETWEEN ? AND ?";
            $total_stmt = $pdo->prepare($total_query);
            $total_stmt->execute([$start_date, $end_date]);
            $total_amount = $total_stmt->fetchColumn() ?: 0;
            
            // Prepare chart data
            foreach ($data as $row) {
                $daily_data[$row['date']] = floatval($row['total_revenue']);
            }
            
            // Prepare chart series
            $chart_data = [
                'labels' => array_keys($daily_data),
                'datasets' => [
                    [
                        'label' => 'Daily Revenue',
                        'data' => array_values($daily_data),
                        'borderColor' => 'rgb(34, 197, 94)',
                        'backgroundColor' => 'rgba(34, 197, 94, 0.2)',
                        'fill' => true
                    ]
                ]
            ];
            break;
            
        case 'outpatient':
            // Fetch outpatient visits summary
            $query = "
                SELECT 
                    DATE(visit_datetime) as date,
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT patient_id) as unique_patients
                FROM outpatient_visits 
                WHERE visit_datetime BETWEEN ? AND ?
                GROUP BY DATE(visit_datetime)
                ORDER BY date ASC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$start_date, $end_date . ' 23:59:59']);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Count total visits
            $count_query = "SELECT COUNT(*) FROM outpatient_visits WHERE visit_datetime BETWEEN ? AND ?";
            $count_stmt = $pdo->prepare($count_query);
            $count_stmt->execute([$start_date, $end_date . ' 23:59:59']);
            $total_count = $count_stmt->fetchColumn();
            
            // Prepare chart data
            foreach ($data as $row) {
                $daily_data[$row['date']] = intval($row['total_visits']);
            }
            
            // Prepare chart series
            $chart_data = [
                'labels' => array_keys($daily_data),
                'datasets' => [
                    [
                        'label' => 'Patient Visits',
                        'data' => array_values($daily_data),
                        'borderColor' => 'rgb(168, 85, 247)',
                        'backgroundColor' => 'rgba(168, 85, 247, 0.2)',
                        'fill' => true
                    ]
                ]
            ];
            break;
            
        case 'services':
            // Fetch services summary
            $query = "
                SELECT 
                    s.service_name, 
                    SUM(ii.quantity) as total_quantity,
                    SUM(ii.quantity * ii.unit_price) as total_revenue
                FROM invoice_items ii
                JOIN services s ON ii.service_id = s.service_id
                JOIN invoices i ON ii.invoice_id = i.invoice_id
                WHERE i.invoice_date BETWEEN ? AND ?
                GROUP BY s.service_id
                ORDER BY total_revenue DESC
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$start_date, $end_date]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate total revenue from services
            $total_query = "
                SELECT SUM(ii.quantity * ii.unit_price) 
                FROM invoice_items ii
                JOIN invoices i ON ii.invoice_id = i.invoice_id
                WHERE i.invoice_date BETWEEN ? AND ?
            ";
            $total_stmt = $pdo->prepare($total_query);
            $total_stmt->execute([$start_date, $end_date]);
            $total_amount = $total_stmt->fetchColumn() ?: 0;
            
            // Prepare chart data (Pie chart for services)
            $service_names = [];
            $service_amounts = [];
            $service_colors = [];
            
            // Generate random colors for pie segments
            $i = 0;
            foreach ($data as $row) {
                $service_names[] = $row['service_name'];
                $service_amounts[] = floatval($row['total_revenue']);
                
                // Generate a different color for each service
                $hue = ($i * 137) % 360; // Golden angle in degrees to ensure good color distribution
                $service_colors[] = "hsl($hue, 70%, 60%)";
                $i++;
            }
            
            // Prepare chart series for pie chart
            $chart_data = [
                'labels' => $service_names,
                'datasets' => [
                    [
                        'data' => $service_amounts,
                        'backgroundColor' => $service_colors
                    ]
                ]
            ];
            break;
    }
} catch (PDOException $e) {
    $data = [];
    $chart_data = [];
    error_log("Reports Query Error: " . $e->getMessage());
}

// Convert chart data to JSON for JavaScript
$chart_json = json_encode($chart_data);

// Helper function for formatting numbers
function format_number($number) {
    return number_format($number, 2);
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 fade-in">
        <h1 class="text-3xl font-bold text-gray-800 mb-4 md:mb-0">Reports & Analytics</h1>
        
        <!-- Report Type Tabs -->
        <div class="flex flex-wrap gap-2">
            <a href="?report_type=appointments&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
               class="px-4 py-2 rounded-lg font-medium transition-colors duration-300 <?php echo $report_type === 'appointments' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                <i class="fas fa-calendar-check mr-2"></i>
                Appointments
            </a>
            <a href="?report_type=revenue&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
               class="px-4 py-2 rounded-lg font-medium transition-colors duration-300 <?php echo $report_type === 'revenue' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                <i class="fas fa-dollar-sign mr-2"></i>
                Revenue
            </a>
            <a href="?report_type=outpatient&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
               class="px-4 py-2 rounded-lg font-medium transition-colors duration-300 <?php echo $report_type === 'outpatient' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                <i class="fas fa-stethoscope mr-2"></i>
                Outpatient
            </a>
            <a href="?report_type=services&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
               class="px-4 py-2 rounded-lg font-medium transition-colors duration-300 <?php echo $report_type === 'services' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                <i class="fas fa-procedures mr-2"></i>
                Services
            </a>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8 fade-in">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
            
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                <input 
                    type="date" 
                    id="start_date" 
                    name="start_date" 
                    value="<?php echo $start_date; ?>"
                    max="<?php echo date('Y-m-d'); ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>
            
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                <input 
                    type="date" 
                    id="end_date" 
                    name="end_date" 
                    value="<?php echo $end_date; ?>"
                    max="<?php echo date('Y-m-d'); ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300">
                    Apply Filter
                </button>
            </div>
            
            <div class="flex items-end">
                <button type="button" id="exportBtn" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-300">
                    <i class="fas fa-file-export mr-2"></i>
                    Export Report
                </button>
            </div>
        </form>
    </div>

    <!-- Report Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <?php if ($report_type === 'appointments'): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-full mr-4">
                        <i class="fas fa-calendar text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Appointments</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_count); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-full mr-4">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Completed</p>
                        <?php 
                            $completed = array_sum(array_column($data, 'completed'));
                            $completion_rate = $total_count > 0 ? ($completed / $total_count * 100) : 0;
                        ?>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($completed); ?> 
                            <span class="text-sm text-gray-500">(<?php echo round($completion_rate); ?>%)</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-red-100 p-3 rounded-full mr-4">
                        <i class="fas fa-times-circle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Cancelled</p>
                        <?php 
                            $cancelled = array_sum(array_column($data, 'cancelled'));
                            $cancellation_rate = $total_count > 0 ? ($cancelled / $total_count * 100) : 0;
                        ?>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($cancelled); ?> 
                            <span class="text-sm text-gray-500">(<?php echo round($cancellation_rate); ?>%)</span>
                        </p>
                    </div>
                </div>
            </div>
            
        <?php elseif ($report_type === 'revenue'): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-full mr-4">
                        <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-900">$<?php echo format_number($total_amount); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-full mr-4">
                        <i class="fas fa-file-invoice-dollar text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Paid Invoices</p>
                        <?php 
                            $paid_amount = 0;
                            $paid_count = 0;
                            foreach ($data as $row) {
                                $paid_amount += floatval($row['paid_amount']);
                                $paid_count += $row['paid_amount'] > 0 ? 1 : 0;
                            }
                            $paid_percentage = $total_amount > 0 ? ($paid_amount / $total_amount * 100) : 0;
                        ?>
                        <p class="text-2xl font-bold text-gray-900">
                            $<?php echo format_number($paid_amount); ?> 
                            <span class="text-sm text-gray-500">(<?php echo round($paid_percentage); ?>%)</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-full mr-4">
                        <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Outstanding</p>
                        <?php 
                            $unpaid_amount = 0;
                            foreach ($data as $row) {
                                $unpaid_amount += floatval($row['unpaid_amount']);
                            }
                            $unpaid_percentage = $total_amount > 0 ? ($unpaid_amount / $total_amount * 100) : 0;
                        ?>
                        <p class="text-2xl font-bold text-gray-900">
                            $<?php echo format_number($unpaid_amount); ?> 
                            <span class="text-sm text-gray-500">(<?php echo round($unpaid_percentage); ?>%)</span>
                        </p>
                    </div>
                </div>
            </div>
            
        <?php elseif ($report_type === 'outpatient'): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-3 rounded-full mr-4">
                        <i class="fas fa-notes-medical text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Visits</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_count); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Unique Patients</p>
                        <?php 
                            $unique_patients = 0;
                            $patient_ids = [];
                            foreach ($data as $row) {
                                $unique_patients += intval($row['unique_patients']);
                            }
                        ?>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($unique_patients); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-full mr-4">
                        <i class="fas fa-chart-line text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Daily Average</p>
                        <?php 
                            $days_with_data = count(array_filter($daily_data));
                            $daily_average = $days_with_data > 0 ? ($total_count / $days_with_data) : 0;
                        ?>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($daily_average, 1); ?></p>
                    </div>
                </div>
            </div>
            
        <?php elseif ($report_type === 'services'): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-full mr-4">
                        <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-900">$<?php echo format_number($total_amount); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-full mr-4">
                        <i class="fas fa-list-ol text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Service Types</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($data); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 fade-in">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-3 rounded-full mr-4">
                        <i class="fas fa-procedures text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Most Popular</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo !empty($data) ? htmlspecialchars($data[0]['service_name']) : 'N/A'; ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Chart Area -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8 fade-in">
        <h2 class="text-xl font-bold text-gray-800 mb-6">
            <?php
            switch ($report_type) {
                case 'appointments':
                    echo 'Appointments Trend';
                    break;
                case 'revenue':
                    echo 'Revenue Trend';
                    break;
                case 'outpatient':
                    echo 'Patient Visits Trend';
                    break;
                case 'services':
                    echo 'Revenue by Service';
                    break;
            }
            ?>
        </h2>
        
        <div style="height: 400px;">
            <canvas id="reportChart"></canvas>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">Detailed Data</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <?php if ($report_type === 'appointments'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cancelled</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scheduled</th>
                        <?php elseif ($report_type === 'revenue'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoices</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unpaid Amount</th>
                        <?php elseif ($report_type === 'outpatient'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Visits</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unique Patients</th>
                        <?php elseif ($report_type === 'services'): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">% of Total</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                No data available for the selected date range
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data as $row): ?>
                            <tr>
                                <?php if ($report_type === 'appointments'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo date('F j, Y', strtotime($row['date'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['total']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['completed']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['cancelled']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['scheduled']; ?></td>
                                <?php elseif ($report_type === 'revenue'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo date('F j, Y', strtotime($row['date'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['invoice_count']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">$<?php echo format_number($row['total_revenue']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">$<?php echo format_number($row['paid_amount']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">$<?php echo format_number($row['unpaid_amount']); ?></td>
                                <?php elseif ($report_type === 'outpatient'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo date('F j, Y', strtotime($row['date'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['total_visits']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['unique_patients']; ?></td>
                                <?php elseif ($report_type === 'services'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($row['service_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $row['total_quantity']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">$<?php echo format_number($row['total_revenue']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $total_amount > 0 ? round(($row['total_revenue'] / $total_amount) * 100) : 0; ?>%
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart configuration
    const ctx = document.getElementById('reportChart').getContext('2d');
    const chartData = <?php echo $chart_json; ?>;
    const reportType = '<?php echo $report_type; ?>';
    
    let chartConfig = {
        type: reportType === 'services' ? 'pie' : 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
        }
    };
    
    if (reportType !== 'services') {
        // Line chart options
        chartConfig.options = {
            ...chartConfig.options,
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'day',
                        displayFormats: {
                            day: 'MMM d'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: reportType === 'revenue' ? 'Amount ($)' : 'Count'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (reportType === 'revenue') {
                                label += '$' + context.parsed.y.toFixed(2);
                            } else {
                                label += context.parsed.y;
                            }
                            return label;
                        }
                    }
                }
            }
        };
    } else {
        // Pie chart options
        chartConfig.options = {
            ...chartConfig.options,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = '$' + context.parsed.toFixed(2);
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.parsed / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        };
    }
    
    // Create the chart
    const myChart = new Chart(ctx, chartConfig);
    
    // Date range validation
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    endDateInput.addEventListener('change', function() {
        if (startDateInput.value > endDateInput.value) {
            alert('End date cannot be before start date');
            endDateInput.value = startDateInput.value;
        }
    });
    
    startDateInput.addEventListener('change', function() {
        if (startDateInput.value > endDateInput.value) {
            alert('Start date cannot be after end date');
            startDateInput.value = endDateInput.value;
        }
    });
    
    // Export functionality
    document.getElementById('exportBtn').addEventListener('click', function() {
        // Simulate export - in a real app this would generate and download a CSV or PDF
        const reportTitle = document.querySelector('h1').textContent;
        const dateRange = `${startDateInput.value} to ${endDateInput.value}`;
        alert(`Export ${reportTitle} for ${dateRange}\n\nIn a real application, this would generate a CSV or PDF file for download.`);
    });
    
    // Add staggered animation
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
    });
});
</script>

<?php include_once '../includes/footer.php'; ?> 