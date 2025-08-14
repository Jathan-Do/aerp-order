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
    <h2>Thêm đơn hàng mới</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <form class="aerp-order-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('aerp_save_order_action', 'aerp_save_order_nonce'); ?>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="customer_id" class="form-label">Khách hàng</label>
                    <select class="form-select customer-select" id="customer_id" name="customer_id" required>
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
                <div class="col-md-6 mb-3">
                    <label for="employee_id" class="form-label">Nhân viên phụ trách</label>
                    <select class="form-select employee-select" id="employee_id" name="employee_id">

                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="order_date" class="form-label">Ngày tạo đơn hàng</label>
                    <input type="date" class="form-control bg-body" id="order_date" name="order_date" required value="<?php echo esc_attr($date_now); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="status_id" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status_id" name="status_id">
                        <?php
                        $statuses = aerp_get_order_statuses();
                        aerp_safe_select_options($statuses, '', 'id', 'name', true);
                        ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="cost" class="form-label">Chi phí đơn hàng</label>
                    <input type="number" class="form-control" id="cost" name="cost" min="0" step="0.01" value="0">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="customer_source_id" class="form-label">Nguồn khách hàng</label>
                    <select class="form-select" id="customer_source_id" name="customer_source_id">
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
                <div class="col-md-6 mb-3">
                    <label for="order_type" class="form-label">Loại đơn</label>
                    <select class="form-select" id="order_type" name="order_type" required>
                        <option value="product">Bán hàng/ Dịch vụ</option>
                        <option value="device">Nhận thiết bị</option>
                    </select>
                </div>
                <div class="col-12 mb-3" id="device-list-section" style="display:none">
                    <div id="device-list-table">
                        <div class="row mb-2 device-row">
                            <div class="col-md-3">
                                <label class="form-label">Tên thiết bị</label>
                                <input type="text" class="form-control" name="devices[0][device_name]" required placeholder="Tên thiết bị">
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
                                <input type="text" class="form-control" name="devices[0][note]" placeholder="Ghi chú">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Đối tác sửa</label>
                                <select class="form-select partner-select supplier-select" style="width:100%" name="devices[0][partner_id]">
                                    <option value="">-- Chọn đối tác --</option>
                                </select>
                            </div>
                            <div class="col-md-1 mt-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger remove-device-row">Xóa</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary mt-2" id="add-device-row">Thêm thiết bị</button>
                </div>
                <div class="col-12 mb-3">
                    <div id="order-items-container">
                        <div class="row mb-2 order-item-row">
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Loại</label>
                                <select class="form-select item-type-select" name="order_items[0][item_type]">
                                    <option value="product">Sản phẩm</option>
                                    <option value="service">Dịch vụ</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <label class="form-label">Sản phẩm trong đơn</label>
                                <input type="text" class="form-control product-name-input" name="order_items[0][product_name]" placeholder="Tên sản phẩm/dịch vụ" required style="display:none">
                                <select class="form-select product-select-all-warehouses" name="order_items[0][product_id]" style="width:100%"></select>
                            </div>
                            <div class="col-md-2 mb-2 d-flex align-items-end">
                                <div class="w-100">
                                    <label class="form-label">Số lượng</label>
                                    <input type="number" class="form-control" name="order_items[0][quantity]" placeholder="Số lượng" min="0.01" step="0.01" required>
                                </div>
                                <span class="unit-label ms-2"></span>
                                <input type="hidden" name="order_items[0][unit_name]" class="unit-name-input">
                            </div>
                            <div class="col-md-1 mb-2">
                                <label class="form-label">VAT</label>
                                <input type="number" class="form-control" name="order_items[0][vat_percent]" placeholder="VAT (%)" min="0" max="100" step="0.01">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Đơn giá</label>
                                <input type="number" class="form-control" name="order_items[0][unit_price]" placeholder="Đơn giá" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Thành tiền</label>
                                <input type="text" class="form-control total-price-field" placeholder="Thành tiền" readonly>
                            </div>
                            <div class="col-md-1 mb-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger remove-order-item">Xóa</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary mt-2" id="add-order-item">Thêm sản phẩm</button>
                </div>
                <div class="col-12 mb-3">
                    <label for="attachments" class="form-label">File đính kèm</label>
                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                </div>
                <div class="col-12 mb-3">
                    <label for="note" class="form-label">Ghi chú</label>
                    <textarea class="form-control" id="note" name="note" rows="2"></textarea>
                </div>
                <div class="col-12 mb-3">
                    <label for="requirements_content" class="form-label">Nội dung yêu cầu</label>
                    <textarea class="form-control" id="requirements_content" name="requirements_content" rows="4" placeholder="Mô tả yêu cầu của khách hàng..."></textarea>
                </div>
                <div class="col-12 mb-3">
                    <label for="implementation_content" class="form-label">Nội dung triển khai</label>
                    <div class="mb-2">
                        <select class="form-select implementation-template-select" id="implementation_template_select" style="width:100%">
                            <option value="">-- Chọn template nội dung triển khai --</option>
                        </select>
                    </div>
                    <textarea class="form-control" id="implementation_content" name="implementation_content" rows="6" placeholder="Nội dung triển khai chi tiết..."></textarea>
                    <small class="form-text text-muted">Có thể chọn template từ danh sách trên và chỉnh sửa nội dung theo yêu cầu cụ thể</small>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_order" class="btn btn-primary">Thêm mới</button>
                <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-secondary">Quay lại</a>
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
        function toggleDeviceSection() {
            if ($('#order_type').val() === 'device') {
                $('#device-list-section').show();
                $('#order-items-container').hide();
                $('#add-order-item').hide();
                $('#device-list-section input, #device-list-section select').prop('required', true);
                $('#order-items-container input, #order-items-container select').prop('required', false);
            } else {
                $('#device-list-section').hide();
                $('#order-items-container').show();
                $('#add-order-item').show();
                $('#device-list-section input, #device-list-section select').prop('required', false);
                $('#order-items-container input, #order-items-container select').prop('required', true);
            }
        }
        $('#order_type').on('change', toggleDeviceSection);
        toggleDeviceSection();

        // Thêm dòng thiết bị
        let deviceIndex = 1;
        $('#add-device-row').on('click', function() {
            let row = `<div class="row mb-2 device-row">
            <div class="col-md-3 mb-2"><input type="text" class="form-control" name="devices[${deviceIndex}][device_name]" placeholder="Tên thiết bị"></div>
            <div class="col-md-2 mb-2"><input type="text" class="form-control" name="devices[${deviceIndex}][serial_number]" placeholder="Serial/IMEI"></div>
            <div class="col-md-2 mb-2"><input type="text" class="form-control" name="devices[${deviceIndex}][status]" placeholder="Tình trạng"></div>
            <div class="col-md-2 mb-2"><input type="text" class="form-control" name="devices[${deviceIndex}][note]" placeholder="Ghi chú"></div>
            <div class="col-md-2 mb-2">
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
        // TODO: AJAX load đối tác sửa chữa cho .partner-select
        
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
                $('#implementation_content').val(data.content);
            }
        });

        // Clear template selection when content is manually edited
        $('#implementation_content').on('input', function() {
            if ($(this).val() !== $('#implementation_template_select').find(':selected').data('content')) {
                $('#implementation_template_select').val(null).trigger('change');
            }
        });
    });
</script>