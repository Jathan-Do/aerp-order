<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
if (!is_user_logged_in() || !aerp_user_has_role($user_id, 'admin')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$date_now = date('Y-m-d');
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
    <h2>Thêm đơn hàng mới</h2>
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
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="customer_id" class="form-label">Khách hàng</label>
                    <select class="form-select customer-select" id="customer_id" name="customer_id" required>
                        <?php
                        if (!empty($_POST['customer_id'])) {
                            $selected_id = intval($_POST['customer_id']);
                            $selected_name = '';
                            if (function_exists('aerp_get_customer')) {
                                $c = aerp_get_customer($selected_id);
                                if ($c) $selected_name = $c->full_name . (!empty($c->code) ? ' (' . $c->code . ')' : '');
                            }
                            if ($selected_name) {
                                echo '<option value="' . esc_attr($selected_id) . '" selected>' . esc_html($selected_name) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="employee_id" class="form-label">Nhân viên phụ trách</label>
                    <select class="form-select employee-select" id="employee_id" name="employee_id">
                        <?php
                        if (!empty($_POST['employee_id'])) {
                            $selected_id = intval($_POST['employee_id']);
                            $selected_name = '';
                            if (function_exists('aerp_get_employees_with_location')) {
                                $employees = aerp_get_employees_with_location();
                                foreach ($employees as $e) {
                                    if ($e->user_id == $selected_id) {
                                        $selected_name = $e->full_name . (!empty($e->work_location_name) ? ' - ' . $e->work_location_name : '');
                                        break;
                                    }
                                }
                            }
                            if ($selected_name) {
                                echo '<option value="' . esc_attr($selected_id) . '" selected>' . esc_html($selected_name) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="order_date" class="form-label">Ngày tạo đơn hàng</label>
                    <input type="date" class="form-control bg-body" id="order_date" name="order_date" required value="<?php echo esc_attr($date_now); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="status_id" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status_id" name="status_id">
                        <?php
                        $statuses = aerp_get_order_statuses();
                        aerp_safe_select_options($statuses, '', 'id', 'name', true);
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="order_type" class="form-label">Loại đơn hàng</label>
                    <select class="form-select" id="order_type" name="order_type" required>
                        <option value="product">Đơn hàng bán hàng</option>
                        <option value="service">Đơn hàng dịch vụ</option>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <div id="order-items-container">
                        <div class="row mb-2 order-item-row">
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Sản phẩm trong đơn</label><label class="form-label">Sản phẩm trong đơn</label>
                                <input type="text" class="form-control product-name-input" name="order_items[0][product_name]" placeholder="Tên sản phẩm/dịch vụ" required>
                                <select class="form-select product-select" name="order_items[0][product_id]" style="display:none;width:100%"></select>
                            </div>
                            <div class="col-md-3 mb-2 d-flex align-items-end">
                                <div class="w-100">
                                    <label class="form-label">Số lượng</label>
                                    <input type="number" class="form-control" name="order_items[0][quantity]" placeholder="Số lượng" min="0.01" step="0.01" required>
                                </div>
                                <span class="unit-label ms-2"></span>
                                <input type="hidden" name="order_items[0][unit_name]" class="unit-name-input">
                            </div>
                            <div class="col-md-1 mb-2">
                                <label class="form-label">VAT</label>
                                <input type="number" class="form-control" name="order_items[0][vat_percent]" placeholder="VAT (%)" min="0" max="100" step="0.01">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Đơn giá</label>
                                <input type="number" class="form-control" name="order_items[0][unit_price]" placeholder="Đơn giá" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Thành tiền</label>
                                <input type="text" class="form-control total-price-field" placeholder="Thành tiền" readonly>
                            </div>
                            <div class="col-md-1 mb-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger remove-order-item">Xóa</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary mt-2" id="add-order-item">Thêm sản phẩm</button>
                </div>
                <div class="col-12 mb-3">
                    <label for="attachments" class="form-label">File đính kèm</label>
                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                </div>
                <div class="col-12 mb-3">
                    <label for="note" class="form-label">Ghi chú</label>
                    <textarea class="form-control" id="note" name="note" rows="2"></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_order" class="btn btn-primary">Thêm mới</button>
                <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Thêm đơn hàng mới';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
