<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$product_id = isset($_GET['id']) ? absint($_GET['id']) : (get_query_var('id') ?: 0);
$product = AERP_Product_Manager::get_by_id($product_id);
$is_edit = isset($product) && $product;
$categories = AERP_Product_Manager::get_all_categories();
$units = AERP_Product_Manager::get_all_units();

ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?= $is_edit ? 'Cập nhật sản phẩm' : 'Thêm sản phẩm mới' ?></h2>
    <div class="user-info text-end">
        Chào, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Thoát
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_product_action', 'aerp_save_product_nonce'); ?>
            <?php if ($is_edit): ?>
                <input type="hidden" name="product_id" value="<?php echo esc_attr($product->id); ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Tên sản phẩm</label>
                    <input type="text" name="name" id="name" value="<?php echo $is_edit ? esc_attr($product->name) : ''; ?>" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="sku" class="form-label">Mã SKU</label>
                    <input type="text" name="sku" id="sku" value="<?php echo $is_edit ? esc_attr($product->sku) : ''; ?>" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="category_id" class="form-label">Danh mục</label>
                    <select name="category_id" id="category_id" class="form-select">
                        <option value="">-- Chọn danh mục --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat->id; ?>" <?php if ($is_edit && $product->category_id == $cat->id) echo 'selected'; ?>><?php echo esc_html($cat->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="unit_id" class="form-label">Đơn vị tính</label>
                    <select name="unit_id" id="unit_id" class="form-select">
                        <option value="">-- Chọn đơn vị --</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?php echo $unit->id; ?>" <?php if ($is_edit && $product->unit_id == $unit->id) echo 'selected'; ?>><?php echo esc_html($unit->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="price" class="form-label">Giá bán</label>
                    <input type="number" name="price" id="price" value="<?php echo $is_edit ? esc_attr($product->price) : 0; ?>" class="form-control" min="0" step="0.01" required>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_product" class="btn btn-primary">
                    <?= $is_edit ? 'Cập nhật' : 'Thêm mới' ?>
                </button>
                <a href="<?= home_url('/aerp-products') ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $is_edit ? 'Cập nhật sản phẩm' : 'Thêm sản phẩm mới';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
