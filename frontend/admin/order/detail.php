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
// Lấy danh sách thiết bị nhận nếu có
global $wpdb;
$device_list = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_order_devices WHERE order_id = %d", $order_id));
$is_device_order = !empty($device_list);

// Xác định tab active theo order_type
$active_tab = 'content';
if (!empty($order->order_type)) {
    switch ($order->order_type) {
        case 'device':
            $active_tab = 'devices';
            break;
        case 'return':
            $active_tab = 'device-returns';
            break;
        case 'product':
        case 'service':
        case 'mixed':
            $active_tab = 'products';
            break;
        default:
            $active_tab = 'content';
            break;
    }
}

$content_lines = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}aerp_order_content_lines WHERE order_id = %d ORDER BY sort_order ASC",
    $order_id
));
$table = new AERP_Frontend_Order_Status_Log_Table($order_id);
$table->set_filters(['order_id' => $order_id]);
$table->process_bulk_action();
ob_start();
?>
<style>
    .aerp-tabs-container .nav-link {
        display: inline-block;
        padding: 10px 18px;
        color: black;
        background: none;
        border-color: #dee2e6;
        font-weight: 400;
        text-decoration: none;
        cursor: pointer;
        transition: color 0.2s, border-color 0.2s;
        min-width: fit-content;
    }

    .aerp-tabs-container .nav-link.active {
        /* border-bottom: 0 !important; */
        border-bottom: 2px solid #0073aa !important;
        /* border-bottom: 2px solid #0073aa; */
        /* background: #dee2e6 !important; */
        /* color: white !important; */
    }

    .aerp-tabs-container .nav-link:not(.active):hover {
        background: #34495e !important;
        color: white !important;
    }

    .aerp-tabs-container .nav-tabs {
        display: flex;
        gap: 4px;
        border-bottom: 0 !important;
        overflow-y: scroll;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Chi tiết đơn hàng #<?php echo esc_html($order->order_code); ?></h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Quản lý đơn hàng', 'url' => home_url('/aerp-order-orders')],
        ['label' => 'Chi tiết đơn hàng']
    ]);
}
?>
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
            <div class="col-md-6 mb-2">
                <label class="fw-bold form-label text-muted small mb-1">Chi phí</label>
                <p class="mb-0"><?php echo number_format($order->cost ?? 0, 0, ',', '.'); ?> đ</p>
            </div>
            <div class="col-md-6 mb-2">
                <label class="fw-bold form-label text-muted small mb-1">Lợi nhuận</label>
                <?php
                $profit = ($order->total_amount ?? 0) - ($order->cost ?? 0);
                $profit_color = $profit >= 0 ? 'text-success' : 'text-danger';
                ?>
                <p class="mb-0 fw-bold <?php echo $profit_color; ?>"><?php echo number_format($profit, 0, ',', '.'); ?> đ</p>
            </div>
            <div class="col-md-6 mb-2">
                <label class="fw-bold form-label text-muted small mb-1">Nguồn khách hàng</label>
                <p class="mb-0">
                    <?php
                    $source = $order->customer_source ?? '';
                    $source_map = [
                        'fb' => 'Facebook',
                        'zalo' => 'Zalo',
                        'tiktok' => 'Tiktok',
                        'youtube' => 'Youtube',
                        'web' => 'Website',
                        'referral' => 'KH cũ giới thiệu',
                        'other' => 'Khác'
                    ];
                    echo $source_map[$source] ?? ($source ? esc_html($source) : '<span class="text-muted">--</span>');
                    ?>
                </p>
            </div>

            <div class="col-md-12 mb-2">
                <label class="fw-bold form-label text-muted small mb-1">Ghi chú</label>
                <p class="mb-0"><?php echo $order->note ? esc_html($order->note) : '<span class="text-muted">--</span>'; ?></p>
            </div>
        </div>
    </div>
    <!-- Tabs for sections -->
    <div class="card aerp-tabs-container">
        <div class="card-header pb-0">
            <div class="nav nav-tabs" role="tablist">
                <a class="nav-link <?php echo $active_tab === 'content' ? 'active' : ''; ?>" id="tab-content-link" data-bs-toggle="tab" href="#tab-content" role="tab" aria-controls="tab-content" aria-selected="<?php echo $active_tab === 'content' ? 'true' : 'false'; ?>">Nội dung yêu cầu/triển khai</a>
                <a class="nav-link <?php echo (in_array($active_tab, ['products', 'service'])) ? 'active' : ''; ?>" id="tab-products-link" data-bs-toggle="tab" href="#tab-products" role="tab" aria-controls="tab-products" aria-selected="<?php echo (in_array($active_tab, ['products', 'service'])) ? 'true' : 'false'; ?>">Sản phẩm/Dịch vụ trong đơn</a>
                <a class="nav-link <?php echo $active_tab === 'devices' ? 'active' : ''; ?>" id="tab-devices-link" data-bs-toggle="tab" href="#tab-devices" role="tab" aria-controls="tab-devices" aria-selected="<?php echo $active_tab === 'devices' ? 'true' : 'false'; ?>">Thiết bị nhận từ khách</a>
                <a class="nav-link <?php echo $active_tab === 'device-returns' ? 'active' : ''; ?>" id="tab-device-returns-link" data-bs-toggle="tab" href="#tab-device-returns" role="tab" aria-controls="tab-device-returns" aria-selected="<?php echo $active_tab === 'device-returns' ? 'true' : 'false'; ?>">Thiết bị đã trả</a>
            </div>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade <?php echo $active_tab === 'products' ? 'show active' : ''; ?>" id="tab-products" role="tabpanel" aria-labelledby="tab-products-link">
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
                    </div>
                    <div class="d-flex justify-content-start align-items-center mt-3 gap-2">
                        <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại danh sách
                        </a>
                        <a href="<?php echo home_url('/aerp-order-orders?action=edit&id=' . $order_id); ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> Chỉnh sửa
                        </a>
                        <a href="#" class="btn btn-success" id="print-invoice-detail-btn"><i class="fas fa-print me-1"></i> In hóa đơn chi tiết</a>
                    </div>
                </div>
                <div class="tab-pane fade <?php echo $active_tab === 'devices' ? 'show active' : ''; ?>" id="tab-devices" role="tabpanel" aria-labelledby="tab-devices-link">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tên thiết bị</th>
                                    <th>Serial/IMEI</th>
                                    <th>Tình trạng</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($device_list)): foreach ($device_list as $idx => $device): ?>
                                        <tr>
                                            <td><?php echo $idx + 1; ?></td>
                                            <td><?php echo esc_html($device->device_name); ?></td>
                                            <td><?php echo esc_html($device->serial_number); ?></td>
                                            <td><?php echo esc_html($device->status); ?></td>
                                            <td><?php echo esc_html($device->note); ?></td>
                                        </tr>
                                    <?php endforeach;
                                else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Chưa có thiết bị nào.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-start align-items-center mt-3 gap-2">
                        <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại danh sách
                        </a>
                        <a href="<?php echo home_url('/aerp-order-orders?action=edit&id=' . $order_id); ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> Chỉnh sửa
                        </a>
                        <a href="#" class="btn btn-success" id="print-invoice-device-btn"><i class="fas fa-print me-1"></i> In hóa đơn thiết bị</a>
                    </div>
                </div>
                <div class="tab-pane fade <?php echo $active_tab === 'device-returns' ? 'show active' : ''; ?>" id="tab-device-returns" role="tabpanel" aria-labelledby="tab-device-returns-link">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tên thiết bị</th>
                                    <th>Serial/IMEI</th>
                                    <th>Ngày trả</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $device_returns = $wpdb->get_results($wpdb->prepare(
                                    "SELECT r.*, d.device_name, d.serial_number FROM {$wpdb->prefix}aerp_order_device_returns r 
                                     LEFT JOIN {$wpdb->prefix}aerp_order_devices d ON d.id = r.device_id WHERE r.order_id = %d ORDER BY r.id ASC",
                                    $order_id
                                ));
                                if (!empty($device_returns)) : foreach ($device_returns as $idx => $ret) : ?>
                                        <tr>
                                            <td><?php echo $idx + 1; ?></td>
                                            <td><?php echo esc_html($ret->device_name ?? ''); ?></td>
                                            <td><?php echo esc_html($ret->serial_number ?? ''); ?></td>
                                            <td><?php echo esc_html($ret->return_date ?? ''); ?></td>
                                            <td><?php echo esc_html($ret->note ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach;
                                else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Chưa có thiết bị trả.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-start align-items-center mt-3 gap-2">
                        <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại danh sách
                        </a>
                        <a href="<?php echo home_url('/aerp-order-orders?action=edit&id=' . $order_id); ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> Chỉnh sửa
                        </a>
                        <a href="#" class="btn btn-success" id="print-invoice-device-return-btn"><i class="fas fa-print me-1"></i> In hóa đơn thiết bị trả</a>
                    </div>
                </div>
                <div class="tab-pane fade <?php echo $active_tab === 'content' ? 'show active' : ''; ?>" id="tab-content" role="tabpanel" aria-labelledby="tab-content-link">
                    <div class="table-responsive">
                        <?php
                        $total_content_amount = 0;
                        ?>
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nội dung yêu cầu </th>
                                    <th>Nội dung triển khai</th>
                                    <th>Đơn giá</th>
                                    <th>Số lượng</th>
                                    <th>Thành tiền</th>
                                    <th>Bảo hành</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($content_lines)): foreach ($content_lines as $idx => $line):
                                        $line_total = floatval($line->total_price ?? 0);
                                        $total_content_amount += $line_total; ?>

                                        <tr>
                                            <td><?php echo $idx + 1; ?></td>
                                            <td><?php echo esc_html($line->requirement) ?? '--'; ?></td>
                                            <td><?php echo esc_html($line->implementation) ?? '--'; ?></td>
                                            <td><?php echo number_format($line->unit_price ?? 0, 0, ',', '.'); ?></td>
                                            <td><?php echo esc_html($line->quantity) ?? '--'; ?></td>
                                            <td><?php echo number_format($line->total_price ?? 0, 0, ',', '.'); ?></td>
                                            <td><?php echo esc_html($line->warranty) ?? '--'; ?></td>
                                        </tr>
                                    <?php endforeach;
                                else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Chưa có nội dung nào.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-end">Tổng tiền</th>
                                    <th colspan="2"><?php echo number_format($total_content_amount ?? 0, 0, ',', '.'); ?></th>
                                </tr>
                                <tr>
                                    <th colspan="1">Ghi chú đơn hàng</th>
                                    <td colspan="6"><?php echo esc_html($order->note) ?? '--'; ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <!-- <div class="row mb-2">
                        <div class="col-md-12 mb-3">
                            <label class="fw-bold form-label text-muted small mb-1">Nội dung yêu cầu và triển khai</label>
                            <?php
                            $content_lines = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}aerp_order_content_lines WHERE order_id = %d ORDER BY sort_order ASC",
                                $order_id
                            ));

                            if (!empty($content_lines)) {
                                foreach ($content_lines as $idx => $line) {
                                    echo '<div class="row mb-3">';
                                    echo '<div class="col-md-6">';
                                    echo '<div class="p-3 border rounded bg-light" style="white-space:pre-line;">';
                                    echo '<strong>Nội dung ' . ($idx + 1) . ' - Yêu cầu:</strong><br>';
                                    echo nl2br(esc_html($line->requirement ?? '--'));
                                    echo '</div>';
                                    echo '</div>';
                                    echo '<div class="col-md-6">';
                                    echo '<div class="p-3 border rounded bg-light" style="white-space:pre-line;">';
                                    echo '<strong>Nội dung ' . ($idx + 1) . ' - Triển khai:</strong><br>';
                                    echo nl2br(esc_html($line->implementation ?? '--'));
                                    echo '</div>';
                                    echo '</div>';
                                    echo '</div>';

                                    // Hiển thị thông tin chi tiết (đơn giá, số lượng, thành tiền, bảo hành)
                                    echo '<div class="row mb-3">';
                                    echo '<div class="col-md-3">';
                                    echo '<div class="p-2 border rounded bg-white">';
                                    echo '<strong class="text-muted small">Đơn giá:</strong><br>';
                                    echo '<span class="fw-bold">' . number_format($line->unit_price ?? 0, 0, ',', '.') . ' đ</span>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '<div class="col-md-3">';
                                    echo '<div class="p-2 border rounded bg-white">';
                                    echo '<strong class="text-muted small">Số lượng:</strong><br>';
                                    echo '<span class="fw-bold">' . esc_html($line->quantity ?? 1) . '</span>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '<div class="col-md-3">';
                                    echo '<div class="p-2 border rounded bg-white">';
                                    echo '<strong class="text-muted small">Thành tiền:</strong><br>';
                                    echo '<span class="fw-bold">' . number_format($line->total_price ?? 0, 0, ',', '.') . ' đ</span>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '<div class="col-md-3">';
                                    echo '<div class="p-2 border rounded bg-white">';
                                    echo '<strong class="text-muted small">Bảo hành:</strong><br>';
                                    echo '<span class="fw-bold">' . esc_html($line->warranty ?? '--') . '</span>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="p-3 border rounded bg-light" style="white-space:pre-line;">';
                                echo '<span class="text-muted">--</span>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div> -->
                    <div class="d-flex justify-content-start align-items-center mt-3 gap-2">
                        <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại danh sách
                        </a>
                        <a href="<?php echo home_url('/aerp-order-orders?action=edit&id=' . $order_id); ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> Chỉnh sửa
                        </a>
                        <a href="#" class="btn btn-success" id="print-invoice-content-btn"><i class="fas fa-print me-1"></i> In hóa đơn nội dung</a>
                    </div>
                </div>
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
                    <select name="old_status_id" class="form-select shadow-sm">
                        <?php
                        $statuses = aerp_get_order_statuses();
                        aerp_safe_select_options($statuses, '', 'id', 'name', true);
                        ?>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Trạng thái mới</label>
                    <select name="new_status_id" class="form-select shadow-sm">
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
    <style>
        @media print {
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                color: #000;
            }
        }

        .sheet {
            width: 100%;
        }

        .inv-header {
            text-align: center;
            margin-bottom: 8px;
        }

        .inv-meta {
            margin-bottom: 8px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 16px;
        }

        .inv-meta div {
            font-size: 12px;
        }

        table.inv {
            width: 100%;
            border-collapse: collapse;
        }

        .inv th,
        .inv td {
            border: 1px solid #000;
            padding: 6px;
        }

        .inv thead th {
            background: #f1f1f1;
            text-align: center;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .sign {
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
        }

        .note {
            margin-top: 6px;
            font-size: 11px;
            font-style: italic;
        }
    </style>
    <div class="sheet">
        <h3 class="inv-header">HÓA ĐƠN BÁN HÀNG</h3>
        <div class="inv-meta">
            <div><strong>Mã đơn hàng:</strong> <?php echo esc_html($order->order_code); ?></div>
            <div><strong>Ngày lập:</strong> <?php echo esc_html($order->order_date); ?></div>
            <div><strong>Khách hàng:</strong> <?php echo $customer ? esc_html($customer->full_name) : '--'; ?></div>
            <div><strong>Nhân viên phụ trách:</strong> <?php echo $employee ? esc_html($employee) : '--'; ?></div>
        </div>
        <?php
        $total_amount = 0;
        $total_amount_with_vat = 0;
        ?>
        <table class="inv">
            <thead>
                <tr>
                    <th style="width:32px;">#</th>
                    <th>Tên sản phẩm/dịch vụ</th>
                    <th style="width:70px;">Số lượng</th>
                    <th style="width:70px;">Đơn vị</th>
                    <th style="width:100px;">Đơn giá</th>
                    <th style="width:70px;">VAT %</th>
                    <th style="width:130px;">Thành tiền (có VAT)</th>
                    <th style="width:120px;">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($order_items)) : ?>
                    <?php foreach ($order_items as $idx => $item) :
                        $line_total = $item->quantity * $item->unit_price;
                        $vat_percent = isset($item->vat_percent) ? floatval($item->vat_percent) : 0;
                        $vat_amount = $vat_percent > 0 ? $line_total * $vat_percent / 100 : 0;
                        $line_total_with_vat = $line_total + $vat_amount;
                        $total_amount += $line_total;
                        $total_amount_with_vat += $line_total_with_vat;

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
                            <td class="text-center"><?php echo $idx + 1; ?></td>
                            <td><?php echo esc_html($item->product_name); ?></td>
                            <td class="text-center"><?php echo esc_html($item->quantity); ?></td>
                            <td class="text-center"><?php echo esc_html($unit_name); ?></td>
                            <td class="text-end"><?php echo number_format($item->unit_price, 0, ',', '.'); ?></td>
                            <td class="text-center"><?php echo $vat_percent > 0 ? esc_html($vat_percent) : '--'; ?></td>
                            <td class="text-end"><?php echo number_format($line_total_with_vat, 0, ',', '.'); ?></td>
                            <td class="text-end"><?php echo number_format($line_total, 0, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">Chưa có sản phẩm/dịch vụ.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6" class="text-end">Tổng cộng (có VAT)</th>
                    <th class="text-end"><?php echo number_format($total_amount_with_vat, 0, ',', '.'); ?></th>
                    <th class="text-end"><?php echo number_format($total_amount, 0, ',', '.'); ?></th>
                </tr>
            </tfoot>
        </table>
        <?php if ($extra_items_count > 0): ?>
            <div class="note">Hiển thị tối đa 7 dòng. Còn <?php echo (int) $extra_items_count; ?> dòng khác không hiển thị.</div>
        <?php endif; ?>
        <div class="sign">
            <div style="text-align:center;">
                <strong>Khách hàng</strong><br><br><br>
                __________________
            </div>
            <div style="text-align:center;">
                <strong>Người lập hóa đơn</strong><br><br><br>
                __________________
            </div>
        </div>
    </div>
</div>
<!-- Template hóa đơn nội dung yêu cầu/triển khai -->
<div id="aerp-invoice-content-print-area" style="display:none; font-family: Arial, sans-serif;">
    <style>
        @media print {
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                color: #000;
            }
        }

        .sheet {
            width: 100%;
        }

        .inv-header {
            text-align: center;
            margin-bottom: 8px;
        }

        .inv-meta {
            margin-bottom: 8px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 16px;
        }

        table.inv {
            width: 100%;
            border-collapse: collapse;
        }

        .inv th,
        .inv td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
        }

        .inv thead th {
            background: #f1f1f1;
            text-align: center;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .sign {
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
        }

        .note {
            margin-top: 6px;
            font-size: 11px;
            font-style: italic;
        }
    </style>
    <div class="sheet">
        <h3 class="inv-header">BẢNG BÁO GIÁ NỘI DUNG TRIỂN KHAI</h3>
        <div class="inv-meta">
            <div><strong>Mã đơn hàng:</strong> <?php echo esc_html($order->order_code); ?></div>
            <div><strong>Ngày lập:</strong> <?php echo esc_html($order->order_date); ?></div>
            <div><strong>Khách hàng:</strong> <?php echo $customer ? esc_html($customer->full_name) : '--'; ?></div>
            <div><strong>Nhân viên phụ trách:</strong> <?php echo $employee ? esc_html($employee) : '--'; ?></div>
        </div>
        <?php
        $total_content_amount = 0;
        ?>
        <table class="inv">
            <thead>
                <tr>
                    <th style="width:32px;">#</th>
                    <th>Nội dung yêu cầu</th>
                    <th>Nội dung triển khai</th>
                    <th style="width:30px;">SL</th>
                    <th style="width:100px;">Đơn giá</th>
                    <th style="width:100px;">Thành tiền</th>
                    <th style="width:60px;">Bảo hành</th>

                </tr>
            </thead>
            <tbody>
                <?php if (!empty($content_lines)) : ?>
                    <?php foreach ($content_lines as $idx => $line) :
                        $line_total = floatval($line->total_price ?? 0);
                        $total_content_amount += $line_total;
                    ?>
                        <tr>
                            <td class="text-center"><?php echo $idx + 1; ?></td>
                            <td><?php echo nl2br(esc_html($line->requirement ?? '--')); ?></td>
                            <td><?php echo nl2br(esc_html($line->implementation ?? '--')); ?></td>
                            <td class="text-center"><?php echo esc_html($line->quantity ?? 1); ?></td>
                            <td class="text-end"><?php echo number_format($line->unit_price ?? 0, 0, ',', '.'); ?></td>
                            <td class="text-end"><?php echo number_format($line_total, 0, ',', '.'); ?></td>
                            <td><?php echo nl2br(esc_html($line->warranty ?? '--')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">Chưa có nội dung triển khai.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="text-end">Tổng tiền</th>
                    <th colspan="2"><?php echo number_format($total_content_amount, 0, ',', '.'); ?> VNĐ</th>
                </tr>
                <tr>
                    <th colspan="2">Ghi chú đơn hàng</th>
                    <td colspan="5"><?php echo esc_html($order->note) ?? '--'; ?></td>
                </tr>
            </tfoot>
        </table>
        <div class="sign">
            <div style="text-align:center;">
                <strong>Khách hàng</strong><br><br><br>
                __________________
            </div>
            <div style="text-align:center;">
                <strong>Người lập chứng từ</strong><br><br><br>
                __________________
            </div>
        </div>
    </div>
</div>
<div id="aerp-invoice-print-area-device" style="display:none; font-family: Arial, sans-serif;">
    <style>
        @media print {
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                color: #000;
            }
        }

        .sheet {
            width: 100%;
        }

        .inv-header {
            text-align: center;
            margin-bottom: 8px;
        }

        .inv-meta {
            margin-bottom: 8px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 16px;
        }

        table.inv {
            width: 100%;
            border-collapse: collapse;
        }

        .inv th,
        .inv td {
            border: 1px solid #000;
            padding: 6px;
        }

        .inv thead th {
            background: #f1f1f1;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .sign {
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
        }

        .note {
            margin-top: 6px;
            font-size: 11px;
            font-style: italic;
        }
    </style>
    <div class="sheet">
        <h3 class="inv-header">BIÊN NHẬN THIẾT BỊ</h3>
        <div class="inv-meta">
            <div><strong>Mã đơn hàng:</strong> <?php echo esc_html($order->order_code); ?></div>
            <div><strong>Ngày lập:</strong> <?php echo esc_html($order->order_date); ?></div>
            <div><strong>Khách hàng:</strong> <?php echo $customer ? esc_html($customer->full_name) : '--'; ?></div>
            <div><strong>Nhân viên phụ trách:</strong> <?php echo $employee ? esc_html($employee) : '--'; ?></div>
        </div>
        <table class="inv">
            <thead>
                <tr>
                    <th style="width:32px;">#</th>
                    <th>Tên thiết bị</th>
                    <th style="width:120px;">Serial/IMEI</th>
                    <th>Tình trạng</th>
                    <th>Ghi chú</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($device_list)) : ?>
                    <?php foreach ($device_list as $idx => $device) : ?>
                        <tr>
                            <td class="text-center"><?php echo $idx + 1; ?></td>
                            <td><?php echo esc_html($device->device_name); ?></td>
                            <td><?php echo esc_html($device->serial_number); ?></td>
                            <td><?php echo esc_html($device->status); ?></td>
                            <td><?php echo esc_html($device->note); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">Chưa có thiết bị nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="sign">
            <div style="text-align:center;">
                <strong>Khách hàng</strong><br><br><br>
                __________________
            </div>
            <div style="text-align:center;">
                <strong>Người nhận thiết bị</strong><br><br><br>
                __________________
            </div>
        </div>
    </div>
