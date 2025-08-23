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
    aerp_user_has_permission($user_id,'supplier_add'),
    aerp_user_has_permission($user_id,'supplier_edit'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
AERP_Supplier_Manager::handle_form_submit();
$is_edit = isset($_GET['id']);
$supplier = $is_edit ? AERP_Supplier_Manager::get_by_id($_GET['id']) : null;
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?php echo $is_edit ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp'; ?></h2>
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
        ['label' => 'Quản lý nhà cung cấp', 'url' => home_url('/aerp-suppliers')],
        ['label' => ($is_edit ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp')]
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_supplier_action', 'aerp_save_supplier_nonce'); ?>
            <?php if ($is_edit): ?><input type="hidden" name="edit_id" value="<?php echo esc_attr($supplier->id); ?>"><?php endif; ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tên nhà cung cấp</label>
                    <input type="text" name="name" class="form-control shadow-sm" value="<?php echo esc_attr($supplier->name ?? ''); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="phone" class="form-control shadow-sm" value="<?php echo esc_attr($supplier->phone ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control shadow-sm" value="<?php echo esc_attr($supplier->email ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Địa chỉ</label>
                    <input type="text" name="address" class="form-control shadow-sm" value="<?php echo esc_attr($supplier->address ?? ''); ?>">
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="note" class="form-control shadow-sm" rows="2"><?php echo esc_textarea($supplier->note ?? ''); ?></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_supplier" class="btn btn-primary"><?php echo $is_edit ? 'Cập nhật' : 'Thêm mới'; ?></button>
                <a href="<?php echo home_url('/aerp-suppliers'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $is_edit ? 'Sửa nhà cung cấp' : 'Thêm nhà cung cấp';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
