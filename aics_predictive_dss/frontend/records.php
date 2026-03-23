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

// --- ACTION HANDLER ---
// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
    $id = (int)$_POST['edit_id'];
    $cause = $conn->real_escape_string($_POST['medical_cause']);
    $type = $conn->real_escape_string($_POST['assistance_type']);
    $status = $conn->real_escape_string($_POST['status']);
    $date = $conn->real_escape_string($_POST['request_date']);
    
    $conn->query("UPDATE aics_sample_data SET medical_cause='$cause', assistance_type='$type', status='$status', request_date='$date' WHERE id=$id");
    header("Location: records.php?msg=updated");
    exit();
}

// Handle Approve/Delete Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($_GET['action'] === 'approve') {
        $conn->query("UPDATE aics_sample_data SET status='Approved' WHERE id=$id");
        header("Location: records.php?msg=success");
    } elseif ($_GET['action'] === 'delete') {
        $conn->query("DELETE FROM aics_sample_data WHERE id=$id");
        header("Location: records.php?msg=success");
    }
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

// 3. FETCH UNIQUE DROPDOWN VALUES (Crucial Fix)
$unique_causes = [];
$res_c = $conn->query("SELECT DISTINCT medical_cause FROM aics_sample_data WHERE medical_cause != '' ORDER BY medical_cause ASC");
while($r = $res_c->fetch_assoc()) $unique_causes[] = $r['medical_cause'];

$unique_types = [];
$res_t = $conn->query("SELECT DISTINCT assistance_type FROM aics_sample_data WHERE assistance_type != '' ORDER BY assistance_type ASC");
while($r = $res_t->fetch_assoc()) $unique_types[] = $r['assistance_type'];

// 4. Build Query
$where_clauses = ["1=1"];
if (!empty($search)) $where_clauses[] = "(medical_cause LIKE '%$search%' OR assistance_type LIKE '%$search%')";
if (!empty($cause_filter)) $where_clauses[] = "medical_cause = '$cause_filter'";
if (!empty($type_filter)) $where_clauses[] = "assistance_type = '$type_filter'";
if (!empty($status_filter)) $where_clauses[] = "status = '$status_filter'";
if (!empty($start_date)) $where_clauses[] = "request_date >= '$start_date'";
if (!empty($end_date)) $where_clauses[] = "request_date <= '$end_date'";
$where_str = implode(" AND ", $where_clauses);

$total_res = $conn->query("SELECT COUNT(*) as total FROM aics_sample_data WHERE $where_str");
$total_records = $total_res->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

