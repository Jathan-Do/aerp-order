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

$customer_id = get_query_var('aerp_crm_customer_id');
$customer = null;
if ($customer_id) {
    $customer = AERP_Frontend_Customer_Manager::get_by_id($customer_id);
}

ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2 class="mb-0">Thông tin chi tiết khách hàng</h2>
    <div class="user-info text-end">
        <span class="me-2">Welcome, <?php echo esc_html($current_user->display_name); ?></span>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<?php // Display messages if any (using Transients API)
$message = get_transient('aerp_customer_message');
if ($message) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    ' . esc_html($message) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
    delete_transient('aerp_customer_message'); // Xóa transient sau khi hiển thị
} ?>
<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Thông tin cơ bản</h5>
        </div>
    </div>

    <div class="card-body">
        <?php if ($customer) : ?>
            <div class="row mb-2">
                <div class="col-6 mb-3">
                    <label class="fw-bold form-label text-muted small mb-1">Mã khách hàng</label>
                    <p class="mb-0 fw-bold"><?php echo esc_html($customer->customer_code); ?><span class="badge bg-<?php echo ($customer->status === 'active') ? 'success' : 'secondary'; ?>">
                            <?php echo esc_html($customer->status === 'active') ? 'Hoạt động' : 'Không hoạt động'; ?>
                        </span></p>
                </div>

                <div class="col-6 mb-3">
                    <label class="fw-bold form-label text-muted small mb-1">Họ và tên</label>
                    <p class="mb-0"><?php echo esc_html($customer->full_name); ?></p>
                </div>

                <div class="col-6 mb-3">
                    <label class="fw-bold form-label text-muted small mb-1">Loại khách hàng</label>
                    <p class="mb-0">
                        <?php
                        $type = aerp_get_customer_type($customer->customer_type_id);
                        if ($type) {
                            echo '<span class="badge bg-' . esc_attr($type->color) . '">' . esc_html($type->name) . '</span>';
                        } else {
                            echo '<span class="badge bg-secondary">Không xác định</span>';
                        }
                        ?>
                    </p>
                </div>

                <div class="col-6 mb-3">
                    <label class="fw-bold form-label text-muted small mb-1">Email</label>
                    <p class="mb-0">
                        <?php if (!empty($customer->email)) : ?>
                            <a href="mailto:<?php echo esc_attr($customer->email); ?>">
                                <?php echo esc_html($customer->email); ?>
                            </a>
                        <?php else : ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-6 mb-3">
                    <label class="fw-bold form-label text-muted small mb-1">Tên công ty</label>
                    <p class="mb-0"><?php echo esc_html($customer->company_name); ?></p>
                </div>

                <div class="col-6 mb-3">
                    <label class="fw-bold form-label text-muted small mb-1">Mã số thuế</label>
                    <p class="mb-0"><?php echo esc_html($customer->tax_code); ?></p>
                </div>

                <div class="col-6 mb-3">
                    <label class="fw-bold form-label text-muted small mb-1">Địa chỉ</label>
                    <p class="mb-0"><?php echo esc_html($customer->address); ?></p>
                </div>

                <div class="col-6 mb-3">
                    <label class="fw-bold form-label text-muted small mb-1">Nhân viên phụ trách</label>
                    <p class="mb-0">
                        <?php
                        if (empty($customer->assigned_to)) {
                            echo 'Chưa được phân công';
                        } else {
                            echo esc_html(aerp_get_customer_assigned_name($customer->assigned_to));
                        }
                        ?>
                    </p>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Số điện thoại</h6>
                        </div>
                        <div class="card-body">
                            <?php $phones = aerp_get_customer_phones($customer->id); ?>
                            <?php if (!empty($phones)) : ?>
                                <ul class="list-group m-0">
                                    <?php foreach ($phones as $phone) : ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <a href="tel:<?php echo esc_attr($phone->phone_number); ?>">
                                                    <?php echo esc_html($phone->phone_number); ?>
                                                </a>
                                                <?php if ($phone->is_primary) : ?>
                                                    <span class="badge bg-success ms-2">Chính</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($phone->note)) : ?>
                                                <small class="text-muted"><?php echo esc_html($phone->note); ?></small>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <div class="alert alert-light mb-0" role="alert">
                                    Không có số điện thoại nào.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">File đính kèm</h6>
                        </div>
                        <div class="card-body">
                            <?php $attachments = aerp_get_customer_attachments($customer->id); ?>
                            <?php if (!empty($attachments)) : ?>
                                <ul class="list-group overflow-hidden m-0">
                                    <?php foreach ($attachments as $attachment) : ?>
                                        <li class="list-group-item">
                                            <a href="<?php echo esc_url($attachment->file_url); ?>" target="_blank" class="d-flex align-items-center">
                                                <i class="fas fa-file me-2 text-primary"></i>
                                                <?php echo esc_html($attachment->file_name); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <div class="alert alert-light mb-0" role="alert">
                                    Không có file đính kèm nào.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Ghi chú</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($customer->note)) : ?>
                        <p class="mb-0"><?php echo nl2br(esc_html($customer->note)); ?></p>
                    <?php else : ?>
                        <p class="text-muted mb-0">Không có ghi chú</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Interaction Log Section -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Lịch sử tương tác</h6>
                </div>
                <div class="card-body">
                    <?php
                    $logs_limit = 5; // Số lượng tương tác muốn hiển thị
                    $logs = aerp_get_customer_logs_paginated($customer->id, $logs_limit, 0);
                    $total_logs = count(aerp_get_customer_logs($customer->id)); // Để kiểm tra xem có nhiều hơn giới hạn không
                    ?>
                    <?php if (!empty($logs)) : ?>
                        <ul class="list-group list-group-flush m-0">
                            <?php foreach ($logs as $log) : ?>
                                <li class="list-group-item">
                                    <div class="d-flex flex-column flex-md-row justify-content-between mb-1">
                                        <strong><?php echo esc_html($log->interaction_type); ?></strong>
                                        <small class="text-muted"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></small>
                                    </div>
                                    <p class="mb-0"><?php echo nl2br(esc_html($log->content)); ?></p>
                                    <small class="text-muted">Bởi: <?php echo esc_html(get_the_author_meta('display_name', $log->interacted_by)); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($total_logs > $logs_limit) : ?>
                            <div class="text-center mt-3">
                                <a href="<?php echo home_url('/aerp-crm-customers/' . $customer->id . '?action=logs'); ?>" class="btn btn-sm btn-outline-primary">Xem tất cả tương tác</a>
                            </div>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="alert alert-light mb-0" role="alert">
                            Không có tương tác nào được ghi nhận.
                        </div>
                    <?php endif; ?>

                    <h6 class="mt-4">Ghi nhận tương tác mới</h6>
                    <form method="post" class="mt-3">
                        <?php wp_nonce_field('aerp_add_customer_log_action', 'aerp_add_customer_log_nonce'); ?>
                        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer->id); ?>">
                        <div class="mb-3">
                            <label for="interaction_type" class="form-label">Loại tương tác</label>
                            <select class="form-select" id="interaction_type" name="interaction_type" required>
                                <option value="">-- Chọn loại tương tác --</option>
                                <option value="Cuộc gọi">Cuộc gọi</option>
                                <option value="Email">Email</option>
                                <option value="Gặp mặt">Gặp mặt</option>
                                <option value="Tin nhắn">Tin nhắn</option>
                                <option value="Khác">Khác</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="interaction_content" class="form-label">Nội dung</label>
                            <textarea class="form-control" id="interaction_content" name="content" rows="3" required></textarea>
                        </div>
                        <button type="submit" name="aerp_add_customer_log" class="btn btn-primary">Ghi nhận tương tác</button>
                    </form>
                </div>
            </div>

            <div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <a href="<?php echo home_url('/aerp-crm-customers'); ?>" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                    </a>
                    <a href="<?php echo home_url('/aerp-crm-customers?action=edit&id=' . $customer->id); ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-1"></i> Chỉnh sửa
                    </a>
                </div>
                <div class="text-muted small text-end">
                    <i class="far fa-clock me-1"></i> Ngày tạo: <?php echo esc_html($customer->created_at); ?>
                </div>
            </div>

        <?php else : ?>
            <div class="alert alert-warning" role="alert">
                Không tìm thấy thông tin khách hàng.
            </div>
            <a href="<?php echo home_url('/aerp-crm-customers'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
            </a>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Chi tiết khách hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
