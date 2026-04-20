<?php
// sidebar.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);

// Check if role exists, otherwise redirect to login or handle as guest
if (!isset($_SESSION['role'])) {
    // If no session exists, the user shouldn't be here
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'];
?>
<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-hand-holding-heart"></i>
        <div style="font-weight: 600; font-size: 1.2rem;">DSWD AICS</div>
        <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Batasan Hills Branch</div>
    </div>
    
    <nav style="display: flex; flex-direction: column; flex-grow: 1;">
        
        <?php if ($user_role === 'Admin'): ?>
        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie" style="margin-right: 12px; width: 20px;"></i> Dashboard
        </a>
        <?php endif; ?>

        <a href="new_applicant.php" class="<?php echo ($current_page == 'new_applicant.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-plus" style="margin-right: 12px; width: 20px;"></i> New Applicant
        </a>

        <?php if ($user_role === 'Admin'): ?>
        <a href="forecast_analysis.php" class="<?php echo ($current_page == 'forecast_analysis.php') ? 'active' : ''; ?>">
            <i class="fas fa-microchip" style="margin-right: 12px; width: 20px;"></i> Forecast Analysis
        </a>
        <?php endif; ?>
        
        <a href="records.php" class="<?php echo ($current_page == 'records.php') ? 'active' : ''; ?>">
            <i class="fas fa-folder-open" style="margin-right: 12px; width: 20px;"></i> Records
        </a>

        <?php if ($user_role === 'Admin'): ?>
        <a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice" style="margin-right: 12px; width: 20px;"></i> Reports
        </a>

        <?php endif; ?>
    </nav>

    <div style="padding: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
        <div style="font-size: 11px; color: #64748b; margin-bottom: 10px; padding-left: 10px;">
            Logged in as: <span style="color: #3b82f6; font-weight: bold;"><?php echo htmlspecialchars($user_role); ?></span>
        </div>
        <a href="login.php" style="padding: 10px; color: #f87171; border-left: none !important; background: transparent !important; display: flex; align-items: center;">
            <i class="fas fa-sign-out-alt" style="margin-right: 12px;"></i> Logout
        </a>
    </div>
</div>