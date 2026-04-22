<?php
// records.php

session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'auth.php';

// 1. Database Configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'aics_dss'; 

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("<div style='color:red; padding:20px; background:#fee2e2;'>Database Connection Error: " . $conn->connect_error . "</div>");
}

// --- ACTION HANDLER ---

function logChange($conn, $record_id, $column, $old_val, $new_val) {
    if ($old_val != $new_val) {
        $stmt = $conn->prepare("INSERT INTO audit_logs (record_id, action_type, changed_column, old_value, new_value) VALUES (?, 'UPDATE', ?, ?, ?)");
        $stmt->bind_param("isss", $record_id, $column, $old_val, $new_val);
        $stmt->execute();
    }
}

function verifyAdminAuth($password, $conn) {
    $stmt = $conn->prepare("SELECT password FROM users WHERE role = 'Admin' LIMIT 1");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        return ($password === $row['password']);
    }
    return false;
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
    if ($_SESSION['role'] === 'Staff') {
        if (!isset($_POST['admin_pass']) || !verifyAdminAuth($_POST['admin_pass'], $conn)) {
            header("Location: records.php?msg=auth_failed");
            exit();
        }
    }
    
    $id = (int)$_POST['edit_id'];
    $status = $conn->real_escape_string($_POST['status']);
    $date = $conn->real_escape_string($_POST['request_date']);
    
    $res = $conn->query("SELECT status, request_date FROM aics_sample_data WHERE id = $id");
    $old = $res->fetch_assoc();

    $conn->query("UPDATE aics_sample_data SET status='$status', request_date='$date' WHERE id=$id");

    logChange($conn, $id, 'status', $old['status'], $status);
    logChange($conn, $id, 'request_date', $old['request_date'], $date);

    header("Location: records.php?msg=updated");
    exit();
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_record'])) {
    if ($_SESSION['role'] === 'Staff') {
        if (!isset($_POST['admin_pass']) || !verifyAdminAuth($_POST['admin_pass'], $conn)) {
            header("Location: records.php?msg=auth_failed");
            exit();
        }
    }
    $id = (int)$_POST['delete_id'];
    $conn->query("INSERT INTO audit_logs (record_id, action_type, changed_column) VALUES ($id, 'DELETE', 'full_record')");
    $conn->query("DELETE FROM aics_sample_data WHERE id = $id");
    header("Location: records.php?msg=success");
    exit();
}

// Handle Approve
if (isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $res = $conn->query("SELECT status FROM aics_sample_data WHERE id = $id");
    $old = $res->fetch_assoc();
    $conn->query("UPDATE aics_sample_data SET status='Approved' WHERE id=$id");
    logChange($conn, $id, 'status', $old['status'], 'Approved');
    header("Location: records.php?msg=success");
    exit();
}

// 2. Pagination & Filter Parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$cause_filter = isset($_GET['cause']) ? $conn->real_escape_string($_GET['cause']) : '';
$type_filter = isset($_GET['type_filter']) ? $conn->real_escape_string($_GET['type_filter']) : '';
$status_filter = isset($_GET['status_filter']) ? $conn->real_escape_string($_GET['status_filter']) : '';
$start_date = isset($_GET['start']) ? $conn->real_escape_string($_GET['start']) : '';
$end_date = isset($_GET['end']) ? $conn->real_escape_string($_GET['end']) : '';

// 3. FETCH UNIQUE DROPDOWN VALUES
$unique_causes = [];
$res_c = $conn->query("SELECT DISTINCT medical_cause FROM aics_sample_data WHERE medical_cause != '' ORDER BY medical_cause ASC");
while($r = $res_c->fetch_assoc()) $unique_causes[] = $r['medical_cause'];

$unique_types = [];
$res_t = $conn->query("SELECT DISTINCT assistance_type FROM aics_sample_data WHERE assistance_type != '' ORDER BY assistance_type ASC");
while($r = $res_t->fetch_assoc()) $unique_types[] = $r['assistance_type'];

