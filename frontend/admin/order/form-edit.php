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
    aerp_user_has_permission($user_id, 'order_edit'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$edit_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
$editing = AERP_Frontend_Order_Manager::get_by_id($edit_id);
if (!$editing) wp_die(__('Order not found.'));
$order_items = function_exists('aerp_get_order_items') ? aerp_get_order_items($edit_id) : [];
// Lấy danh sách thiết bị
global $wpdb;
$device_list = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_order_devices WHERE order_id = %d", $edit_id));
// Lấy order_type từ DB nếu có, fallback về heuristic cũ để tương thích ngược
$order_type = !empty($editing->order_type)
    ? $editing->order_type
    : (!empty($device_list) ? 'device' : 'product');

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

    .nav-tabs .nav-link {
        color: black !important;
        border-color: #dee2e6;
        font-weight: 400;
    }

    .nav-tabs .nav-link:not(.active):hover {
        color: white !important;
    }
</style>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Cập nhật đơn hàng</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Quản lý đơn hàng', 'url' => home_url('/aerp-order-orders')],
        ['label' => 'Cập nhật đơn hàng']
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form class="aerp-order-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('aerp_save_order_action', 'aerp_save_order_nonce'); ?>
            <input type="hidden" name="order_id" value="<?php echo esc_attr($edit_id); ?>">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="customer_id" class="form-label">Khách hàng</label>
                    <select class="form-select customer-select" id="customer_id" name="customer_id">
                        <?php
                        $selected_id = $editing->customer_id;
                        $selected_name = '';
                        if (function_exists('aerp_get_customer')) {
                            $c = aerp_get_customer($selected_id);
                            if ($c) $selected_name = $c->full_name . (!empty($c->code) ? ' (' . $c->code . ')' : '');
                        }
                        if ($selected_name) {
                            echo '<option value="' . esc_attr($selected_id) . '" selected>' . esc_html($selected_name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="employee_id" class="form-label">Nhân viên phụ trách</label>
                    <select class="form-select employee-select" id="employee_id" name="employee_id">
                        <option value="">-- Chọn nhân viên --</option>
                        <?php
                        $selected_id = $editing->employee_id;
                        $selected_name = '';
                        if (function_exists('aerp_get_employees_with_location')) {
                            $employees = aerp_get_employees_with_location();
                            foreach ($employees as $e) {
                                if ($e->id == $selected_id) {
                                    $selected_name = $e->full_name . (!empty($e->work_location_name) ? ' - ' . $e->work_location_name : '');
                                    break;
                                }
                            }
                        }
                        if ($selected_name) {
                            echo '<option value="' . esc_attr($selected_id) . '" selected>' . esc_html($selected_name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="order_date" class="form-label">Ngày tạo đơn hàng</label>
                    <input type="date" class="form-control bg-body" id="order_date" name="order_date" value="<?php echo esc_attr($editing->order_date); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="status_id" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status_id" name="status_id">
                        <?php
                        $statuses = aerp_get_order_statuses();
                        aerp_safe_select_options($statuses, $editing->status_id, 'id', 'name', true);
                        ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="cost" class="form-label">Chi phí đơn hàng</label>
                    <input type="number" class="form-control" id="cost" name="cost" min="0" step="0.01" value="<?php echo esc_attr($editing->cost ?? 0); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="customer_source_id" class="form-label">Nguồn khách hàng</label>
                    <select class="form-select" id="customer_source_id" name="customer_source_id">
                        <option value="">-- Chọn nguồn --</option>
                        <?php
                        $customer_sources = function_exists('aerp_get_customer_sources') ? aerp_get_customer_sources() : [];
                        if ($customer_sources) {
                            foreach ($customer_sources as $source) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($source->id),
                                    selected($editing->customer_source_id ?? '', $source->id, false),
                                    esc_html($source->name)
                                );
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Nội dung yêu cầu và triển khai</label>
                    <div id="content-container">
                        <?php
                        // Lấy nội dung hiện có
                        $content_lines = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}aerp_order_content_lines WHERE order_id = %d ORDER BY sort_order ASC",
                            $edit_id
                        ));

                        if (!empty($content_lines)) {
                            foreach ($content_lines as $idx => $line) {
                                echo '<div class="row mb-2 content-row">';
                                echo '<div class="col-md-12 mb-2">';
                                echo '<select class="form-select implementation-template-select" name="content_lines[' . $idx . '][template_id]" style="width:100%">';
                                echo '<option value="">-- Chọn template nội dung triển khai --</option>';
                                echo '</select>';
                                echo '</div>';
                                echo '<div class="col-md-6 mb-2">';
                                echo '<label class="form-label">Nội dung yêu cầu</label>';
                                echo '<textarea class="form-control" name="content_lines[' . $idx . '][requirement]" rows="2" placeholder="Mô tả yêu cầu của khách hàng...">' . esc_textarea($line->requirement ?? '') . '</textarea>';
                                echo '</div>';
                                echo '<div class="col-md-6 mb-2">';
                                echo '<label class="form-label">Nội dung triển khai</label>';
                                echo '<textarea class="form-control" name="content_lines[' . $idx . '][implementation]" rows="2" placeholder="Nội dung triển khai chi tiết...">' . esc_textarea($line->implementation ?? '') . '</textarea>';
                                echo '</div>';
                                echo '<div class="col-md-3">';
                                echo '<label class="form-label">Đơn giá</label>';
                                echo '<input type="number" class="form-control content-unit-price" name="content_lines[' . $idx . '][unit_price]" placeholder="0" min="0" step="0.01" value="' . esc_attr($line->unit_price ?? 0) . '">';
                                echo '</div>';
                                echo '<div class="col-md-3">';
                                echo '<label class="form-label">Số lượng</label>';
                                echo '<input type="number" class="form-control content-quantity" name="content_lines[' . $idx . '][quantity]" placeholder="1" min="0" step="0.01" value="' . esc_attr($line->quantity ?? 1) . '">';
                                echo '</div>';
                                echo '<div class="col-md-3">';
                                echo '<label class="form-label">Thành tiền</label>';
                                echo '<input type="text" class="form-control content-total-price" name="content_lines[' . $idx . '][total_price]" placeholder="0" readonly value="' . number_format($line->total_price ?? 0, 0, ',', '.') . '">';
                                echo '</div>';
                                echo '<div class="col-md-3">';
                                echo '<label class="form-label">Bảo hành</label>';
                                echo '<input type="text" class="form-control" name="content_lines[' . $idx . '][warranty]" placeholder="VD: 12 tháng" value="' . esc_attr($line->warranty ?? '') . '">';
                                echo '</div>';
                                echo '<div class="col-md-12 mt-2">';
                                echo '<button type="button" class="btn btn-outline-danger remove-content">Xóa dòng</button>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            // Dòng mặc định nếu chưa có
                            echo '<div class="row mb-2 content-row">';
                            echo '<div class="col-md-12 mb-2">';
                            echo '<select class="form-select implementation-template-select" name="content_lines[0][template_id]" style="width:100%">';
                            echo '<option value="">-- Chọn template nội dung triển khai --</option>';
                            echo '</select>';
                            echo '</div>';
                            echo '<div class="col-md-6 mb-2">';
                            echo '<label class="form-label">Nội dung yêu cầu</label>';
                            echo '<textarea class="form-control" name="content_lines[0][requirement]" rows="2" placeholder="Mô tả yêu cầu của khách hàng..."></textarea>';
                            echo '</div>';
                            echo '<div class="col-md-6 mb-2">';
                            echo '<label class="form-label">Nội dung triển khai</label>';
                            echo '<textarea class="form-control" name="content_lines[0][implementation]" rows="2" placeholder="Nội dung triển khai chi tiết..."></textarea>';
                            echo '</div>';
                            echo '<div class="col-md-3">';
                            echo '<label class="form-label">Đơn giá</label>';
                            echo '<input type="number" class="form-control content-unit-price" name="content_lines[0][unit_price]" placeholder="0" min="0" step="0.01" value="0">';
                            echo '</div>';
                            echo '<div class="col-md-3">';
                            echo '<label class="form-label">Số lượng</label>';
                            echo '<input type="number" class="form-control content-quantity" name="content_lines[0][quantity]" placeholder="1" min="0" step="0.01" value="1">';
                            echo '</div>';
                            echo '<div class="col-md-3">';
                            echo '<label class="form-label">Thành tiền</label>';
                            echo '<input type="text" class="form-control content-total-price" name="content_lines[0][total_price]" placeholder="0" readonly value="0">';
                            echo '</div>';
                            echo '<div class="col-md-3">';
                            echo '<label class="form-label">Bảo hành</label>';
                            echo '<input type="text" class="form-control" name="content_lines[0][warranty]" placeholder="VD: 12 tháng">';
                            echo '</div>';
                            echo '<div class="col-md-12 mt-2">';
                            echo '<button type="button" class="btn btn-outline-danger remove-content">Xóa dòng</button>';
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-secondary" id="add-content">Thêm dòng nội dung</button>
                        <small class="form-text text-muted">(Mỗi dòng có thể chọn template riêng và chỉnh sửa nội dung theo yêu cầu cụ thể)</small>
                    </div>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Loại đơn</label>
                    <input type="hidden" id="order_type" name="order_type" value="<?= esc_attr($order_type); ?>">
                    <ul class="nav nav-tabs gap-1" id="order-type-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link<?= $order_type === 'product' ? ' active' : '' ?>" data-type="product" role="tab">Bán hàng/ Dịch vụ</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link<?= $order_type === 'device' ? ' active' : '' ?>" data-type="device" role="tab">Nhận thiết bị</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link <?= $order_type === 'return' ? ' active' : '' ?>" data-type="return" role="tab">Trả thiết bị</button>
                        </li>
                    </ul>
                </div>
                <div class="col-12 mb-3">
                    <div id="order-items-container">
                        <!-- <label class="form-label">Sản phẩm trong đơn</label> -->
                        <?php
                        if (!empty($order_items)) {
                            foreach ($order_items as $idx => $item) {
                                echo '<div class="row order-item-row">';
                                echo '<input type="hidden" name="order_items[' . $idx . '][id]" value="' . esc_attr($item->id) . '">';
                                // Select loại sản phẩm/dịch vụ
                                $item_type = isset($item->item_type) ? $item->item_type : ((empty($item->product_id)) ? 'service' : 'product');
                                echo '<div class="col-md-2 mb-2">';
                                echo '<label class="form-label">Loại</label>';
                                echo '<select class="form-select item-type-select" name="order_items[' . $idx . '][item_type]">';
                                echo '<option value="product"' . selected($item_type, 'product', false) . '>Sản phẩm</option>';
                                echo '<option value="service"' . selected($item_type, 'service', false) . '>Dịch vụ</option>';
                                echo '</select>';
                                echo '</div>';
                                // Tên sản phẩm/dịch vụ
                                echo '<div class="col-md-2 mb-2">';
                                echo '<label class="form-label">Sản phẩm trong đơn</label>';
                                echo '<input type="text" class="form-control product-name-input" name="order_items[' . $idx . '][product_name]" value="' . esc_attr($item->product_name) . '" placeholder="Tên sản phẩm/dịch vụ"' . ($item_type == 'service' ? '' : ' style="display:none"') . '>';
                                // Select2 sản phẩm (ẩn nếu là dịch vụ)
                                echo '<select class="form-select product-select-all-warehouses" name="order_items[' . $idx . '][product_id]" style="width:100%;' . ($item_type == 'service' ? 'display:none;' : '') . '">';
                                if ($item_type == 'product' && !empty($item->product_id)) {
                                    // Hiển thị option đã chọn
                                    echo '<option value="' . esc_attr($item->product_id) . '" selected>' . esc_html($item->product_name) . '</option>';
                                }
                                echo '</select>';
                                echo '</div>';
                                // Số lượng, đơn giá, v.v. giữ nguyên
                                echo '<div class="col-md-2 mb-2 d-flex align-items-end">';
                                echo '<div class="w-100">';
                                echo '<label class="form-label">Số lượng</label>';
                                echo '<input type="number" class="form-control" name="order_items[' . $idx . '][quantity]" value="' . esc_attr($item->quantity) . '" placeholder="Số lượng" min="0" step="0.01">';
                                echo '</div>';
                                echo '<span class="unit-label ms-2">' . esc_html($item->unit_name ?? '') . '</span>';
                                echo '<input type="hidden" name="order_items[' . $idx . '][unit_name]" value="' . esc_attr($item->unit_name ?? '') . '" class="unit-name-input">';
                                echo '</div>';
                                echo '<div class="col-md-1 mb-2">';
                                echo '<label class="form-label">VAT</label>';
                                echo '<input type="number" class="form-control" name="order_items[' . $idx . '][vat_percent]" value="' . esc_attr(isset($item->vat_percent) ? $item->vat_percent : '') . '" placeholder="VAT (%)" min="0" max="100" step="0.01">';
                                echo '</div>';
                                echo '<div class="col-md-2 mb-2"><label class="form-label">Đơn giá</label><input type="number" class="form-control" name="order_items[' . $idx . '][unit_price]" value="' . esc_attr($item->unit_price) . '" placeholder="Đơn giá" min="0" step="0.01"></div>';
                                echo '<div class="col-md-2 mb-2"><label class="form-label">Thành tiền</label><input type="text" class="form-control total-price-field" value="' . number_format($item->total_price, 0, ',', '.') . '" placeholder="Thành tiền" readonly></div>';
                                echo '<div class="col-md-1 mb-2 d-flex align-items-end"><button type="button" class="btn btn-outline-danger remove-order-item">Xóa</button></div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="row order-item-row">';
                            // Select loại sản phẩm/dịch vụ mặc định product
                            echo '<div class="col-md-2 mb-2">';
                            echo '<label class="form-label">Loại</label>';
                            echo '<select class="form-select item-type-select" name="order_items[0][item_type]">';
                            echo '<option value="product" selected>Sản phẩm</option>';
                            echo '<option value="service">Dịch vụ</option>';
                            echo '</select>';
                            echo '</div>';
                            // Tên sản phẩm/dịch vụ + select2 sản phẩm
                            echo '<div class="col-md-2 mb-2">';
                            echo '<label class="form-label">Sản phẩm trong đơn</label>';
                            echo '<input type="text" class="form-control product-name-input" name="order_items[0][product_name]" placeholder="Tên sản phẩm/dịch vụ" style="display:none">';
                            echo '<select class="form-select product-select-all-warehouses" name="order_items[0][product_id]" style="width:100%"></select>';
                            echo '<input type="hidden" name="order_items[0][unit_name]" class="unit-name-input">';
                            echo '</div>';
                            // Số lượng
                            echo '<div class="col-md-2 mb-2 d-flex align-items-end">';
                            echo '<div class="w-100">';
                            echo '<label class="form-label">Số lượng</label>';
                            echo '<input type="number" class="form-control" name="order_items[0][quantity]" placeholder="Số lượng" min="0" step="0.01" value="1">';
                            echo '</div>';
                            echo '<span class="unit-label ms-2"></span>';
                            echo '</div>';
                            // VAT
                            echo '<div class="col-md-1 mb-2">';
                            echo '<label class="form-label">VAT</label>';
                            echo '<input type="number" class="form-control" name="order_items[0][vat_percent]" placeholder="VAT (%)" min="0" max="100" step="0.01">';
                            echo '</div>';
                            // Đơn giá
                            echo '<div class="col-md-2 mb-2"><label class="form-label">Đơn giá</label><input type="number" class="form-control" name="order_items[0][unit_price]" placeholder="Đơn giá" min="0" step="0.01"></div>';
                            // Thành tiền
                            echo '<div class="col-md-2 mb-2"><label class="form-label">Thành tiền</label><input type="text" class="form-control total-price-field" placeholder="Thành tiền" readonly></div>';
                            // Xóa dòng
                            echo '<div class="col-md-1 mb-2 d-flex align-items-end"><button type="button" class="btn btn-outline-danger remove-order-item">Xóa</button></div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <button type="button" class="btn btn-secondary" id="add-order-item">Thêm sản phẩm</button>
                </div>
                <div class="col-12 mb-3" id="device-list-section" style="display:<?= $order_type === 'device' ? 'block' : 'none' ?>">
                    <div id="device-list-table">
                        <?php if (!empty($device_list)) :
                            foreach ($device_list as $idx => $device) : ?>
                                <div class="row mb-2">
                                    <div class="col-md-3">
                                        <label class="form-label">Tên thiết bị</label>
                                        <input type="text" class="form-control" name="devices[<?= $idx ?>][device_name]" value="<?= esc_attr($device->device_name) ?>" placeholder="Tên thiết bị">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Serial/IMEI</label>
                                        <input type="text" class="form-control" name="devices[<?= $idx ?>][serial_number]" value="<?= esc_attr($device->serial_number) ?>" placeholder="Serial/IMEI">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Tình trạng</label>
                                        <input type="text" class="form-control" name="devices[<?= $idx ?>][status]" value="<?= esc_attr($device->status) ?>" placeholder="Tình trạng">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Ghi chú</label>
                                        <textarea type="text" class="form-control" name="devices[<?= $idx ?>][note]" value="<?= esc_attr($device->note) ?>" placeholder="Ghi chú" rows="1"></textarea>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Đối tác sửa</label>
                                        <select class="form-select partner-select supplier-select" style="width:100%" name="devices[<?= $idx ?>][partner_id]">
                                            <option value="">-- Chọn nhà cung cấp --</option>
                                            <?php foreach (AERP_Supplier_Manager::get_all() as $s): ?>
                                                <option value="<?php echo esc_attr($s->id); ?>" <?php selected($device && $device->partner_id == $s->id); ?>>
                                                    <?php echo esc_html($s->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-1 mt-2 d-flex align-items-end">
                                        <button type="button" class="btn btn-outline-danger remove-device-row">Xóa</button>
                                    </div>
                                </div>
                            <?php endforeach;
                        else : ?>
                            <div class="row mb-2">
                                <div class="col-md-3">
                                    <label class="form-label">Tên thiết bị</label>
                                    <input type="text" class="form-control" name="devices[0][device_name]" placeholder="Tên thiết bị">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Serial/IMEI</label>
                                    <input type="text" class="form-control" name="devices[0][serial_number]" placeholder="Serial/IMEI">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Tình trạng</label>
                                    <input type="text" class="form-control" name="devices[0][status]" placeholder="Tình trạng">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Ghi chú</label>
                                    <textarea type="text" class="form-control" name="devices[0][note]" placeholder="Ghi chú" rows="1"></textarea>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Đối tác sửa</label>
                                    <select class="form-select partner-select supplier-select" style="width:100%" name="devices[0][partner_id]">
                                        <option value="">-- Chọn nhà cung cấp --</option>

                                    </select>
                                </div>
                                <div class="col-md-1 mt-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger remove-device-row">Xóa</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-secondary" id="add-device-row">Thêm thiết bị</button>
                </div>
                <div class="col-12 mb-3" id="device-return-section" style="display:none">
                    <div id="device-return-table">
                        <?php
                        // Load các dòng trả thiết bị
                        $device_returns = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}aerp_order_device_returns WHERE order_id = %d ORDER BY id ASC",
                            $edit_id
                        ));
                        if (!empty($device_returns)) {
                            foreach ($device_returns as $rIdx => $ret) {
                                echo '<div class="row mb-2 device-return-row">';
                                echo '<div class="col-md-4">';
                                echo '<label class="form-label">Thiết bị nhận</label>';
                                echo '<select class="form-select received-device-select" style="width:100%" name="device_returns[' . $rIdx . '][device_id]">';
                                // Hiển thị option đã chọn
                                $device = $wpdb->get_row($wpdb->prepare("SELECT device_name, serial_number, status FROM {$wpdb->prefix}aerp_order_devices WHERE id = %d", $ret->device_id));
                                if ($device) {
                                    $text = $device->device_name;
                                    if (!empty($device->serial_number)) {
                                        $text .= ' (' . $device->serial_number . ')';
                                    }
                                    if (!empty($device->status)) {
                                        $text .= ' - ' . $device->status;
                                    }
                                    echo '<option value="' . esc_attr($ret->device_id) . '" selected>' . esc_html($text) . '</option>';
                                }
                                echo '</select>';
                                echo '</div>';
                                echo '<div class="col-md-3">';
                                echo '<label class="form-label">Ngày trả</label>';
                                echo '<input type="date" class="form-control" name="device_returns[' . $rIdx . '][return_date]" value="' . esc_attr($ret->return_date ?? date('Y-m-d')) . '">';
                                echo '</div>';
                                echo '<div class="col-md-4">';
                                echo '<label class="form-label">Ghi chú</label>';
                                echo '<input type="text" class="form-control" name="device_returns[' . $rIdx . '][note]" value="' . esc_attr($ret->note ?? '') . '" placeholder="Ghi chú">';
                                echo '</div>';
                                echo '<div class="col-md-1 mt-2 d-flex align-items-end">';
                                echo '<button type="button" class="btn btn-outline-danger remove-device-return-row">Xóa</button>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="row mb-2 device-return-row">';
                            echo '<div class="col-md-4">';
                            echo '<label class="form-label">Thiết bị nhận</label>';
                            echo '<select class="form-select received-device-select" style="width:100%" name="device_returns[0][device_id]"></select>';
                            echo '</div>';
                            echo '<div class="col-md-3">';
                            echo '<label class="form-label">Ngày trả</label>';
                            echo '<input type="date" class="form-control" name="device_returns[0][return_date]" value="' . esc_attr(date('Y-m-d')) . '">';
                            echo '</div>';
                            echo '<div class="col-md-4">';
                            echo '<label class="form-label">Ghi chú</label>';
                            echo '<input type="text" class="form-control" name="device_returns[0][note]" placeholder="Ghi chú">';
                            echo '</div>';
                            echo '<div class="col-md-1 mt-2 d-flex align-items-end">';
                            echo '<button type="button" class="btn btn-outline-danger remove-device-return-row">Xóa</button>';
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <button type="button" class="btn btn-secondary  " id="add-device-return-row">Thêm dòng trả thiết bị</button>
                </div>
                <div class="row flex-column-reverse flex-md-row gap-md-0 gap-2">
                    <div class="col-md-6 mb-3">
                        <label for="note" class="form-label">Ghi chú</label>
                        <textarea placeholder="Nội dung ghi chú" class="form-control" id="note" name="note" rows="2"><?php echo esc_textarea($editing->note); ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3 overflow-hidden">
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
                </div>
                <?php if (!empty($editing->cancel_reason)): ?>
                    <div class="col-12 mb-3">
                        <div class="alert alert-danger">
                            <strong>Đơn hàng đã bị hủy</strong><br>
                            <strong>Lý do:</strong> <?php echo esc_html($editing->cancel_reason); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="row">
                <div class="col-md-6 d-flex align-items-end">
                    <div class="d-flex gap-2 w-100">
                        <button type="submit" name="aerp_save_order" class="btn btn-primary">Cập nhật</button>
                        <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-secondary">Quay lại</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Doanh thu dự kiến</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Thành tiền (nội dung triển khai)</label>
                                    <input type="text" class="form-control" id="content-total-amount" readonly value="0 đ">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Lợi nhuận</label>
                                    <input type="text" class="form-control" id="expected-profit" readonly value="0 đ">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    jQuery(document).ready(function($) {
        function toggleDeviceSection() {
            var type = $('#order_type').val();
            if (type === 'device') {
                $('#device-list-section').show();
                $('#device-return-section').hide();
                $('#order-items-container').hide();
                $('#add-order-item').hide();
                // Tắt input sản phẩm khi là đơn nhận thiết bị
                $('#order-items-container input, #order-items-container select').prop('disabled', true);
            } else if (type === 'return') {
                $('#device-list-section').hide();
                $('#device-return-section').show();
                $('#order-items-container').hide();
                $('#add-order-item').hide();
                // Tắt input sản phẩm khi là đơn trả thiết bị
                $('#order-items-container input, #order-items-container select').prop('disabled', true);
            } else {
                // Bán hàng/Dịch vụ
                $('#device-list-section').hide();
                $('#device-return-section').hide();
                $('#order-items-container').show();
                $('#add-order-item').show();
                // Bật lại input sản phẩm
                $('#order-items-container input, #order-items-container select').prop('disabled', false);
            }
        }
        // Tabs: đổi loại đơn
        $(document).on('click', '#order-type-tabs .nav-link', function() {
            $('#order-type-tabs .nav-link').removeClass('active');
            $(this).addClass('active');
            var type = $(this).data('type');
            $('#order_type').val(type);
            toggleDeviceSection();
        });
        toggleDeviceSection();


        // Thêm dòng thiết bị
        let deviceIndex = $('#device-list-table .row').length;
        $('#add-device-row').on('click', function() {
            let row = `<div class="row mb-2 device-row">
            <div class="col-md-3 mb-2"><label class="form-label">Tên thiết bị</label><input type="text" class="form-control" name="devices[${deviceIndex}][device_name]" placeholder="Tên thiết bị"></div>
            <div class="col-md-2 mb-2"><label class="form-label">Serial/IMEI</label><input type="text" class="form-control" name="devices[${deviceIndex}][serial_number]" placeholder="Serial/IMEI"></div>
            <div class="col-md-2 mb-2"><label class="form-label">Tình trạng</label><input type="text" class="form-control" name="devices[${deviceIndex}][status]" placeholder="Tình trạng"></div>
            <div class="col-md-2 mb-2"><label class="form-label">Ghi chú</label><input type="text" class="form-control" name="devices[${deviceIndex}][note]" placeholder="Ghi chú"></div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Đối tác sửa</label>
                <select class="form-select partner-select supplier-select" style="width:100%" name="devices[${deviceIndex}][partner_id]">
                    <option value="">-- Chọn đối tác --</option>
                </select>
            </div>
            <div class="col-md-1 mb-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger remove-device-row">Xóa</button>
            </div>
        </div>`;
            $('#device-list-table').append(row);
            deviceIndex++;

            // Khởi tạo Select2 cho supplier mới
            $('#device-list-table .supplier-select:last').select2({
                placeholder: "Chọn nhà cung cấp/ Đối tác",
                allowClear: true,
                ajax: {
                    url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                    dataType: "json",
                    delay: 250,
                    data: function(params) {
                        return {
                            action: "aerp_order_search_suppliers",
                            q: params.term,
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    cache: true,
                },
                minimumInputLength: 0,
            });
        });
        $(document).on('click', '.remove-device-row', function() {
            $(this).closest('.device-row').remove();
        });

        // Khởi tạo form theo loại đơn hàng hiện tại
        if ($('#order_type').val() === 'device') {
            // Nếu là đơn nhận thiết bị, tắt input sản phẩm ngay từ đầu
            $('#order-items-container input, #order-items-container select').prop('required', false);
            $('#order-items-container input, #order-items-container select').prop('disabled', true);
        } else if ($('#order_type').val() === 'return') {
            // Nếu là đơn trả thiết bị, tắt input sản phẩm ngay từ đầu
            $('#order-items-container input, #order-items-container select').prop('required', false);
            $('#order-items-container input, #order-items-container select').prop('disabled', true);
        } else {
            // Nếu là đơn bán hàng/dịch vụ
            $('#order-items-container input, #order-items-container select').prop('required', false);
            $('#order-items-container input, #order-items-container select').prop('disabled', false);
        }

        // Xử lý thêm/xóa dòng nội dung
        let contentIndex = $('#content-container .content-row').length;
        $('#add-content').on('click', function() {
            let row = `<div class="row mb-2 content-row">
                <div class="col-md-12 mb-2">
                    <select class="form-select implementation-template-select" name="content_lines[${contentIndex}][template_id]" style="width:100%">
                        <option value="">-- Chọn template nội dung triển khai --</option>
                    </select>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">Nội dung yêu cầu</label>
                    <textarea class="form-control" name="content_lines[${contentIndex}][requirement]" rows="2" placeholder="Mô tả yêu cầu của khách hàng..."></textarea>
                </div>
                <div class="col-md-6 mb-2">
                    <label class="form-label">Nội dung triển khai</label>
                    <textarea class="form-control" name="content_lines[${contentIndex}][implementation]" rows="2" placeholder="Nội dung triển khai chi tiết..."></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Đơn giá</label>
                    <input type="number" class="form-control content-unit-price" name="content_lines[${contentIndex}][unit_price]" placeholder="0" min="0" step="0.01" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Số lượng</label>
                    <input type="number" class="form-control content-quantity" name="content_lines[${contentIndex}][quantity]" placeholder="1" min="0.01" step="0.01" value="1">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Thành tiền</label>
                    <input type="text" class="form-control content-total-price" name="content_lines[${contentIndex}][total_price]" placeholder="0" readonly value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bảo hành</label>
                    <input type="text" class="form-control" name="content_lines[${contentIndex}][warranty]" placeholder="VD: 12 tháng">
                </div>
                <div class="col-md-12 mt-2">
                    <button type="button" class="btn btn-outline-danger remove-content">Xóa dòng</button>
                </div>
            </div>`;
            $('#content-container').append(row);

            // Khởi tạo Select2 cho dòng mới
            let $newRow = $('#content-container .content-row').last();
            $newRow.find('.implementation-template-select').select2({
                placeholder: "Chọn template nội dung triển khai",
                allowClear: true,
                ajax: {
                    url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                    dataType: "json",
                    delay: 250,
                    data: function(params) {
                        return {
                            action: "aerp_order_search_implementation_templates",
                            q: params.term,
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    cache: true,
                },
                minimumInputLength: 0,
            });

            // Khởi tạo tính toán thành tiền cho dòng mới
            let unitPrice = parseFloat($newRow.find('.content-unit-price').val()) || 0;
            let quantity = parseFloat($newRow.find('.content-quantity').val()) || 1;
            let totalPrice = unitPrice * quantity;
            // Lưu giá trị thực tế (không có định dạng) vào input
            $newRow.find('.content-total-price').val(totalPrice);

            contentIndex++;
        });

        $(document).on('click', '.remove-content', function() {
            if ($('#content-container .content-row').length > 1) {
                $(this).closest('.content-row').remove();
            }
        });

        // Xử lý template selection cho từng dòng riêng biệt
        $(document).on('select2:select', '.implementation-template-select', function(e) {
            var data = e.params.data;
            if (data.content) {
                // Chỉ áp dụng template cho dòng hiện tại
                let $currentRow = $(this).closest('.content-row');
                $currentRow.find('textarea[name*="[implementation]"]').val(data.content);
            }
        });

        // Clear template selection when content is manually edited
        $(document).on('input', 'textarea[name*="[implementation]"]', function() {
            let $currentRow = $(this).closest('.content-row');
            $currentRow.find('.implementation-template-select').val(null).trigger('change');
        });

        // Khởi tạo Select2 cho tất cả dòng hiện có
        $(document).ready(function() {
            $('#content-container .implementation-template-select').each(function() {
                $(this).select2({
                    placeholder: "Chọn template nội dung triển khai",
                    allowClear: true,
                    ajax: {
                        url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                        dataType: "json",
                        delay: 250,
                        data: function(params) {
                            return {
                                action: "aerp_order_search_implementation_templates",
                                q: params.term,
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data
                            };
                        },
                        cache: true,
                    },
                    minimumInputLength: 0,
                });
            });
        });
    });
</script>
<script>
    jQuery(document).ready(function($) {
        // Initialize implementation template select
        $('#implementation_template_select').select2({
            placeholder: "Chọn template nội dung triển khai",
            allowClear: true,
            ajax: {
                url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                dataType: "json",
                delay: 250,
                data: function(params) {
                    return {
                        action: "aerp_order_search_implementation_templates",
                        q: params.term,
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                cache: true,
            },
            minimumInputLength: 0,
        });

        // Handle template selection
        $('#implementation_template_select').on('select2:select', function(e) {
            var data = e.params.data;
            if (data.content) {
                // Áp dụng template cho tất cả dòng triển khai
                $('#content-container textarea[name*="[implementation]"]').val(data.content);
            }
        });

        // Clear template selection when content is manually edited
        $('#content-container').on('input', 'textarea[name*="[implementation]"]', function() {
            $('#implementation_template_select').val(null).trigger('change');
        });

        // Tính toán thành tiền cho nội dung
        $(document).on('input', '.content-unit-price, .content-quantity', function() {
            let $row = $(this).closest('.content-row');
            let unitPrice = parseFloat($row.find('.content-unit-price').val()) || 0;
            let quantity = parseFloat($row.find('.content-quantity').val()) || 0;
            let totalPrice = unitPrice * quantity;
            $row.find('.content-total-price').val(totalPrice.toLocaleString('vi-VN'));

            // Cập nhật tổng thành tiền nội dung và lợi nhuận dự kiến
            updateExpectedStats();
        });

        // Tính toán thành tiền cho sản phẩm/dịch vụ
        $(document).on('input', 'input[name*="[unit_price]"], input[name*="[quantity]"]', function() {
            let $row = $(this).closest('.order-item-row');
            let unitPrice = parseFloat($row.find('input[name*="[unit_price]"]').val()) || 0;
            let quantity = parseFloat($row.find('input[name*="[quantity]"]').val()) || 0;
            let vatPercent = parseFloat($row.find('input[name*="[vat_percent]"]').val()) || 0;

            let subtotal = unitPrice * quantity;
            let vatAmount = subtotal * (vatPercent / 100);
            let totalPrice = subtotal + vatAmount;

            $row.find('.total-price-field').val(totalPrice.toLocaleString('vi-VN'));

            // Cập nhật tổng thành tiền nội dung và lợi nhuận dự kiến
            updateExpectedStats();
        });

        // Cập nhật chi phí
        $(document).on('input', '#cost', function() {
            updateExpectedStats();
        });

        // Hàm cập nhật thống kê dự kiến
        function updateExpectedStats() {
            // Tính tổng thành tiền nội dung triển khai
            let contentTotal = 0;
            $('.content-total-price').each(function() {
                let value = $(this).val();
                if (value) {
                    // Chuyển đổi từ định dạng "1.000.000" về số
                    let numericValue = parseFloat(value.replace(/\./g, '')) || 0;
                    contentTotal += numericValue;
                }
            });

            // Tính tổng thành tiền sản phẩm/dịch vụ
            let productTotal = 0;
            $('.total-price-field').each(function() {
                let value = $(this).val();
                if (value) {
                    let numericValue = parseFloat(value.replace(/\./g, '')) || 0;
                    productTotal += numericValue;
                }
            });

            // Lấy chi phí
            let cost = parseFloat($('#cost').val()) || 0;

            // Tính lợi nhuận dự kiến
            let expectedProfit = contentTotal - cost - productTotal;

            // Cập nhật hiển thị
            $('#content-total-amount').val(contentTotal.toLocaleString('vi-VN') + ' đ');
            $('#expected-profit').val(expectedProfit.toLocaleString('vi-VN') + ' đ');

            // Thay đổi màu sắc cho lợi nhuận
            if (expectedProfit >= 0) {
                $('#expected-profit').removeClass('text-danger').addClass('text-success');
            } else {
                $('#expected-profit').removeClass('text-success').addClass('text-danger');
            }
        }

        // Khởi tạo thống kê khi trang load
        $(document).ready(function() {
            updateExpectedStats();

            // Khởi tạo toggle cho tất cả dòng hiện có
            $('#order-items-container .order-item-row').each(function() {
                let $row = $(this);
                let itemType = $row.find('.item-type-select').val();
                let $nameInput = $row.find('.product-name-input');
                let $select = $row.find('.product-select-all-warehouses');

                if (itemType === 'service') {
                    $nameInput.show();
                    $select.hide();
                } else {
                    $nameInput.hide();
                    $select.show();
                }
            });
        });
    });
</script>
<?php
$content = ob_get_clean();
$title = 'Cập nhật đơn hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
