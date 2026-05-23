<?php
// forecast_analysis.php

session_start(); // THIS MUST BE THE VERY FIRST LINE

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// 1. Database Configuration
$host = 'localhost';
$user = 'root';
$pass = 'root';
$db   = 'aics_dss'; 

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- PIPELINE CONNECTIONS ---
$python_lstm = shell_exec('python3 lstm_model.py 2>&1');
$lstm_val = trim($python_lstm); 

$python_rf = shell_exec('python3 random_forest.py'); 
$rf_results = json_decode($python_rf, true);

// 2. Initialize Variables
$all_records = []; 
$cause_patterns = []; 
$daily_counts = [];
$monthly_counts = []; 
$yearly_counts = [];
$cause_series = [];
$rising_causes = [];

// 3. Database Data Retrieval (Replacing CSV logic)
$sql = "SELECT request_date, medical_cause FROM aics_sample_data ORDER BY request_date ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $row_date = trim($row['request_date']);
        $medical_cause = $row['medical_cause'] ?? 'Unknown';
        
        $all_records[] = ['date' => $row_date, 'cause' => $medical_cause];

        $ts = strtotime($row_date);
        if ($ts) {
            $d = date("Y-m-d", $ts);
            $m = date("Y-m", $ts);
            $y = date("Y", $ts);

            $daily_counts[$d] = ($daily_counts[$d] ?? 0) + 1;
            $monthly_counts[$m] = ($monthly_counts[$m] ?? 0) + 1;
            $yearly_counts[$y] = ($yearly_counts[$y] ?? 0) + 1;

            $clean_label = ucwords(strtolower(trim($medical_cause))); 
            $cause_patterns[$clean_label] = ($cause_patterns[$clean_label] ?? 0) + 1;
            
            if (!isset($cause_series[$clean_label][$m])) $cause_series[$clean_label][$m] = 0;
            $cause_series[$clean_label][$m]++;
        }
    }
}
$conn->close();

// 3. Sync & Forecast Logic
ksort($monthly_counts);
$historical_values = array_values($monthly_counts);
$count = count($historical_values);
$last_actual = !empty($historical_values) ? end($historical_values) : 0;

$predicted_val = round($last_actual * 1.12); 
$display_perc = 12.0;

echo "<script>
    const csvData = " . json_encode($all_records) . ";
    const lstmPrediction = " . (is_numeric($lstm_val) ? $lstm_val : "null") . ";
</script>";

// ... (Rest of your existing Forecast Logic, HTML, and JS remains exactly the same)
if (is_numeric($lstm_val) && $lstm_val > 0) {
    $predicted_val = $lstm_val;
    $display_perc = ($last_actual > 0) ? round((($predicted_val - $last_actual) / $last_actual) * 100, 1) : 5.8;
} elseif ($count >= 2) {
    $previous_month = $historical_values[$count - 2];
    $growth_rate = ($previous_month > 0) ? (($last_actual - $previous_month) / $previous_month) : 0.058;
    $predicted_val = round($last_actual * (1 + $growth_rate));
    $display_perc = round($growth_rate * 100, 1);
} else {
    $predicted_val = round($last_actual * 1.058);
    $display_perc = 5.8;
}

// --- PIPELINE B LOGIC ---
if (!empty($rf_results)) {
    $rising_causes = array_slice($rf_results, 0, 5, true); 
} elseif (!empty($cause_patterns)) {
    arsort($cause_patterns);
    foreach (array_slice($cause_patterns, 0, 5, true) as $name => $count) {
        $rising_causes[$name] = [
            "status" => "Analyzing",
            "growth" => "0%",
            "color" => "#64748b",
            "count" => $count
        ];
    }
} else {
    $rising_causes = [];
}

// Prepare Medical Trends with Forecast Lines
$chart_labels = array_keys(array_slice($monthly_counts, -6));
$medical_labels_with_forecast = array_merge($chart_labels, ["Forecast"]);
$top_causes_keys = array_keys($rising_causes); // No longer crashes because $rising_causes is guaranteed an array
$final_datasets = [];
$dataset_colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'];

