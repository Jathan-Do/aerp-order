<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
if (!is_user_logged_in() || !aerp_user_has_role($user_id, 'admin')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$edit_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$editing = AERP_Frontend_Order_Manager::get_by_id($edit_id);
if (!$editing) wp_die(__('Order not found.'));
$order_items = function_exists('aerp_get_order_items') ? aerp_get_order_items($edit_id) : [];
ob_start();
?>
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
                    <select class="form-select" id="customer_id" name="customer_id" required>
                        <?php
                        $customers = function_exists('aerp_get_customers') ? aerp_get_customers() : [];
                        foreach ($customers as $c) {
                            printf('<option value="%s"%s>%s</option>', esc_attr($c->id), selected($editing->customer_id, $c->id, false), esc_html($c->full_name));
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="employee_id" class="form-label">Nhân viên phụ trách</label>
                    <select class="form-select" id="employee_id" name="employee_id">
                        <option value="">-- Chọn nhân viên --</option>
                        <?php
                        $employees = function_exists('aerp_get_employees_with_location') ? aerp_get_employees_with_location() : [];
                        foreach ($employees as $employee) {
                            $display_name = esc_html($employee->full_name);
                            if (!empty($employee->work_location_name)) {
                                $display_name .= ' - ' . esc_html($employee->work_location_name);
                            }
                            printf(
                                '<option value="%s"%s>%s</option>',
                                esc_attr($employee->user_id),
                                selected($editing->employee_id, $employee->user_id, false),
                                $display_name
                            );
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="order_date" class="form-label">Ngày tạo đơn hàng</label>
                    <input type="date" class="form-control bg-body" id="order_date" name="order_date" value="<?php echo esc_attr($editing->order_date); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="new" <?php selected($editing->status, 'new'); ?>>Mới</option>
                        <option value="processing" <?php selected($editing->status, 'processing'); ?>>Xử lý</option>
                        <option value="completed" <?php selected($editing->status, 'completed'); ?>>Hoàn tất</option>
                        <option value="cancelled" <?php selected($editing->status, 'cancelled'); ?>>Hủy</option>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Sản phẩm trong đơn</label>
                    <div id="order-items-container">
                        <?php
                        if (!empty($order_items)) {
                            foreach ($order_items as $idx => $item) {
                                echo '<div class="row mb-2 order-item-row">';
                                echo '<input type="hidden" name="order_items['.$idx.'][id]" value="'.esc_attr($item->id).'">';
                                echo '<div class="col-md-4 mb-2"><input type="text" class="form-control" name="order_items['.$idx.'][product_name]" value="'.esc_attr($item->product_name).'" placeholder="Tên sản phẩm" required></div>';
                                echo '<div class="col-md-2 mb-2"><input type="number" class="form-control" name="order_items['.$idx.'][quantity]" value="'.esc_attr($item->quantity).'" placeholder="Số lượng" min="1" required></div>';
                                echo '<div class="col-md-3 mb-2"><input type="number" class="form-control" name="order_items['.$idx.'][unit_price]" value="'.esc_attr($item->unit_price).'" placeholder="Đơn giá" min="0" step="0.01" required></div>';
                                echo '<div class="col-md-2 mb-2"><input type="text" class="form-control total-price-field" value="'.number_format($item->total_price,0,',','.').'" placeholder="Thành tiền" readonly></div>';
                                echo '<div class="col-md-1 mb-2"><button type="button" class="btn btn-outline-danger remove-order-item">Xóa</button></div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="row mb-2 order-item-row">';
                            echo '<div class="col-md-4 mb-2"><input type="text" class="form-control" name="order_items[0][product_name]" placeholder="Tên sản phẩm" required></div>';
                            echo '<div class="col-md-2 mb-2"><input type="number" class="form-control" name="order_items[0][quantity]" placeholder="Số lượng" min="1" value="1" required></div>';
                            echo '<div class="col-md-3 mb-2"><input type="number" class="form-control" name="order_items[0][unit_price]" placeholder="Đơn giá" min="0" step="0.01" required></div>';
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
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_order" class="btn btn-primary">Cập nhật</button>
                <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<script>
(function($){
    let itemIndex = <?php echo !empty($order_items) ? count($order_items) : 1; ?>;
    $('#add-order-item').on('click', function(){
        let row = `<div class="row mb-2 order-item-row">
            <div class="col-md-4 mb-2"><input type="text" class="form-control" name="order_items[${itemIndex}][product_name]" placeholder="Tên sản phẩm" required></div>
            <div class="col-md-2 mb-2"><input type="number" class="form-control" name="order_items[${itemIndex}][quantity]" placeholder="Số lượng" min="1" value="1" required></div>
            <div class="col-md-3 mb-2"><input type="number" class="form-control" name="order_items[${itemIndex}][unit_price]" placeholder="Đơn giá" min="0" step="0.01" required></div>
            <div class="col-md-2 mb-2"><input type="text" class="form-control total-price-field" placeholder="Thành tiền" readonly></div>
            <div class="col-md-1 mb-2"><button type="button" class="btn btn-outline-danger remove-order-item">Xóa</button></div>
        </div>`;
        $('#order-items-container').append(row);
        itemIndex++;
    });
    $(document).on('click', '.remove-order-item', function(){
        $(this).closest('.order-item-row').remove();
    });
    $(document).on('input', 'input[name*="[quantity]"], input[name*="[unit_price]"], input[name*="[product_name]"]', function(){
        let row = $(this).closest('.order-item-row');
        let qty = parseFloat(row.find('input[name*="[quantity]"]').val()) || 0;
        let price = parseFloat(row.find('input[name*="[unit_price]"]').val()) || 0;
        row.find('.total-price-field').val((qty * price).toLocaleString('vi-VN'));
    });
})(jQuery);
</script>
<?php
$content = ob_get_clean();
$title = 'Cập nhật đơn hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php'); 