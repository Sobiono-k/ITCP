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
    die("Database Connection Error: " . $conn->connect_error);
}

// --- ACTION HANDLER ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($_GET['action'] === 'delete') {
        $conn->query("DELETE FROM aics_sample_data WHERE id = $id");
    } elseif ($_GET['action'] === 'approve') {
        $conn->query("UPDATE aics_sample_data SET status = 'Approved' WHERE id = $id");
    }
    header("Location: records.php?msg=success");
    exit();
}

if (isset($_POST['update_action']) && $_POST['update_action'] === 'update_record') {
    $id = (int)$_POST['edit_id'];
    $cause = $conn->real_escape_string($_POST['medical_cause']);
    $type = $conn->real_escape_string($_POST['assistance_type']);
    $status = $conn->real_escape_string($_POST['status']);
    $date = $conn->real_escape_string($_POST['request_date']);
    
    $conn->query("UPDATE aics_sample_data SET medical_cause='$cause', assistance_type='$type', status='$status', request_date='$date' WHERE id=$id");
    header("Location: records.php?msg=updated");
    exit();
}

// 2. Pagination & Filter Parameters
$limit = 20; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$cause_filter = isset($_GET['cause']) ? $conn->real_escape_string($_GET['cause']) : '';

$where_clauses = ["1=1"];
if (!empty($search)) $where_clauses[] = "(medical_cause LIKE '%$search%' OR assistance_type LIKE '%$search%')";
if (!empty($cause_filter)) $where_clauses[] = "medical_cause = '$cause_filter'";
$where_str = implode(" AND ", $where_clauses);

$total_res = $conn->query("SELECT COUNT(*) as total FROM aics_sample_data WHERE $where_str");
$total_records = $total_res->fetch_assoc()['total'];

$sql = "SELECT id, request_date, medical_cause, assistance_type, status FROM aics_sample_data WHERE $where_str ORDER BY request_date DESC LIMIT $offset, $limit";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Beneficiary Records</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --dswd-dark: #2c3e50; --sidebar-bg: #1e293b; --bg-color: #f0f2f5; --sidebar-width: 260px; }
        body { font-family: 'Inter', sans-serif; margin: 0; background: var(--bg-color); display: flex; color: #334155; }
        .main { margin-left: var(--sidebar-width); padding: 40px; width: calc(100% - var(--sidebar-width)); min-height: 100vh; box-sizing: border-box; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background: #fff; margin: 8% auto; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .table-container { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); overflow: hidden; border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 20px; background: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #eff6ff; color: #1d4ed8; }
        .input-field { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 5px; box-sizing: border-box; font-family: inherit; }
        .readonly-field { background-color: #f8fafc; cursor: not-allowed; color: #64748b; font-weight: 600; border: 1px solid #e2e8f0; }
        .action-btn { padding: 6px; border-radius: 4px; border: 1px solid #e2e8f0; color: #64748b; cursor: pointer; text-decoration: none; font-size: 12px; margin-right: 5px; background: white; }
        .btn-edit { color: #3b82f6; } .btn-delete { color: #ef4444; } .btn-approve { color: #10b981; }
        .toast-msg { position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 15px 25px; border-radius: 8px; z-index: 3000; font-weight: 600; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
    <?php if (isset($_GET['msg'])): ?>
        <div id="toast" class="toast-msg"><i class="fas fa-check-circle"></i> Success! Action completed.</div>
        <script>setTimeout(() => { document.getElementById('toast').style.display='none'; }, 3000);</script>
    <?php endif; ?>

    <h1 style="margin:0; color:var(--dswd-dark); font-size: 28px;">Beneficiary Records</h1>
    <div style="background: #fff; padding: 20px; border-radius: 12px; margin: 20px 0; border: 1px solid #e2e8f0;">
        <div style="font-size: 24px; font-weight: 700; color: #1e293b;"><?php echo number_format($total_records); ?> Total Applicants</div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Medical Cause</th>
                    <th>Assistance Type</th>
                    <th>Status</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date("M d, Y", strtotime($row['request_date'])); ?></td>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($row['medical_cause']); ?></td>
                    <td><span class="badge"><?php echo htmlspecialchars($row['assistance_type']); ?></span></td>
                    <td>
                        <?php $s = $row['status'] ?: 'Pending'; ?>
                        <span style="font-size: 12px; font-weight: 600; color: <?php echo ($s == 'Approved' ? '#10b981' : '#94a3b8'); ?>;">
                            <?php echo $s; ?>
                        </span>
                    </td>
                    <td style="text-align: center;">
                        <button type="button" class="action-btn btn-edit" 
                                onclick="openEditModal('<?php echo $row['id']; ?>', '<?php echo addslashes($row['medical_cause']); ?>', '<?php echo addslashes($row['assistance_type']); ?>', '<?php echo addslashes($s); ?>', '<?php echo $row['request_date']; ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="records.php?action=approve&id=<?php echo $row['id']; ?>" class="action-btn btn-approve" onclick="return confirm('Approve this?')"><i class="fas fa-check"></i></a>
                        <a href="records.php?action=delete&id=<?php echo $row['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Delete this?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <h2 style="margin-top:0;">Edit Record</h2>
        <form method="POST">
            <input type="hidden" name="update_action" value="update_record">
            <input type="hidden" name="edit_id" id="edit_id">
            
            <div style="margin-bottom:15px;">
                <label style="font-size:12px; font-weight:700;">Request Date</label>
                <input type="date" name="request_date" id="edit_date" class="input-field" required>
            </div>

            <div style="margin-bottom:15px;">
                <label style="font-size:12px; font-weight:700;">Medical Cause</label>
                <input type="text" name="medical_cause" id="edit_cause" class="input-field" required>
            </div>

            <div style="margin-bottom:15px;">
                <label style="font-size:12px; font-weight:700;">Assistance Type (Locked)</label>
                <input type="text" name="assistance_type" id="edit_type" class="input-field readonly-field" readonly>
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-size:12px; font-weight:700;">Status</label>
                <select name="status" id="edit_status" class="input-field" required>
                    <option value="Pending">Pending</option>
                    <option value="Processed">Processed</option>
                    <option value="Approved">Approved</option>
                    <option value="Paid">Paid</option>
                    <option value="Waitlisted">Waitlisted</option>
                    <option value="Declined">Declined</option>
                </select>
            </div>

            <div style="display:flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeEditModal()" class="action-btn">Cancel</button>
                <button type="submit" class="action-btn" style="background: #3b82f6; color:white; border:none; padding: 8px 15px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function setSelectValue(elementId, value) {
    const select = document.getElementById(elementId);
    if(!select) return;
    const cleanValue = value ? value.trim().toLowerCase() : "";
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value.toLowerCase() === cleanValue) {
            select.selectedIndex = i;
            return;
        }
    }
}

function openEditModal(id, cause, type, status, date) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_cause').value = cause;
    
    // Assigns value to the read-only field
    const typeField = document.getElementById('edit_type');
    if(typeField) typeField.value = type ? type.trim() : "";

    if(date) {
        const d = new Date(date);
        if(!isNaN(d.getTime())) {
            document.getElementById('edit_date').value = d.toISOString().split('T')[0];
        } else {
            document.getElementById('edit_date').value = date;
        }
    }

    setSelectValue('edit_status', status);
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('editModal')) closeEditModal();
}
</script>

</body>
</html>