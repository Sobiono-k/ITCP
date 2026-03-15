<?php
// Get the current page filename to handle active states
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-hand-holding-heart"></i>
        <div style="font-weight: 600; font-size: 1.2rem;">DSWD AICS</div>
        <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Batasan Hills Branch</div>
    </div>
    
    <nav style="display: flex; flex-direction: column; flex-grow: 1;">
        
        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie" style="margin-right: 12px; width: 20px;"></i> Dashboard
        </a>

        <a href="new_applicant.php" class="<?php echo ($current_page == 'new_applicant.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-plus" style="margin-right: 12px; width: 20px;"></i> New Applicant
        </a>

        <a href="forecast_analysis.php" class="<?php echo ($current_page == 'forecast_analysis.php') ? 'active' : ''; ?>">
            <i class="fas fa-microchip" style="margin-right: 12px; width: 20px;"></i> Forecast Analysis
        </a>
        
        <a href="records.php" class="<?php echo ($current_page == 'records.php') ? 'active' : ''; ?>">
            <i class="fas fa-folder-open" style="margin-right: 12px; width: 20px;"></i> Request Records
        </a>

        <a href="reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice" style="margin-right: 12px; width: 20px;"></i> Reports
        </a>
    </nav>

    <div style="padding: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
        <a href="logout.php" style="padding: 10px; color: #f87171; border-left: none !important; background: transparent !important;">
            <i class="fas fa-sign-out-alt" style="margin-right: 12px;"></i> Logout
        </a>
    </div>
</div>