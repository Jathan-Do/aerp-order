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
    aerp_user_has_permission($user_id, 'device_edit'),
];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

AERP_Device_Progress_Manager::handle_form_submit();
$edit_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$editing = $edit_id ? AERP_Device_Progress_Manager::get_by_id($edit_id) : null;
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
    <h2><?php echo $edit_id ? 'Cập nhật' : 'Thêm'; ?> tiến độ thiết bị</h2>
    <div class="user-info text-end">
        Hi, <?php echo esc_html($user_fullname); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Đăng xuất
        </a>
    </div>
</div>
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Danh mục', 'url' => home_url('/aerp-categories')],
        ['label' => 'Quản lý thiết bị', 'url' => home_url('/aerp-devices')],
        ['label' => 'Quản lý tiến độ thiết bị', 'url' => home_url('/aerp-device-progresses')],
        ['label' => ($edit_id ? 'Cập nhật tiến độ' : 'Thêm tiến độ mới')]
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_progress_action', 'aerp_save_progress_nonce'); ?>
            <?php if ($edit_id): ?><input type="hidden" name="edit_id" value="<?php echo esc_attr($edit_id); ?>"><?php endif; ?>
            <div class="row">
                <div class="col-12 mb-3">
                    <label for="name" class="form-label">Tên tiến độ</label>
                    <input type="text" class="form-control shadow-sm" id="name" name="name" value="<?php echo esc_attr($editing->name ?? ''); ?>" placeholder="Nhập tên tiến độ" required>
                </div>
                <div class="col-12 mb-3">
                    <label for="description" class="form-label">Mô tả</label>
                    <textarea class="form-control shadow-sm" id="description" name="description" rows="3" placeholder="Nhập mô tả"><?php echo esc_textarea($editing->description ?? ''); ?></textarea>
                </div>
                <div class="col-12 mb-3">
                    <label for="color" class="form-label">Màu sắc</label>
                    <input type="color" class="form-control shadow-sm form-control-color" id="color" name="color" value="<?php echo esc_attr($editing->color ?? '#007cba'); ?>" title="Chọn màu sắc">
                </div>
                <div class="col-12 mb-3 ms-3 form-check form-switch">
                    <input type="checkbox" role="switch" class="form-check-input" id="is_active" name="is_active" <?php checked(($editing->is_active ?? 1) == 1); ?> style="width: 3em; height: 1.5em; margin-right: 10px;">
                    <label class="form-check-label" for="is_active">Kích hoạt</label>
                </div>
            </div>
            <button type="submit" name="aerp_save_progress" class="btn btn-primary"><?php echo $edit_id ? 'Cập nhật' : 'Thêm mới'; ?></button>
            <a href="<?php echo home_url('/aerp-device-progresses'); ?>" class="btn btn-secondary ms-2">Quay lại</a>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $edit_id ? 'Cập nhật tiến độ thiết bị' : 'Thêm tiến độ thiết bị';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
?>