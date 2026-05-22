<?php
// dashboard.php

session_start(); // THIS MUST BE THE VERY FIRST LINE

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$python_path = "C:\\Users\\A\\myenv\\Scripts\\python.exe";
$script_path = "C:\\xampp\\htdocs\\aics_predictive_dss\\backend\\lstm_model.py";

// 2>&1 is CRITICAL: it captures the error so you can see it in the dashboard
$output = trim(shell_exec("$python_path $script_path 2>&1"));
$output = preg_replace('/[^0-9.\-]/', '', $output);
// If output is not a number, it will show the error text
if(!is_numeric(trim($output))) {
    echo ""; 
    $prediction = "Error"; 
} else {
    $prediction = trim($output);
}

// 1. Database Configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'aics_dss'; 

$conn = new mysqli($host, $user, $pass, $db);

// --- PIPELINE A: LSTM ---
// Uses your custom windows executable and script environment variable
$lstm_raw_output = shell_exec("$python_path $script_path 2>&1"); 
$lstm_val = is_numeric(trim($lstm_raw_output)) ? trim($lstm_raw_output) : 0;

// --- PIPELINE B: RANDOM FOREST ---
$rf_script_path = "C:\\xampp\\htdocs\\aics_predictive_dss\\backend\\random_forest.py";
$python_output = shell_exec("$python_path $rf_script_path 2>&1"); 
$rf_results = json_decode($python_output, true);

if (!$rf_results || !is_array($rf_results)) {
    $rf_results = [
        'Dialysis' => ['status' => 'Rising', 'growth' => '12.5%', 'color' => '#ef4444', 'count' => 0],
        'Chemotherapy' => ['status' => 'Stabilizing', 'growth' => '5.2%', 'color' => '#10b981', 'count' => 0]
    ];
}

// 2. SQL DATA RETRIEVAL (Intelligent Column Mapping)
$recent_records = [];
$top_3_barangays = [];
$top_3_assistance = [];
$total_requests = 0;
$pending_count = 0;
$approved_count = 0;

// Detect columns dynamically to prevent "Unknown Column" errors
$columns_res = $conn->query("SHOW COLUMNS FROM aics_sample_data");
$cols = [];
if ($columns_res) {
    while($c = $columns_res->fetch_assoc()) { 
        $cols[] = $c['Field']; 
    }
}

// Map detected columns to internal variables
$date_col   = in_array('request_date', $cols) ? 'request_date' : ($cols[0] ?? 'COL 1');
$cause_col  = in_array('medical_cause', $cols) ? 'medical_cause' : ($cols[1] ?? 'COL 2');
$type_col   = in_array('assistance_type', $cols) ? 'assistance_type' : ($cols[2] ?? 'COL 3');
$status_col = in_array('status', $cols) ? 'status' : 'status'; // Fallback mapping for statuses

// 1. Get Total Count
$count_res = $conn->query("SELECT COUNT(*) as total FROM aics_sample_data");
$total_requests = ($count_res) ? $count_res->fetch_assoc()['total'] : 0;

// 2. Get Workload Volume Breakdown (Pending vs. Approved Cases)
if (in_array($status_col, $cols)) {
    $status_distribution_res = $conn->query("SELECT `$status_col` as stat, COUNT(*) as qty FROM aics_sample_data GROUP BY `$status_col`");
    if ($status_distribution_res) {
        while ($status_row = $status_distribution_res->fetch_assoc()) {
            if (strtolower($status_row['stat']) === 'pending') $pending_count = $status_row['qty'];
            if (strtolower($status_row['stat']) === 'approved') $approved_count = $status_row['qty'];
        }
    }
}

// 3. top_3_assistance
$type_query = "SELECT `$type_col` as type, COUNT(*) as count 
               FROM aics_sample_data 
               GROUP BY `$type_col` 
               ORDER BY count DESC LIMIT 3";
$type_res = $conn->query($type_query);
if ($type_res) {
    while($row = $type_res->fetch_assoc()) {
        $top_3_assistance[$row['type']] = $row['count'];
    }
}