</div>
<div id="aerp-invoice-print-area-device-return" style="display:none; font-family: Arial, sans-serif;">
    <style>
        @media print {
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                color: #000;
            }
        }

        .sheet {
            width: 100%;
        }

        .inv-header {
            text-align: center;
            margin-bottom: 8px;
        }

        .inv-meta {
            margin-bottom: 8px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 16px;
        }

        table.inv {
            width: 100%;
            border-collapse: collapse;
        }

        .inv th,
        .inv td {
            border: 1px solid #000;
            padding: 6px;
        }

        .inv thead th {
            background: #f1f1f1;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .sign {
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
        }

        .note {
            margin-top: 6px;
            font-size: 11px;
            font-style: italic;
        }
    </style>
    <div class="sheet">
        <h3 class="inv-header">BIÊN NHẬN THIẾT BỊ TRẢ</h3>
        <div class="inv-meta">
            <div><strong>Mã đơn hàng:</strong> <?php echo esc_html($order->order_code); ?></div>
            <div><strong>Ngày lập:</strong> <?php echo esc_html($order->order_date); ?></div>
            <div><strong>Khách hàng:</strong> <?php echo $customer ? esc_html($customer->full_name) : '--'; ?></div>
            <div><strong>Nhân viên phụ trách:</strong> <?php echo $employee ? esc_html($employee) : '--'; ?></div>
        </div>
        <table class="inv">
            <thead>
                <tr>
                    <th style="width:32px;">#</th>
                    <th>Tên thiết bị</th>
                    <th style="width:120px;">Serial/IMEI</th>
                    <th>Ngày trả</th>
                    <th>Ghi chú</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($device_returns)) : ?>
                    <?php foreach ($device_returns as $idx => $device) : ?>
                        <tr>
                            <td class="text-center"><?php echo $idx + 1; ?></td>
                            <td><?php echo esc_html($device->device_name); ?></td>
                            <td><?php echo esc_html($device->serial_number); ?></td>
                            <td><?php echo esc_html($device->return_date); ?></td>
                            <td><?php echo esc_html($device->note); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">Chưa có thiết bị trả nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="sign">
            <div style="text-align:center;">
                <strong>Khách hàng</strong><br><br><br>
                __________________
            </div>
            <div style="text-align:center;">
                <strong>Người trả thiết bị</strong><br><br><br>
                __________________
            </div>
        </div>
    </div>
</div>
<script>
    jQuery(function($) {
        $('#print-invoice-detail-btn').on('click', function() {
            var printContents = document.getElementById('aerp-invoice-print-area').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        });

        $('#print-invoice-content-btn').on('click', function() {
            var printContents = document.getElementById('aerp-invoice-content-print-area').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        });
        $('#print-invoice-device-btn').on('click', function() {
            var printContents = document.getElementById('aerp-invoice-print-area-device').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        });

        $('#print-invoice-device-return-btn').on('click', function() {
            var printContents = document.getElementById('aerp-invoice-print-area-device-return').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        });


        // Legacy button handlers for backward compatibility
        $('#print-invoice-btn').on('click', function() {
            $('#print-invoice-detail-btn').click();
        });

        $('#print-invoice-device-btn').on('click', function() {
            $('#print-invoice-detail-btn').click();
        });
    });
</script>
<?php
$content = ob_get_clean();
$title = 'Chi tiết đơn hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
