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
    aerp_user_has_permission($user_id, 'order_edit'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$edit_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$editing = AERP_Frontend_Order_Manager::get_by_id($edit_id);
if (!$editing) wp_die(__('Order not found.'));
$order_items = function_exists('aerp_get_order_items') ? aerp_get_order_items($edit_id) : [];

// Xử lý xác nhận đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aerp_confirm_order'], $_POST['order_id'])) {
    global $wpdb;
    $order_id = absint($_POST['order_id']);
    // 1 là status_id của trạng thái đã xác nhận, bạn thay đúng giá trị nếu khác
    $wpdb->update(
        $wpdb->prefix . 'aerp_order_orders',
        ['status' => 'confirmed'],
        ['id' => $order_id]
    );
    // Có thể set message hoặc redirect
    wp_redirect(home_url('/aerp-order-orders'));
    exit;
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
    <h2>Cập nhật đơn hàng</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('aerp_save_order_action', 'aerp_save_order_nonce'); ?>
            <input type="hidden" name="order_id" value="<?php echo esc_attr($edit_id); ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="customer_id" class="form-label">Khách hàng</label>
                    <select class="form-select customer-select" id="customer_id" name="customer_id" required>
                        <?php
                        $selected_id = $editing->customer_id;
                        $selected_name = '';
                        if (function_exists('aerp_get_customer')) {
                            $c = aerp_get_customer($selected_id);
                            if ($c) $selected_name = $c->full_name . (!empty($c->code) ? ' (' . $c->code . ')' : '');
                        }
                        if ($selected_name) {
                            echo '<option value="' . esc_attr($selected_id) . '" selected>' . esc_html($selected_name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="employee_id" class="form-label">Nhân viên phụ trách</label>
                    <select class="form-select employee-select" id="employee_id" name="employee_id">
                        <option value="">-- Chọn nhân viên --</option>
                        <?php
                        $selected_id = $editing->employee_id;
                        $selected_name = '';
                        if (function_exists('aerp_get_employees_with_location')) {
                            $employees = aerp_get_employees_with_location();
                            foreach ($employees as $e) {
                                if ($e->id == $selected_id) {
                                    $selected_name = $e->full_name . (!empty($e->work_location_name) ? ' - ' . $e->work_location_name : '');
                                    break;
                                }
                            }
                        }
                        if ($selected_name) {
                            echo '<option value="' . esc_attr($selected_id) . '" selected>' . esc_html($selected_name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="order_date" class="form-label">Ngày tạo đơn hàng</label>
                    <input type="date" class="form-control bg-body" id="order_date" name="order_date" value="<?php echo esc_attr($editing->order_date); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="status_id" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status_id" name="status_id">
                        <?php
                        $statuses = aerp_get_order_statuses();
                        aerp_safe_select_options($statuses, $editing->status_id, 'id', 'name', true);
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="cost" class="form-label">Chi phí đơn hàng</label>
                    <input type="number" class="form-control" id="cost" name="cost" min="0" step="0.01" value="<?php echo esc_attr($editing->cost ?? 0); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="customer_source" class="form-label">Nguồn khách hàng</label>
                    <select class="form-select" id="customer_source" name="customer_source">
                        <option value="">-- Chọn nguồn --</option>
                        <option value="fb" <?php selected($editing->customer_source ?? '', 'fb'); ?>>Facebook</option>
                        <option value="zalo" <?php selected($editing->customer_source ?? '', 'zalo'); ?>>Zalo</option>
                        <option value="tiktok" <?php selected($editing->customer_source ?? '', 'tiktok'); ?>>Tiktok</option>
                        <option value="youtube" <?php selected($editing->customer_source ?? '', 'youtube'); ?>>Youtube</option>
                        <option value="web" <?php selected($editing->customer_source ?? '', 'web'); ?>>Website</option>
                        <option value="referral" <?php selected($editing->customer_source ?? '', 'referral'); ?>>KH cũ giới thiệu</option>
                        <option value="other" <?php selected($editing->customer_source ?? '', 'other'); ?>>Khác</option>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Sản phẩm trong đơn</label>
                    <div id="order-items-container">
                        <?php
                        if (!empty($order_items)) {
                            foreach ($order_items as $idx => $item) {
                                echo '<div class="row mb-2 order-item-row">';
                                echo '<input type="hidden" name="order_items[' . $idx . '][id]" value="' . esc_attr($item->id) . '">';
                                // Select loại sản phẩm/dịch vụ
                                $item_type = isset($item->item_type) ? $item->item_type : ((empty($item->product_id)) ? 'service' : 'product');
                                echo '<div class="col-md-2 mb-2">';
                                echo '<select class="form-select item-type-select" name="order_items[' . $idx . '][item_type]">';
                                echo '<option value="product"' . selected($item_type, 'product', false) . '>Sản phẩm</option>';
                                echo '<option value="service"' . selected($item_type, 'service', false) . '>Dịch vụ</option>';
                                echo '</select>';
                                echo '</div>';
                                // Tên sản phẩm/dịch vụ
                                echo '<div class="col-md-3 mb-2">';
                                echo '<input type="text" class="form-control product-name-input" name="order_items[' . $idx . '][product_name]" value="' . esc_attr($item->product_name) . '" placeholder="Tên sản phẩm/dịch vụ"' . ($item_type == 'product' ? ' style="display:none"' : '') . ' required>';
                                // Select2 sản phẩm (ẩn nếu là dịch vụ)
                                echo '<select class="form-select product-select-all-warehouses" name="order_items[' . $idx . '][product_id]" style="width:100%;' . ($item_type == 'service' ? 'display:none;' : '') . '">';
                                if ($item_type == 'product' && !empty($item->product_id)) {
                                    // Hiển thị option đã chọn
                                    echo '<option value="' . esc_attr($item->product_id) . '" selected>' . esc_html($item->product_name) . '</option>';
                                }
                                echo '</select>';
                                echo '</div>';
                                // Số lượng, đơn giá, v.v. giữ nguyên
                                echo '<div class="col-md-2 mb-2 d-flex align-items-center">';
                                echo '<input type="number" class="form-control" name="order_items[' . $idx . '][quantity]" value="' . esc_attr($item->quantity) . '" placeholder="Số lượng" min="0.01" step="0.01" required>';
                                echo '<span class="unit-label ms-2">' . esc_html($item->unit_name ?? '') . '</span>';
                                echo '<input type="hidden" name="order_items[' . $idx . '][unit_name]" value="' . esc_attr($item->unit_name ?? '') . '" class="unit-name-input">';
                                echo '</div>';
                                echo '<div class="col-md-1 mb-2">';
                                echo '<input type="number" class="form-control" name="order_items[' . $idx . '][vat_percent]" value="' . esc_attr(isset($item->vat_percent) ? $item->vat_percent : '') . '" placeholder="VAT (%)" min="0" max="100" step="0.01">';
                                echo '</div>';
                                echo '<div class="col-md-2 mb-2"><input type="number" class="form-control" name="order_items[' . $idx . '][unit_price]" value="' . esc_attr($item->unit_price) . '" placeholder="Đơn giá" min="0" step="0.01" required></div>';
                                echo '<div class="col-md-2 mb-2"><input type="text" class="form-control total-price-field" value="' . number_format($item->total_price, 0, ',', '.') . '" placeholder="Thành tiền" readonly></div>';
                                echo '<div class="col-md-1 mb-2"><button type="button" class="btn btn-outline-danger remove-order-item">Xóa</button></div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="row mb-2 order-item-row">';
                            echo '<div class="col-md-3 mb-2"><input type="text" class="form-control" name="order_items[0][product_name]" placeholder="Tên sản phẩm" required></div>';
                            echo '<div class="col-md-3 mb-2 d-flex align-items-center">';
                            echo '<input type="number" class="form-control" name="order_items[0][quantity]" placeholder="Số lượng" min="0.01" step="0.01" value="1" required>';
                            echo '<span class="unit-label ms-2"></span>';
                            echo '</div>';
                            echo '<div class="col-md-1 mb-2">';
                            echo '<input type="number" class="form-control" name="order_items[0][vat_percent]" placeholder="VAT (%)" min="0" max="100" step="0.01">';
                            echo '</div>';
                            echo '<div class="col-md-2 mb-2"><input type="number" class="form-control" name="order_items[0][unit_price]" placeholder="Đơn giá" min="0" step="0.01" required></div>';
                            echo '<div class="col-md-2 mb-2"><input type="text" class="form-control total-price-field" placeholder="Thành tiền" readonly></div>';
                            echo '<div class="col-md-1 mb-2"><button type="button" class="btn btn-outline-danger remove-order-item">Xóa</button></div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <button type="button" class="btn btn-secondary mt-2" id="add-order-item">Thêm sản phẩm</button>
                </div>
                <div class="col-12 mb-3 overflow-hidden">
                    <label for="attachments" class="form-label">File đính kèm mới</label>
                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                    <div id="existing-attachments-container" class="mt-2">
                        <?php
                        $existing_attachments = function_exists('aerp_get_order_attachments') ? aerp_get_order_attachments($edit_id) : [];
                        if (!empty($existing_attachments)) {
                            foreach ($existing_attachments as $attachment) {
                                echo '<div class="d-flex align-items-center mb-1">';
                                echo '<a href="' . esc_url($attachment->file_url) . '" target="_blank" class="me-2">' . esc_html($attachment->file_name) . '</a>';
                                echo '<button type="button" class="btn btn-sm btn-danger delete-attachment" data-attachment-id="' . esc_attr($attachment->id) . '">Xóa</button>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                </div>
                <div class="col-12 mb-3">
                    <label for="note" class="form-label">Ghi chú</label>
                    <textarea class="form-control" id="note" name="note" rows="2"><?php echo esc_textarea($editing->note); ?></textarea>
                </div>

                <?php if (!empty($editing->cancel_reason)): ?>
                    <div class="col-12 mb-3">
                        <div class="alert alert-danger">
                            <strong>Đơn hàng đã bị hủy</strong><br>
                            <strong>Lý do:</strong> <?php echo esc_html($editing->cancel_reason); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_order" class="btn btn-primary">Cập nhật</button>
                <?php
                // Chỉ hiển thị nút xác nhận nếu trạng thái KHÔNG phải là 'confirmed' hoặc 'cancelled'
                if (
                    (aerp_user_has_role($user_id, 'accountant') || aerp_user_has_role($user_id, 'admin'))
                    && (!isset($editing->status) || ($editing->status !== 'confirmed' && $editing->status !== 'cancelled'))
                ) {
                    echo '<button type="submit" name="aerp_confirm_order" class="btn btn-success" onclick="return confirm(\'Xác nhận đơn này?\')">Xác nhận</button>';
                }
                ?>
                <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<script>
    (function($) {
        let itemIndex = <?php echo !empty($order_items) ? count($order_items) : 1; ?>;
        $('#add-order-item').on('click', function() {
            let row = `<div class="row mb-2 order-item-row">
            <div class="col-md-3 mb-2"><input type="text" class="form-control" name="order_items[${itemIndex}][product_name]" placeholder="Tên sản phẩm" required></div>
            <div class="col-md-3 mb-2 d-flex align-items-center">
                <input type="number" class="form-control" name="order_items[${itemIndex}][quantity]" placeholder="Số lượng" min="0.01" step="0.01" value="1" required>
                <span class="unit-label ms-2"></span>
            </div>
            <div class="col-md-1 mb-2">
                <input type="number" class="form-control" name="order_items[${itemIndex}][vat_percent]" placeholder="VAT (%)" min="0" max="100" step="0.01">
            </div>
            <div class="col-md-2 mb-2"><input type="number" class="form-control" name="order_items[${itemIndex}][unit_price]" placeholder="Đơn giá" min="0" step="0.01" required></div>
            <div class="col-md-2 mb-2"><input type="text" class="form-control total-price-field" placeholder="Thành tiền" readonly></div>
            <div class="col-md-1 mb-2"><button type="button" class="btn btn-outline-danger remove-order-item">Xóa</button></div>
        </div>`;
            $('#order-items-container').append(row);
            itemIndex++;
        });
        $(document).on('click', '.remove-order-item', function() {
            $(this).closest('.order-item-row').remove();
        });
        $(document).on('input', 'input[name*="[quantity]"], input[name*="[unit_price]"], input[name*="[product_name]"], input[name*="[vat_percent]"]', function() {
            let row = $(this).closest('.order-item-row');
            let qty = parseFloat(row.find('input[name*="[quantity]"]').val()) || 0;
            let price = parseFloat(row.find('input[name*="[unit_price]"]').val()) || 0;
            let vat = parseFloat(row.find('input[name*="[vat_percent]"]').val()) || 0;
            let total = qty * price;
            if (vat > 0) {
                total = total + (total * vat / 100);
            }
            row.find('.total-price-field').val(total.toLocaleString('vi-VN'));
        });
    })(jQuery);
</script>
<?php
$content = ob_get_clean();
$title = 'Cập nhật đơn hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
