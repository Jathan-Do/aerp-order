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
    aerp_user_has_permission($user_id,'stock_transfer'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
AERP_Inventory_Transfer_Manager::handle_form_submit();
$message = get_transient('aerp_inventory_transfer_message');
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
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Thêm phiếu chuyển kho</h2>
    <div class="user-info text-end">
        Chào, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Thoát
        </a>
    </div>
</div>
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Quản lý kho', 'url' => home_url('/aerp-warehouses')],
        ['label' => 'Danh sách phiếu chuyển kho', 'url' => home_url('/aerp-inventory-transfers')],
        ['label' => 'Thêm phiếu chuyển kho']
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <?php if ($message) : ?>
            <div class="notice notice-success alert alert-success alert-dismissible fade show" role="alert">
                <?php echo esc_html($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php delete_transient('aerp_inventory_transfer_message'); ?>
        <?php endif; ?>
        <form method="post">
            <?php wp_nonce_field('aerp_save_inventory_transfer_action', 'aerp_save_inventory_transfer_nonce'); ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Kho xuất</label>
                    <?php
                    $warehouses = AERP_Warehouse_Manager::aerp_get_warehouses_by_user($user_id);
                    ?>
                    <select name="warehouse_id" class="form-select shadow-sm warehouse-select-by-user" required>
                        <option value="">-- Chọn kho xuất --</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?php echo esc_attr($w->id); ?>">
                                <?php echo AERP_Warehouse_Manager::get_full_warehouse_name($w->id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Kho nhập</label>
                    <select name="to_warehouse_id" class="form-select shadow-sm warehouse-select" required>
                        <option value="">-- Chọn kho nhập --</option>
                        <?php foreach (AERP_Warehouse_Manager::get_all() as $w): ?>
                            <option value="<?php echo esc_attr($w->id); ?>">
                                <?php echo AERP_Warehouse_Manager::get_full_warehouse_name($w->id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="transfer-items-container">
                <div class="row transfer-item-row">
                    <div class="col-md-6 mb-3">
                        <select class="form-select shadow-sm product-select-by-warehouse" name="products[0][product_id]" required style="width:100%"></select>
                    </div>
                    <div class="col-md-5 mb-3">
                        <input type="number" name="products[0][quantity]" class="form-control shadow-sm" placeholder="Số lượng" min="1" required>
                    </div>
                    <div class="col-md-1 mb-3 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger remove-transfer-item">Xóa</button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-secondary mb-3" id="add-transfer-item">Thêm sản phẩm</button>
            <div class="mb-3">
                <label class="form-label">Ghi chú</label>
                <textarea name="note" class="form-control shadow-sm" rows="2"></textarea>
            </div>
            <button type="submit" name="aerp_save_inventory_transfer" class="btn btn-primary">Ghi nhận chuyển kho</button>
            <a href="<?php echo home_url('/aerp-inventory-transfers'); ?>" class="btn btn-secondary">Quay lại</a>
        </form>
    </div>
</div>
<script>
    jQuery(function($) {
        function initSelect2(context) {
            var $ctx = context ? $(context) : $(document);
            // Chỉ init những select chưa được init
            $ctx.find('.product-select-by-warehouse').filter(':not(.select2-hidden-accessible)').select2({
                placeholder: '-- Chọn sản phẩm trong kho --',
                allowClear: true,
                ajax: {
                    url: (typeof aerp_order_ajax !== 'undefined' ? aerp_order_ajax.ajaxurl : ajaxurl),
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: "aerp_order_search_products_in_warehouse",
                            warehouse_id: $("select[name='warehouse_id']").val(),
                            q: params.term || "",
                        };
                    },
                    processResults: function(data) {
                        return { results: data };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });

            $ctx.find('.product-select-by-warehouse').off('select2:select').on('select2:select', function(e) {
                const warehouse_id = $("select[name='warehouse_id']").val();
                if (!warehouse_id) {
                    alert('Vui lòng chọn kho trước!');
                    $(this).val(null).trigger('change');
                    return;
                }
            });
        }

        // Khởi tạo cho hàng đầu tiên
        initSelect2();

        // Đổi kho xuất -> reset toàn bộ dropdown sản phẩm để nạp lại theo kho
        $("select[name='warehouse_id']").on('change', function() {
            $('.product-select-by-warehouse').val(null).trigger('change');
        });

        // Thêm dòng sản phẩm
        $('#add-transfer-item').on('click', function() {
            var idx = $('#transfer-items-container .transfer-item-row').length;
            var row = `<div class="row transfer-item-row">
        <div class="col-md-6 mb-3">
            <select class="form-select shadow-sm product-select-by-warehouse" name="products[${idx}][product_id]" required style="width:100%"></select>
        </div>
        <div class="col-md-5 mb-3">
            <input type="number" name="products[${idx}][quantity]" class="form-control shadow-sm" placeholder="Số lượng" min="1" required>
        </div>
        <div class="col-md-1 mb-3 d-flex align-items-end">
            <button type="button" class="btn btn-outline-danger remove-transfer-item">Xóa</button>
        </div>
    </div>`;
            $('#transfer-items-container').append(row);
            // Init select2 cho dòng mới
            initSelect2($('#transfer-items-container .transfer-item-row:last'));
        });

        // Xóa dòng
        $(document).on('click', '.remove-transfer-item', function() {
            $(this).closest('.transfer-item-row').remove();
        });
    });
</script>
<?php
$content = ob_get_clean();
$title = 'Thêm phiếu chuyển kho';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
