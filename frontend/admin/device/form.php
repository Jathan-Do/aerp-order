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
    aerp_user_has_permission($user_id, 'product_add'),
    aerp_user_has_permission($user_id, 'product_edit'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
AERP_Device_Manager::handle_form_submit();
$edit_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$editing = $edit_id ? AERP_Device_Manager::get_by_id($edit_id) : null;
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
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_device_action', 'aerp_save_device_nonce'); ?>
            <?php if ($edit_id): ?><input type="hidden" name="edit_id" value="<?php echo esc_attr($edit_id); ?>"><?php endif; ?>
            <div class="mb-3">
                <label for="device_name" class="form-label">Tên thiết bị</label>
                <input type="text" class="form-control" id="device_name" name="device_name" value="<?php echo esc_attr($editing->device_name ?? ''); ?>" placeholder="Nhập tên thiết bị" required>
            </div>
            <div class="mb-3">
                <label for="serial_number" class="form-label">Serial/IMEI</label>
                <input type="text" class="form-control" id="serial_number" name="serial_number" value="<?php echo esc_attr($editing->serial_number ?? ''); ?>" placeholder="Nhập serial/IMEI" required>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Tình trạng</label>
                <input type="text" class="form-control" id="status" name="status" value="<?php echo esc_attr($editing->status ?? ''); ?>" placeholder="Nhập tình trạng" required>
            </div>
            <div class="mb-3">
                <label for="note" class="form-label">Ghi chú</label>
                <textarea class="form-control" id="note" name="note" rows="2" placeholder="Nhập ghi chú"><?php echo esc_textarea($editing->note ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="partner_id" class="form-label">Đối tác</label>
                <select class="form-select supplier-select" id="partner_id" name="partner_id" style="width:100%">
                    <option value="">-- Chọn đối tác --</option>
                    <?php foreach (AERP_Supplier_Manager::get_all() as $s): ?>
                        <option value="<?php echo esc_attr($s->id); ?>" <?php selected($editing->partner_id, $s->id); ?>><?php echo esc_html($s->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="aerp_save_device" class="btn btn-primary"><?php echo $edit_id ? 'Cập nhật' : 'Thêm mới'; ?></button>
            <a href="<?php echo home_url('/aerp-devices'); ?>" class="btn btn-secondary ms-2">Quay lại</a>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $edit_id ? 'Cập nhật thiết bị' : 'Thêm thiết bị';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
