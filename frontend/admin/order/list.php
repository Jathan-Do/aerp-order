<?php
// Get current user
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$employee = aerp_get_employee_by_user_id($user_id);
$user_fullname = $employee ? $employee->full_name : '';

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
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-5">
    <h2>Quản lý đơn hàng</h2>
    <div class="user-info text-end">
        Hi, <?php echo esc_html($user_fullname); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Đăng xuất
        </a>
    </div>
</div>
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Quản lý đơn hàng']
    ]);
}
?>
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
                    <select id="filter-status" name="status_id" class="form-select shadow-sm">
                        <?php
                        $statuses = aerp_get_order_statuses();
                        aerp_safe_select_options($statuses, '', 'id', 'name', true);
                        ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-status" class="form-label mb-1">Tình trạng</label>
                    <select id="filter-status" name="status" class="form-select shadow-sm">
                        <option value="">Tất cả loại</option>
                        <option value="new">Mới tiếp nhận</option>
                        <option value="assigned">Đã phân đơn</option>
                        <option value="rejected">Từ chối</option>
                        <option value="completed">Đã hoàn thành</option>
                        <option value="paid">Đã thu tiền</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-employee" class="form-label mb-1">Nhân viên</label>
                    <select id="filter-employee" name="employee_id" class="form-select shadow-sm <?php echo aerp_user_has_role($user_id, 'admin') ? 'employee-select-all' : 'employee-select'; ?>">
                        <?php
                        $employees = function_exists('aerp_get_order_assigned_employees') ? aerp_get_order_assigned_employees() : [];
                        aerp_safe_select_options($employees, '', 'user_id', 'full_name', true);
                        ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-customer" class="form-label mb-1">Khách hàng</label>
                    <select id="filter-customer" name="customer_id" class="form-select shadow-sm customer-select">
                        <?php
                        $customers = function_exists('aerp_get_customers') ? aerp_get_customers() : [];
                        aerp_safe_select_options($customers, '', 'id', 'full_name', true);
                        ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-order-type" class="form-label mb-1">Loại đơn</label>
                    <select id="filter-order-type" name="order_type" class="form-select shadow-sm">
                        <option value="">Tất cả loại</option>
                        <option value="content">Nội dung yêu cầu</option>
                        <option value="product">Bán hàng</option>
                        <option value="service">Dịch vụ</option>
                        <option value="mixed">Bán hàng + Dịch vụ</option>
                        <option value="device">Nhận thiết bị</option>
                        <option value="return">Trả thiết bị</option>
                        <option value="all">Tổng hợp</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-customer-source" class="form-label mb-1">Nguồn khách hàng</label>
                    <select id="filter-customer-source" name="customer_source_id" class="form-select shadow-sm">
                        <option value="">Tất cả nguồn</option>
                        <?php
                        $customer_sources = function_exists('aerp_get_customer_sources') ? aerp_get_customer_sources() : [];
                        if ($customer_sources) {
                            foreach ($customer_sources as $source) {
                                printf(
                                    '<option value="%s">%s</option>',
                                    esc_attr($source->id),
                                    esc_html($source->name)
                                );
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-date-from" class="form-label mb-1">Từ ngày</label>
                    <input type="date" id="filter-date-from" name="date_from" class="form-control shadow-sm bg-body">
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <label for="filter-date-to" class="form-label mb-1">Đến ngày</label>
                    <input type="date" id="filter-date-to" name="date_to" class="form-control shadow-sm bg-body">
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
        <form method="post" action="<?= admin_url('admin-post.php') ?>">
            <?php wp_nonce_field('aerp_export_excel', 'aerp_export_nonce'); ?>
            <input type="hidden" name="action" value="aerp_export_excel_common">
            <input type="hidden" name="callback" value="order_list_export">
            <button type="submit" name="aerp_export_excel" class="btn btn-success">📥 Xuất Excel</button>
        </form>
    </div>
</div>

<script>
    // Đảm bảo chức năng copy số điện thoại hoạt động trên trang danh sách khách hàng
    jQuery(document).on("click", ".copy-phone", function(e) {
        e.preventDefault();
        var phone = jQuery(this).data("phone");
        if (!phone) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(String(phone)).then(() => {
                var $icon = jQuery(this).find("i");
                var original = $icon.attr("data-original-class") || $icon.attr("class");
                if (!$icon.attr("data-original-class")) {
                    $icon.attr("data-original-class", original);
                }
                $icon.removeClass().addClass("fas fa-check text-success");
                setTimeout(() => {
                    $icon.removeClass().addClass($icon.attr("data-original-class"));
                }, 1200);
            });
        }
    });
</script>
<?php
$content = ob_get_clean();
$title = 'Quản lý đơn hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
