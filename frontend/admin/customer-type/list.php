<?php
$current_user = wp_get_current_user();
$table = new AERP_Frontend_Customer_Type_Table();
$table->process_bulk_action();
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Quản lý loại khách hàng</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0">Danh sách loại khách hàng</h5>
        <a href="<?php echo esc_url(home_url('/aerp-crm-customer-types/?action=add')); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Thêm mới
        </a>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form id="aerp-customer-type-filter-form" class="row g-2 mb-3 aerp-table-ajax-form" data-table-wrapper="#aerp-customer-type-table-wrapper" data-ajax-action="aerp_crm_filter_customers_type">
            <div class="col-12 col-md-3 mb-2">
                <label for="filter-color" class="form-label mb-1">Màu sắc</label>
                <select id="filter-color" name="color" class="form-select">
                    <option value="">Tất cả màu</option>
                    <option value="primary">Xanh dương</option>
                    <option value="secondary">Xám</option>
                    <option value="success">Xanh lá</option>
                    <option value="danger">Đỏ</option>
                    <option value="warning">Vàng</option>
                    <option value="info">Xanh nhạt</option>
                    <option value="dark">Đen</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary w-100">Lọc</button>
            </div>
        </form>
        <?php $message = get_transient('aerp_customer_type_message');
        if ($message) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
                . esc_html($message) .
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            delete_transient('aerp_customer_type_message');
        } ?>
        <div id="aerp-customer-type-table-wrapper">
            <?php $table->render(); ?>
        </div>
        <a href="<?php echo home_url('/aerp-crm-customers'); ?>" class="btn btn-outline-secondary m-3 mt-0" style="width: fit-content;">
            <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Quản lý loại khách hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