// --- HEATMAP LOCATION DATA ---
$location_data = [];
$l_res = $conn->query("SELECT barangay, COUNT(*) as count FROM aics_sample_data WHERE barangay IS NOT NULL AND barangay != '' GROUP BY barangay ORDER BY count DESC LIMIT 8");
while($l_row = $l_res->fetch_assoc()) { $location_data[] = $l_row; }
$loc_labels = json_encode(array_column($location_data, 'barangay'));
$loc_counts = json_encode(array_column($location_data, 'count'));

// 4. Build Query
$where_clauses = ["1=1"];
$is_duplicate_view = (isset($_GET['action']) && $_GET['action'] === 'find_duplicates');

if ($is_duplicate_view) {
    $where_clauses[] = "CONCAT(fname, lname, birth_date) IN (
        SELECT CONCAT(fname, lname, birth_date) 
        FROM aics_sample_data 
        GROUP BY fname, lname, birth_date 
        HAVING COUNT(*) > 1
    )";
    $sort_logic = "lname ASC, fname ASC, request_date DESC";
} else {
    $sort_logic = "request_date DESC, id DESC";
}

if (!empty($search)) $where_clauses[] = "(medical_cause LIKE '%$search%' OR assistance_type LIKE '%$search%' OR fname LIKE '%$search%' OR lname LIKE '%$search%')";
if (!empty($cause_filter)) $where_clauses[] = "medical_cause = '$cause_filter'";
if (!empty($type_filter)) $where_clauses[] = "assistance_type = '$type_filter'";
if (!empty($status_filter)) $where_clauses[] = "status = '$status_filter'";
if (!empty($start_date)) $where_clauses[] = "request_date >= '$start_date'";
if (!empty($end_date)) $where_clauses[] = "request_date <= '$end_date'";

$where_str = implode(" AND ", $where_clauses);

$total_res = $conn->query("SELECT COUNT(*) as total FROM aics_sample_data WHERE $where_str");
$total_records = $total_res->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$sql = "SELECT id, id_number, request_date, medical_cause, assistance_type, status, fname, mname, lname, barangay, birth_date
        FROM aics_sample_data
        WHERE $where_str 
        ORDER BY $sort_logic 
        LIMIT $offset, $limit";

$result = $conn->query($sql);
$all_records = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) { $all_records[] = $row; }
}