foreach ($top_causes_keys as $index => $c_name) {
    $hist_points = [];
    foreach ($chart_labels as $label) {
        $hist_points[] = $cause_series[$c_name][$label] ?? 0;
    }
    
    $last_point = end($hist_points);
    $predicted_point = round($last_point * 1.058);
    $full_data = array_merge($hist_points, [$predicted_point]);

    $final_datasets[] = [
        'label' => $c_name, 
        'data' => $full_data,
        'borderColor' => $dataset_colors[$index] ?? '#cbd5e1',
        'backgroundColor' => ($dataset_colors[$index] ?? '#cbd5e1') . '20',
        'borderWidth' => 3,
        'pointRadius' => 4,
        'tension' => 0.4,
        'fill' => false
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecast Analysis - DSWD AICS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --dswd-dark: #2c3e50;
            --sidebar-bg: #1e293b;
            --bg-color: #f0f2f5;
            --card-shadow: 0 2px 12px rgba(0,0,0,0.08);
            --sidebar-width: 260px;
        }
        body { font-family: 'Inter', sans-serif; margin: 0; background: var(--bg-color); display: flex; color: #334155; }

        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); position: fixed; left: 0; top: 0; color: #fff; display: flex; flex-direction: column; z-index: 1000; }
        .sidebar-header { padding: 30px 20px; text-align: center; background: rgba(0,0,0,0.2); }
        .sidebar a { padding: 15px 25px; text-decoration: none; color: #94a3b8; display: flex; align-items: center; transition: 0.3s; border-left: 4px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background: #334155; color: #fff; border-left: 4px solid #3b82f6; }

        .main { margin-left: var(--sidebar-width); padding: 40px; width: calc(100% - var(--sidebar-width)); min-height: 100vh; }

        .header-area { margin-bottom: 30px; }
        .header-area h1 { margin: 0; font-size: 22px; color: #344767; }
        .header-area p { color: #8392ab; margin: 5px 0 0; font-style: italic; }
        
        .forecast-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .f-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .full-width { grid-column: span 2; }
        .label { color: #8392ab; font-size: 14px; font-weight: 600; margin-bottom: 10px; }
        .big-number { font-size: 42px; font-weight: 700; color: #2c3e50; margin: 5px 0; }
        .trend-up { color: #10b981; font-weight: 600; font-size: 18px; }
        .tag-container { display: flex; gap: 10px; margin-top: 15px; }
        .tag { background: #f8f9fa; border: 1px solid #e9ecef; padding: 8px 16px; border-radius: 8px; color: #475569; font-size: 13px; font-weight: 500; }
        .rising-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .rising-item:last-child { border-bottom: none; }
        .chart-toggle { background: #f1f5f9; padding: 4px; border-radius: 8px; display: flex; gap: 5px; }
        .chart-toggle button { border: none; background: transparent; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; transition: 0.2s; }
        .chart-toggle button.active { background: #fff; color: #3b82f6; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    
<?php include 'sidebar.php'; ?>

<div class="main">
    <div class="header-area">
        <h1>Predictive Decision Support Dashboard</h1>
        <p>AICS Program of DSWD <i class="fas fa-chevron-right" style="font-size: 10px; margin: 0 5px;"></i> Batasan Hills</p>
    </div>

    <div class="forecast-container">
        <div class="f-card">
            <div class="label" id="forecast-label">Expected Requests (Monthly)</div>
            <div style="display: flex; align-items: baseline; gap: 15px;">
                <div class="big-number" id="big-number-val"><?php echo number_format($predicted_val); ?></div>
                <div class="trend-up" id="trend-container">
                    <i id="trend-icon" class="fas fa-arrow-<?php echo ($display_perc >= 0) ? 'up' : 'down'; ?>"></i> 
                    <span id="trend-perc-val"><?php echo abs($display_perc); ?></span>%
                </div>
            </div>
        </div>

        <div class="f-card">
            <div class="label">Medical Risk Hotspots (Pipeline B)</div>
            <div class="tag-container">
                <?php if (!empty($rising_causes)): ?>
                    <?php foreach(array_keys(array_slice($rising_causes, 0, 3)) as $cluster): ?>
                        <div class="tag"><i class="fas fa-notes-medical"></i> <?php echo htmlspecialchars($cluster); ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="tag">Analyzing Clusters...</div>
                <?php endif; ?>
                <div class="tag"><i class="fas fa-folder"></i> Accident Injury
</div>
            </div>
        </div>

        <div class="f-card full-width">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div class="label" style="color:#2c3e50; font-size:16px; margin:0;">Forecasted Request Volume</div>
                <div class="chart-toggle">
                    <button onclick="updateChart('daily')" id="btn-daily">Daily</button>
                    <button onclick="updateChart('monthly')" id="btn-monthly" class="active">Monthly</button>
                    <button onclick="updateChart('yearly')" id="btn-yearly">Yearly</button>
                </div>
            </div>
            <canvas id="forecastVolumeChart" height="100"></canvas>
        </div>

        <div class="f-card full-width">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div class="label" style="color:#2c3e50; font-size:16px; margin:0;">Top 5 Medical Assistance Prediction</div>
                <div class="chart-toggle">
                    <button onclick="updateMedicalChart('daily')" id="m-btn-daily">Daily</button>
                    <button onclick="updateMedicalChart('monthly')" id="m-btn-monthly" class="active">Monthly</button>
                    <button onclick="updateMedicalChart('yearly')" id="m-btn-yearly">Yearly</button>
                </div>
            </div>
            <canvas id="medicalCausesChart" height="100"></canvas>
        </div>

        <div class="f-card full-width">
    <div class="label" style="color:#2c3e50; font-size:16px;">Rising Medical Causes (Hotspots)</div>
    <div id="hotspots-list-container" style="margin-top: 15px;">
        <?php 
        $i = 0;
        if (!empty($rising_causes)):
            foreach($rising_causes as $name => $data): 
                $trend_status = $data['status'] ?? 'Stable';
                $status_color = $data['color'] ?? '#3b82f6';
                $total_count = $data['count'] ?? 0; // Show total count initially
        ?>
        <div class="rising-item">
            <div style="font-weight: 600; color: #344767; display: flex; align-items: center; gap: 8px;">
                <div style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo $dataset_colors[$i % 5]; ?>;"></div>
                <?php echo htmlspecialchars($name); ?>
                <span style="font-size: 11px; color: #8392ab; font-weight: 400;">(Total: <?php echo number_format($total_count); ?>)</span>
            </div>
            <div style="color: <?php echo $status_color; ?>; font-weight: 600; font-size: 13px;">
                <i class="fas fa-<?php echo ($trend_status === 'Rising') ? 'arrow-trend-up' : (($trend_status === 'Declining') ? 'arrow-trend-down' : 'circle-check'); ?>"></i>
                <?php echo $trend_status; ?> Medical Trend
            </div>
        </div>
        <?php $i++; endforeach; 
        endif; ?>
    </div>
</div>

<script>
    // Global chart instances
    let volumeChart;
    let medicalChart;
    const datasetColors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'];
    const topCauses = <?php echo json_encode(array_keys($rising_causes)); ?>;

    function parseCSVDate(dateStr) {
        const d = new Date(dateStr);
        return isNaN(d.getTime()) ? null : d;
    }

    // --- FUNCTION 1: VOLUME CHART (TOP CARD) ---
    function updateChart(unit) {
        const groups = {};
        csvData.forEach(row => {
            const date = parseCSVDate(row.date);
            if (!date) return;
            let key;
            if (unit === 'daily') key = row.date;
            else if (unit === 'monthly') key = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
            else if (unit === 'yearly') key = date.getFullYear().toString();
            groups[key] = (groups[key] || 0) + 1;
        });

        const sortedKeys = Object.keys(groups).sort().slice(unit === 'daily' ? -15 : -8);
        const displayLabels = sortedKeys.map(k => {
            if(unit === 'monthly') {
                const [y, m] = k.split('-');
                const d = new Date(y, m-1);
                return d.toLocaleString('default', { month: 'short', year: '2-digit' });
            }
            return k;
        });

        const lastVal = groups[sortedKeys[sortedKeys.length - 1]] || 0;
        let forecastVal = (unit === 'monthly' && typeof lstmPrediction === 'number') ? lstmPrediction : Math.round(lastVal * 1.12);
        const growth = (lastVal > 0) ? (((forecastVal - lastVal) / lastVal) * 100).toFixed(1) : "12.0";

        // Update UI
        document.getElementById('big-number-val').innerText = Math.round(forecastVal).toLocaleString();
        document.getElementById('trend-perc-val').innerText = Math.abs(growth);
        document.getElementById('forecast-label').innerText = `Expected Requests (${unit.charAt(0).toUpperCase() + unit.slice(1)})`;
        document.getElementById('trend-icon').className = growth >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down';
        
        document.querySelectorAll('[id^="btn-"]').forEach(b => b.classList.remove('active'));
        document.getElementById('btn-' + unit).classList.add('active');

        if (volumeChart) volumeChart.destroy();
        const ctx = document.getElementById('forecastVolumeChart').getContext('2d');
        volumeChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [...displayLabels, "Forecast"],
                datasets: [{
                    label: 'Historical',
                    data: sortedKeys.map(k => groups[k]),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.05)',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Forecast',
                    data: [...Array(sortedKeys.length - 1).fill(null), lastVal, forecastVal],
                    borderColor: '#f59e0b',
                    borderDash: [5, 5],
                    tension: 0.4
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    }

    // --- FUNCTION 2: MEDICAL TRENDS CHART (BOTTOM CARD) ---
    function updateMedicalChart(unit) {
    const medicalGroups = {};
    topCauses.forEach(c => medicalGroups[c] = {});

    csvData.forEach(row => {
        const date = parseCSVDate(row.date);
        if (!date) return;
        let key;
        if (unit === 'daily') key = row.date;
        else if (unit === 'monthly') key = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
        else if (unit === 'yearly') key = date.getFullYear().toString();

        if (medicalGroups[row.cause] !== undefined) {
            medicalGroups[row.cause][key] = (medicalGroups[row.cause][key] || 0) + 1;
        }
    });

    const allDates = [...new Set(csvData.map(row => {
        const d = parseCSVDate(row.date);
        if(!d) return null;
        if (unit === 'daily') return row.date;
        if (unit === 'monthly') return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        return d.getFullYear().toString();
    }).filter(d => d))].sort();

    const sortedKeys = allDates.slice(unit === 'daily' ? -15 : -8);
    const displayLabels = sortedKeys.map(k => {
        if(unit === 'monthly') {
            const [y, m] = k.split('-');
            return new Date(y, m-1).toLocaleString('default', { month: 'short', year: '2-digit' });
        }
        return k;
    });

    const datasets = topCauses.map((cause, i) => {
        const data = sortedKeys.map(k => medicalGroups[cause][k] || 0);
        
        // --- NEW: Calculate Total Volume for this period ---
        const totalInPeriod = data.reduce((a, b) => a + b, 0);
        
        const lastPoint = data[data.length - 1] || 0;
        const prevPoint = data[data.length - 2] || 0;
        
        const trendStatus = lastPoint > prevPoint ? 'Rising' : (lastPoint < prevPoint ? 'Declining' : 'Stable');
        const statusColor = lastPoint > prevPoint ? '#10b981' : (lastPoint < prevPoint ? '#ef4444' : '#64748b');
        const trendIcon = lastPoint > prevPoint ? 'arrow-trend-up' : (lastPoint < prevPoint ? 'arrow-trend-down' : 'circle-check');

        return {
            label: cause,
            data: [...data, Math.round(lastPoint * 1.08)],
            total: totalInPeriod, // Pass the sum
            status: trendStatus,
            color: statusColor,
            icon: trendIcon,
            borderColor: datasetColors[i],
            backgroundColor: 'transparent',
            tension: 0.4
        };
    });

    // UPDATE HOTSPOTS LIST WITH TOTALS
    const listContainer = document.getElementById('hotspots-list-container');
    if (listContainer) {
        listContainer.innerHTML = datasets.map(ds => `
            <div class="rising-item">
                <div style="font-weight: 600; color: #344767; display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; border-radius: 50%; background: ${ds.borderColor};"></div>
                    ${ds.label}
                    <span style="font-size: 11px; color: #8392ab; font-weight: 400;">(Total: ${ds.total.toLocaleString()})</span>
                </div>
                <div style="color: ${ds.color}; font-weight: 600; font-size: 13px;">
                    <i class="fas fa-${ds.icon}"></i>
                    ${ds.status} Medical Trend
                </div>
            </div>`).join('');
    }

    // Update Tabs and Chart
    document.querySelectorAll('[id^="m-btn-"]').forEach(b => b.classList.remove('active'));
    document.getElementById('m-btn-' + unit).classList.add('active');

    if (medicalChart) medicalChart.destroy();
    medicalChart = new Chart(document.getElementById('medicalCausesChart'), {
        type: 'line',
        data: { labels: [...displayLabels, "Forecast"], datasets: datasets },
        options: { 
            responsive: true, 
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

    // Initialize both on load
    document.addEventListener('DOMContentLoaded', () => {
        updateChart('monthly');
        updateMedicalChart('monthly');
    });
</script>
</body>
</html>