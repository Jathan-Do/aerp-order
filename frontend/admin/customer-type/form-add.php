<?php
if (!defined('ABSPATH')) {
    exit;
}
$current_user = wp_get_current_user();
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Thêm loại khách hàng mới</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<?php $message = get_transient('aerp_customer_type_message');
if ($message) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
        . esc_html($message) .
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    delete_transient('aerp_customer_type_message');
} ?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_customer_type_action', 'aerp_save_customer_type_nonce'); ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="type_key" class="form-label">Mã loại</label>
                    <input type="text" class="form-control" id="type_key" name="type_key" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Tên loại</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="color" class="form-label">Màu sắc</label>
                    <div class="mb-2">
                        <?php
                        $bootstrap_colors = [
                            'primary' => 'Xanh dương',
                            'secondary' => 'Xám',
                            'success' => 'Xanh lá',
                            'danger' => 'Đỏ',
                            'warning' => 'Vàng',
                            'info' => 'Xanh nhạt',
                            'dark' => 'Đen',
                        ];
                        foreach ($bootstrap_colors as $key => $label) {
                            echo '<span class="badge bg-' . esc_attr($key) . ' me-2">' . esc_html($label) . '</span>';
                        }
                        ?>
                    </div>
                    <select class="form-select" id="color" name="color">
                        <?php
                        foreach ($bootstrap_colors as $key => $label) {
                            echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="description" class="form-label">Mô tả</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_customer_type" class="btn btn-primary">Thêm mới</button>
                <a href="<?php echo home_url('/aerp-crm-customer-types'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Thêm loại khách hàng mới';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
