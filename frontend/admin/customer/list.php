<?php
// Get current user
$current_user = wp_get_current_user();

// Process bulk actions
$table = new AERP_Frontend_Customer_Table(); // We will create this class next
$table->process_bulk_action();

ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Quản lý khách hàng</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>


<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0">Danh sách khách hàng</h5>
        <div class="d-flex gap-2 flex-column flex-md-row">
            <a href="<?php echo esc_url(home_url('/aerp-crm-customer-types')); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Thêm mới loại khách hàng
            </a>
            <a href="<?php echo esc_url(home_url('/aerp-crm-customers/?action=add')); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Thêm mới khách hàng
            </a>
        </div>
    </div>
    <div class="card-body">

        <!-- Filter Form -->
        <form id="aerp-customer-filter-form" class="row g-2 mb-3 aerp-table-ajax-form" data-table-wrapper="#aerp-customer-table-wrapper" data-ajax-action="aerp_crm_filter_customers">
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-customer-type" class="form-label mb-1">Loại khách hàng</label>
                <select id="filter-customer-type" name="customer_type_id" class="form-select">
                    <?php
                    $types = aerp_get_customer_types();
                    aerp_safe_select_options($types, '', 'id', 'name', true);
                    ?>
                </select>
            </div>
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-status" class="form-label mb-1">Trạng thái</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active">Hoạt động</option>
                    <option value="inactive">Không hoạt động</option>
                </select>
            </div>
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-assigned-to" class="form-label mb-1">Nhân viên phụ trách</label>
                <select id="filter-assigned-to" name="assigned_to" class="form-select">
                    <?php
                    $employees = aerp_get_assigned_employees();
                    aerp_safe_select_options($employees, '', 'user_id', 'full_name', true);
                    ?>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary w-100">Lọc</button>
            </div>
        </form>
        <?php // Display messages if any (using Transients API)
        $message = get_transient('aerp_customer_message');
        if ($message) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    ' . esc_html($message) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            delete_transient('aerp_customer_message'); // Xóa transient sau khi hiển thị
        }
        ?>
        <div id="aerp-customer-table-wrapper">
            <?php $table->render(); ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Quản lý khách hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
