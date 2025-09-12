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
    aerp_user_has_permission($user_id, 'order_add'),

];
if (!in_array(true, $access_conditions, true)) {
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

    .nav-tabs .nav-link {
        color: black !important;
        border-color: #dee2e6;
        font-weight: 500;
    }

    .nav-tabs .nav-link.active {
        border-color: rgb(157, 157, 158);
        border-bottom-color: #fff;
    }

    .nav-tabs .nav-link:not(.active):hover {
        color: white !important;
    }

    /* Ẩn hoàn toàn select2 khi không cần thiết */
    .product-select-all-warehouses[style*="display: none"]+.select2-container,
    .product-select-all-warehouses[style*="display:none"]+.select2-container {
        display: none !important;
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
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Quản lý đơn hàng', 'url' => home_url('/aerp-order-orders')],
        ['label' => 'Thêm đơn hàng mới']
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form class="aerp-order-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('aerp_save_order_action', 'aerp_save_order_nonce'); ?>
            <label class="form-label fs-5">Thông tin đơn hàng</label>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="customer_id" class="form-label">Khách hàng</label>
                    <select class="form-select customer-select shadow-sm" id="customer_id" name="customer_id" required>
                        <?php
                        // Check if customer_id is passed via GET parameter (from customer detail page)
                        $customer_id_from_url = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

                        if (!empty($_POST['customer_id'])) {
                            $selected_id = intval($_POST['customer_id']);
                            $selected_name = '';
                            if (function_exists('aerp_get_customer')) {
                                $c = aerp_get_customer($selected_id);
                                if ($c) $selected_name = $c->full_name . (!empty($c->customer_code) ? ' (' . $c->customer_code . ')' : '');
                            }
                            if ($selected_name) {
                                echo '<option value="' . esc_attr($selected_id) . '" selected>' . esc_html($selected_name) . '</option>';
                            }
                        } elseif ($customer_id_from_url > 0) {
                            // Auto-select customer from URL parameter
                            $selected_name = '';
                            if (function_exists('aerp_get_customer')) {
                                $c = aerp_get_customer($customer_id_from_url);
                                if ($c) $selected_name = $c->full_name . (!empty($c->customer_code) ? ' (' . $c->customer_code . ')' : '');
                            }
                            if ($selected_name) {
                                echo '<option value="' . esc_attr($customer_id_from_url) . '" selected>' . esc_html($selected_name) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="customer_source_id" class="form-label">Nguồn khách hàng</label>
                    <select class="form-select shadow-sm" id="customer_source_id" name="customer_source_id">
                        <option value="">-- Chọn nguồn --</option>
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
                <div class="col-md-4 mb-3">
                    <label for="order_date" class="form-label">Ngày tạo đơn hàng</label>
                    <input type="date" class="form-control bg-body shadow-sm" id="order_date" name="order_date" required value="<?php echo esc_attr($date_now); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="employee_id" class="form-label">Nhân viên phụ trách</label>
                    <select class="form-select shadow-sm <?php echo aerp_user_has_role($user_id, 'admin') ? 'employee-select-all' : 'employee-select'; ?>" id="employee_id" name="employee_id">
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="cost" class="form-label">Chi phí đơn hàng</label>
                    <input type="number" class="form-control shadow-sm" id="cost" name="cost" min="0" step="0.01" value="0">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="status_id" class="form-label">Trạng thái</label>
                    <select class="form-select shadow-sm" id="status_id" name="status_id">
                        <?php
                        $statuses = aerp_get_order_statuses();
                        aerp_safe_select_options($statuses, '', 'id', 'name', true);
                        ?>
                    </select>
                </div>
                <hr />
                <div class="col-md-12 mb-3">
                    <!-- <label class="form-label fs-5">Loại đơn</label> -->
                    <input type="hidden" id="order_type" name="order_type" value="content">
                    <ul class="nav nav-tabs gap-1" id="order-type-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link active" data-type="content" role="tab">Nội dung yêu cầu & Triển khai</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link" data-type="product" role="tab">Bán hàng/ Dịch vụ</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link" data-type="device" role="tab">Nhận thiết bị</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link" data-type="return" role="tab">Trả thiết bị</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button type="button" class="nav-link" data-other-type="revenue" role="tab">Doanh thu</button>
                        </li>
                    </ul>
                </div>
                <div class="col-12 mb-3" id="content-section">
                    <!-- <label class="form-label fs-5">Nội dung yêu cầu và triển khai</label> -->
                    <div id="content-container">
                        <div class="row mb-2 content-row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Template</label>
                                <select class="form-select implementation-template-select shadow-sm" name="content_lines[0][template_id]" style="width:100%">
                                    <option value="">-- Chọn template nội dung triển khai --</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Nội dung yêu cầu</label>
                                <textarea class="form-control shadow-sm" name="content_lines[0][requirement]" rows="2" placeholder="Mô tả yêu cầu của khách hàng..."></textarea>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Nội dung triển khai</label>
                                <textarea class="form-control shadow-sm" name="content_lines[0][implementation]" rows="2" placeholder="Nội dung triển khai chi tiết..."></textarea>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Đơn giá</label>
                                <input type="number" class="form-control content-unit-price shadow-sm" name="content_lines[0][unit_price]" placeholder="0" min="0" step="0.01" value="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Số lượng</label>
                                <input type="number" class="form-control content-quantity shadow-sm" name="content_lines[0][quantity]" placeholder="1" min="0" step="0.01" value="1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Thành tiền</label>
                                <input type="text" class="form-control  content-total-price shadow-sm" name="content_lines[0][total_price]" placeholder="0" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Bảo hành</label>
                                <input type="text" class="form-control  shadow-sm" name="content_lines[0][warranty]" placeholder="VD: 12 tháng" value="1 tháng">
                            </div>
                            <div class="col-md-2 mt-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger remove-content">Xóa dòng</button>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-secondary" id="add-content">Thêm dòng nội dung</button>
                        <small class="form-text text-muted">(Mỗi dòng có thể chọn template riêng và chỉnh sửa nội dung theo yêu cầu cụ thể)</small>
                    </div>
                </div>
                <div class="col-12 mb-3" id="device-list-section" style="display:none">
                    <div id="device-list-table">
                        <div class="row mb-2 device-row">
                            <div class="col-md-3">
                                <label class="form-label">Tên thiết bị</label>
                                <input type="text" class="form-control shadow-sm" name="devices[0][device_name]" placeholder="Tên thiết bị">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Serial/IMEI</label>
                                <input type="text" class="form-control shadow-sm" name="devices[0][serial_number]" placeholder="Serial/IMEI">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tình trạng</label>
                                <textarea type="text" class="form-control shadow-sm" name="devices[0][status]" placeholder="Tình trạng" rows="1"></textarea>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Ghi chú</label>
                                <textarea type="text" class="form-control shadow-sm" name="devices[0][note]" placeholder="Ghi chú" rows="1"></textarea>
                            </div>
                            <!-- <div class="col-md-2">
                                <label class="form-label">Đối tác sửa</label>
                                <select class="form-select shadow-sm partner-select supplier-select shadow-sm" style="width:100%" name="devices[0][partner_id]">
                                    <option value="">-- Chọn đối tác --</option>
                                </select>
                            </div> -->
                            <div class="col-md-1 mt-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger remove-device-row">Xóa</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" id="add-device-row">Thêm thiết bị</button>
                </div>
                <div class="col-12 mb-3" id="device-return-section" style="display:none">
                    <div id="device-return-table">
                        <div class="row mb-2 device-return-row">
                            <div class="col-md-4">
                                <label class="form-label">Thiết bị nhận</label>
                                <select class="form-select received-device-select shadow-sm" style="width:100%" name="device_returns[0][device_id]"></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ngày trả</label>
                                <input type="date" class="form-control shadow-sm" name="device_returns[0][return_date]" value="<?php echo esc_attr(date('Y-m-d')); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ghi chú</label>
                                <textarea type="text" class="form-control shadow-sm" name="device_returns[0][note]" placeholder="Ghi chú" rows="1"></textarea>
                            </div>
                            <div class="col-md-1 mt-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger remove-device-return-row">Xóa</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" id="add-device-return-row">Thêm dòng trả thiết bị</button>
                </div>
                <div class="col-12 mb-3" id="order-items-section" style="display:none">
                    <div id="order-items-container">
                        <!-- <label class="form-label">Sản phẩm trong đơn</label> -->
                        <div class="form-check mb-2">
                            <label class="form-check-label" for="toggle-vat">VAT %</label>
                            <input class="form-check-input" type="checkbox" id="toggle-vat">
                        </div>
                        <div class="row order-item-row">
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Loại</label>
                                <select class="form-select shadow-sm item-type-select" name="order_items[0][item_type]">
                                    <option value="product">Sản phẩm</option>
                                    <option value="service">Dịch vụ</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2 purchase-type-container">
                                <label class="form-label">Nguồn mua</label>
                                <select class="form-select shadow-sm purchase-type-select" name="order_items[0][purchase_type]">
                                    <option value="warehouse">Từ kho</option>
                                    <option value="external">Mua ngoài</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Sản phẩm trong đơn</label>
                                <input type="text" class="form-control shadow-sm product-name-input" name="order_items[0][product_name]" placeholder="Tên sản phẩm/dịch vụ" style="display:none">
                                <select class="form-select shadow-sm product-select-all-warehouses" name="order_items[0][product_id]" style="width:100%"></select>
                                <input type="hidden" name="order_items[0][product_id]" class="product-id-input">
                            </div>
                            <div class="col-md-2 mb-2 d-flex align-items-end">
                                <div class="w-100">
                                    <label class="form-label">Số lượng</label>
                                    <input type="number" class="form-control shadow-sm" name="order_items[0][quantity]" placeholder="Số lượng" min="0" step="0.01">
                                </div>
                                <span class="unit-label ms-2"></span>
                                <input type="hidden" name="order_items[0][unit_name]" class="unit-name-input">
                            </div>
                            <div class="col-md-1 mb-2 vat-percent" style="display:none;">
                                <label class="form-label">VAT %</label>
                                <input type="number" class="form-control shadow-sm" name="order_items[0][vat_percent]" placeholder="VAT (%)" min="0" max="100" step="0.01" value="0">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Đơn giá</label>
                                <input type="number" class="form-control shadow-sm" name="order_items[0][unit_price]" placeholder="Đơn giá" min="0" step="0.01">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Thành tiền</label>
                                <input type="text" class="form-control shadow-sm total-price-field" placeholder="Thành tiền" readonly>
                            </div>
                            
                            <!-- External purchase fields (hidden by default) -->
                            <div class="col-md-2 mb-2 external-fields" style="display:none;">
                                <label class="form-label">Nhà cung cấp</label>
                                <input type="text" class="form-control shadow-sm external-supplier-input" name="order_items[0][external_supplier_name]" placeholder="Tên nhà cung cấp">
                            </div>
                            <div class="col-md-2 mb-2 external-fields" style="display:none;">
                                <label class="form-label">Chi phí mua ngoài</label>
                                <input type="number" class="form-control shadow-sm external-cost-input" name="order_items[0][external_cost]" placeholder="Chi phí" min="0" step="0.01">
                            </div>
                            <!-- <div class="col-md-2 mb-2 external-fields" style="display:none;">
                                <label class="form-label">Ngày giao hàng dự kiến</label>
                                <input type="date" class="form-control shadow-sm external-delivery-input" name="order_items[0][external_delivery_date]">
                            </div> -->
                            <div class="col-md-1 mb-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger remove-order-item">Xóa</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" id="add-order-item">Thêm sản phẩm</button>
                </div>
                <div class="col-12 mb-3" id="revenue-section" style="display:none">
                    <!-- <label class="form-label fs-5">Nội dung yêu cầu và triển khai</label> -->
                    <div id="revenue-container">
                        <div class="row mb-2 revenue-row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Nhân viên</label>
                                <select class="form-select shadow-sm <?php echo aerp_user_has_role($user_id, 'admin') ? 'employee-select-all' : 'employee-select'; ?>" style="width:100%" name=""></select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Hoa hồng</label>
                                <input type="number" class="form-control shadow-sm" name="" placeholder="0" min="0" step="0.01" value="0">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="note" class="form-label">Ghi chú</label>
                    <textarea class="form-control shadow-sm" id="note" name="note" rows="2" placeholder="Nội dung ghi chú"></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="attachments" class="form-label">File đính kèm</label>
                    <input type="file" class="form-control shadow-sm" id="attachments" name="attachments[]" multiple>
                </div>
            </div>
            <div class="row flex-column-reverse flex-md-row gap-md-0 gap-2">
                <div class="col-md-6 d-flex align-items-end">
                    <div class="d-flex gap-2 w-100">
                        <button type="submit" name="aerp_save_order" class="btn btn-primary">Thêm mới</button>
                        <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-secondary">Quay lại</a>
                    </div>
                </div>
                <!-- <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Doanh thu dự kiến</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Thành tiền (nội dung triển khai)</label>
                                    <input type="text" class="form-control shadow-sm" id="content-total-amount" readonly value="0 đ">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Lợi nhuận</label>
                                    <input type="text" class="form-control shadow-sm" id="expected-profit" readonly value="0 đ">
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Thêm đơn hàng mới';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
?>
<script>
    jQuery(document).ready(function($) {
        // Thêm dòng thiết bị
        let deviceIndex = 1;
        $('#add-device-row').on('click', function() {
            let row = `<div class="row mb-2 device-row">
            <div class="col-md-3 mb-2"><label class="form-label">Tên thiết bị</label><input type="text" class="form-control shadow-sm" name="devices[${deviceIndex}][device_name]" placeholder="Tên thiết bị"></div>
            <div class="col-md-2 mb-2"><label class="form-label">Serial/IMEI</label><input type="text" class="form-control shadow-sm" name="devices[${deviceIndex}][serial_number]" placeholder="Serial/IMEI"></div>
            <div class="col-md-2 mb-2"><label class="form-label">Tình trạng</label><textarea type="text" class="form-control shadow-sm" name="devices[${deviceIndex}][status]" placeholder="Tình trạng" rows="1"></textarea></div>
            <div class="col-md-2 mb-2"><label class="form-label">Ghi chú</label><textarea type="text" class="form-control shadow-sm" name="devices[${deviceIndex}][note]" placeholder="Ghi chú" rows="1"></textarea></div>
            <div class="col-md-1 mb-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger remove-device-row">Xóa</button>
            </div>
        </div>`;
            $('#device-list-table').append(row);
            deviceIndex++;
            $('#device-list-table .supplier-select').select2({
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
        $(document).on('click', '.remove-device-return-row', function() {
            $(this).closest('.device-return-row').remove();
        });
        // TODO: AJAX load đối tác sửa chữa cho .partner-select

        // Toggle VAT input per order item
        $(document).on('change', '#toggle-vat', function() {
            const isOn = this.checked;
            $('#order-items-container .order-item-row').each(function() {
                const $vat = $(this).find('.vat-percent');
                if (isOn) {
                    $vat.css('display', 'block');
                } else {
                    $vat.css('display', 'none');
                    $(this).find('input[name*="[vat_percent]"]').val(0).trigger('input');
                }
            });
        });

        // Cập nhật order_type khi item_type thay đổi
        $(document).on('change', '.item-type-select', function() {
            const itemType = $(this).val();
            if (itemType === 'service') {
                $('#order_type').val('service');
            } else if (itemType === 'product') {
                $('#order_type').val('product');
            }
        });

        // Xử lý thêm/xóa dòng nội dung
        let contentIndex = 1;
        $('#add-content').on('click', function() {
            let row = `<div class="row mb-2 content-row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Template</label>
                    <select class="form-select implementation-template-select shadow-sm" name="content_lines[${contentIndex}][template_id]" style="width:100%">
                        <option value="">-- Chọn template nội dung triển khai --</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Nội dung yêu cầu</label>
                    <textarea class="form-control shadow-smm" name="content_lines[${contentIndex}][requirement]" rows="2" placeholder="Mô tả yêu cầu của khách hàng..."></textarea>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Nội dung triển khai</label>
                    <textarea class="form-control shadow-sm" name="content_lines[${contentIndex}][implementation]" rows="2" placeholder="Nội dung triển khai chi tiết..."></textarea>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Đơn giá</label>
                    <input type="number" class="form-control shadow-sm content-unit-price" name="content_lines[${contentIndex}][unit_price]" placeholder="0" min="0" step="0.01" value="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Số lượng</label>
                    <input type="number" class="form-control shadow-sm content-quantity" name="content_lines[${contentIndex}][quantity]" placeholder="1" min="0" step="0.01" value="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Thành tiền</label>
                    <input type="text" class="form-control shadow-sm content-total-price" name="content_lines[${contentIndex}][total_price]" placeholder="0" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Bảo hành</label>
                    <input type="text" class="form-control shadow-sm" name="content_lines[${contentIndex}][warranty]" placeholder="VD: 12 tháng" value="1 tháng">
                </div>
                <div class="col-md-2 mt-2 d-flex align-items-end">
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
            $newRow.find('.content-total-price').val(totalPrice.toLocaleString('vi-VN'));

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

        // Tính toán thành tiền cho nội dung
        $(document).on('input', '.content-unit-price, .content-quantity', function() {
            let $row = $(this).closest('.content-row');
            let unitPrice = parseFloat($row.find('.content-unit-price').val()) || 0;
            let quantity = parseFloat($row.find('.content-quantity').val()) || 0;
            let totalPrice = unitPrice * quantity;
            $row.find('.content-total-price').val(totalPrice.toLocaleString('vi-VN'));

            // Cập nhật tổng thành tiền nội dung và lợi nhuận dự kiến
            // updateExpectedStats();
        });

        // Tính toán thành tiền cho sản phẩm/dịch vụ
        $(document).on('input', 'input[name*="[unit_price]"], input[name*="[quantity]"], input[name*="[vat_percent]"]', function() {
            let $row = $(this).closest('.order-item-row');
            let unitPrice = parseFloat($row.find('input[name*="[unit_price]"]').val()) || 0;
            let quantity = parseFloat($row.find('input[name*="[quantity]"]').val()) || 0;
            // If VAT field is hidden (global toggle off), ignore VAT
            let vatField = $row.find('.vat-percent');
            let vatPercent = (vatField.is(':visible') ? parseFloat($row.find('input[name*="[vat_percent]"]').val()) || 0 : 0);

            let subtotal = unitPrice * quantity;
            let vatAmount = subtotal * (vatPercent / 100);
            let totalPrice = subtotal + vatAmount;

            $row.find('.total-price-field').val(totalPrice.toLocaleString('vi-VN'));

            // Cập nhật tổng thành tiền nội dung và lợi nhuận dự kiến
            // updateExpectedStats();
        });

        // Khởi tạo thống kê khi trang load
        $(document).ready(function() {
            // updateExpectedStats();

            // Khởi tạo toggle cho dòng đầu tiên
            if ($('#order-items-container .order-item-row').length > 0) {
                let $firstRow = $('#order-items-container .order-item-row').first();
                let itemType = $firstRow.find('.item-type-select').val();
                let purchaseType = $firstRow.find('.purchase-type-select').val();
                let $nameInput = $firstRow.find('.product-name-input');
                let $select = $firstRow.find('.product-select-all-warehouses');
                let $purchaseTypeContainer = $firstRow.find('.purchase-type-container');
                let $externalFields = $firstRow.next('.external-fields');

                if (itemType === 'service') {
                    // Ẩn trường nguồn mua cho dịch vụ
                    $purchaseTypeContainer.hide();

                    $nameInput.show();
                    $select.hide();

                    // Ẩn external fields
                    $externalFields.hide();
                } else {
                    // Hiển thị trường nguồn mua cho sản phẩm
                    $purchaseTypeContainer.show();

                    if (purchaseType === 'external') {
                        // Nếu là mua ngoài, hiển thị input text và external fields
                        $nameInput.show();
                        $select.hide();

                        // Hiển thị external fields
                        $externalFields.show();
                    } else {
                        // Nếu là từ kho, hiển thị select2
                        $nameInput.hide();
                        $select.show();

                        // Ẩn external fields
                        $externalFields.hide();
                    }
                }
            }
        });

        // Initialize customer select with pre-selected value
        <?php if ($customer_id_from_url > 0): ?>
            $(document).ready(function() {
                // If customer is pre-selected from URL, initialize Select2 with that value
                if ($('#customer_id option:selected').val()) {
                    $('.customer-select').select2({
                        placeholder: "Chọn khách hàng",
                        allowClear: true,
                        ajax: {
                            url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                            dataType: "json",
                            delay: 250,
                            data: function(params) {
                                return {
                                    action: "aerp_order_search_customers",
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
                }
            });
        <?php endif; ?>

        // Preselect device for return order when device_id is passed in query
        const urlParams = new URLSearchParams(window.location.search);
        const deviceIdFromUrl = parseInt(urlParams.get('device_id') || '0', 10);
        const orderTypeFromUrl = urlParams.get('order_type') || '';
        if (orderTypeFromUrl === 'return') {
            // Switch to return tab
            $('#order-type-tabs .nav-link').removeClass('active');
            $('#order-type-tabs .nav-link[data-type="return"]').addClass('active');
            $('#order_type').val('return');
            $('#order-items-section, #device-list-section, #content-section').hide();
            $('#device-return-section').show();

            // Initialize select2 and preselect device by id
            const $select = $('.received-device-select').first();
            $select.select2({
                placeholder: 'Chọn thiết bị nhận',
                allowClear: true,
                ajax: {
                    url: typeof aerp_order_ajax !== 'undefined' ? aerp_order_ajax.ajaxurl : ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'aerp_order_search_received_devices',
                            q: params.term,
                            device_id: deviceIdFromUrl
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

            if (deviceIdFromUrl > 0) {
                // Fetch device by id to append as selected option
                $.ajax({
                    url: typeof aerp_order_ajax !== 'undefined' ? aerp_order_ajax.ajaxurl : ajaxurl,
                    dataType: 'json',
                    data: {
                        action: 'aerp_order_search_received_devices',
                        device_id: deviceIdFromUrl
                    },
                }).then(function(data) {
                    if (data && data.length) {
                        const item = data[0];
                        const option = new Option(item.text, item.id, true, true);
                        $select.append(option).trigger('change');
                    }
                });
            }
        }

        // Khởi tạo Select2 cho dòng đầu tiên
        $(document).ready(function() {
            $('#content-container .implementation-template-select').first().select2({
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
    });
</script>