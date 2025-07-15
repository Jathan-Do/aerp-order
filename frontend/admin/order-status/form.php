<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user is logged in and has admin capabilities
if (!is_user_logged_in() || !aerp_user_has_role($user_id, 'admin')) {
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
                <div class="mb-2">
                    <?php
                    $bootstrap_colors = [
                        'primary' => 'Xanh dương',
                        'secondary' => 'Xám',
                        'success' => 'Xanh lá',
                        'danger' => 'Đỏ',
                        'warning' => 'Vàng',
                        'info' => 'Xanh nhạt',
                        'dark' => 'Đen',
                    ];
                    foreach ($bootstrap_colors as $key => $label) {
                        echo '<span class="badge bg-' . esc_attr($key) . ' me-2">' . esc_html($label) . '</span>';
                    }
                    ?>
                </div>
                <select class="form-select" id="color" name="color">
                    <?php
                    foreach ($bootstrap_colors as $key => $label) {
                        $selected = ($editing->color ?? '') === $key ? 'selected' : '';
                        echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                    }
                    ?>
                </select>
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