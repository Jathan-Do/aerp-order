<?php
if (!defined('ABSPATH')) exit;
// Get current user
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

if (!is_user_logged_in()) {
    wp_die(__('You must be logged in to access this page.'));
}

// Danh sách điều kiện, chỉ cần 1 cái đúng là qua
$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'department_lead'),
    // aerp_user_has_permission($user_id, 'device_add'),
    aerp_user_has_permission($user_id, 'device_edit'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
AERP_Device_Return_Manager::handle_form_submit();
$edit_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$editing = $edit_id ? AERP_Device_Return_Manager::get_by_id($edit_id) : null;
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
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?php echo $edit_id ? 'Cập nhật' : 'Thêm'; ?> thiết bị</h2>
    <div class="user-info text-end">
        Chào, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Thoát
        </a>
    </div>
</div>
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Danh mục', 'url' => home_url('/aerp-categories')],
        ['label' => 'Quản lý thiết bị trả lại', 'url' => home_url('/aerp-device-returns')],
        ['label' => ($edit_id ? 'Cập nhật thiết bị' : 'Thêm thiết bị mới')]
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_device_return_action', 'aerp_save_device_return_nonce'); ?>
            <?php if ($edit_id): ?><input type="hidden" name="edit_id" value="<?php echo esc_attr($edit_id); ?>"><?php endif; ?>
            <?php if ($edit_id): ?>
                <div class="mb-3">
                    <label for="order_code" class="form-label">Đơn hàng</label>
                    <input type="text" class="form-control shadow-sm" id="order_code" value="<?php echo esc_attr(aerp_get_order_code_by_id($editing->order_id) ?? ''); ?>" readonly disabled>
                    <input type="hidden" name="order_id" value="<?php echo esc_attr($editing->order_id); ?>">
                </div>
            <?php endif; ?>
            <div class="mb-3">
                <label for="device_id" class="form-label">Thiết bị</label>
                <select type="text" class="form-select shadow-sm received-device-select" id="device_id" name="device_id" data-placeholder="Nhập thiết bị" required>
                    <?php
                    $devices = aerp_get_devices_select2();
                    aerp_safe_select_options($devices, $editing->device_id, 'id', 'device_name', true);
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="return_date" class="form-label">Ngày trả lại</label>
                <input type="date" class="form-control shadow-sm" id="return_date" name="return_date" value="<?php echo esc_attr($editing->return_date ?? ''); ?>" placeholder="Nhập ngày trả lại" required>
            </div>
            <div class="mb-3">
                <label for="note" class="form-label">Ghi chú</label>
                <textarea class="form-control shadow-sm" id="note" name="note" rows="2" placeholder="Nhập ghi chú"><?php echo esc_textarea($editing->note ?? ''); ?></textarea>
            </div>
            <button type="submit" name="aerp_save_device_return" class="btn btn-primary"><?php echo $edit_id ? 'Cập nhật' : 'Thêm mới'; ?></button>
            <a href="<?php echo home_url('/aerp-device-returns'); ?>" class="btn btn-secondary ms-2">Quay lại</a>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $edit_id ? 'Cập nhật thiết bị' : 'Thêm thiết bị';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
