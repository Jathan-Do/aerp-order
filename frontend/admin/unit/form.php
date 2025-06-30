<?php
if (!defined('ABSPATH')) exit;
$edit_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$edit_unit = $edit_id ? AERP_Unit_Manager::get_by_id($edit_id) : null;
ob_start();
?>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0"><?php echo $edit_id ? 'Cập nhật đơn vị tính' : 'Thêm mới đơn vị tính'; ?></h5>
    </div>
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_unit_action', 'aerp_save_unit_nonce'); ?>
            <?php if ($edit_id): ?><input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>"><?php endif; ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Tên đơn vị</label>
                    <input type="text" name="name" id="name" value="<?php echo $edit_unit ? esc_attr($edit_unit->name) : ''; ?>" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="symbol" class="form-label">Ký hiệu</label>
                    <input type="text" name="symbol" id="symbol" value="<?php echo $edit_unit ? esc_attr($edit_unit->symbol) : ''; ?>" class="form-control">
                </div>
            </div>
            <button type="submit" name="aerp_save_unit" class="btn btn-primary"><?php echo $edit_id ? 'Cập nhật' : 'Thêm mới'; ?></button>
            <?php if ($edit_id): ?>
                <a href="<?php echo home_url('/aerp-units'); ?>" class="btn btn-secondary ms-2">Huỷ</a>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $edit_id ? 'Cập nhật đơn vị tính' : 'Thêm mới đơn vị tính';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
