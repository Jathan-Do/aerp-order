<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'accountant'),
    aerp_user_has_permission($user_id, 'acc_payment_view'),
];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$table = new AERP_Acc_Payment_Table();
$table->process_bulk_action();
$message = get_transient('aerp_acc_payment_message');
ob_start();
?>
<style>
    .select2-container--default .select2-selection--single {
        border: 1px solid #dee2e6 !important;
        border-radius: 0.375rem !important;
        height: 38px !important;
        min-height: 38px !important;
        padding: 6px 12px !important;
        background: #fff !important;
        font-size: 1rem !important;
        box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075) !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 24px !important;
        padding-left: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
        right: 0.75rem !important;
    }
</style>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Phiếu chi</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Quản lý phiếu chi']
    ]);
}
?>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0">Danh sách phiếu chi</h5>
        <div class="d-flex gap-2 flex-column flex-md-row">
            <a href="<?php echo esc_url(home_url('/aerp-acc-payments/?action=add')); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Thêm phiếu chi
            </a>
            <a href="<?php echo esc_url(home_url('/aerp-acc-categories')); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Thêm loại phiếu chi
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form id="aerp-acc-payment-filter-form" class="row g-2 mb-3 aerp-table-ajax-form" data-table-wrapper="#aerp-acc-payment-table-wrapper" data-ajax-action="aerp_acc_payment_filter_payments">
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-status" class="form-label mb-1">Trạng thái</label>
                <select id="filter-status" name="status" class="form-select shadow-sm">
                    <option value="">Tất cả</option>
                    <option value="draft">Nháp</option>
                    <option value="confirmed">Đã xác nhận</option>
                    <option value="paid">Đã chi</option>
                </select>
            </div>
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-date-from" class="form-label mb-1">Từ ngày</label>
                <input type="date" id="filter-date-from" name="date_from" class="form-control shadow-sm bg-body">
            </div>
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-date-to" class="form-label mb-1">Đến ngày</label>
                <input type="date" id="filter-date-to" name="date_to" class="form-control shadow-sm bg-body">
            </div>
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-employee" class="form-label mb-1">Nhân viên chi</label>
                <select id="filter-employee" name="employee_id" class="form-select shadow-sm <?php echo aerp_user_has_role($user_id, 'admin') || aerp_user_has_role($user_id, 'accountant') ? 'employee-select-all' : 'employee-select'; ?>">
                    <?php
                    $employees = function_exists('aerp_get_order_assigned_employees') ? aerp_get_order_assigned_employees() : [];
                    aerp_safe_select_options($employees, '', 'user_id', 'full_name', true);
                    ?>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary w-100">Lọc</button>
            </div>
        </form>
        <?php if ($message) {
            echo '<div class="notice notice-success alert alert-success alert-dismissible fade show" role="alert">' . esc_html($message) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            delete_transient('aerp_acc_payment_message');
        } ?>
        <div id="aerp-acc-payment-table-wrapper">
            <?php $table->render(); ?>
        </div>
        <a href="<?php echo home_url('/aerp-acc-reports'); ?>" class="btn btn-outline-secondary" style="width: fit-content;">
            <i class="fas fa-arrow-left me-1"></i> Báo cáo
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Phiếu chi';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
