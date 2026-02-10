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
    aerp_user_has_permission($user_id, 'stock_adjustment'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
$log = $id ? AERP_Inventory_Log_Manager::get_log_by_id($id) : null;
if (isset($_GET['type']) && in_array($_GET['type'], ['import', 'export', 'stocktake'])) {
    $type = $_GET['type'];
} elseif ($log && isset($log->type)) {
    $type = $log->type;
} else {
    $type = 'import';
}
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
    <h2>
        <?php echo $id ? 'Xác nhận phiếu ' . ($type === 'import' ? 'nhập' : 'xuất') . ' kho' : 'Tạo phiếu ' . ($type === 'import' ? 'nhập' : 'xuất') . ' kho'; ?>
    </h2>
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
        ['label' => 'Lịch sử nhập/xuất kho', 'url' => home_url('/aerp-inventory-logs')],
        ['label' => ($id ? 'Xác nhận phiếu ' . ($type === 'import' ? 'nhập' : 'xuất') . ' kho' : 'Tạo phiếu ' . ($type === 'import' ? 'nhập' : 'xuất') . ' kho')]
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_inventory_log_action', 'aerp_save_inventory_log_nonce'); ?>
            <input type="hidden" name="type" value="<?php echo esc_attr($type); ?>">
            <?php if ($id): ?>
                <input type="hidden" name="log_id" value="<?php echo esc_attr($id); ?>">
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="warehouse_id" class="form-label">Kho</label>
                    <select class="form-select shadow-sm warehouse-select-by-user" id="warehouse_id" name="warehouse_id" required>
                        <option value="">-- Chọn kho --</option>
                        <?php
                        $warehouses = function_exists('aerp_get_warehouses_by_user_select2')
                            ? aerp_get_warehouses_by_user_select2($q, $user_id)
                            : [];
                        foreach ($warehouses as $w): ?>
                            <option value="<?php echo esc_attr($w->id); ?>" <?php selected($log && $log->warehouse_id == $w->id); ?>>
                                <?php echo AERP_Warehouse_Manager::get_full_warehouse_name($w->id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="product_id" class="form-label">Sản phẩm</label>
                    <select class="form-select shadow-sm <?php echo ($type !== 'import') ? 'product-select-by-warehouse' : 'product-select-all'; ?>" id="product_id" name="product_id" required style="width:100%">
                        <option value="">-- Chọn sản phẩm --</option>
                        <?php if ($log && $log->product_id): ?>
                            <?php $product = function_exists('aerp_get_product') ? aerp_get_product($log->product_id) : null; ?>
                            <?php if ($product): ?>
                                <option value="<?php echo esc_attr($product->id); ?>" selected>
                                    <?php echo esc_html($product->name); ?>
                                </option>
                            <?php endif; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="quantity" class="form-label">Số lượng</label>
                    <input type="number" name="quantity" id="quantity" class="form-control shadow-sm" min="1" required
                        value="<?php echo esc_attr($log->quantity ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="note" class="form-label">Ghi chú</label>
                    <textarea name="note" id="note" class="form-control shadow-sm" rows="1"><?php echo esc_textarea($log->note ?? ''); ?></textarea>
                </div>
                <?php if ($type === 'import'): ?>
                    <div class="col-md-6 mb-3">
                        <label for="supplier_id" class="form-label">Nhà cung cấp</label>
                        <select class="form-select shadow-sm supplier-select" id="supplier_id" name="supplier_id" required style="width:100%">
                            <option value="">-- Chọn nhà cung cấp --</option>
                            <?php foreach (AERP_Supplier_Manager::get_all() as $s): ?>
                                <option value="<?php echo esc_attr($s->id); ?>" <?php selected($log && $log->supplier_id == $s->id); ?>>
                                    <?php echo esc_html($s->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <?php if (!$id): ?>
                    <button type="submit" name="aerp_save_inventory_log" class="btn btn-primary">
                        <?php echo $type === 'import' ? 'Tạo phiếu nhập kho' : 'Tạo phiếu xuất kho'; ?>
                    </button>
                <?php else: ?>
                    <button type="submit" name="aerp_confirm_inventory_log" class="btn btn-success" onclick="return confirm('Xác nhận phiếu này?')">
                        Xác nhận
                    </button>
                <?php endif; ?>
                <a href="<?php echo home_url('/aerp-inventory-logs'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = $id ? 'Xác nhận phiếu' : ($type === 'import' ? 'Tạo phiếu nhập kho' : 'Tạo phiếu xuất kho');
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
