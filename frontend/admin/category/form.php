<?php
if (!defined('ABSPATH')) exit;
$edit_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$edit_cat = $edit_id ? AERP_Category_Manager::get_by_id($edit_id) : null;
$categories = AERP_Category_Manager::get_all();
ob_start();
?>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0"><?php echo $edit_id ? 'Cập nhật danh mục' : 'Thêm mới danh mục'; ?></h5>
    </div>
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