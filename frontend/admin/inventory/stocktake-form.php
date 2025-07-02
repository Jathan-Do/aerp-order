<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
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
    <h2>Kiểm kho</h2>
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

            <div id="stocktake-items-container">
                <div class="row mb-2 stocktake-item-row">
                    <!-- Chọn Kho -->
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Kho</label>
                        <select class="form-select warehouse-select" name="products[0][warehouse_id]" required>
                            <option value="">-- Chọn kho --</option>
                            <?php foreach (AERP_Warehouse_Manager::get_all() as $w): ?>
                                <option value="<?php echo esc_attr($w->id); ?>">
                                    <?php echo AERP_Warehouse_Manager::get_full_warehouse_name($w->id); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Chọn SP -->
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Sản phẩm</label>
                        <select class="form-select product-select" name="products[0][product_id]" required style="width:100%"></select>
                    </div>

                    <!-- Tồn kho hệ thống -->
                    <div class="col-md-2 mb-2">
                        <label class="form-label">Tồn kho hệ thống</label>
                        <input type="text" class="form-control system-qty" value="" readonly tabindex="-1">
                    </div>

                    <!-- Thực tế -->
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Số lượng thực tế</label>
                        <input type="number" class="form-control" name="products[0][actual_qty]" min="0" required>
                    </div>

                    <!-- Xóa -->
                    <div class="col-md-1 mb-2 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger remove-stocktake-item">Xóa</button>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-secondary mt-2" id="add-stocktake-item">Thêm sản phẩm</button>

            <div class="mb-3 mt-3">
                <label class="form-label">Ghi chú</label>
                <textarea name="note" class="form-control" rows="2"></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_stocktake" class="btn btn-primary">Ghi nhận kiểm kho</button>
                <a href="<?php echo home_url('/aerp-inventory-logs'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>

    </div>
</div>
<script>
    jQuery(function($) {
        function initProductSelect2(selector) {
            if (window.initAerpProductSelect2) {
                window.initAerpProductSelect2(selector);
            } else if ($.fn.select2) {
                $(selector).select2({
                    placeholder: '-- Chọn sản phẩm --',
                    allowClear: true,
                    ajax: {
                        url: (typeof aerp_order_ajax !== 'undefined' ? aerp_order_ajax.ajaxurl : ajaxurl),
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'aerp_order_search_products',
                                q: params.term || ''
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
            }

            // Khi chọn SP: gọi tồn kho
            $(selector).on('select2:select', function(e) {
                var product_id = e.params.data.id;
                var $row = $(this).closest('.stocktake-item-row');
                var $qty = $row.find('.system-qty');

                var warehouse_id = $row.find('.warehouse-select').val(); // 👈 lấy đúng kho dòng đó
                if (!warehouse_id) {
                    alert('Vui lòng chọn kho trước khi chọn sản phẩm!');
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

            $(selector).on('select2:clear', function() {
                $(this).closest('.stocktake-item-row').find('.system-qty').val('');
            });
        }

        initProductSelect2('.stocktake-item-row .product-select');

        $('#add-stocktake-item').on('click', function() {
            var idx = $('#stocktake-items-container .stocktake-item-row').length;
            var row = `<div class="row mb-2 stocktake-item-row">
      <div class="col-md-3 mb-2">
        <select class="form-select warehouse-select" name="products[${idx}][warehouse_id]" required>
          <option value="">-- Chọn kho --</option>
          <?php foreach (AERP_Warehouse_Manager::get_all() as $w): ?>
            <option value="<?php echo esc_attr($w->id); ?>"><?php echo esc_html($w->name); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 mb-2">
        <select class="form-select product-select" name="products[${idx}][product_id]" required style="width:100%"></select>
      </div>
      <div class="col-md-2 mb-2">
        <input type="text" class="form-control system-qty" value="" readonly tabindex="-1">
      </div>
      <div class="col-md-3 mb-2">
        <input type="number" class="form-control" name="products[${idx}][actual_qty]" min="0" required>
      </div>
      <div class="col-md-1 mb-2 d-flex align-items-end">
        <button type="button" class="btn btn-outline-danger remove-stocktake-item">Xóa</button>
      </div>
    </div>`;
            $('#stocktake-items-container').append(row);
            initProductSelect2(`#stocktake-items-container .stocktake-item-row:last .product-select`);
        });

        $(document).on('click', '.remove-stocktake-item', function() {
            $(this).closest('.stocktake-item-row').remove();
        });
    });
</script>
<?php
$content = ob_get_clean();
$title = 'Kiểm kho';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
