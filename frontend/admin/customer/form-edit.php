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


$edit_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$editing = AERP_Frontend_Customer_Manager::get_by_id($edit_id);

if (!$editing) {
    wp_die(__('Customer not found.'));
}

ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Cập nhật khách hàng</h2>
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
            <?php wp_nonce_field('aerp_delete_attachment_nonce', 'aerp_delete_attachment_nonce'); ?>
            <input type="hidden" name="customer_id" value="<?php echo esc_attr($edit_id); ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="full_name" class="form-label">Họ và tên</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo esc_attr($editing->full_name); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="company_name" class="form-label">Tên công ty</label>
                    <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo esc_attr($editing->company_name); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="tax_code" class="form-label">Mã số thuế</label>
                    <input type="text" class="form-control" id="tax_code" name="tax_code" value="<?php echo esc_attr($editing->tax_code); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo esc_attr($editing->email); ?>">
                </div>
                <div class="col-12 mb-3">
                    <label for="address" class="form-label">Địa chỉ</label>
                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo esc_textarea($editing->address); ?></textarea>
                </div>
                <!-- Phone Numbers Section -->
                <div class="col-12 mb-3">
                    <label class="form-label">Số điện thoại</label>
                    <div id="phone-numbers-container">
                        <?php
                        // Fetch existing phone numbers for the customer
                        $existing_phones = aerp_get_customer_phones($edit_id); // Assuming this function exists or will be created
                        if (!empty($existing_phones)) {
                            foreach ($existing_phones as $index => $phone) {
                                $is_primary_checked = checked($phone->is_primary, 1, false);
                                echo '<div class="input-group mb-2">';
                                echo '<input type="hidden" name="phone_numbers[' . $index . '][id]" value="' . esc_attr($phone->id) . '">';
                                echo '<input type="text" class="form-control" name="phone_numbers[' . $index . '][number]" placeholder="Số điện thoại" value="' . esc_attr($phone->phone_number) . '">';
                                echo '<div class="input-group-text">';
                                echo '<input type="checkbox" name="phone_numbers[' . $index . '][primary]" value="1" ' . $is_primary_checked . '> &nbsp; Chính';
                                echo '</div>';
                                echo '<input type="text" class="form-control" name="phone_numbers[' . $index . '][note]" placeholder="Ghi chú" value="' . esc_attr($phone->note) . '">';
                                echo '<button type="button" class="btn btn-outline-danger remove-phone-field">Xóa</button>';
                                echo '</div>';
                            }
                        } else {
                            // Display at least one empty field if no phones exist
                            echo '<div class="input-group mb-2">';
                            echo '<input type="text" class="form-control" name="phone_numbers[0][number]" placeholder="Số điện thoại">';
                            echo '<div class="input-group-text">';
                            echo '<input type="checkbox" name="phone_numbers[0][primary]" value="1"> &nbsp; Chính';
                            echo '</div>';
                            echo '<input type="text" class="form-control" name="phone_numbers[0][note]" placeholder="Ghi chú">';
                            echo '<button type="button" class="btn btn-outline-danger remove-phone-field">Xóa</button>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <button type="button" class="btn btn-outline-primary mt-2" id="add-phone-field">Thêm số điện thoại</button>
                </div>

                <!-- Attachments Section -->
                <div class="col-md-6 mb-3">
                    <label for="attachments" class="form-label">File đính kèm</label>
                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                    <div id="existing-attachments-container" class="mt-2">
                        <?php
                        // Fetch existing attachments for the customer
                        $existing_attachments = aerp_get_customer_attachments($edit_id); // Assuming this function exists or will be created
                        if (!empty($existing_attachments)) {
                            foreach ($existing_attachments as $attachment) {
                                echo '<div class="d-flex align-items-center mb-1">';
                                echo '<a href="' . esc_url($attachment->file_url) . '" target="_blank" class="me-2">' . esc_html($attachment->file_name) . '</a>';
                                echo '<button type="button" class="btn btn-sm btn-danger delete-attachment" data-attachment-id="' . esc_attr($attachment->id) . '">Xóa</button>';
                                echo '<input type="hidden" name="existing_attachments[]" value="' . esc_attr($attachment->id) . '">'; // Để biết file nào giữ lại
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="customer_type_id" class="form-label">Loại khách hàng</label>
                    <select class="form-select" id="customer_type_id" name="customer_type_id">
                        <option value="">-- Chọn loại khách hàng --</option>
                        <?php
                        $customer_types = aerp_get_customer_types();
                        foreach ($customer_types as $type) {
                            printf('<option value="%s"%s>%s</option>', esc_attr($type->id), selected($editing->customer_type_id, $type->id, false), esc_html($type->name));
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?php selected($editing->status, 'active'); ?>>Hoạt động</option>
                        <option value="inactive" <?php selected($editing->status, 'inactive'); ?>>Không hoạt động</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="assigned_to" class="form-label">Người phụ trách</label>
                    <select class="form-select" id="assigned_to" name="assigned_to">
                        <option value="">-- Chọn nhân viên --</option>
                        <?php
                        $employees = aerp_get_employees_with_location(); // Lấy danh sách nhân viên
                        foreach ($employees as $employee) {
                            $display_name = esc_html($employee->full_name);
                            if (!empty($employee->work_location_name)) {
                                $display_name .= ' - ' . esc_html($employee->work_location_name);
                            }
                            printf(
                                '<option value="%s"%s>%s</option>',
                                esc_attr($employee->user_id),
                                selected($editing->assigned_to, $employee->user_id, false),
                                $display_name
                            );
                        }
                        ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label for="note" class="form-label">Ghi chú</label>
                    <textarea class="form-control" id="note" name="note" rows="3"><?php echo esc_textarea($editing->note); ?></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_customer" class="btn btn-primary">Cập nhật</button>
                <a href="<?php echo home_url('/aerp-crm-customers'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Cập nhật khách hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
