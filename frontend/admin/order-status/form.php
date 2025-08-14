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
    aerp_user_has_permission($user_id,'order_status_add'),
    aerp_user_has_permission($user_id,'order_status_edit'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
AERP_Order_Status_Manager::handle_form_submit();
$edit_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$editing = $edit_id ? AERP_Order_Status_Manager::get_by_id($edit_id) : null;
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?php echo $edit_id ? 'Cập nhật' : 'Thêm'; ?> trạng thái đơn hàng</h2>
    <a href="<?php echo home_url('/aerp-order-statuses'); ?>" class="btn btn-secondary mb-2 mb-md-0">Quay lại</a>
</div>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_order_status_action', 'aerp_save_order_status_nonce'); ?>
            <?php if ($edit_id): ?><input type="hidden" name="edit_id" value="<?php echo esc_attr($edit_id); ?>"><?php endif; ?>
            <div class="mb-3">
                <label for="name" class="form-label">Tên trạng thái</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo esc_attr($editing->name ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="color" class="form-label">Màu sắc</label>
                <input type="color" class="form-control form-control-color" id="color" name="color" value="<?php echo esc_attr($editing->color ?: '#007bff'); ?>">
                <div class="form-text">Màu sắc để phân biệt trạng thái</div>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Mô tả</label>
                <textarea class="form-control" id="description" name="description" rows="2"><?php echo esc_textarea($editing->description ?? ''); ?></textarea>
            </div>
            <button type="submit" name="aerp_save_order_status" class="btn btn-primary"><?php echo $edit_id ? 'Cập nhật' : 'Thêm mới'; ?></button>
            <a href="<?php echo home_url('/aerp-order-statuses'); ?>" class="btn btn-secondary ms-2">Quay lại</a>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $edit_id ? 'Cập nhật trạng thái đơn hàng' : 'Thêm trạng thái đơn hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php'); 