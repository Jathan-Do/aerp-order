<?php
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
    aerp_user_has_permission($user_id, 'order_view'),
    aerp_user_has_permission($user_id, 'order_view_full'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$table = new AERP_Frontend_Order_Table();
$table->process_bulk_action();
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
    <h2>Quản lý đơn hàng</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0">Danh sách đơn hàng</h5>
        <div class="d-flex gap-2 flex-column flex-md-row">
            <a href="<?php echo esc_url(home_url('/aerp-order-statuses')); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Thêm mới trạng thái
            </a>
            <a href="<?php echo esc_url(home_url('/aerp-order-orders/?action=add')); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Thêm mới đơn hàng
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form id="aerp-order-filter-form" class="g-2 mb-3 aerp-table-ajax-form" data-table-wrapper="#aerp-order-table-wrapper" data-ajax-action="aerp_order_filter_orders">
            <div class="row">
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-status" class="form-label mb-1">Trạng thái</label>
                    <select id="filter-status" name="status_id" class="form-select">
                        <?php
                        $statuses = aerp_get_order_statuses();
                        aerp_safe_select_options($statuses, '', 'id', 'name', true);
                        ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-employee" class="form-label mb-1">Nhân viên</label>
                    <select id="filter-employee" name="employee_id" class="form-select employee-select">
                        <?php
                        $employees = function_exists('aerp_get_order_assigned_employees') ? aerp_get_order_assigned_employees() : [];
                        aerp_safe_select_options($employees, '', 'user_id', 'full_name', true);
                        ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-customer" class="form-label mb-1">Khách hàng</label>
                    <select id="filter-customer" name="customer_id" class="form-select customer-select">
                        <?php
                        $customers = function_exists('aerp_get_customers') ? aerp_get_customers() : [];
                        aerp_safe_select_options($customers, '', 'id', 'full_name', true);
                        ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-order-type" class="form-label mb-1">Loại đơn</label>
                    <select id="filter-order-type" name="order_type" class="form-select">
                        <option value="">Tất cả loại</option>
                        <option value="product">Bán hàng</option>
                        <option value="service">Dịch vụ</option>
                        <option value="mixed">Tổng hợp</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-customer-source" class="form-label mb-1">Nguồn khách hàng</label>
                    <select id="filter-customer-source" name="customer_source" class="form-select">
                        <option value="">Tất cả nguồn</option>
                        <option value="fb">Facebook</option>
                        <option value="zalo">Zalo</option>
                        <option value="tiktok">Tiktok</option>
                        <option value="youtube">Youtube</option>
                        <option value="web">Website</option>
                        <option value="referral">KH cũ giới thiệu</option>
                        <option value="other">Khác</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-date-from" class="form-label mb-1">Từ ngày</label>
                    <input type="date" id="filter-date-from" name="date_from" class="form-control">
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-date-to" class="form-label mb-1">Đến ngày</label>
                    <input type="date" id="filter-date-to" name="date_to" class="form-control">
                </div>
                <div class="col-12 col-md-1 d-flex align-items-end mb-2">
                    <button type="submit" class="btn btn-primary w-100">Lọc</button>
                </div>
            </div>

        </form>
        <?php $message = get_transient('aerp_order_message');
        if ($message) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
                . esc_html($message) .
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            delete_transient('aerp_order_message');
        }
        ?>
        <div id="aerp-order-table-wrapper">
            <?php $table->render(); ?>
        </div>
    </div>
</div>

<!-- Modal hủy đơn hàng -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelOrderModalLabel">Hủy đơn hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc muốn hủy đơn hàng <strong id="cancelOrderCode"></strong>?</p>
                <div class="mb-3">
                    <label for="cancelReason" class="form-label">Lý do hủy đơn <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="cancelReason" rows="3" placeholder="Nhập lý do hủy đơn..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-warning" id="confirmCancelOrder">Hủy đơn</button>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        let currentOrderId = null;

        // Xử lý click nút hủy đơn
        $(document).on('click', '.cancel-order-btn', function() {
            currentOrderId = $(this).data('order-id');
            let orderCode = $(this).data('order-code');

            $('#cancelOrderCode').text(orderCode);
            $('#cancelReason').val('');
            $('#cancelOrderModal').modal('show');
        });

        // Xử lý xác nhận hủy đơn
        $('#confirmCancelOrder').on('click', function() {
            let reason = $('#cancelReason').val().trim();

            if (!reason) {
                alert('Vui lòng nhập lý do hủy đơn.');
                return;
            }

            $.ajax({
                url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                type: 'POST',
                data: {
                    action: 'aerp_cancel_order',
                    order_id: currentOrderId,
                    reason: reason,
                    _wpnonce: '<?php echo wp_create_nonce('aerp_cancel_order_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        $('#cancelOrderModal').modal('hide');
                        location.reload(); // Reload trang để cập nhật trạng thái
                    } else {
                        alert('Lỗi: ' + response.data);
                    }
                },
                error: function() {
                    alert('Có lỗi xảy ra khi hủy đơn hàng.');
                }
            });
        });
    });
</script>

<?php
$content = ob_get_clean();
$title = 'Quản lý đơn hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
