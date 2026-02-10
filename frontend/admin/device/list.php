<?php
if (!defined('ABSPATH')) exit;
// Get current user
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$employee = aerp_get_employee_by_user_id($user_id);
$user_fullname = $employee ? $employee->full_name : '';

if (!is_user_logged_in()) {
    wp_die(__('You must be logged in to access this page.'));
}

// Danh sách điều kiện, chỉ cần 1 cái đúng là qua
$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'department_lead'),
    aerp_user_has_permission($user_id, 'device_view'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$table = new AERP_Device_Table();
$table->process_bulk_action();
ob_start();
?>
<style>
    .select2-container--default .select2-selection--single {
        border: 1px solid #dee2e6 !important;
        border-radius: 0.375rem !important;
        height: 38px !important;
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
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-5">
    <h2>Quản lý thiết bị</h2>
    <div class="user-info text-end">
        Hi, <?php echo esc_html($user_fullname); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Đăng xuất
        </a>
    </div>
</div>
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Danh mục', 'url' => home_url('/aerp-categories')],
        ['label' => 'Quản lý thiết bị']
    ]);
}
?>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0">Danh sách thiết bị</h5>
        <div class="d-flex gap-2 flex-column flex-md-row">
            <a href="<?php echo esc_url(home_url('/aerp-device-progresses')); ?>" class="btn btn-primary">
                <i class="fas fa-cog"></i> Quản lý tiến độ
            </a>
            <a href="<?php echo esc_url(home_url('/aerp-device-returns')); ?>" class="btn btn-primary">
                <i class="fas fa-cog"></i> Quản lý thiết bị trả lại
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form id="aerp-device-filter-form" class="row g-2 mb-3 aerp-table-ajax-form" data-table-wrapper="#aerp-device-table-wrapper" data-ajax-action="aerp_device_filter_devices">
            <div class="row">
                <div class="col-12 col-md-3 mb-2">
                    <label for="filter-partner" class="form-label mb-1">Đối tác</label>
                    <select id="filter-partner" name="partner_id" class="form-select shadow-sm supplier-select" style="width:100%">
                        <option value="">-- Tất cả --</option>
                        <?php foreach (AERP_Supplier_Manager::get_all() as $s): ?>
                            <option value="<?php echo esc_attr($s->id); ?>"><?php echo esc_html($s->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-progress" class="form-label mb-1">Tiến độ</label>
                    <select id="filter-progress" name="progress_id" class="form-select shadow-sm">
                        <option value="">-- Tất cả --</option>
                        <?php
                        $progresses = AERP_Device_Progress_Manager::get_active();
                        foreach ($progresses as $progress): ?>
                            <option value="<?php echo esc_attr($progress->id); ?>"><?php echo esc_html($progress->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-device-status" class="form-label mb-1">Trạng thái</label>
                    <select id="filter-device-status" name="device_status" class="form-select shadow-sm">
                        <option value="">Tất cả</option>
                        <option value="disposed">Nhận thiết bị</option>
                        <option value="received">Trả thiết bị</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-md-3 mb-2">
                    <label for="filter-date-from" class="form-label mb-1">Từ ngày</label>
                    <input type="date" id="filter-date-from" name="date_from" class="form-control shadow-sm">
                </div>
                <div class="col-12 col-md-3 mb-2">
                    <label for="filter-date-to" class="form-label mb-1">Đến ngày</label>
                    <input type="date" id="filter-date-to" name="date_to" class="form-control shadow-sm">
                </div>
                <div class="col-12 col-md-1 d-flex align-items-end mb-2">
                    <button type="submit" class="btn btn-primary w-100">Lọc</button>
                </div>
            </div>

        </form>
        <?php $message = get_transient('aerp_device_message');
        if ($message) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
                . esc_html($message) .
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            delete_transient('aerp_device_message');
        } ?>
        <div id="aerp-device-table-wrapper">
            <?php $table->render(); ?>
        </div>
        <a href="<?php echo home_url('/aerp-categories'); ?>" class="btn btn-outline-secondary" style="width: fit-content;">
            <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Quản lý thiết bị';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