function getPaginationUrl($p, $l = null) {
    $params = $_GET;
    $params['page'] = $p;
    if($l) $params['limit'] = $l;
    return "?" . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSWD AICS - All Records</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { overflow-y: scroll; }
        :root { --dswd-dark: #2c3e50; --sidebar-bg: #1e293b; --bg-color: #f8fafc; --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); --sidebar-width: 260px; }
        body { font-family: 'Inter', sans-serif; margin: 0; background: var(--bg-color); display: flex; color: #334155; }
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); position: fixed; left: 0; top: 0; color: #fff; display: flex; flex-direction: column; z-index: 1000; }
        .sidebar-header { padding: 30px 20px; text-align: center; background: rgba(0,0,0,0.2); }
        .sidebar a { padding: 15px 25px; text-decoration: none; color: #94a3b8; display: flex; align-items: center; transition: all 0.3s ease; border-left: 4px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255, 255, 255, 0.05); color: #fff; border-left: 4px solid #3b82f6; }
        .main { margin-left: 260px; padding: 40px; width: calc(100% - 260px); min-height: 100vh; }
        .header-area { margin-bottom: 30px; }
        .header-area h1 { margin: 0; font-size: 24px; color: var(--dswd-dark); }
        .table-container { background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); overflow: hidden; border: 1px solid #e2e8f0; }
        .filter-header { padding: 20px; background: #fff; border-bottom: 1px solid #f1f5f9; }
        .controls-row { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 15px; }
        .search-wrapper { position: relative; flex: 2; min-width: 250px; }
        .search-wrapper i { position: absolute; left: 12px; top: 12px; color: #94a3b8; }
        .input-field { padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; background: #fff; }
        .search-box { width: 100%; padding-left: 35px; box-sizing: border-box; }
        .filter-select { flex: 1; min-width: 150px; background: #f8fafc; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 20px; background: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #eff6ff; color: #1d4ed8; }
        .status-badge { padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; display: inline-block; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-approved { background: #dcfce7; color: #16a34a; }
        .status-paid { background: #dbeafe; color: #2563eb; }
        .status-waitlisted { background: #f3e8ff; color: #9333ea; }
        .status-declined { background: #fee2e2; color: #dc2626; }
        .btn-print { padding: 10px 20px; background: #0f172a; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-duplicate { padding: 10px 20px; background: #f59e0b; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration:none; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-recent { padding: 10px 20px; background: #f8fafc; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .action-btn { padding: 6px; border-radius: 4px; border: 1px solid #e2e8f0; color: #64748b; transition: 0.2s; cursor: pointer; text-decoration: none; font-size: 12px; margin-right: 5px; background: #fff; }
        .btn-edit { color: #3b82f6; } .btn-edit:hover { background: #eff6ff; }
        .btn-delete { color: #ef4444; } .btn-delete:hover { background: #fef2f2; }
        .btn-approve { color: #10b981; } .btn-approve:hover { background: #f0fdf4; }
        .total-counter-box { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px; }
        .counter-icon { width: 45px; height: 45px; background: #eff6ff; color: #3b82f6; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .pagination-footer { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-top: 1px solid #e2e8f0; }
        .pagination-btns { display: flex; gap: 4px; }
        .pg-btn { padding: 6px 12px; border: 1px solid #e2e8f0; background: #fff; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 13px; font-weight: 600; transition: 0.1s; min-width: 32px; text-align: center; }
        .pg-btn.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
        .pg-btn.disabled { color: #cbd5e1; pointer-events: none; background: #f8fafc; }
        .modal-overlay, .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-box, .modal-content { background: #fff; margin: 5% auto; padding: 30px; border-radius: 12px; width: 450px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; position: relative;}
        .modal-input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 5px; box-sizing: border-box; font-family: inherit; }
        .toast-msg { position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 15px 25px; border-radius: 8px; z-index: 10000; font-weight: 600; }
        .close { position: absolute; right: 20px; top: 15px; font-size: 24px; cursor: pointer; color: #94a3b8; }
        @media print {
            .sidebar, .btn-print, .btn-duplicate, .btn-recent, .filter-header, .pagination-footer, .no-print, .toast-msg, .heatmap-container { display: none !important; }
            body { background: white; color: black; }
            .main { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
            .table-container { box-shadow: none !important; border: 1px solid #ccc !important; }
            table { width: 100% !important; }
            th, td { border: 1px solid #eee !important; }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
    
    <?php if (isset($_GET['msg'])): ?>
    <div id="toast" class="toast-msg" style="<?php echo ($_GET['msg'] == 'auth_failed') ? 'background:#ef4444;' : ''; ?>">
        <i class="fas <?php echo ($_GET['msg'] == 'auth_failed') ? 'fa-shield-alt' : 'fa-check-circle'; ?>"></i>
        <?php 
            if($_GET['msg'] == 'updated') echo "Record updated successfully!";
            elseif($_GET['msg'] == 'success') echo "Action completed!";
            elseif($_GET['msg'] == 'auth_failed') echo "Invalid Admin Credentials!";
        ?>
    </div>
    <script>setTimeout(() => { document.getElementById('toast').style.display = 'none'; }, 3000);</script>
    <?php endif; ?>

    <form id="filterForm" method="GET">
        <input type="hidden" name="limit" value="<?php echo $limit; ?>">

        <div class="header-area" style="display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <h1 style="margin:0; color:var(--dswd-dark); font-size: 28px;">Beneficiary Records</h1>
                <p style="color:#64748b; margin-top: 5px;">Historical database of AICS interventions</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="records.php?action=find_duplicates" class="btn-duplicate">
                    <i class="fas fa-copy"></i> Find Duplicates
                </a>
                <button type="button" onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Print Results
                </button>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-top: 20px; margin-bottom: 20px;">
            <div class="total-counter-box">
                <div class="counter-icon"><i class="fas fa-users"></i></div>
                <div>
                    <div style="font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: 700;">Total Applicants</div>
                    <div style="font-size: 24px; font-weight: 700; color: #1e293b;"><?php echo number_format($total_records); ?></div>
                </div>
            </div>

            <div class="heatmap-container" style="background: #fff; padding: 15px 20px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 20px; box-shadow: var(--card-shadow);">
                <div style="flex: 1;">
                    <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 5px;">Demand Heatmap (by Barangay)</div>
                    <div style="height: 60px;"><canvas id="locationChart"></canvas></div>
                </div>
                <div id="top-barangay" style="text-align: right; border-left: 1px solid #f1f5f9; padding-left: 20px;">
                    <div style="font-size: 10px; color: #ef4444; font-weight: 700;">HIGHEST DEMAND</div>
                    <div style="font-size: 16px; font-weight: 700; color: #1e293b;"><?php echo $location_data[0]['barangay'] ?? 'N/A'; ?></div>
                </div>
            </div>
        </div>

        <div class="table-container">
            <div class="filter-header">
                <div class="controls-row">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="input-field search-box" placeholder="Search records..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <select name="cause" class="input-field filter-select" onchange="this.form.submit()">
                        <option value="">All Medical Causes</option>
                        <?php foreach($unique_causes as $cause): ?>
                            <option value="<?php echo htmlspecialchars($cause); ?>" <?php if($cause_filter == $cause) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($cause); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status_filter" class="input-field filter-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <?php 
                        $statuses = ['Pending', 'Approved', 'Paid', 'Waitlisted', 'Declined'];
                        foreach($statuses as $s): ?>
                            <option value="<?php echo $s; ?>" <?php if($status_filter == $s) echo 'selected'; ?>><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <a href="records.php" class="btn-recent">
                        <i class="fas fa-clock"></i> Recent
                    </a>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">Date</th>
                        <th style="width: 30%;">Medical Cause</th>
                        <th style="width: 25%;">Assistance Type</th>
                        <th style="width: 15%;">Status</th> 
                        <th style="width: 15%; text-align: center;" class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_records)): ?>
                    <?php foreach ($all_records as $row): 
                        $fn = $row['fname'] ?? '';
                        $mn = $row['mname'] ?? '';
                        $ln = $row['lname'] ?? '';
                        $fullname = trim("$fn $mn $ln") ?: "Unknown Beneficiary";
                        $brgy = $row['barangay'] ?? 'N/A';
                        
                        // Proper date formatting for JS consumption
$bdate = (!empty($row['birth_date']) && $row['birth_date'] != '0000-00-00') 
         ? date("Y-m-d", strtotime($row['birth_date'])) 
         : 'N/A';
                        
                        $idNum = $row['id_number'] ?? 'N/A';
                        $status = $row['status'] ?: 'Pending';
                        $fullname = trim(($row['fname'] ?? '') . " " . ($row['lname'] ?? ''));
                    ?>
                    <tr>
                        <td><?php echo date("M d, Y", strtotime($row['request_date'])); ?></td>
                        <td style="font-weight:600;"><?php echo htmlspecialchars($row['medical_cause']); ?></td>
                        <td><span class="badge"><?php echo htmlspecialchars($row['assistance_type']); ?></span></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($status); ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </td>
                        <td style="text-align: center;" class="no-print">
                            <button type="button" class="action-btn btn-edit" 
    onclick="openModal('<?php echo $row['id']; ?>', 
                       '<?php echo addslashes($row['medical_cause']); ?>', 
                       '<?php echo addslashes($row['assistance_type']); ?>', 
                       '<?php echo addslashes($s); ?>', 
                       '<?php echo $row['request_date']; ?>', 
                       '<?php echo addslashes($fullname); ?>', 
                       '<?php echo addslashes($brgy); ?>', 
                       '<?php echo $bdate; ?>', 
                       '<?php echo $idNum; ?>')">
    <i class="fas fa-edit"></i>
</button>
                            <button type="button" class="action-btn" title="View History" onclick="viewHistory(<?php echo $row['id']; ?>)">
                                <i class="fas fa-history"></i>
                            </button>
                            <a href="records.php?action=approve&id=<?php echo $row['id']; ?>" class="action-btn btn-approve" onclick="return confirm('Approve this record?')"><i class="fas fa-check"></i></a>
                            <button type="button" class="action-btn btn-delete" onclick="openDeleteModal('<?php echo $row['id']; ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding: 40px;">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

            <?php if ($total_pages >= 1): ?>
            <div class="pagination-footer">
                <div class="pagination-info">
                    <?php 
                        $start_count = $offset + 1;
                        $end_count = min($offset + $limit, $total_records);
                        echo "$start_count - $end_count of $total_records"; 
                    ?>
                </div>
                <div class="pagination-btns">
                    <a href="<?php echo getPaginationUrl($page - 1); ?>" class="pg-btn <?php if($page <= 1) echo 'disabled'; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="<?php echo getPaginationUrl($page + 1); ?>" class="pg-btn <?php if($page >= $total_pages) echo 'disabled'; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <h2 style="margin-top:0;">Edit Record</h2>
        <form method="POST">
            <input type="hidden" name="update_record" value="1">
            <input type="hidden" name="edit_id" id="m_id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom:15px; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <div>
                    <label style="font-size:10px; font-weight:700; color: #64748b;">ID NUMBER</label>
                    <input type="text" id="read_id" class="modal-input" style="background:#f1f5f9; border:none; font-weight:600;" readonly>
                </div>
                <div>
                    <label style="font-size:10px; font-weight:700; color: #64748b;">BARANGAY</label>
                    <input type="text" id="read_brgy" class="modal-input" style="background:#f1f5f9; border:none; font-weight:600;" readonly>
                </div>
                <div>
                    <label style="font-size:10px; font-weight:700; color: #64748b;">BIRTH DATE</label>
                    <input type="text" id="read_bdate" class="modal-input" style="background:#f1f5f9; border:none; font-weight:600;" readonly>
                </div>
                <div>
                    <label style="font-size:10px; font-weight:700; color: #64748b;">FULL NAME</label>
                    <input type="text" id="read_name" class="modal-input" style="background:#f1f5f9; border:none; font-weight:600;" readonly>
                </div>
            </div>

            <div style="margin-bottom:15px;">
                <label style="font-size:12px; font-weight:700;">REQUEST DATE</label>
                <input type="date" name="request_date" id="m_date" class="modal-input" required>
            </div>
            <div style="margin-bottom:15px;">
                <label style="font-size:12px; font-weight:700;">MEDICAL CAUSE</label>
                <input type="text" id="m_cause" class="modal-input" style="background:#f1f5f9;" readonly>
            </div>
            <div style="margin-bottom:15px;">
                <label style="font-size:12px; font-weight:700;">ASSISTANCE TYPE</label>
                <input type="text" id="m_type" class="modal-input" style="background:#f1f5f9;" readonly>
            </div>
            <div style="margin-bottom:25px;">
                <label style="font-size:12px; font-weight:700;">STATUS</label>
                <select name="status" id="m_status" class="modal-input" required>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Paid">Paid</option>
                    <option value="Waitlisted">Waitlisted</option>
                    <option value="Declined">Declined</option>
                </select>
            </div>

            <?php if($_SESSION['role'] === 'Staff'): ?>
            <div style="background: #fff1f2; padding: 15px; border-radius: 8px; border: 1px solid #fecaca; margin-bottom: 20px;">
                <label style="font-size:11px; color:#e11d48; font-weight:700;">ADMIN AUTHORIZATION</label>
                <input type="password" name="admin_pass" class="modal-input" placeholder="Enter Admin Password" required>
            </div>
            <?php endif; ?>

            <div style="display:flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeModal()" class="action-btn">Cancel</button>
                <button type="submit" style="background:#3b82f6; color:#fff; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-weight:600;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="historyModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeHistoryModal()">&times;</span>
        <h3><i class="fas fa-history"></i> Audit Trail - Record #<span id="historyRecordId"></span></h3>
        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 15px 0;">
        <div id="historyContent">
            <p>Loading history...</p>
        </div>
    </div>
</div>

<div id="deleteModal" class="modal-overlay">
    <div class="modal-box" style="width: 350px; text-align: center;">
        <i class="fas fa-user-shield" style="font-size: 40px; color: #ef4444; margin-bottom: 15px;"></i>
        <h3>Admin Authorization</h3>
        <form method="POST">
            <input type="hidden" name="delete_record" value="1">
            <input type="hidden" name="delete_id" id="d_id">
            <input type="password" name="admin_pass" class="modal-input" placeholder="Enter Admin Password" required style="text-align: center; margin-bottom: 15px;">
            <div style="display:flex; gap: 10px;">
                <button type="button" onclick="closeDeleteModal()" class="action-btn" style="flex:1">Cancel</button>
                <button type="submit" style="background:#ef4444; color:#fff; border:none; padding:10px; border-radius:8px; cursor:pointer; flex:1;">Confirm Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
// Chart Logic
const locCtx = document.getElementById('locationChart').getContext('2d');
new Chart(locCtx, {
    type: 'bar',
    data: {
        labels: <?php echo $loc_labels; ?>,
        datasets: [{
            label: 'Requests',
            data: <?php echo $loc_counts; ?>,
            backgroundColor: 'rgba(239, 68, 68, 0.6)',
            borderColor: '#ef4444',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { 
            x: { display: false },
            y: { ticks: { font: { size: 9 } } }
        }
    }
});

// View History Logic
function viewHistory(id) {
    document.getElementById('historyRecordId').innerText = id;
    document.getElementById('historyModal').style.display = 'block';
    document.getElementById('historyContent').innerHTML = '<p style="text-align:center; padding:20px;">Fetching records...</p>';

    fetch('fetch_history.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('historyContent').innerHTML = data;
    });
}

function closeModal() {
    // This must match the id="editModal" in your HTML
    const modal = document.getElementById('editModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function closeHistoryModal() { document.getElementById('historyModal').style.display = 'none'; }

function openDeleteModal(id) {
    // 1. Set the ID in the hidden input inside the delete modal
    const idInput = document.getElementById('d_id');
    if (idInput) {
        idInput.value = id;
    }
    
    // 2. Show the modal
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.display = 'block';
    }
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Edit Modal Logic
// FIXED Edit Modal Logic
function openModal(id, cause, type, status, date, name, brgy, bdate, idNum) {
    // Fill the hidden and editable fields
    document.getElementById('m_id').value = id;
    document.getElementById('m_cause').value = cause;
    document.getElementById('m_type').value = type;
    document.getElementById('m_status').value = status;
    document.getElementById('m_date').value = date;
    
    // Fill the read-only section
    document.getElementById('read_id').value = idNum;
    document.getElementById('read_name').value = name;
    document.getElementById('read_brgy').value = brgy;
    
    // FILL BIRTHDATE HERE
    document.getElementById('read_bdate').value = bdate;

    // Show the modal
    document.getElementById('editModal').style.display = 'block';
}

// Ensure outside click handles the editModal too
window.onclick = function(event) {
    if (event.target == document.getElementById('historyModal')) { closeHistoryModal(); }
    if (event.target == document.getElementById('editModal')) { closeModal(); }
    if (event.target == document.getElementById('deleteModal')) { closeDeleteModal(); }
}
</script>
</body>
</html>
