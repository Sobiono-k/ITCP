<?php
// reports.php

session_start(); // THIS MUST BE THE VERY FIRST LINE

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Database Connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'aics_dss'; 
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Handle Budget Pool Input
$totalBudgetPool = isset($_GET['pool']) ? (float)$_GET['pool'] : 1000000;

// 3. Get Total Requests
$totalRes = $conn->query("SELECT COUNT(*) as total FROM aics_sample_data");
$totalRequests = $totalRes->fetch_assoc()['total'];

// 4. Fetch Assistance Type Allocations
$allocations = [];
$typeQuery = "SELECT assistance_type, COUNT(*) as count FROM aics_sample_data GROUP BY assistance_type";
$typeRes = $conn->query($typeQuery);
while($row = $typeRes->fetch_assoc()){
    $percent = ($row['count'] / $totalRequests);
    $allocations[] = [
        'type' => $row['assistance_type'],
        'percent' => round($percent * 100, 1),
        'amount' => $totalBudgetPool * $percent
    ];
}

// 5. Fetch Medical Cause Distribution
$medicalCauses = [];
$causeQuery = "SELECT medical_cause, COUNT(*) as count FROM aics_sample_data GROUP BY medical_cause ORDER BY count DESC LIMIT 10";
$causeRes = $conn->query($causeQuery);
while($row = $causeRes->fetch_assoc()){
    $medicalCauses[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSWD AICS - Reports</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --dswd-dark: #2c3e50;
            --sidebar-bg: #1e293b;
            --bg-color: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --sidebar-width: 260px;
            --dswd-blue: #3b82f6;
        }

        body { font-family: 'Inter', sans-serif; margin: 0; background: var(--bg-color); display: flex; color: #334155; }
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); position: fixed; left: 0; top: 0; color: #fff; display: flex; flex-direction: column; z-index: 1000; }
        .sidebar-header { padding: 30px 20px; text-align: center; background: rgba(0,0,0,0.2); }
        .sidebar a { padding: 15px 25px; text-decoration: none; color: #94a3b8; display: flex; align-items: center; transition: 0.3s; border-left: 4px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background: #334155; color: #fff; border-left: 4px solid #3b82f6; }

        .main { margin-left: var(--sidebar-width); padding: 40px; width: calc(100% - var(--sidebar-width)); min-height: 100vh; box-sizing: border-box; }
        .header-area { margin-bottom: 30px; }
        .header-area h1 { margin: 0; font-size: 24px; color: var(--dswd-dark); }
        
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .report-card { background: #fff; padding: 30px 20px; border-radius: 12px; box-shadow: var(--card-shadow); text-align: center; cursor: pointer; transition: 0.2s; border: 1px solid transparent; }
        .report-card:hover { transform: translateY(-5px); border-color: var(--dswd-blue); background: #f0f7ff; }
        .report-card i { font-size: 40px; margin-bottom: 15px; color: var(--dswd-blue); }
        .report-card h3 { margin: 0; font-size: 16px; color: var(--dswd-dark); }

        .section-box { background: #fff; padding: 25px; border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 20px; }
        .hidden { display: none; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th { text-align: left; padding: 15px; background: #f8fafc; color: #64748b; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        
        .view-btn { background: var(--dswd-blue); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        
        .pool-input { padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-weight: 700; color: #059669; width: 150px; }

        @media print {
            .sidebar, .report-grid, .view-btn, .pool-row { display: none !important; }
            .main { margin: 0; padding: 0; width: 100%; }
            .section-box { box-shadow: none; border: none; display: block !important; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
    <div class="header-area">
        <h1>Reports & Analytics</h1>
        <p style="color:#64748b; margin-top:5px;">AICS Decision Support <span style="color:red">▶</span> Batasan Hills</p>
    </div>

    <div class="report-grid">
        <div class="report-card" onclick="showSection('budget')">
            <i class="fas fa-chart-pie"></i>
            <h3>Budget Allocation</h3>
        </div>
        <div class="report-card" onclick="showSection('medical')">
            <i class="fas fa-notes-medical"></i>
            <h3>Medical Cause Analysis</h3>
        </div>
        <div class="report-card" onclick="showSection('recent')">
            <i class="fas fa-history"></i>
            <h3>Generated Reports</h3>
        </div>
    </div>

    <div id="budgetSection" class="section-box">
        <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2 style="margin:0;">Proportional Budget Allocation</h2>
                <p style="font-size:13px; color:#64748b; margin-top:5px;">Based on <?php echo number_format($totalRequests); ?> processed records</p>
            </div>
            <div class="pool-row">
                <form method="GET" style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:13px; font-weight:600;">PHP Pool:</span>
                    <input type="number" name="pool" class="pool-input" value="<?php echo $totalBudgetPool; ?>">
                    <button type="submit" class="view-btn">Update</button>
                    <button type="button" class="view-btn" onclick="window.print()" style="background:#0f172a;"><i class="fas fa-file-pdf"></i> Export</button>
                </form>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Assistance Category</th>
                    <th>Current Demand %</th>
                    <th>Recommended Allocation (PHP)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allocations as $item): ?>
                <tr>
                    <td style="font-weight:700; color:var(--dswd-dark);"><?php echo htmlspecialchars($item['type']); ?></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="flex:1; background:#f1f5f9; height:8px; border-radius:4px; max-width:100px;">
                                <div style="width:<?php echo $item['percent']; ?>%; background:var(--dswd-blue); height:100%; border-radius:4px;"></div>
                            </div>
                            <?php echo $item['percent']; ?>%
                        </div>
                    </td>
                    <td style="color:#059669; font-weight:700;">₱<?php echo number_format($item['amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="medicalSection" class="section-box hidden">
        <h2>Top Medical Causes of Distress</h2>
        <table>
            <thead>
                <tr>
                    <th>Medical Cause</th>
                    <th>Occurrence Count</th>
                    <th>Priority Level</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($medicalCauses as $row): ?>
                <tr>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($row['medical_cause']); ?></td>
                    <td><?php echo number_format($row['count']); ?> cases</td>
                    <td>
                        <?php if($row['count'] > 100): ?>
                            <span style="color:#dc2626; font-weight:700;">CRITICAL</span>
                        <?php else: ?>
                            <span style="color:#ea580c; font-weight:700;">HIGH</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="recentSection" class="section-box hidden">
        <h2>Trend Comparisons</h2>
        <table>
            <thead>
                <tr>
                    <th>Report Type</th>
                    <th>Data Range</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Weekly Forecast Analysis</td>
                    <td>Mar 01 - Mar 07, 2026</td>
                    <td><span style="color:#059669;">Ready</span></td>
                </tr>
                <tr>
                    <td>Monthly Demand Trend</td>
                    <td>February 2026</td>
                    <td><span style="color:#059669;">Ready</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function showSection(type) {
    // Hide all
    document.getElementById('budgetSection').classList.add('hidden');
    document.getElementById('medicalSection').classList.add('hidden');
    document.getElementById('recentSection').classList.add('hidden');
    
    // Show target
    document.getElementById(type + 'Section').classList.remove('hidden');
}
</script>

</body>
</html>