<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$type = isset($_GET['type']) && $_GET['type'] === 'export' ? 'export' : 'import';
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?php echo $type === 'import' ? 'Tạo phiếu nhập kho' : 'Tạo phiếu xuất kho'; ?></h2>
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
            <?php wp_nonce_field('aerp_save_inventory_log_action', 'aerp_save_inventory_log_nonce'); ?>
            <input type="hidden" name="type" value="<?php echo esc_attr($type); ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="product_id" class="form-label">Sản phẩm</label>
                    <select class="form-select" id="product_id" name="product_id" required>
                        <option value="">-- Chọn sản phẩm --</option>
                        <?php
                        $products = function_exists('aerp_get_products') ? aerp_get_products() : [];
                        foreach ($products as $product) {
                            echo '<option value="' . esc_attr($product->id) . '">' . esc_html($product->name) . ' (Tồn: ' . intval($product->quantity) . ')</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="quantity" class="form-label">Số lượng</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                </div>
                <div class="col-12 mb-3">
                    <label for="note" class="form-label">Ghi chú</label>
                    <textarea name="note" id="note" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_inventory_log" class="btn btn-primary">
                    <?php echo $type === 'import' ? 'Nhập kho' : 'Xuất kho'; ?>
                </button>
                <a href="<?php echo home_url('/aerp-inventory-logs'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $type === 'import' ? 'Tạo phiếu nhập kho' : 'Tạo phiếu xuất kho';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php'); 