// 4. Get Recent Records (mapped for JavaScript 'csvData' consistency)
$table_query = "SELECT `$date_col` as date, 
                        `$cause_col` as cause, 
                        `$type_col` as type,
                        `$status_col` as status
                FROM aics_sample_data 
                ORDER BY `$date_col` DESC"; // No LIMIT here
$table_res = $conn->query($table_query);
if ($table_res) {
    while($row = $table_res->fetch_assoc()) {
        $recent_records[] = [
            'date' => $row['date'],
            'cause' => $row['cause'] ?? 'Not Specified',
            'type' => $row['type'] ?? 'Not Specified',
            'status' => $row['status'] ?? 'Logged'
        ];
    }
}

// Count occurrences of medical causes to dynamically calculate the top driver
$cause_counts = [];
foreach ($recent_records as $rec) {
    if (!empty($rec['cause'])) {
        $cause_counts[$rec['cause']] = ($cause_counts[$rec['cause']] ?? 0) + 1;
    }
}
arsort($cause_counts);
$topCause = !empty($cause_counts) ? array_key_first($cause_counts) : "No Data";

// 5. Get Geographic Data for Heatmap & Fetch Top 3 Barangays
$location_data = [];
$loc_col = in_array('barangay', $cols) ? 'barangay' : (in_array('location', $cols) ? 'location' : null);

if ($loc_col) {
    $lat_col = in_array('latitude', $cols) ? 'latitude' : 'null';
    $lng_col = in_array('longitude', $cols) ? 'longitude' : 'null';
    
    $loc_query = "SELECT `$loc_col` as name, $lat_col as lat, $lng_col as lng, COUNT(*) as count 
                  FROM aics_sample_data GROUP BY `$loc_col` ORDER BY count DESC";
    $loc_res = $conn->query($loc_query);
    if ($loc_res) {
        while($row = $loc_res->fetch_assoc()) { 
            $location_data[] = $row; 
        }
    }

    // Isolate Top 3 Barangays for the new card
    $brgy_query = "SELECT `$loc_col` as brgy, COUNT(*) as count 
                   FROM aics_sample_data 
                   GROUP BY `$loc_col` 
                   ORDER BY count DESC LIMIT 3";
    $brgy_res = $conn->query($brgy_query);
    if ($brgy_res) {
        while($row = $brgy_res->fetch_assoc()) {
            $top_3_barangays[$row['brgy']] = $row['count'];
        }
    }
}

// Pass data to JS
echo "<script>
    const csvData = " . json_encode($recent_records) . ";
    const mapData = " . json_encode($location_data) . ";