$sql = "SELECT id, request_date, medical_cause, assistance_type, status 
        FROM aics_sample_data WHERE $where_str ORDER BY request_date DESC LIMIT $offset, $limit";
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
    <style>
        :root { --dswd-dark: #2c3e50; --sidebar-bg: #1e293b; --bg-color: #f0f2f5; --card-shadow: 0 2px 12px rgba(0,0,0,0.08); --sidebar-width: 260px; }
        body { font-family: 'Inter', sans-serif; margin: 0; background: var(--bg-color); display: flex; color: #334155; }
        .sidebar { width: var(--sidebar-width); height: 100vh; background: var(--sidebar-bg); position: fixed; left: 0; top: 0; color: #fff; display: flex; flex-direction: column; z-index: 1000; }
        .sidebar-header { padding: 30px 20px; text-align: center; background: rgba(0,0,0,0.2); }
        .sidebar a { padding: 15px 25px; text-decoration: none; color: #94a3b8; display: flex; align-items: center; transition: 0.3s; border-left: 4px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background: #334155; color: #fff; border-left: 4px solid #3b82f6; }
        .main { margin-left: var(--sidebar-width); padding: 40px; width: calc(100% - var(--sidebar-width)); min-height: 100vh; box-sizing: border-box; }
        .table-container { background: #fff; border-radius: 12px; box-shadow: var(--card-shadow); overflow: hidden; border: 1px solid #e2e8f0; }
        .filter-header { padding: 20px; background: #fff; border-bottom: 1px solid #f1f5f9; }
        .controls-row { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 15px; }
        .search-wrapper { position: relative; flex: 2; min-width: 250px; }
        .search-wrapper i { position: absolute; left: 12px; top: 12px; color: #94a3b8; }
        .input-field { padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; background: #fff; }
        .search-box { width: 100%; padding-left: 35px; box-sizing: border-box; }
        .filter-select { flex: 1; min-width: 150px; background: #f8fafc; cursor: pointer; }
        .date-group { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; font-weight: 500; }
        .date-input { border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 8px; font-family: inherit; }
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
        .btn-recent { padding: 10px 20px; background: #f8fafc; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .action-btn { padding: 6px; border-radius: 4px; border: 1px solid #e2e8f0; color: #64748b; transition: 0.2s; cursor: pointer; text-decoration: none; font-size: 12px; margin-right: 5px; background: #fff; }
        .btn-edit { color: #3b82f6; } .btn-edit:hover { background: #eff6ff; }
        .btn-delete { color: #ef4444; } .btn-delete:hover { background: #fef2f2; }
        .btn-approve { color: #10b981; } .btn-approve:hover { background: #f0fdf4; }
        .total-counter-box { background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px; }
        .counter-icon { width: 45px; height: 45px; background: #eff6ff; color: #3b82f6; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .pagination-footer { padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-top: 1px solid #e2e8f0; }
        .pagination-left { display: flex; align-items: center; gap: 15px; }
        .pagination-info { font-size: 13px; color: #64748b; }
        .page-search-wrapper { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; }
        .page-input { width: 45px; padding: 5px; border: 1px solid #e2e8f0; border-radius: 6px; text-align: center; font-weight: 600; }
        .pagination-btns { display: flex; gap: 4px; }
        .pg-btn { padding: 6px 12px; border: 1px solid #e2e8f0; background: #fff; border-radius: 6px; text-decoration: none; color: #1e293b; font-size: 13px; font-weight: 600; transition: 0.1s; min-width: 32px; text-align: center; }
        .pg-btn:hover { background: #f1f5f9; }
        .pg-btn.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
        .pg-btn.disabled { color: #cbd5e1; pointer-events: none; background: #f8fafc; }
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-box { background: #fff; margin: 5% auto; padding: 30px; border-radius: 12px; width: 450px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        .modal-input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 5px; box-sizing: border-box; font-family: inherit; }
        .toast-msg { position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 15px 25px; border-radius: 8px; z-index: 10000; font-weight: 600; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
    <?php if (isset($_GET['msg'])): ?>
        <div id="toast" class="toast-msg">
            <i class="fas fa-check-circle"></i>
            <?php 
                if($_GET['msg'] == 'updated') echo "Record updated successfully!";
                elseif($_GET['msg'] == 'success') echo "Action completed successfully!";
            ?>
        </div>
        <script>setTimeout(() => { document.getElementById('toast').style.display = 'none'; }, 3000);</script>
    <?php endif; ?>

    <form id="filterForm" method="GET">
        <input type="hidden" name="limit" value="<?php echo $limit; ?>">

        <div class="header-area" style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <h1 style="margin:0; color:var(--dswd-dark); font-size: 28px;">Beneficiary Records</h1>
                <p style="color:#64748b; margin-top: 5px;">Historical database of AICS interventions</p>
            </div>
            <button type="button" onclick="window.print()" class="btn-print">
                <i class="fas fa-print"></i> Print Results
            </button>
        </div>

        <div class="total-counter-box">
            <div class="counter-icon"><i class="fas fa-users"></i></div>
            <div>
                <div style="font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: 700;">Total Applicants</div>
                <div style="font-size: 24px; font-weight: 700; color: #1e293b;"><?php echo number_format($total_records); ?></div>
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

                    <select name="type_filter" class="input-field filter-select" onchange="this.form.submit()">
                        <option value="">All Assistance Types</option>
                        <?php foreach($unique_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php if($type_filter == $type) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($type); ?>
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

                    <a href="records.php" class="btn-recent"><i class="fas fa-sync-alt"></i> Reset</a>
                </div>

                <div class="controls-row" style="margin-top: 10px;">
                    <div class="date-group">
                        <span>From:</span>
                        <input type="date" name="start" class="date-input" value="<?php echo $start_date; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="date-group">
                        <span>To:</span>
                        <input type="date" name="end" class="date-input" value="<?php echo $end_date; ?>" onchange="this.form.submit()">
                    </div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">Date</th>
                        <th style="width: 30%;">Medical Cause</th>
                        <th style="width: 25%;">Assistance Type</th>
                        <th style="width: 15%;">Status</th> 
                        <th style="width: 15%; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_records)): ?>
                        <?php foreach ($all_records as $row): ?>
                        <tr>
                            <td><?php echo date("M d, Y", strtotime($row['request_date'])); ?></td>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($row['medical_cause']); ?></td>
                            <td><span class="badge"><?php echo htmlspecialchars($row['assistance_type']); ?></span></td>
                            <td>
                                <?php 
                                    $s = $row['status'] ?: 'Pending';
                                    $s_class = 'status-' . strtolower($s);
                                ?>
                                <span class="status-badge <?php echo $s_class; ?>"><?php echo htmlspecialchars($s); ?></span>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="action-btn btn-edit" 
                                    onclick="openModal('<?php echo $row['id']; ?>', '<?php echo addslashes($row['medical_cause']); ?>', '<?php echo addslashes($row['assistance_type']); ?>', '<?php echo addslashes($row['status']); ?>', '<?php echo $row['request_date']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="records.php?action=approve&id=<?php echo $row['id']; ?>" class="action-btn btn-approve" onclick="return confirmAction('approve')"><i class="fas fa-check"></i></a>
                                <a href="records.php?action=delete&id=<?php echo $row['id']; ?>" class="action-btn btn-delete" onclick="return confirmAction('delete')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px;">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (isset($_GET['new_id'])): ?>
    <div class="alert-success" style="background: #dcfce7; color: #166534; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
        <i class="fas fa-check-circle"></i> 
        Successfully registered! <strong>Applicant ID: #<?php echo $_GET['new_id']; ?></strong>
    </div>
<?php endif; ?>

            <?php if ($total_pages >= 1): ?>
            <div class="pagination-footer">
                <div class="pagination-left">
                    <div class="page-search-wrapper">
                        Show rows: 
                        <select class="page-input" style="width: 70px;" onchange="changeLimit(this.value)">
                            <?php 
                            $options = [10, 20, 50, 100, 250, 500];
                            foreach($options as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php if($limit == $opt) echo 'selected'; ?>><?php echo $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="page-search-wrapper">
                        Go to: 
                        <input type="number" id="pageJump" class="page-input" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $page; ?>" 
                               onkeydown="if(event.key==='Enter'){ event.preventDefault(); jumpToPage(this.value); }">
                    </div>
                    
                    <div class="pagination-info">
                        <?php 
                            $start_count = $offset + 1;
                            $end_count = min($offset + $limit, $total_records);
                            echo "$start_count - $end_count of $total_records"; 
                        ?>
                    </div>
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
            
            <div style="margin-bottom:15px;">
                <label style="font-size:12px; font-weight:700;">REQUEST DATE</label>
                <input type="date" name="request_date" id="m_date" class="modal-input" required>
            </div>

            <div style="margin-bottom:15px;">
                <label style="font-size:12px; font-weight:700;">MEDICAL CAUSE</label>
                <input type="text" name="medical_cause" id="m_cause" class="modal-input" required>
            </div>

            <div style="margin-bottom:15px;">
                <label style="font-size:12px; font-weight:700;">ASSISTANCE TYPE</label>
                <select name="assistance_type" id="m_type" class="modal-input" required>
                    <option value="Medical">Medical</option>
                    <option value="Burial">Burial</option>
                    <option value="Transportation">Transportation</option>
                    <option value="Food">Food</option>
                </select>
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

            <div style="display:flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeModal()" class="action-btn">Cancel</button>
                <button type="submit" style="background:#3b82f6; color:#fff; border:none; padding:10px 20px; border-radius:8px; cursor:pointer;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function jumpToPage(p) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', p);
    window.location.search = urlParams.toString();
}

function changeLimit(l) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('limit', l);
    urlParams.set('page', 1);
    window.location.search = urlParams.toString();
}

function confirmAction(type) {
    return confirm("Are you sure you want to " + type + " this record?");
}

function openModal(id, cause, type, status, date) {
    document.getElementById('m_id').value = id;
    document.getElementById('m_date').value = date;
    document.getElementById('m_cause').value = cause;
    document.getElementById('m_type').value = type.trim();
    document.getElementById('m_status').value = status.trim() || 'Pending';
    document.getElementById('editModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

window.onclick = function(e) {
    if (e.target == document.getElementById('editModal')) closeModal();
}
</script>

</body>
</html>
