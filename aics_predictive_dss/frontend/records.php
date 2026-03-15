<?php
// records.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Database Configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'aics_dss'; 

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("<div style='color:red; padding:20px; background:#fee2e2;'>Database Connection Error: " . $conn->connect_error . "</div>");
}

// 2. Pagination & Filter Parameters
$limit = 20; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$cause_filter = isset($_GET['cause']) ? $conn->real_escape_string($_GET['cause']) : '';
$start_date = isset($_GET['start']) ? $conn->real_escape_string($_GET['start']) : '';
$end_date = isset($_GET['end']) ? $conn->real_escape_string($_GET['end']) : '';

// 3. Build Query with Filters
$where_clauses = ["1=1"];
if (!empty($search)) {
    $where_clauses[] = "(medical_cause LIKE '%$search%' OR assistance_type LIKE '%$search%')";
}
if (!empty($cause_filter)) {
    $where_clauses[] = "medical_cause = '$cause_filter'";
}
if (!empty($start_date)) {
    $where_clauses[] = "request_date >= '$start_date'";
}
if (!empty($end_date)) {
    $where_clauses[] = "request_date <= '$end_date'";
}
$where_str = implode(" AND ", $where_clauses);

// Get Total for Counter
$total_res = $conn->query("SELECT COUNT(*) as total FROM aics_sample_data WHERE $where_str");
$total_records = $total_res->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get Records
$sql = "SELECT request_date, medical_cause, assistance_type 
        FROM aics_sample_data 
        WHERE $where_str 
        ORDER BY request_date DESC 
        LIMIT $offset, $limit";
$result = $conn->query($sql);

$all_records = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $all_records[] = $row;
    }
}

// Get Unique Causes for Dropdown
$causes_res = $conn->query("SELECT DISTINCT medical_cause FROM aics_sample_data ORDER BY medical_cause ASC");
$unique_causes = [];
while($c = $causes_res->fetch_assoc()) {
    if(!empty($c['medical_cause'])) $unique_causes[] = $c['medical_cause'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSWD AICS - All Records</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .main { margin-left: var(--sidebar-width); padding: 40px; width: calc(100% - var(--sidebar-width)); min-height: 100vh; box-sizing: border-box; }
        .table-container { background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); overflow: hidden; border: 1px solid #e2e8f0; }

        .filter-header { padding: 20px; background: #fff; border-bottom: 1px solid #f1f5f9; }
        .controls-row { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 15px; }
        .controls-row:last-child { margin-bottom: 0; }

        .search-wrapper { position: relative; flex: 2; min-width: 250px; }
        .search-wrapper i { position: absolute; left: 12px; top: 12px; color: #94a3b8; }
        
        .input-field { padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; background: #fff; }
        .search-box { width: 100%; padding-left: 35px; box-sizing: border-box; }
        .filter-select { flex: 1; min-width: 180px; background: #f8fafc; cursor: pointer; }
        
        .date-group { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; font-weight: 500; }
        .date-input { border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 8px; font-family: inherit; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 20px; background: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e2e8f0; }
        td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        tr:hover { background-color: #f8fafc; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #eff6ff; color: #1d4ed8; text-transform: uppercase; }
        .btn-print { padding: 10px 20px; background: #0f172a; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .btn-recent { padding: 10px 20px; background: #f8fafc; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-recent:hover { background: #f1f5f9; }

        .pagination { padding: 20px; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-top: 1px solid #e2e8f0; }
        .page-btn { text-decoration: none; padding: 8px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; color: #1e293b; font-size: 13px; font-weight: 600; transition: 0.2s; }
        .page-btn:hover:not(.disabled) { background: #f1f5f9; border-color: #cbd5e1; }
        .page-btn.disabled { opacity: 0.5; cursor: not-allowed; }

        @media print { .sidebar, .filter-header, .pagination { display: none !important; } .main { margin-left: 0; padding: 0; width: 100%; } }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
    <form id="filterForm" method="GET">
        <div class="header-area" style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <h1 style="margin:0; color:var(--dswd-dark); font-size: 28px;">Beneficiary Records</h1>
                <p style="color:#64748b; margin-top: 5px;">Historical database of AICS interventions</p>
            </div>
            <div style="text-align: right; color: #94a3b8; font-size: 13px;">
                Showing <strong><?php echo count($all_records); ?></strong> of <strong><?php echo $total_records; ?></strong> entries
            </div>
        </div>

        <div class="table-container">
            <div class="filter-header">
                <div class="controls-row">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="input-field search-box" placeholder="Search cause or type..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <select name="cause" class="input-field filter-select">
                        <option value="">All Medical Causes</option>
                        <?php foreach($unique_causes as $cause): ?>
                            <option value="<?php echo htmlspecialchars($cause); ?>" <?php if($cause_filter == $cause) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($cause); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-print" style="background: #3b82f6;">Apply Filters</button>
                    <a href="records.php" class="btn-recent"><i class="fas fa-sync-alt"></i> Recent Records</a>
                    <button type="button" onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Print</button>
                </div>

                <div class="controls-row">
                    <div class="date-group">
                        <span>From:</span>
                        <input type="date" name="start" class="date-input" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="date-group">
                        <span>To:</span>
                        <input type="date" name="end" class="date-input" value="<?php echo $end_date; ?>">
                    </div>
                    <a href="records.php" style="color:#3b82f6; text-decoration:none; font-size:13px; font-weight:600;">Reset All</a>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 20%;">Date</th>
                        <th style="width: 40%;">Medical Cause</th>
                        <th style="width: 30%;">Assistance Type</th>
                        <th style="width: 10%;">Status</th> 
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_records)): ?>
                        <?php foreach ($all_records as $row): ?>
                        <tr>
                            <td style="color: #64748b;">
                                <?php 
                                    $d = date_create($row['request_date']);
                                    echo $d ? date_format($d, "M d, Y") : "Invalid Date"; 
                                ?>
                            </td>
                            <td style="font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($row['medical_cause']); ?></td>
                            <td><span class="badge"><?php echo htmlspecialchars($row['assistance_type']); ?></span></td>
                            <td><span style="color:#94a3b8; font-size: 12px;">Processed</span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding: 40px; color: #94a3b8;">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination">
                <div style="font-size: 13px; color: #64748b;">Page <?php echo $page; ?> of <?php echo $total_pages; ?></div>
                <div style="display: flex; gap: 8px;">
                    <?php 
                    $params = $_GET;
                    $params['page'] = $page - 1;
                    $prev_url = "?" . http_build_query($params);
                    $params['page'] = $page + 1;
                    $next_url = "?" . http_build_query($params);
                    ?>
                    
                    <a href="<?php echo $prev_url; ?>" class="page-btn <?php if($page <= 1) echo 'disabled'; ?>">Previous</a>
                    <a href="<?php echo $next_url; ?>" class="page-btn <?php if($page >= $total_pages) echo 'disabled'; ?>">Next</a>
                </div>
            </div>
        </div>
    </form>
</div>

</body>
</html>