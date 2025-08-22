<?php
if (!defined('ABSPATH')) exit;
// Get current user
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

if (!is_user_logged_in()) {
    wp_die(__('You must be logged in to access this page.'));
}

// Quyền thêm/sửa
$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'department_lead'),
    aerp_user_has_permission($user_id, 'order_edit'),
];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

AERP_Implementation_Template_Manager::handle_form_submit();
$is_edit = isset($_GET['id']);
$template = $is_edit ? AERP_Implementation_Template_Manager::get_by_id(absint($_GET['id'])) : null;
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?php echo $is_edit ? 'Sửa template nội dung triển khai' : 'Thêm template nội dung triển khai'; ?></h2>
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
        ['label' => 'Template nội dung', 'url' => home_url('/aerp-implementation-templates')],
        ['label' => ($is_edit ? 'Sửa template nội dung triển khai' : 'Thêm template nội dung triển khai')]
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_implementation_template_action', 'aerp_save_implementation_template_nonce'); ?>
            <?php if ($is_edit): ?><input type="hidden" name="edit_id" value="<?php echo esc_attr($template->id); ?>"><?php endif; ?>
            <div class="row">
                <div class="col-12 mb-3">
                    <label class="form-label">Tên template</label>
                    <input type="text" name="name" class="form-control" value="<?php echo esc_attr($template->name ?? ''); ?>" required>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Nội dung</label>
                    <textarea name="content" class="form-control" rows="8" required><?php echo esc_textarea($template->content ?? ''); ?></textarea>
                </div>
                <div class="col-12 mb-3 ms-3 form-check form-switch">
                    <input type="checkbox" role="switch" class="form-check-input" id="is_active" name="is_active" <?php checked(($template->is_active ?? 1) == 1); ?> style="width: 3em; height: 1.5em; margin-right: 10px;">
                    <label class="form-check-label" for="is_active">Kích hoạt</label>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_implementation_template" class="btn btn-primary"><?php echo $is_edit ? 'Cập nhật' : 'Thêm mới'; ?></button>
                <a href="<?php echo home_url('/aerp-implementation-templates'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $is_edit ? 'Sửa template nội dung triển khai' : 'Thêm template nội dung triển khai';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');


