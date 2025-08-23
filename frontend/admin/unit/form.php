<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user is logged in and has admin capabilities
if (!is_user_logged_in() || !aerp_user_has_role($user_id, 'admin')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$edit_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$edit_unit = $edit_id ? AERP_Unit_Manager::get_by_id($edit_id) : null;
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?php echo $edit_id ? 'Sửa đơn vị tính' : 'Thêm đơn vị tính'; ?></h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Danh mục', 'url' => home_url('/aerp-categories')],
        ['label' => 'Quản lý đơn vị tính', 'url' => home_url('/aerp-units')],
        ['label' => ($edit_id ? 'Sửa đơn vị tính' : 'Thêm đơn vị tính')]
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_unit_action', 'aerp_save_unit_nonce'); ?>
            <?php if ($edit_id): ?><input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>"><?php endif; ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Tên đơn vị</label>
                    <input type="text" name="name" id="name" value="<?php echo $edit_unit ? esc_attr($edit_unit->name) : ''; ?>" class="form-control shadow-sm" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="symbol" class="form-label">Ký hiệu</label>
                    <input type="text" name="symbol" id="symbol" value="<?php echo $edit_unit ? esc_attr($edit_unit->symbol) : ''; ?>" class="form-control shadow-sm">
                </div>
            </div>
            <button type="submit" name="aerp_save_unit" class="btn btn-primary"><?php echo $edit_id ? 'Cập nhật' : 'Thêm mới'; ?></button>
            <a href="<?php echo home_url('/aerp-units'); ?>" class="btn btn-secondary ms-2">Quay lại</a>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $edit_id ? 'Cập nhật đơn vị tính' : 'Thêm mới đơn vị tính';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
