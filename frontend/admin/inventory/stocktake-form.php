<?php
if (!defined('ABSPATH')) exit;
// Get current user
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

if (!is_user_logged_in()) {
    wp_die(__('You must be logged in to access this page.'));
}

// Danh sách điều kiện, chỉ cần 1 cái đúng là qua
$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'department_lead'),
    aerp_user_has_permission($user_id,'stocktake'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
$log = $id ? AERP_Inventory_Log_Manager::get_log_by_id($id) : null;
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aerp_save_stocktake'])) {
    $changed = AERP_Inventory_Log_Manager::handle_stocktake_submit($_POST);
    if ($changed > 0) {
        $success = true;
    } else {
        $error = 'Không có thay đổi tồn kho nào.';
    }
}
$system_qty = aerp_get_stock_qty($log->product_id, $log->warehouse_id);
$actual_qty = $system_qty + ($log->quantity ?? 0);
ob_start();
?>
<style>
    .select2-container--default .select2-selection--single {
        border: 1px solid #dee2e6 !important;
        border-radius: 0.375rem !important;
        height: 38px !important;
        min-height: 38px !important;
        padding: 6px 12px !important;
        background: #fff !important;
        font-size: 1rem !important;
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

<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?php echo $id ? 'Xác nhận phiếu kiểm kho' : 'Tạo phiếu kiểm kho'; ?></h2>
    <div class="user-info text-end">
        Xin chào, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Thoát
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if ($success): ?>
            <div class="alert alert-success">Đã ghi nhận kiểm kho!</div>
        <?php elseif ($error): ?>
            <div class="alert alert-warning"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('aerp_save_stocktake_action', 'aerp_save_stocktake_nonce'); ?>
            <?php if ($id): ?>
                <input type="hidden" name="log_id" value="<?php echo esc_attr($id); ?>">
            <?php endif; ?>
            <div class="row">
                <!-- Chọn kho -->
                <div class="col-md-6 mb-3">
                    <label class="form-label">Kho</label>
                    <select class="form-select warehouse-select-by-user" name="warehouse_id" required>
                        <option value="">-- Chọn kho --</option>
                        <?php foreach (AERP_Warehouse_Manager::get_all() as $w): ?>
                            <option value="<?php echo esc_attr($w->id); ?>" <?php selected($log && $log->warehouse_id == $w->id); ?>>
                                <?php echo AERP_Warehouse_Manager::get_full_warehouse_name($w->id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Chọn sản phẩm -->
                <div class="col-md-6 mb-3">
                    <label class="form-label">Sản phẩm</label>
                    <select class="form-select product-select-by-warehouse" name="product_id" required style="width:100%">
                        <option value="">-- Chọn sản phẩm --</option>
                        <?php if ($log && $log->product_id): ?>
                            <?php $product = function_exists('aerp_get_product') ? aerp_get_product($log->product_id) : null; ?>
                            <?php if ($product): ?>
                                <option value="<?php echo esc_attr($product->id); ?>" selected><?php echo esc_html($product->name); ?></option>
                            <?php endif; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Tồn kho hệ thống -->
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tồn kho hệ thống</label>
                    <input type="text" class="form-control system-qty" value="<?php echo esc_attr($system_qty); ?>" readonly>

                </div>

                <!-- Số lượng thực tế -->
                <div class="col-md-6 mb-3">
                    <label class="form-label">Số lượng thực tế</label>
                    <input type="number" class="form-control" name="actual_qty" min="0" required value="<?php echo esc_attr($actual_qty); ?>">

                </div>

                <!-- Ghi chú -->
                <div class="col-12 mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="note" class="form-control" rows="2"><?php echo esc_textarea($log->note ?? ''); ?></textarea>
                </div>
            </div>
            <!-- Submit -->
            <div class="d-flex gap-2">
                <?php if (!$id): ?>
                    <button type="submit" name="aerp_save_stocktake" class="btn btn-primary">Tạo phiếu kiểm kho</button>
                <?php else: ?>
                    <button type="submit" name="aerp_confirm_inventory_log" class="btn btn-success" onclick="return confirm('Xác nhận phiếu này?')">Xác nhận</button>
                <?php endif; ?>
                <a href="<?php echo home_url('/aerp-inventory-logs'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>

<script>
    jQuery(function($) {
        function initSelect2() {
            $('.product-select-by-warehouse').select2({
                placeholder: '-- Chọn sản phẩm trong kho --',
                allowClear: true,
                ajax: {
                    url: (typeof aerp_order_ajax !== 'undefined' ? aerp_order_ajax.ajaxurl : ajaxurl),
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: "aerp_order_search_products_in_warehouse",
                            warehouse_id: $("select[name='warehouse_id']").val(), // <-- lấy động mỗi lần gọi ajax
                            q: params.term || "",
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });

            $('.product-select-by-warehouse').on('select2:select', function(e) {
                const product_id = e.params.data.id;
                const warehouse_id = $('.warehouse-select-by-user').val();
                const $qty = $('.system-qty');

                if (!warehouse_id) {
                    alert('Vui lòng chọn kho trước!');
                    $(this).val(null).trigger('change');
                    return;
                }

                $qty.val('...');

                $.ajax({
                    url: (typeof aerp_order_ajax !== 'undefined' ? aerp_order_ajax.ajaxurl : ajaxurl),
                    data: {
                        action: 'aerp_get_product_stock',
                        product_id: product_id,
                        warehouse_id: warehouse_id
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success && typeof res.data.quantity !== 'undefined') {
                            $qty.val(res.data.quantity);
                        } else {
                            $qty.val('0');
                        }
                    },
                    error: function() {
                        $qty.val('?');
                    }
                });
            });

            $('.product-select').on('select2:clear', function() {
                $('.system-qty').val('');
            });
        }

        initSelect2();
    });
</script>

<?php
$content = ob_get_clean();
$title = 'Kiểm kho';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