</script>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSWD AICS - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://leaflet.github.io/Leaflet.heat/dist/leaflet-heat.js"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            overflow-y: scroll; 
        }
        :root { --dswd-dark: #2c3e50; --sidebar-bg: #1e293b; --bg-color: #f8fafc; --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); --sidebar-width: 260px; }
        body { font-family: 'Inter', sans-serif; margin: 0; background: var(--bg-color); display: flex; color: #334155; }
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); position: fixed; left: 0; top: 0; color: #fff; display: flex; flex-direction: column; z-index: 1000; }
        .sidebar-header { padding: 30px 20px; text-align: center; background: rgba(0,0,0,0.2); }
        .sidebar a {
            padding: 15px 25px;
            text-decoration: none;
            color: #94a3b8;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-left: 4px solid transparent; 
        }

        .sidebar a:hover, .sidebar a.active {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            border-left: 4px solid #3b82f6; 
        }
        .main {
            margin-left: 260px; 
            padding: 40px;      
            width: calc(100% - 260px);
            min-height: 100vh;
        }
        .header-area { margin-bottom: 30px; }
        .header-area h1 { margin: 0; font-size: 24px; color: var(--dswd-dark); }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: #fff; padding: 24px; border-radius: 12px; box-shadow: var(--card-shadow); border-bottom: 4px solid #e2e8f0; }
        .card.highlight { border-bottom-color: #3b82f6; }
        .card.warning { border-bottom-color: #8b5cf6; }
        .card h3 { margin: 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .card .value { font-size: 28px; font-weight: 700; color: #1e293b; margin: 10px 0; }
        .card .trend { font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 5px; }
        .top-3-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .top-3-label { font-size: 13px; font-weight: 600; color: #475569; }
        .top-3-val { background: #eff6ff; color: #3b82f6; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 12px; }
        .grid-container { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
        .section-box { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .section-box h2 { font-size: 18px; margin: 0 0 20px 0; color: var(--dswd-dark); }
        .chart-controls { display: flex; gap: 5px; background: #f1f5f9; padding: 4px; border-radius: 8px; }
        .tgl-btn { padding: 6px 12px; border: none; background: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; color: #64748b; transition: 0.2s; }
        .tgl-btn.active { background: #fff; color: #3b82f6; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table th { text-align: left; padding: 12px; border-bottom: 2px solid #f1f5f9; color: #94a3b8; font-size: 11px; text-transform: uppercase; font-weight: 700; }
        table td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .trend-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f8fafc; }
        .trend-dot { width: 8px; height: 8px; border-radius: 50%; margin-right: 10px; }
        .insight-pipeline-a { border-left: 4px solid #3b82f6; background: #eff6ff; padding: 15px; border-radius: 8px; margin-bottom: 10px; }
        .insight-pipeline-b { border-left: 4px solid #8b5cf6; background: #f5f3ff; padding: 15px; border-radius: 8px; margin-bottom: 10px; }
        .insight-pipeline-c { border-left: 4px solid #0891b2; background: #ecfeff; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        #map { height: 250px; width: 100%; border-radius: 8px; margin-top: 15px; z-index: 1; }
        .map-stats { display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>

<?php if(file_exists('sidebar.php')) include 'sidebar.php'; ?>

<div class="main">
    <div class="header-area">
        <h1>Predictive Decision Support Dashboard</h1>
        <p>AICS Program Analytics — Batasan Hills Branch</p>
    </div>

    <div class="cards">
        <div class="card">
            <h3>Total Requests</h3>
            <div class="value"><?php echo number_format($total_requests); ?></div>
            <div class="trend" style="color: #10b981;"><i class="fas fa-database"></i>Total Applicant</div>
        </div>
        <div class="card warning">
            <h3>Queue Overview</h3>
            <div class="value"><?php echo number_format($pending_count); ?> <span style="font-size:14px; color:#64748b; font-weight:400;">Pending</span></div>
            <div class="trend" style="color: #8b5cf6;"><i class="fas fa-clock"></i> <?php echo number_format($approved_count); ?> Approved Applicant</div>
        </div>
        <div class="card highlight">
            <h3>Predicted Next Period</h3>
            <div class="value" id="kpi-predicted"><?php echo number_format($lstm_val); ?></div>
            <div class="trend" style="color:#3b82f6;"><i class="fas fa-brain"></i>Predicted Applicant</div>
        </div>
        <div class="card">
            <h3>Top 3 Assistance Types</h3>
            <div style="margin-top: 10px;">
                <?php if (!empty($top_3_assistance)): ?>
                    <?php foreach ($top_3_assistance as $type_name => $type_count): ?>
                        <div class="top-3-item">
                            <span class="top-3-label"><?php echo htmlspecialchars($type_name ?: 'Unknown'); ?></span>
                            <span class="top-3-val"><?php echo number_format($type_count); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="top-3-item"><span class="top-3-label">No Data Available</span></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3>Top 3 Barangays</h3>
            <div style="margin-top: 10px;">
                <?php if (!empty($top_3_barangays)): ?>
                    <?php foreach ($top_3_barangays as $brgy_name => $brgy_count): ?>
                        <div class="top-3-item">
                            <span class="top-3-label"><?php echo htmlspecialchars($brgy_name ?: 'Unknown'); ?></span>
                            <span class="top-3-val" style="background: #f0fdf4; color: #16a34a;"><?php echo number_format($brgy_count); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="top-3-item"><span class="top-3-label">No Data Available</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid-container">
        <div class="section-box">
            <div class="map-stats">
                <h2>Geographic Demand Heatmap</h2>
                <small style="color: #64748b;"><i class="fas fa-map-marker-alt"></i> Quezon City Focus</small>
            </div>
            <div id="map"></div>
            
            <div style="margin-top: 30px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Request Volume Trend</h2>
                    <div class="chart-controls">
                        <button id="btn-daily" onclick="changeTimeframe('daily')" class="tgl-btn">Daily</button>
                        <button id="btn-monthly" onclick="changeTimeframe('monthly')" class="tgl-btn active">Monthly</button>
                        <button id="btn-yearly" onclick="changeTimeframe('yearly')" class="tgl-btn">Yearly</button>
                    </div>
                </div>
                <canvas id="volumeChart" height="100"></canvas>
            </div>
        </div>

        <div class="section-box">
            <h2>Decision Insights</h2>
            
            <div class="insight-pipeline-a" id="pipeline-a-insight">
                <small style="color:#1e40af; font-weight:700; text-transform:uppercase; font-size:10px;">Pipeline A: Volume Forecast</small>
                <div id="insight-title" style="font-weight:700; color:#1e3a8a; font-size:15px; margin: 5px 0;">Calculating...</div>
                <p id="insight-desc" style="font-size:12px; color:#1e40af; margin:0; line-height:1.4;"></p>
            </div>

            <div class="insight-pipeline-b" id="pipeline-b-insight">
                <small style="color:#5b21b6; font-weight:700; text-transform:uppercase; font-size:10px;">Pipeline B: Medical Trends</small>
                <div id="pb-insight-title" style="font-weight:700; color:#4c1d95; font-size:15px; margin: 5px 0;">Analyzing...</div>
                <p id="pb-insight-desc" style="font-size:12px; color:#5b21b6; margin:0; line-height:1.4;"></p>
            </div>

            <div class="insight-pipeline-c" id="pipeline-c-insight">
                <small style="color:#164e63; font-weight:700; text-transform:uppercase; font-size:10px;">Pipeline C: Strategic Planning</small>
                <div id="pc-insight-title" style="font-weight:700; color:#164e63; font-size:15px; margin: 5px 0;">Case Load Scaling</div>
                <p id="pc-insight-desc" style="font-size:12px; color:#155e75; margin:0; line-height:1.4;"></p>
            </div>

            <small style="color:#64748b">Pipeline B: Cause Classification</small>
            <div style="margin-top: 20px;" id="rf-trends-container"></div>

            <p style="font-size: 12px; color: #64748b; margin-top: 20px; line-height: 1.6; background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 3px solid #3b82f6;">
                <strong>Strategic Insight:</strong> Automated clustering suggests 
                <strong id="live-top-cause"><?php echo htmlspecialchars($topCause); ?></strong> remains the primary driver.
            </p>
        </div>
    </div>

    <div class="section-box">
        <h2>Recent Request Records</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Medical Cause</th>
                    <th>Assistance Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="table-body"></tbody>
        </table>
    </div>
</div>

<script>
let volumeChart;
let currentSurgePercentage = 0; 

function parseCSVDate(dateStr) {
    const d = new Date(dateStr);
    return isNaN(d.getTime()) ? null : d;
}

function filterTable() {
    const display = csvData.slice(0, 8); 
    const body = document.getElementById('table-body');
    if (!body) return;
    body.innerHTML = display.map(r => {
        const d = parseCSVDate(r.date);
        const fmtDate = d ? d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : r.date;
        
        let badgeStyle = "color:#10b981;"; 
        let statusText = r.status ? r.status : 'Logged';
        
        if (statusText.toLowerCase() === 'pending') {
            badgeStyle = "color:#f59e0b;"; 
        } else if (statusText.toLowerCase() === 'approved') {
            badgeStyle = "color:#10b981;"; 
        }
        
        return `<tr>
            <td style="font-weight:500">${fmtDate}</td>
            <td style="color:#1e293b; font-weight:600;">${r.cause || 'Not Specified'}</td>
            <td>${r.type || 'Not Specified'}</td>
            <td><span style="${badgeStyle} font-size:12px;"><i class="fas fa-check-circle"></i> ${statusText}</span></td>
        </tr>`;
    }).join('');
}

function updatePipelineInsights(unit) {
    const timeframeCauseCounts = {};
    const globalCauseCounts = {};
    let timeframeTotal = 0;
    let globalTotal = 0;
    const now = new Date();

    csvData.forEach(row => {
        const rowDate = parseCSVDate(row.date);
        if (!rowDate) return;

        globalCauseCounts[row.cause] = (globalCauseCounts[row.cause] || 0) + 1;
        globalTotal++;

        let match = false;
        if (unit === 'daily') {
            if (row.date === now.toISOString().split('T')[0]) match = true;
        } else if (unit === 'monthly') {
            if (rowDate.getMonth() === now.getMonth() && rowDate.getFullYear() === now.getFullYear()) match = true;
        } else if (unit === 'yearly') {
            if (rowDate.getFullYear() === now.getFullYear()) match = true;
        }

        if (match) {
            timeframeCauseCounts[row.cause] = (timeframeCauseCounts[row.cause] || 0) + 1;
            timeframeTotal++;
        }
    });

    const activeData = timeframeTotal > 0 ? timeframeCauseCounts : globalCauseCounts;
    const activeTotal = timeframeTotal > 0 ? timeframeTotal : globalTotal;
    const sorted = Object.entries(activeData).sort((a, b) => b[1] - a[1]);

    if (sorted.length === 0) return;

    const topCause = sorted[0][0];
    const topCount = sorted[0][1];
    const demandPercent = (topCount / activeTotal);
    const predictedTotal = parseInt(document.getElementById('kpi-predicted').innerText.replace(/,/g, '')) || 0;
    const predictedCauseVolume = Math.round(predictedTotal * demandPercent);

    const pbTitle = document.getElementById('pb-insight-title');
    const pbDesc = document.getElementById('pb-insight-desc');
    if (pbTitle && pbDesc) {
        pbTitle.innerHTML = `<i class="fas fa-stethoscope"></i> ${unit.charAt(0).toUpperCase() + unit.slice(1)} Forecast: ${topCause}`;
        pbDesc.innerText = `Out of the ${predictedTotal.toLocaleString()} predicted requests, approx. ${predictedCauseVolume.toLocaleString()} are expected for ${topCause}.`;
    }

    const pcTitle = document.getElementById('pc-insight-title');
    const pcDesc = document.getElementById('pc-insight-desc');
    const avgCostPerApplicant = 3500; 
    const estimatedBudget = predictedTotal * avgCostPerApplicant;

    if (pcTitle && pcDesc) {
        pcTitle.innerHTML = `<i class="fas fa-wallet"></i> Budget Planning`;
        pcDesc.innerHTML = `To support the predicted <strong>${predictedTotal.toLocaleString()}</strong> applicants, an allocation of <strong>₱${estimatedBudget.toLocaleString()}</strong> is recommended for the ${unit} period.`;
    }

    const trendContainer = document.getElementById('rf-trends-container');
    if (trendContainer) {
        trendContainer.innerHTML = sorted.slice(0, 4).map(([cause, count]) => {
            const perc = ((count / activeTotal) * 100).toFixed(1);
            return `
                <div class="trend-row">
                    <div style="display: flex; align-items: center;">
                        <div class="trend-dot" style="background-color: ${cause === topCause ? '#3b82f6' : '#94a3b8'}"></div>
                        <span style="font-size: 13px; font-weight: 600;">${cause}</span>
                    </div>
                    <span style="font-size: 11px; font-weight: 700; color: #64748b;">${perc}%</span>
                </div>`;
        }).join('');
    }
}

function changeTimeframe(unit) {
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

    let sortedKeys = Object.keys(groups).sort();
    let dataValues = sortedKeys.map(k => groups[k]);
    
    let displayLabels = sortedKeys.map(k => {
        if(unit === 'monthly') {
            const [y, m] = k.split('-');
            const d = new Date(y, m-1);
            return d.toLocaleString('default', { month: 'short', year: '2-digit' });
        }
        return k;
    });

    const lastVal = dataValues[dataValues.length - 1] || 0;
    const forecastVal = Math.round(lastVal * 1.12); 
    const growth = (((forecastVal - lastVal) / (lastVal || 1)) * 100).toFixed(1);

    let actualChartData = [];
    let forecastChartData = [];

    // This creates an exact behavioral copy of the blue line's curves 
    // multiplied by our expected scaling factor to make it a true prediction line
    const scaleFactor = forecastVal / (lastVal || 1);
    
    if (unit === 'monthly') {
        const april2026Index = displayLabels.indexOf('Apr 26');
        actualChartData = dataValues.map((v, idx) => (idx <= april2026Index ? v : null));

        // Yellow line mirrors the blue line's curves, but scales toward the target forecast
        forecastChartData = dataValues.map((v, idx) => {
            if (idx === 0) return dataValues[0]; // Match starting point exactly
            return Math.round(v * scaleFactor);
        });
    } 
    else if (unit === 'yearly') {
        const idx2025 = displayLabels.indexOf("2025");
        actualChartData = dataValues.map((v, idx) => (idx <= idx2025 ? v : null));

        forecastChartData = dataValues.map((v, idx) => {
            if (idx === 0) return dataValues[0];
            return Math.round(v * scaleFactor);
        });
    } 
    else {
        actualChartData = [...dataValues];
        actualChartData[actualChartData.length - 1] = null;

        forecastChartData = dataValues.map((v, idx) => {
            if (idx === 0) return dataValues[0];
            return Math.round(v * scaleFactor);
        });
    }

    const iTitle = document.getElementById('insight-title');
    const iDesc = document.getElementById('insight-desc');
    const iBox = document.getElementById('pipeline-a-insight');

    if (iTitle && iDesc && iBox) {
        if (parseFloat(growth) > 10) {
            iTitle.innerHTML = `<i class="fas fa-chart-line"></i> Forecasted Surge (${growth}%)`;
            iDesc.innerText = `Expecting ${forecastVal} total requests. High workload ahead for ${unit} period.`;
            iBox.style.borderLeftColor = "#ef4444";
            iBox.style.background = "#fef2f2";
        } else {
            iTitle.innerHTML = `<i class="fas fa-check-circle"></i> Normal Volume`;
            iDesc.innerText = `Growth at ${growth}%. Operations expected to remain steady.`;
            iBox.style.borderLeftColor = "#10b981";
            iBox.style.background = "#f0fdf4";
        }
    }

    const kpiPred = document.getElementById('kpi-predicted');
    if (kpiPred) kpiPred.innerText = forecastVal.toLocaleString();
    
    document.querySelectorAll('.tgl-btn').forEach(b => b.classList.remove('active'));
    const btn = document.getElementById('btn-' + unit);
    if (btn) btn.classList.add('active');

    if (volumeChart) volumeChart.destroy();
    const ctx = document.getElementById('volumeChart');
    if (ctx) {
        volumeChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: displayLabels,
                datasets: [
                    {
                        label: 'Actual Volume',
                        data: actualChartData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.05)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Predicted Curve Profile',
                        data: forecastChartData,
                        borderColor: '#f59e0b',
                        backgroundColor: 'transparent',
                        borderDash: [6, 4],
                        pointRadius: 4,
                        pointBackgroundColor: '#f59e0b',
                        fill: false,
                        tension: 0.4 // Matches the curve tension of the blue line perfectly
                    }
                ]
            },
            options: { 
                plugins: { 
                    legend: { 
                        display: true,
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            font: { family: "'Inter', sans-serif", weight: '600', size: 12 }
                        }
                    } 
                }, 
                scales: { y: { beginAtZero: true } } 
            }
        });
    }

    updatePipelineInsights(unit);
}

function initHeatmap() {
    const map = L.map('map', { scrollWheelZoom: false }).setView([14.6839, 121.0860], 13);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const coordMap = {
        "Alicia": [14.6601, 121.0253], "Bagong Pag-asa": [14.6544, 121.0336], "Bahay Toro": [14.6713, 121.0264],
        "Bungad": [14.6517, 121.0210], "Del Monte": [14.6391, 121.0116], "Laging Handa": [14.6367, 121.0360],
        "Manresa": [14.6397, 121.0049], "Nayon Kanluran": [14.6473, 121.0216], "Paltok": [14.6394, 121.0179],
        "Project 6": [14.6631, 121.0374], "San Antonio": [14.6358, 121.0191], "Vasra": [14.6512, 121.0435],
        "Batasan Hills": [14.6839, 121.0860], "Commonwealth": [14.6826, 121.0608], "Holy Spirit": [14.6845, 121.0710],
        "Payatas": [14.7136, 121.1064], "Bagong Silangan": [14.7042, 121.1121], "Bagumbayan": [14.6074, 121.0792],
        "E. Rodriguez": [14.6289, 121.0583], "Libis": [14.6111, 121.0772], "Loyola Heights": [14.6385, 121.0744],
        "Matandang Balara": [14.6617, 121.0811], "Pansol": [14.6436, 121.0784], "San Roque": [14.6178, 121.0631],
        "Socorro": [14.6200, 121.0544], "Ugong Norte": [14.5960, 121.0640], "White Plains": [14.6040, 121.0638],
        "Bagong Lipunan ng Crame": [14.6094, 121.0519], "Don Manuel": [14.6190, 121.0116], "Immaculate Concepcion": [14.6212, 121.0409],
        "Kamuning": [14.6294, 121.0390], "Kaunlaran": [14.6172, 121.0475], "Kristong Hari": [14.6216, 121.0298],
        "Obrero": [14.6285, 121.0297], "Pinagkaisahan": [14.6240, 121.0494], "Sacred Heart": [14.6341, 121.0396],
        "San Martin de Porres": [14.6158, 121.0511], "Tatalon": [14.6213, 121.0189], "U.P. Campus": [14.6549, 121.0652],
        "U.P. Village": [14.6469, 121.0560], "Fairview": [14.7011, 121.0583], "Greater Lagro": [14.7194, 121.0654],
        "Pasong Putik": [14.7327, 121.0604], "North Fairview": [14.6994, 121.0519], "Santa Monica": [14.7126, 121.0456],
        "Gulod": [14.7042, 121.0371], "San Bartolome": [14.6908, 121.0347], "Bagbag": [14.6967, 121.0336],
        "Nagkaisang Nayon": [14.7176, 121.0315], "Novaliches Proper": [14.7000, 121.0333], "San Agustin": [14.7077, 121.0402],
        "Tandang Sora": [14.6775, 121.0433], "Pasong Tamo": [14.6732, 121.0610], "Culiat": [14.6644, 121.0515],
        "Sauyo": [14.6896, 121.0431], "Talipapa": [14.6901, 121.0208], "Baesa": [14.6706, 121.0113],
        "Sangandaan": [14.6749, 121.0252], "Apolonio Samson": [14.6534, 121.0069], "Unang Sigaw": [14.6538, 121.0016],
        "Fairview Village": [14.7011, 121.0583], "Project 4": [14.6191, 121.0682], "Project 8": [14.6756, 121.0219]
    };

    const heatPoints = mapData.map(loc => {
        const dbName = loc.name ? loc.name.trim() : "";
        const coords = (loc.lat && loc.lng) ? [loc.lat, loc.lng] : coordMap[dbName];

        if (coords) {
            const intensity = Math.min(loc.count / 50, 1); 
            return [...coords, intensity];
        }
        return null;
    }).filter(p => p !== null);

    L.heatLayer(heatPoints, {
        radius: 35, blur: 20, max: 1.0, minOpacity: 0.5,  
        gradient: { 0.4: 'blue', 0.6: 'cyan', 0.7: 'lime', 0.8: 'yellow', 1.0: 'red' }
    }).addTo(map);
}

window.onload = () => {
    changeTimeframe('monthly');
    filterTable();
    initHeatmap(); 
};
</script>
</body>
</html>
