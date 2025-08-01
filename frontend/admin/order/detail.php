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
    aerp_user_has_permission($user_id, 'order_view'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$order_id = get_query_var('aerp_order_id');
$order = function_exists('aerp_get_order') ? aerp_get_order($order_id) : null;
if (!$order) wp_die('Đơn hàng không tồn tại!');
$customer = function_exists('aerp_get_customer') ? aerp_get_customer($order->customer_id) : null;
$employee = function_exists('aerp_get_customer_assigned_name') ? aerp_get_customer_assigned_name($order->employee_id) : '';
$order_items = function_exists('aerp_get_order_items') ? aerp_get_order_items($order_id) : [];
$total_amount = 0;
$order_logs = function_exists('aerp_get_order_status_logs') ? aerp_get_order_status_logs($order_id) : [];
$table = new AERP_Frontend_Order_Status_Log_Table($order_id);
$table->set_filters(['order_id' => $order_id]);
$table->process_bulk_action();
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Chi tiết đơn hàng #<?php echo esc_html($order->order_code); ?></h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<div class="card mb-4">
    <div class="card-body">
        <div class="row mb-2">
            <div class="col-md-6 mb-2">
                <label class="fw-bold form-label text-muted small mb-1">Khách hàng</label>
                <p class="mb-0"><?php echo $customer ? esc_html($customer->full_name) : '<span class="text-muted">--</span>'; ?></p>
            </div>
            <div class="col-md-6 mb-2">
                <label class="fw-bold form-label text-muted small mb-1">Nhân viên phụ trách</label>
                <p class="mb-0"><?php echo $employee ? esc_html($employee) : '<span class="text-muted">--</span>'; ?></p>
            </div>
            <div class="col-md-6 mb-2">
                <label class="fw-bold form-label text-muted small mb-1">Trạng thái</label>
                <p class="mb-0"><?php
                                global $wpdb;
                                $status = $wpdb->get_row($wpdb->prepare("SELECT name, color FROM {$wpdb->prefix}aerp_order_statuses WHERE id = %d", $order->status_id));
                                if ($status) {
                                    $color = $status->color ? 'bg-' . esc_attr($status->color) : 'bg-secondary';
                                    echo '<span class="badge ' . $color . '">' . esc_html($status->name) . '</span>';
                                } else {
                                    echo '<span class="badge bg-secondary">Không rõ</span>';
                                }
                                ?></p>
            </div>
            <div class="col-md-6 mb-2">
                <label class="fw-bold form-label text-muted small mb-1">Ngày tạo đơn hàng</label>
                <p class="mb-0"><?php echo esc_html($order->order_date); ?></p>
            </div>

            <div class="col-md-12 mb-2">
                <label class="fw-bold form-label text-muted small mb-1">Ghi chú</label>
                <p class="mb-0"><?php echo $order->note ? esc_html($order->note) : '<span class="text-muted">--</span>'; ?></p>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Sản phẩm trong đơn</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tên sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Đơn vị</th>
                        <th>Đơn giá</th>
                        <th>VAT (%)</th>
                        <th>Thành tiền (có VAT)</th>
                        <th>Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($order_items)) :
                        foreach ($order_items as $idx => $item) :
                            $line_total = $item->quantity * $item->unit_price;
                            $vat_percent = isset($item->vat_percent) ? floatval($item->vat_percent) : 0;
                            $vat_amount = $vat_percent > 0 ? $line_total * $vat_percent / 100 : 0;
                            $line_total_with_vat = $line_total + $vat_amount;
                            $total_amount += $line_total;
                            $total_amount_with_vat = ($total_amount_with_vat ?? 0) + $line_total_with_vat;
                            $unit_name = '';
                            if (!empty($item->unit_name)) {
                                $unit_name = $item->unit_name;
                            } elseif (!empty($item->product_id)) {
                                if (class_exists('AERP_Product_Manager')) {
                                    $unit_name = AERP_Product_Manager::get_unit_name($item->product_id);
                                }
                            }
                    ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td><?php echo esc_html($item->product_name); ?></td>
                                <td><?php echo esc_html($item->quantity); ?></td>
                                <td><?php echo esc_html($unit_name); ?></td>
                                <td><?php echo number_format($item->unit_price, 0, ',', '.'); ?></td>
                                <td><?php echo $vat_percent > 0 ? esc_html($vat_percent) : '--'; ?></td>
                                <td><?php echo number_format($line_total_with_vat, 0, ',', '.'); ?></td>
                                <td><?php echo number_format($line_total, 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">Chưa có sản phẩm nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="6" class="text-end">Tổng cộng (có VAT)</th>
                        <th><?php echo number_format($total_amount_with_vat ?? 0, 0, ',', '.'); ?></th>
                        <th><?php echo number_format($total_amount, 0, ',', '.'); ?></th>
                    </tr>
                </tfoot>
            </table>
            <div class="card-body d-flex justify-content-start align-items-center mt-4 gap-2">
                <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Quay lại danh sách
                </a>
                <a href="<?php echo home_url('/aerp-order-orders?action=edit&id=' . $order_id); ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i> Chỉnh sửa
                </a>
                <a href="javascript:void(0);" class="btn btn-success" id="print-invoice-btn">
                    <i class="fas fa-print me-1"></i> In hóa đơn
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($order_logs)) : ?>
    <!-- Lịch sử trạng thái đơn hàng -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Lịch sử trạng thái đơn hàng</h5>
        </div>
        <div class="card-body">
            <form id="aerp-order-status-log-filter-form" class="row g-2 mb-3 aerp-table-ajax-form"
                data-table-wrapper="#aerp-order-status-log-table-wrapper"
                data-ajax-action="aerp_order_filter_status_logs">
                <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">

                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Trạng thái cũ</label>
                    <select name="old_status_id" class="form-select">
                        <?php
                        $statuses = aerp_get_order_statuses();
                        aerp_safe_select_options($statuses, '', 'id', 'name', true);
                        ?>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Trạng thái mới</label>
                    <select name="new_status_id" class="form-select">
                        <?php
                        $statuses = aerp_get_order_statuses();
                        aerp_safe_select_options($statuses, '', 'id', 'name', true);
                        ?>
                    </select>
                </div>
                <div class="col-12 col-md-1 d-flex align-items-end mb-0">
                    <button type="submit" class="btn btn-primary w-100">Lọc</button>
                </div>
            </form>
            <?php
            $message = get_transient('aerp_order_status_log_message');
            if ($message) {
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                ' . esc_html($message) . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                delete_transient('aerp_order_status_log_message'); // Xóa transient sau khi hiển thị
            }
            ?>
            <div id="aerp-order-status-log-table-wrapper">
                <?php
                $table->render();
                ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<!-- Template hóa đơn in ẩn -->
