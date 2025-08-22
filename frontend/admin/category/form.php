<?php
if (!defined('ABSPATH')) exit;
$edit_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$edit_cat = $edit_id ? AERP_Category_Manager::get_by_id($edit_id) : null;
$categories = AERP_Category_Manager::get_all();
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user is logged in and has admin capabilities
if (!is_user_logged_in() || !aerp_user_has_role($user_id, 'admin')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?php echo $edit_id ? 'Cập nhật danh mục' : 'Thêm mới danh mục'; ?></h2>
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
        ['label' => 'Quản lý danh mục sản phẩm', 'url' => home_url('/aerp-product-categories')],
        ['label' => ($edit_id ? 'Cập nhật danh mục' : 'Thêm mới danh mục')]
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_category_action', 'aerp_save_category_nonce'); ?>
            <?php if ($edit_id): ?><input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>"><?php endif; ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Tên danh mục</label>
                    <input type="text" name="name" id="name" value="<?php echo $edit_cat ? esc_attr($edit_cat->name) : ''; ?>" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="parent_id" class="form-label">Danh mục cha</label>
                    <select name="parent_id" id="parent_id" class="form-select">
                        <option value="">-- Không chọn --</option>
                        <?php foreach ($categories as $cat): if ($edit_id && $cat->id == $edit_id) continue; ?>
                            <option value="<?php echo $cat->id; ?>" <?php if ($edit_cat && $edit_cat->parent_id == $cat->id) echo 'selected'; ?>><?php echo esc_html($cat->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="aerp_save_category" class="btn btn-primary"><?php echo $edit_id ? 'Cập nhật' : 'Thêm mới'; ?></button>
            <a href="<?php echo home_url('/aerp-product-categories'); ?>" class="btn btn-secondary">Quay lại</a>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $edit_id ? 'Cập nhật danh mục' : 'Thêm mới danh mục';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php'); 