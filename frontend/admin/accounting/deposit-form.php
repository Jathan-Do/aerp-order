<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$employee = aerp_get_employee_by_user_id($user_id);
$user_fullname = $employee ? $employee->full_name : '';

// Quyền: kỹ thuật tạo/sửa, kế toán duyệt
$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'accountant'),
    aerp_user_has_permission($user_id, 'acc_deposit_add'),
    aerp_user_has_permission($user_id, 'acc_deposit_edit'),
];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$deposit_id = isset($_GET['id']) ? absint($_GET['id']) : (get_query_var('id') ?: 0);
$deposit = AERP_Acc_Deposit_Manager::get_by_id($deposit_id);
$is_edit = !empty($deposit);
$existing_lines = $is_edit ? AERP_Acc_Deposit_Manager::get_lines($deposit_id) : [];

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
    <h2><?= $is_edit ? 'Cập nhật phiếu nộp tiền' : 'Thêm phiếu nộp tiền' ?></h2>
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
        ['label' => 'Phiếu nộp tiền', 'url' => home_url('/aerp-acc-deposits')],
        ['label' => ($is_edit ? 'Cập nhật' : 'Thêm mới')]
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_acc_save_deposit_action', 'aerp_acc_save_deposit_nonce'); ?>
            <?php if ($is_edit): ?>
                <input type="hidden" name="deposit_id" value="<?php echo esc_attr($deposit->id); ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Ngày nộp</label>
                    <input type="date" name="deposit_date" value="<?php echo $is_edit ? esc_attr($deposit->deposit_date) : date('Y-m-d'); ?>" class="form-control shadow-sm bg-body" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Phiếu thu (bắt buộc)</label>
                    <select name="receipt_id" id="receipt-select" class="form-select shadow-sm" style="width:100%" required>
                        <?php if ($is_edit && !empty($deposit->receipt_id)):
                            global $wpdb;
                            $rc = $wpdb->get_row($wpdb->prepare("SELECT id, code FROM {$wpdb->prefix}aerp_acc_receipts WHERE id = %d", (int)$deposit->receipt_id));
                        ?>
                            <option value="<?php echo intval($deposit->receipt_id); ?>" selected><?php echo esc_html($rc && $rc->code ? $rc->code : ('#' . $deposit->receipt_id)); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control shadow-sm" name="note" placeholder="Ghi chú" rows="1"><?php echo $is_edit ? esc_textarea($deposit->note) : ''; ?></textarea>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Dòng nộp</label>
                <div id="deposit-lines-container" class=" border rounded">
                    <?php if (!empty($existing_lines)) : foreach ($existing_lines as $idx => $ln): ?>
                            <div class="row g-2 align-items-end mb-2 p-2 deposit-line-row">
                                <div class="col-md-4">
                                    <label class="form-label">Đơn (thuộc phiếu thu đã chọn)</label>
                                    <select class="form-select order-select shadow-sm" name="lines[<?php echo $idx; ?>][order_id]" style="width:100%">
                                        <?php if (!empty($ln->order_id)):
                                            $pre_code = function_exists('aerp_get_order_code_by_id') ? aerp_get_order_code_by_id($ln->order_id) : '';
                                        ?>
                                            <option value="<?php echo esc_attr($ln->order_id); ?>" selected>#<?php echo esc_html($pre_code ?: $ln->order_id); ?></option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Doanh thu</label>
                                    <input type="number" name="lines[<?php echo $idx; ?>][revenue_amount]" class="form-control rev-input shadow-sm" value="<?php echo isset($ln->revenue_amount) ? esc_attr($ln->revenue_amount) : '0'; ?>" min="0" step="0.01">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tạm ứng (chọn phiếu chi)</label>
                                    <select class="form-select adv-payment-select shadow-sm" name="lines[<?php echo $idx; ?>][advance_payment_id]" style="width:100%">
                                        <?php if (!empty($ln->advance_payment_id)):
                                            $pm = $wpdb->get_row($wpdb->prepare("SELECT id, code, total_amount FROM {$wpdb->prefix}aerp_acc_payments WHERE id = %d", (int)$ln->advance_payment_id));
                                            $pm_label = ($pm && $pm->code ? $pm->code : ('#'.(int)$ln->advance_payment_id));
                                        ?>
                                            <option value="<?php echo intval($ln->advance_payment_id); ?>" selected><?php echo esc_html($pm_label); ?></option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Số tiền tạm ứng</label>
                                    <input type="number" name="lines[<?php echo $idx; ?>][advance_amount]" class="form-control adv-input shadow-sm" value="<?php echo isset($ln->advance_amount) ? esc_attr($ln->advance_amount) : '0'; ?>" min="0" step="0.01" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Mua ngoài</label>
                                    <input type="number" name="lines[<?php echo $idx; ?>][external_amount]" class="form-control ext-input shadow-sm" value="<?php echo isset($ln->external_amount) ? esc_attr($ln->external_amount) : '0'; ?>" min="0" step="0.01">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Số tiền nộp</label>
                                    <input type="number" name="lines[<?php echo $idx; ?>][amount]" class="form-control deposit-amount shadow-sm" value="<?php echo esc_attr($ln->amount); ?>" min="0" step="0.01" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Ghi chú</label>
                                    <textarea class="form-control shadow-sm" name="lines[<?php echo $idx; ?>][note]" placeholder="Ghi chú" rows="1"><?php echo esc_textarea($ln->note); ?></textarea>
                                </div>
                                <div class="col-md-1 mt-2">
                                    <button type="button" class="btn btn-outline-danger remove-line">Xóa</button>
                                </div>
                            </div>
                        <?php endforeach;
                    else: ?>
                        <div class="row g-2 align-items-end mb-2 p-2 deposit-line-row">
                            <div class="col-md-4">
                                <label class="form-label">Đơn (thuộc phiếu thu đã chọn)</label>
                                <select class="form-select order-select shadow-sm" name="lines[0][order_id]" style="width:100%"></select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Doanh thu</label>
                                <input type="number" name="lines[0][revenue_amount]" class="form-control rev-input shadow-sm" value="0" min="0" step="0.01">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tạm ứng (chọn phiếu chi)</label>
                                <select class="form-select adv-payment-select shadow-sm" name="lines[0][advance_payment_id]" style="width:100%"></select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">Số tiền tạm ứng</label>
                                <input type="number" name="lines[0][advance_amount]" class="form-control adv-input shadow-sm" value="0" min="0" step="0.01" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Mua ngoài</label>
                                <input type="number" name="lines[0][external_amount]" class="form-control ext-input shadow-sm" value="0" min="0" step="0.01">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Số tiền nộp</label>
                                <input type="number" name="lines[0][amount]" class="form-control deposit-amount shadow-sm" value="0" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ghi chú</label>
                                <textarea class="form-control shadow-sm" name="lines[0][note]" placeholder="Ghi chú" rows="1"></textarea>
                            </div>
                            <div class="col-md-1 mt-2">
                                <button type="button" class="btn btn-outline-danger remove-line">Xóa</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" id="add-deposit-line" class="btn btn-secondary mt-2">Thêm dòng</button>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tổng tiền</label>
                    <input type="number" name="total_amount" id="deposit-total" value="<?php echo $is_edit ? esc_attr($deposit->total_amount) : 0; ?>" class="form-control shadow-sm" min="0" step="0.01" required>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_acc_save_deposit" class="btn btn-primary">
                    <?= $is_edit ? 'Cập nhật' : 'Thêm mới' ?>
                </button>
                <button type="submit" name="aerp_acc_submit_deposit" value="1" class="btn btn-warning">Gửi duyệt</button>
                <?php if (aerp_user_has_role($user_id, 'admin') || aerp_user_has_role($user_id, 'accountant')): ?>
                    <button type="submit" name="aerp_acc_approve_deposit" value="1" class="btn btn-success">Xác nhận nộp</button>
                <?php endif; ?>
                <a href="<?= home_url('/aerp-acc-deposits') ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<script>
    (function($) {
        let index = 1;

        function getAjaxUrl() {
            try {
                if (typeof aerp_order_ajax !== 'undefined' && aerp_order_ajax.ajaxurl) return aerp_order_ajax.ajaxurl;
                if (typeof ajaxurl !== 'undefined' && ajaxurl) return ajaxurl;
                if (window.wp && wp.ajax && wp.ajax.settings && wp.ajax.settings.url) return wp.ajax.settings.url;
            } catch (e) {}
            return (window.location.origin || '') + '/wp-admin/admin-ajax.php';
        }

        function recomputeTotal() {
            let t = 0;
            $('.deposit-amount').each(function() {
                const v = parseFloat($(this).val() || 0);
                if (!isNaN(v)) t += v;
            });
            $('#deposit-total').val(t.toFixed(2));
        }
        // Recompute per-row deposit = revenue + advance - external (but allow manual override by typing)
        function recomputeRow($row) {
            const rev = parseFloat($row.find('.rev-input').val() || 0) || 0;
            const adv = parseFloat($row.find('.adv-input').val() || 0) || 0;
            const ext = parseFloat($row.find('.ext-input').val() || 0) || 0;
            const val = rev + adv - ext;
            if (!isNaN(val)) {
                $row.find('.deposit-amount').val(val.toFixed(2));
            }
            recomputeTotal();
        }
        $(document).on('input', '.deposit-amount', recomputeTotal);
        $(document).on('input', '.rev-input,.adv-input,.ext-input', function() {
            recomputeRow($(this).closest('.deposit-line-row'));
        });

        $('#add-deposit-line').on('click', function() {
            const row = `<div class=\"row g-2 align-items-end mb-2 p-2 deposit-line-row\">\n                <div class=\"col-md-4\">\n                    <label class=\"form-label\">Đơn (thuộc phiếu thu đã chọn)</label>\n                    <select class=\"form-select order-select\" name=\"lines[${index}][order_id]\" style=\"width:100%\"></select>\n                </div>\n                <div class=\"col-md-2\">\n                    <label class=\"form-label\">Doanh thu</label>\n                    <input type=\"number\" name=\"lines[${index}][revenue_amount]\" class=\"form-control rev-input\" value=\"0\" min=\"0\" step=\"0.01\">\n                </div>\n                <div class=\"col-md-3\">\n                    <label class=\"form-label\">Tạm ứng (chọn phiếu chi)</label>\n                    <select class=\"form-select adv-payment-select\" name=\"lines[${index}][advance_payment_id]\" style=\"width:100%\"></select>\n                </div>\n                <div class=\"col-md-1\">\n                    <label class=\"form-label\">Số tiền tạm ứng</label>\n                    <input type=\"number\" name=\"lines[${index}][advance_amount]\" class=\"form-control adv-input\" value=\"0\" min=\"0\" step=\"0.01\" readonly>\n                </div>\n                <div class=\"col-md-2\">\n                    <label class=\"form-label\">Mua ngoài</label>\n                    <input type=\"number\" name=\"lines[${index}][external_amount]\" class=\"form-control ext-input\" value=\"0\" min=\"0\" step=\"0.01\">\n                </div>\n                <div class=\"col-md-2\">\n                    <label class=\"form-label\">Số tiền nộp</label>\n                    <input type=\"number\" name=\"lines[${index}][amount]\" class=\"form-control deposit-amount\" value=\"0\" min=\"0\" step=\"0.01\" required>\n                </div>\n                <div class=\"col-md-4\">\n                    <label class=\"form-label\">Ghi chú</label>\n                    <textarea class=\"form-control\" name=\"lines[${index}][note]\" placeholder=\"Ghi chú\" rows=\"1\"></textarea>\n                </div>\n                <div class=\"col-md-1 mt-2\">\n                    <button type=\"button\" class=\"btn btn-outline-danger remove-line\">Xóa</button>\n                </div>\n            </div>`;
            $('#deposit-lines-container').append(row);
            var $last = $('#deposit-lines-container .deposit-line-row:last');
            initOrderSelect($last.find('.order-select'));
            initAdvancePaymentSelect($last.find('.adv-payment-select'));
            index++;
        });
        $(document).on('click', '.remove-line', function() {
            $(this).closest('.deposit-line-row').remove();
            recomputeTotal();
        });

        // Khởi tạo select2 cho phiếu thu (chỉ khi select2 & aerp_order_ajax đã sẵn sàng)
        function initReceiptSelect() {
            var $el = $('#receipt-select');
            if (!$el.length || $el.hasClass('select2-hidden-accessible')) return;
            $el.select2({
                placeholder: 'Chọn phiếu thu',
                allowClear: true,
                ajax: {
                    url: getAjaxUrl(),
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'aerp_acc_search_receipts',
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
            }).on('select2:select', function() {
                // Khi đổi phiếu thu: reset container và luôn render mẫu dòng mới đầy đủ cột
                $('#deposit-lines-container').html('' +
                    '<div class="row g-2 align-items-end mb-2 p-2 deposit-line-row">' +
                    '<div class="col-md-4">' +
                    '<label class="form-label">Đơn (thuộc phiếu thu đã chọn)</label>' +
                    '<select class="form-select order-select" name="lines[0][order_id]" style="width:100%"></select>' +
                    '</div>' +
                    '<div class="col-md-2">' +
                    '<label class="form-label">Doanh thu</label>' +
                    '<input type="number" name="lines[0][revenue_amount]" class="form-control rev-input" value="0" min="0" step="0.01">' +
                    '</div>' +
                    '<div class="col-md-3">' +
                    '<label class="form-label">Tạm ứng (chọn phiếu chi)</label>' +
                    '<select class="form-select adv-payment-select" name="lines[0][advance_payment_id]" style="width:100%"></select>' +
                    '</div>' +
                    '<div class="col-md-1">' +
                    '<label class="form-label">Số tiền tạm ứng</label>' +
                    '<input type="number" name="lines[0][advance_amount]" class="form-control adv-input" value="0" min="0" step="0.01" readonly>' +
                    '</div>' +
                    '<div class="col-md-2">' +
                    '<label class="form-label">Mua ngoài</label>' +
                    '<input type="number" name="lines[0][external_amount]" class="form-control ext-input" value="0" min="0" step="0.01">' +
                    '</div>' +
                    '<div class="col-md-2">' +
                    '<label class="form-label">Số tiền nộp</label>' +
                    '<input type="number" name="lines[0][amount]" class="form-control deposit-amount" value="0" min="0" step="0.01" required>' +
                    '</div>' +
                    '<div class="col-md-4">' +
                    '<label class="form-label">Ghi chú</label>' +
                    '<textarea type="text" class="form-control" name="lines[0][note]" placeholder="Ghi chú" rows="1"></textarea>' +
                    '</div>' +
                    '<div class="col-md-1 mt-2">' +
                    '<button type="button" class="btn btn-outline-danger remove-line">Xóa</button>' +
                    '</div>' +
                    '</div>'
                );
                index = 1;
                var $row0 = $('#deposit-lines-container .deposit-line-row').first();
                initOrderSelect($row0.find('.order-select'));
                initAdvancePaymentSelect($row0.find('.adv-payment-select'));
            });
        }

        function initOrderSelect($select) {
            $select.select2({
                placeholder: 'Chọn đơn trong phiếu thu',
                allowClear: true,
                ajax: {
                    url: getAjaxUrl(),
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'aerp_acc_search_orders_in_receipt',
                            receipt_id: parseInt($('#receipt-select').val() || 0, 10) || 0,
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
            }).on('select2:select', function(e) {
                var data = e.params.data || {};
                var $row = $(this).closest('.deposit-line-row');
                if (typeof data.collected_amount !== 'undefined') {
                    // fill revenue only; user sẽ nhập tạm ứng/mua ngoài thủ công
                    $row.find('.rev-input').val(parseFloat(data.collected_amount).toFixed(2));
                    recomputeRow($row);
                }
            });
        }

        function initAdvancePaymentSelect($select) {
            $select.select2({
                placeholder: 'Chọn phiếu chi tạm ứng',
                allowClear: true,
                ajax: {
                    url: getAjaxUrl(),
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        var $row = $select.closest('.deposit-line-row');
                        var orderId = parseInt($row.find('.order-select').val() || 0, 10) || 0;
                        return {
                            action: 'aerp_acc_search_payments_for_advance',
                            order_id: orderId, // filter theo đơn nếu đã chọn
                            q: params.term || ''
                        };
                    },
                    processResults: function(data) {
                        return { results: data };
                    },
                    cache: true
                },
                minimumInputLength: 0
            }).on('select2:select', function(e) {
                var data = e.params.data || {};
                var $row = $(this).closest('.deposit-line-row');
                if (typeof data.total_amount !== 'undefined') {
                    $row.find('.adv-input').val(parseFloat(data.total_amount).toFixed(2));
                    recomputeRow($row);
                }
            }).on('select2:clear', function() {
                var $row = $(this).closest('.deposit-line-row');
                $row.find('.adv-input').val('0.00');
                recomputeRow($row);
            });
        }

        function ensureSelect2ThenInit(attempts) {
            attempts = attempts || 0;
            if ($.fn && typeof $.fn.select2 === 'function') {
                initReceiptSelect();
                $('.order-select').each(function() {
                    if (!$(this).hasClass('select2-hidden-accessible')) initOrderSelect($(this));
                });
                $('.adv-payment-select').each(function(){
                    if (!$(this).hasClass('select2-hidden-accessible')) initAdvancePaymentSelect($(this));
                });
                return;
            }
            if (attempts < 50) setTimeout(function() {
                ensureSelect2ThenInit(attempts + 1);
            }, 100);
        }
        ensureSelect2ThenInit(0);
    })(jQuery);
</script>
<?php
$content = ob_get_clean();
$title = $is_edit ? 'Cập nhật phiếu nộp tiền' : 'Thêm phiếu nộp tiền';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
