<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
AERP_Acc_Payment_Manager::handle_form_submit();

$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'accountant'),
];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$payment_id = isset($_GET['id']) ? absint($_GET['id']) : (get_query_var('id') ?: 0);
$prefill_order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
$payment = AERP_Acc_Payment_Manager::get_by_id($payment_id);
$is_edit = !empty($payment);
$existing_lines = $is_edit ? AERP_Acc_Payment_Manager::get_lines($payment_id) : [];

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
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?= $is_edit ? 'Cập nhật phiếu chi' : 'Thêm phiếu chi' ?></h2>
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
        ['label' => 'Quản lý phiếu chi', 'url' => home_url('/aerp-acc-payments')],
        ['label' => ($is_edit ? 'Cập nhật phiếu chi' : 'Thêm phiếu chi')]
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_acc_save_payment_action', 'aerp_acc_save_payment_nonce'); ?>
            <?php if ($is_edit): ?>
                <input type="hidden" name="payment_id" value="<?php echo esc_attr($payment->id); ?>">
            <?php endif; ?>
            <?php if ($prefill_order_id && !$is_edit): $order = AERP_Acc_Payment_Manager::get_prefill_from_order($prefill_order_id); ?>
                <div class="alert alert-secondary">
                    Tạo từ đơn: <strong>#<?php echo esc_html($order->order_code ?? $prefill_order_id); ?></strong>
                </div>
            <?php endif; ?>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Ngày chi</label>
                    <input type="date" name="payment_date" value="<?php echo $is_edit ? esc_attr($payment->payment_date) : date('Y-m-d'); ?>" class="form-control shadow-sm bg-body" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Phương thức thanh toán</label>
                    <?php $pmethod = $is_edit ? ($payment->payment_method ?? 'cash') : 'cash'; ?>
                    <select name="payment_method" class="form-select shadow-sm">
                        <option value="cash" <?php selected($pmethod, 'cash'); ?>>Tiền mặt</option>
                        <option value="bank_transfer" <?php selected($pmethod, 'bank_transfer'); ?>>Chuyển khoản</option>
                        <option value="card" <?php selected($pmethod, 'card'); ?>>Thẻ</option>
                        <option value="other" <?php selected($pmethod, 'other'); ?>>Khác</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Số tài khoản</label>
                    <input type="text" name="bank_account" class="form-control shadow-sm" value="<?php echo $is_edit ? esc_attr($payment->bank_account) : ''; ?>" placeholder="Số tài khoản/Thẻ">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Người chi</label>
                    <select name="payer_employee_id" id="payer-employee" class="form-select shadow-sm" style="width:100%">
                        <?php if ($is_edit && !empty($payment->payer_employee_id)):
                            global $wpdb;
                            $payer_name = $wpdb->get_var($wpdb->prepare("SELECT full_name FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d", (int)$payment->payer_employee_id));
                        ?>
                            <option value="<?php echo intval($payment->payer_employee_id); ?>" selected><?php echo esc_html($payer_name ?: ('#' . $payment->payer_employee_id)); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Loại chi</label>
                    <select name="payee_type" id="payee-type" class="form-select shadow-sm">
                        <?php $pt = $is_edit ? ($payment->payee_type ?? 'employee') : 'employee'; ?>
                        <option value="employee" <?php selected($pt, 'employee'); ?>>Nhân viên (NV)</option>
                        <option value="supplier" <?php selected($pt, 'supplier'); ?>>Nhà cung cấp (NCC)</option>
                        <option value="customer" <?php selected($pt, 'customer'); ?>>Khách hàng (KH)</option>
                        <option value="other" <?php selected($pt, 'other'); ?>>Khác</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3 payee-employee-wrap" style="display:none;">
                    <label class="form-label">Người nhận (nhân viên)</label>
                    <select name="payee_employee_id" id="payee-employee" class="form-select shadow-sm" style="width:100%">
                        <?php if ($is_edit && !empty($payment->payee_employee_id)):
                            $name = $wpdb->get_var($wpdb->prepare("SELECT full_name FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d", (int)$payment->payee_employee_id));
                        ?>
                            <option value="<?php echo intval($payment->payee_employee_id); ?>" selected><?php echo esc_html($name ?: ('#' . $payment->payee_employee_id)); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3 payee-supplier-wrap" style="display:none;">
                    <label class="form-label">Người nhận (nhà cung cấp)</label>
                    <select name="supplier_id" id="payee-supplier" class="form-select" style="width:100%">
                        <?php if ($is_edit && !empty($payment->supplier_id)):
                            $supp = $wpdb->get_row($wpdb->prepare("SELECT id,name FROM {$wpdb->prefix}aerp_suppliers WHERE id = %d", (int)$payment->supplier_id));
                        ?>
                            <option value="<?php echo intval($payment->supplier_id); ?>" selected><?php echo esc_html($supp->name ?? ('#' . $payment->supplier_id)); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3 payee-customer-wrap" style="display:none;">
                    <label class="form-label">Người nhận (khách hàng)</label>
                    <select name="customer_id" id="payee-customer" class="form-select" style="width:100%">
                        <?php if ($is_edit && !empty($payment->customer_id)):
                            $cus = $wpdb->get_row($wpdb->prepare("SELECT id,full_name FROM {$wpdb->prefix}aerp_crm_customers WHERE id = %d", (int)$payment->customer_id));
                        ?>
                            <option value="<?php echo intval($payment->customer_id); ?>" selected><?php echo esc_html(($cus->full_name ?? '') ?: ('#' . $payment->customer_id)); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3 payee-text-wrap" style="display:none;">
                    <label class="form-label">Tên người nhận (nhập thủ công)</label>
                    <input type="text" name="payee_text" class="form-control shadow-sm" value="<?php echo $is_edit ? esc_attr($payment->payee_text) : ''; ?>" placeholder="Tên người nhận (tùy chọn)">
                </div>               
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Dòng chi</label>
                <div id="payment-lines-container" class=" border rounded">
                    <?php if (!empty($existing_lines)) : foreach ($existing_lines as $idx => $ln): ?>
                            <div class="payment-line-row p-2 mb-2">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Danh mục</label>
                                        <select name="lines[<?php echo $idx; ?>][category_id]" class="form-select payment-line-category shadow-sm" style="width:100%">
                                            <?php if (!empty($ln->category_id)):
                                                $cat_name = class_exists('AERP_Acc_Category_Manager') ? AERP_Acc_Category_Manager::get_name((int)$ln->category_id) : '';
                                            ?>
                                                <option value="<?php echo intval($ln->category_id); ?>" selected><?php echo esc_html($cat_name ?: ('#' . $ln->category_id)); ?></option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Mô tả</label>
                                        <input type="text" name="lines[<?php echo $idx; ?>][description]" class="form-control shadow-sm" value="<?php echo esc_attr($ln->description); ?>" placeholder="Nội dung chi">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Số tiền</label>
                                        <input type="number" name="lines[<?php echo $idx; ?>][amount]" class="form-control payment-amount shadow-sm" value="<?php echo esc_attr($ln->amount); ?>" min="0" step="0.01" required>
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">VAT (%)</label>
                                        <input type="number" name="lines[<?php echo $idx; ?>][vat_percent]" class="form-control payment-vat shadow-sm" value="<?php echo esc_attr($ln->vat_percent); ?>" min="0" step="0.01">
                                    </div>
                                    <div class="col-md-1 mt-2 d-flex align-items-end">
                                        <button type="button" class="btn btn-outline-danger remove-line">Xóa</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach;
                    else: ?>
                        <div class="payment-line-row p-2 mb-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Danh mục</label>
                                    <select name="lines[0][category_id]" class="form-select payment-line-category shadow-sm" style="width:100%"></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Mô tả</label>
                                    <input type="text" name="lines[0][description]" class="form-control shadow-sm" placeholder="Nội dung chi">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Số tiền</label>
                                    <input type="number" name="lines[0][amount]" class="form-control payment-amount shadow-sm" value="0" min="0" step="0.01" required>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">VAT (%)</label>
                                    <input type="number" name="lines[0][vat_percent]" class="form-control payment-vat shadow-sm" value="0" min="0" step="0.01">
                                </div>
                                <div class="col-md-1 mt-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger remove-line">Xóa</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" id="add-payment-line" class="btn btn-secondary mt-2">Thêm dòng</button>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tổng tiền</label>
                    <input type="number" name="total_amount" id="payment-total" value="<?php echo $is_edit ? esc_attr($payment->total_amount) : 0; ?>" class="form-control shadow-sm" min="0" step="0.01" required>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control shadow-sm" name="note" placeholder="Ghi chú" rows="1"><?php echo $is_edit ? esc_textarea($payment->note) : ''; ?></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_acc_save_payment" class="btn btn-primary">
                    <?= $is_edit ? 'Cập nhật' : 'Thêm mới' ?>
                </button>
                <?php $status_now = $is_edit ? ($payment->status ?? 'draft') : 'draft';
                $is_admin_flag = function_exists('aerp_user_has_role') && aerp_user_has_role($user_id, 'admin');
                $is_accountant_flag = function_exists('aerp_user_has_role') && aerp_user_has_role($user_id, 'accountant');
                $can_manage = ($is_admin_flag || $is_accountant_flag); ?>
                <?php if ($status_now === 'draft' && $can_manage): ?>
                    <button type="submit" name="aerp_acc_confirm_payment" value="1" class="btn btn-success">Xác nhận</button>
                <?php elseif ($status_now === 'confirmed' && $can_manage): ?>
                    <button type="submit" name="aerp_acc_mark_paid" value="1" class="btn btn-success">Đã chi</button>
                <?php elseif ($status_now === 'paid'): ?>
                    <a href="#" class="btn btn-success disabled" tabindex="-1" aria-disabled="true">Đã chi</a>
                <?php endif; ?>

                <a href="<?= home_url('/aerp-acc-payments') ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<script>
    (function($) {
        let index = 1;
        const statusNow = '<?php echo esc_js($status_now); ?>';
        const isAdmin = <?php echo $is_admin_flag ? 'true' : 'false'; ?>;
        const isAccountant = <?php echo $is_accountant_flag ? 'true' : 'false'; ?>;
        // Init select2 cho danh mục từng dòng
        function initLineCategorySelect($sel) {
            if ($sel.hasClass('select2-hidden-accessible')) return;
            $sel.select2({
                placeholder: 'Danh mục chi',
                allowClear: true,
                ajax: {
                    url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                    dataType: 'json',
                    delay: 200,
                    data: function(params) {
                        return {
                            action: 'aerp_acc_search_categories',
                            q: params.term || ''
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });
        }
        $('#add-payment-line').on('click', function() {
            const row = `<div class=\"payment-line-row p-2 mb-2\">\n                <div class=\"row g-2 align-items-end\">\n                    <div class=\"col-md-3\">\n                        <label class=\"form-label\">Danh mục</label>\n                        <select name=\"lines[${index}][category_id]\" class=\"form-select payment-line-category\" style=\"width:100%\"></select>\n                    </div>\n                    <div class=\"col-md-4\">\n                        <label class=\"form-label\">Mô tả</label>\n                        <input type=\"text\" name=\"lines[${index}][description]\" class=\"form-control\" placeholder=\"Nội dung chi\">\n                    </div>\n                    <div class=\"col-md-3\">\n                        <label class=\"form-label\">Số tiền</label>\n                        <input type=\"number\" name=\"lines[${index}][amount]\" class=\"form-control payment-amount\" value=\"0\" min=\"0\" step=\"0.01\" required>\n                    </div>\n                    <div class=\"col-md-1\">\n                        <label class=\"form-label\">VAT (%)</label>\n                        <input type=\"number\" name=\"lines[${index}][vat_percent]\" class=\"form-control payment-vat\" value=\"0\" min=\"0\" step=\"0.01\">\n                    </div>\n                    <div class=\"col-md-1 mt-2 d-flex align-items-end\">\n                        <button type=\"button\" class=\"btn btn-outline-danger remove-line\">Xóa</button>\n                    </div>\n                </div>\n            </div>`;
            $('#payment-lines-container').append(row);
            initLineCategorySelect($('#payment-lines-container .payment-line-row:last .payment-line-category'));
            index++;
        });
        $(document).on('click', '.remove-line', function() {
            $(this).closest('.payment-line-row').remove();
            recomputeTotal();
        });
        $(document).on('input', '.payment-amount,.payment-vat', function() {
            recomputeTotal();
        });

        function recomputeTotal() {
            let t = 0;
            $('#payment-lines-container .payment-line-row').each(function() {
                const amt = parseFloat($(this).find('.payment-amount').val() || 0);
                const vat = parseFloat($(this).find('.payment-vat').val() || 0);
                const vatAmt = isNaN(amt) || isNaN(vat) ? 0 : (amt * vat / 100);
                if (!isNaN(amt)) t += amt + vatAmt;
            });
            $('#payment-total').val(t.toFixed(2));
        }
        // Ensure select2 is ready
        function ensureSelect2ThenInit(attempts) {
            attempts = attempts || 0;
            if ($.fn && typeof $.fn.select2 === 'function') {
                $('.payment-line-category').each(function() {
                    initLineCategorySelect($(this));
                });
                // init header selects
                $('#payer-employee').select2({
                    placeholder: 'Chọn nhân viên',
                    allowClear: true,
                    ajax: {
                        url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                        dataType: 'json',
                        delay: 200,
                        data: function(params) {
                            return {
                                action: 'aerp_order_search_employees',
                                q: params.term || ''
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    },
                    minimumInputLength: 0
                });
                $('#payee-employee').select2({
                    placeholder: 'Chọn nhân viên',
                    allowClear: true,
                    ajax: {
                        url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                        dataType: 'json',
                        delay: 200,
                        data: function(params) {
                            return {
                                action: 'aerp_order_search_employees',
                                q: params.term || ''
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    },
                    minimumInputLength: 0
                });
                $('#payee-supplier').select2({
                    placeholder: 'Chọn nhà cung cấp',
                    allowClear: true,
                    ajax: {
                        url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                        dataType: 'json',
                        delay: 200,
                        data: function(params) {
                            return {
                                action: 'aerp_order_search_suppliers',
                                q: params.term || ''
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    },
                    minimumInputLength: 0
                });
                $('#payee-customer').select2({
                    placeholder: 'Chọn khách hàng',
                    allowClear: true,
                    ajax: {
                        url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                        dataType: 'json',
                        delay: 200,
                        data: function(params) {
                            return {
                                action: 'aerp_order_search_customers',
                                q: params.term || ''
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    },
                    minimumInputLength: 0
                });
                return;
            }
            if (attempts < 50) {
                setTimeout(function() {
                    ensureSelect2ThenInit(attempts + 1);
                }, 100);
            }
        }
        ensureSelect2ThenInit(0);
        // Disable form when confirmed/paid per role: only accountant gets locked; admin never locked
        if (statusNow === 'confirmed' && isAccountant && !isAdmin) {
            // Disable everything except hidden fields and the 'Đã chi' button
            $('form :input').not('[type="hidden"]').not('[name="aerp_acc_mark_paid"]').prop('disabled', true);
        }
        if (statusNow === 'paid' && (isAccountant && !isAdmin)) {
            // Fully lock (except hidden) once đã chi
            $('form :input').not('[type="hidden"]').prop('disabled', true);
        }

        function togglePayee() {
            const t = $('#payee-type').val();
            $('.payee-employee-wrap,.payee-supplier-wrap,.payee-customer-wrap,.payee-text-wrap').hide();
            if (t === 'employee') $('.payee-employee-wrap').show();
            else if (t === 'supplier') $('.payee-supplier-wrap').show();
            else if (t === 'customer') $('.payee-customer-wrap').show();
            else if (t === 'other') $('.payee-text-wrap').show();
        }
        $('#payee-type').on('change', togglePayee);
        togglePayee();
    })(jQuery);
</script>
<?php
$content = ob_get_clean();
$title = $is_edit ? 'Cập nhật phiếu chi' : 'Thêm phiếu chi';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
