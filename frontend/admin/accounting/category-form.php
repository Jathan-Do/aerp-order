<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
AERP_Acc_Category_Manager::handle_form_submit();

$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'department_lead'),
    aerp_user_has_permission($user_id, 'acc_category_add'),
    aerp_user_has_permission($user_id, 'acc_category_edit'),
];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$category_id = isset($_GET['id']) ? absint($_GET['id']) : (get_query_var('id') ?: 0);
$category = AERP_Acc_Category_Manager::get_by_id($category_id);
$is_edit = !empty($category);

ob_start();
?>
<style>
    .form-check-input {
        width: 3em !important;
        height: 1.5em !important;
        margin-right: 10px;
    }

    .form-check-label {
        display: flex;
        flex-direction: column;
    }
</style>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?= $is_edit ? 'Cập nhật danh mục chi' : 'Thêm danh mục chi' ?></h2>
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
        ['label' => 'Quản lý phiếu chi', 'url' => home_url('/aerp-acc-payments')],
        ['label' => 'Quản lý danh mục chi', 'url' => home_url('/aerp-acc-categories')],
        ['label' => ($is_edit ? 'Cập nhật danh mục chi' : 'Thêm danh mục chi')]
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_acc_save_category_action', 'aerp_acc_save_category_nonce'); ?>
            <?php if ($is_edit): ?>
                <input type="hidden" name="category_id" value="<?php echo esc_attr($category->id); ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Tên danh mục</label>
                    <input type="text" name="name" value="<?php echo $is_edit ? esc_attr($category->name) : ''; ?>" class="form-control" required>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Mã</label>
                    <input type="text" name="code" value="<?php echo $is_edit ? esc_attr($category->code) : ''; ?>" class="form-control">
                </div>
                <div class="col-md-12 mb-3 d-flex align-items-center gap-2">
                    <label class="form-label mb-0">Có hạch toán</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_accounted" value="1" <?php echo ($is_edit && !empty($category->is_accounted)) ? 'checked' : (!$is_edit ? 'checked' : ''); ?>>
                    </div>
                </div>
                <div class="col-md-12 mb-3 d-flex align-items-center gap-2">
                    <label class="form-label mb-0">Kích hoạt</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="active" value="1" <?php echo ($is_edit && !empty($category->active) && $category->active == 1) ? 'checked' : (!$is_edit ? 'checked' : ''); ?>>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_acc_save_category" class="btn btn-primary">
                    <?= $is_edit ? 'Cập nhật' : 'Thêm mới' ?>
                </button>
                <a href="<?= home_url('/aerp-acc-categories') ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $is_edit ? 'Cập nhật danh mục chi' : 'Thêm danh mục chi';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
