<?php

/**
 * Frontend Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}
// Get current user
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user is logged in and has admin capabilities
if (!is_user_logged_in() || !aerp_user_has_role($user_id, 'admin')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Get dashboard statistics
$total_employees = count_users()['total_users'];
$total_departments = wp_count_posts('department')->publish;
$total_positions = wp_count_posts('position')->publish;
$total_branches = wp_count_posts('branch')->publish;

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Dashboard Overview</h2>
    <div class="user-info">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<div class="row">
    <div class="col-md-3">
        <div class="stat-card bg-primary text-white">
            <h3><?php echo $total_employees; ?></h3>
            <p class="mb-0">Total Employees</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-success text-white">
            <h3><?php echo $total_departments; ?></h3>
            <p class="mb-0">Departments</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-info text-white">
            <h3><?php echo $total_positions; ?></h3>
            <p class="mb-0">Positions</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-warning text-white">
            <h3><?php echo $total_branches; ?></h3>
            <p class="mb-0">Branches</p>
        </div>
    </div>
</div>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Activities</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <i class="fas fa-user-plus text-success me-2"></i>
                        New employee joined - John Doe
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-award text-warning me-2"></i>
                        Employee of the month announced
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-calendar-alt text-info me-2"></i>
                        Team meeting scheduled
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i> Add New Employee
                    </button>
                    <button class="btn btn-success">
                        <i class="fas fa-file-alt me-2"></i> Generate Report
                    </button>
                    <button class="btn btn-info">
                        <i class="fas fa-calendar-plus me-2"></i> Schedule Meeting
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'AERP Dashboard';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
