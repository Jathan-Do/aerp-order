<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
AERP_Warehouse_Manager::handle_form_submit();
$is_edit = isset($_GET['id']);
$warehouse = $is_edit ? AERP_Warehouse_Manager::get_by_id($_GET['id']) : null;
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?php echo $is_edit ? 'Sửa kho' : 'Thêm kho'; ?></h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_warehouse_action', 'aerp_save_warehouse_nonce'); ?>
            <?php if ($is_edit): ?><input type="hidden" name="edit_id" value="<?php echo esc_attr($warehouse->id); ?>"><?php endif; ?>
            <div class="mb-3">
                <label class="form-label">Tên kho</label>
                <input type="text" name="name" class="form-control" value="<?php echo esc_attr($warehouse->name ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Chi nhánh</label>
                <select name="work_location_id" class="form-select" required>
                    <?php $locations = aerp_get_work_locations();
                    aerp_safe_select_options($locations, '$warehouse->work_location_id', 'id', 'name', true); 
                    ?>
                </select>
            </div>
            <button type="submit" name="aerp_save_warehouse" class="btn btn-primary"><?php echo $is_edit ? 'Cập nhật' : 'Thêm mới'; ?></button>
            <a href="<?php echo home_url('/aerp-warehouses'); ?>" class="btn btn-secondary">Quay lại</a>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $is_edit ? 'Sửa kho' : 'Thêm kho';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