<div id="aerp-invoice-print-area" style="display:none; font-family: Arial, sans-serif;">
    <div style="max-width:700px;margin:0 auto;padding:24px;">
        <h2 style="text-align:center;">HÓA ĐƠN BÁN HÀNG</h2>
        <div style="margin-bottom:16px;">
            <strong>Mã đơn hàng:</strong> <?php echo esc_html($order->order_code); ?><br>
            <strong>Ngày lập:</strong> <?php echo esc_html($order->order_date); ?><br>
            <strong>Khách hàng:</strong> <?php echo $customer ? esc_html($customer->full_name) : '--'; ?><br>
            <strong>Nhân viên phụ trách:</strong> <?php echo $employee ? esc_html($employee) : '--'; ?><br>
        </div>
        <table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse:collapse;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tên sản phẩm</th>
                    <th>Số lượng</th>
                    <th>Đơn vị</th>
                    <th>Đơn giá</th>
                    <th>VAT (%)</th>
                    <th>Thành tiền (có VAT)</th>
                    <th>Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($order_items)) :
                    $total_amount = 0;
                    foreach ($order_items as $idx => $item) :
                        $line_total = $item->quantity * $item->unit_price;
                        $vat_percent = isset($item->vat_percent) ? floatval($item->vat_percent) : 0;
                        $vat_amount = $vat_percent > 0 ? $line_total * $vat_percent / 100 : 0;
                        $line_total_with_vat = $line_total + $vat_amount;
                        $total_amount += $line_total;
                        // $total_amount_with_vat = ($total_amount_with_vat ?? 0) + $line_total_with_vat;

                        $unit_name = '';
                        if (!empty($item->unit_name)) {
                            $unit_name = $item->unit_name;
                        } elseif (!empty($item->product_id)) {
                            if (class_exists('AERP_Product_Manager')) {
                                $unit_name = AERP_Product_Manager::get_unit_name($item->product_id);
                            }
                        }
                ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><?php echo esc_html($item->product_name); ?></td>
                            <td><?php echo esc_html($item->quantity); ?></td>
                            <td><?php echo esc_html($unit_name); ?></td>
                            <td><?php echo number_format($item->unit_price, 0, ',', '.'); ?></td>
                            <td><?php echo $vat_percent > 0 ? esc_html($vat_percent) : '--'; ?></td>
                            <td><?php echo number_format($line_total_with_vat, 0, ',', '.'); ?></td>
                            <td><?php echo number_format($line_total, 0, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach;
                else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">Chưa có sản phẩm nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6" style="text-align:right;">Tổng cộng (có VAT)</th>
                    <th><?php echo number_format($total_amount_with_vat ?? 0, 0, ',', '.'); ?></th>
                    <th><?php echo number_format($total_amount, 0, ',', '.'); ?></th>
                </tr>
            </tfoot>
        </table>
        <div style="margin-top:32px;display:flex;justify-content:space-between;">
            <div><strong>Khách hàng</strong><br><br><br>__________________</div>
            <div><strong>Người lập hóa đơn</strong><br><br><br>__________________</div>
        </div>
    </div>
</div>

<script>
    jQuery(function($) {
        $('#print-invoice-btn').on('click', function() {
            var printContents = document.getElementById('aerp-invoice-print-area').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        });
    });
</script>
<?php
$content = ob_get_clean();
$title = 'Chi tiết đơn hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
