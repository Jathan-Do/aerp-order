<?php
if (!defined('ABSPATH')) {
    exit;
}
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
// Check if user is logged in and has admin capabilities (adjust as needed for CRM roles)
if (!is_user_logged_in() || !aerp_user_has_role($user_id, 'admin')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Thêm khách hàng mới</h2>
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
            <?php wp_nonce_field('aerp_save_customer_action', 'aerp_save_customer_nonce'); ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="full_name" class="form-label">Họ và tên</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="company_name" class="form-label">Tên công ty</label>
                    <input type="text" class="form-control" id="company_name" name="company_name">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="tax_code" class="form-label">Mã số thuế</label>
                    <input type="text" class="form-control" id="tax_code" name="tax_code">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email">
                </div>
                <div class="col-12 mb-3">
                    <label for="address" class="form-label">Địa chỉ</label>
                    <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Số điện thoại</label>
                    <div id="phone-numbers-container">
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" name="phone_numbers[0][number]" placeholder="Số điện thoại">
                            <div class="input-group-text">
                                <input class="form-check-input border-secondary mt-0" type="checkbox" name="phone_numbers[0][primary]" value="1"> &nbsp; Chính
                            </div>
                            <input type="text" class="form-control" name="phone_numbers[0][note]" placeholder="Ghi chú">
                            <button type="button" class="btn btn-outline-danger remove-phone-field">Xóa</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary mt-2" id="add-phone-field">Thêm số điện thoại</button>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="attachments" class="form-label">File đính kèm</label>
                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="customer_type_id" class="form-label">Loại khách hàng</label>
                    <select class="form-select" id="customer_type_id" name="customer_type_id">
                        <?php
                        $customer_types = aerp_get_customer_types();
                        aerp_safe_select_options($customer_types, '', 'id', 'name', true);
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active">Hoạt động</option>
                        <option value="inactive">Không hoạt động</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="assigned_to" class="form-label">Người phụ trách</label>
                    <select class="form-select" id="assigned_to" name="assigned_to">
                        <option value="">-- Chọn nhân viên --</option>
                        <?php
                        $employees = aerp_get_employees_with_location();
                        foreach ($employees as $employee) {
                            $display_name = esc_html($employee->full_name);
                            if (!empty($employee->work_location_name)) {
                                $display_name .= ' - ' . esc_html($employee->work_location_name);
                            }
                            printf(
                                '<option value="%s">%s</option>',
                                esc_attr($employee->user_id),
                                $display_name
                            );
                        }
                        ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label for="note" class="form-label">Ghi chú</label>
                    <textarea class="form-control" id="note" name="note" rows="2"></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_customer" class="btn btn-primary">Thêm mới</button>
                <a href="<?php echo home_url('/aerp-crm-customers'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Thêm khách hàng mới';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
