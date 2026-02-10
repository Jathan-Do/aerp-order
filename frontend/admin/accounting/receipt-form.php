<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$employee = aerp_get_employee_by_user_id($user_id);
$user_fullname = $employee ? $employee->full_name : '';
// Submit handler
AERP_Acc_Receipt_Manager::handle_form_submit();

// Quyền thêm/sửa phiếu thu
$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'department_lead'),
    aerp_user_has_permission($user_id, 'acc_receipt_add'),
    aerp_user_has_permission($user_id, 'acc_receipt_edit'),
];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$receipt_id = isset($_GET['id']) ? absint($_GET['id']) : (get_query_var('id') ?: 0);
$prefill_order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
$receipt = AERP_Acc_Receipt_Manager::get_by_id($receipt_id);
$is_edit = !empty($receipt);
$existing_lines = $is_edit ? AERP_Acc_Receipt_Manager::get_lines($receipt_id) : [];

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
    <h2><?= $is_edit ? 'Cập nhật phiếu thu' : 'Thêm phiếu thu' ?></h2>
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
        ['label' => 'Quản lý phiếu thu', 'url' => home_url('/aerp-acc-receipts')],
        ['label' => ($is_edit ? 'Cập nhật phiếu thu' : 'Thêm phiếu thu')]
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_acc_save_receipt_action', 'aerp_acc_save_receipt_nonce'); ?>
            <?php if ($is_edit): ?>
                <input type="hidden" name="receipt_id" value="<?php echo esc_attr($receipt->id); ?>">
            <?php endif; ?>
            <div class="row">
                <?php if ($is_edit): ?>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Mã phiếu thu</label>
                        <input type="text" name="code" value="<?php echo $is_edit ? esc_attr($receipt->code) : ''; ?>" class="form-control shadow-sm" readonly>
                    </div>
                <?php endif; ?>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Ngày thu</label>
                    <input type="date" name="receipt_date" value="<?php echo $is_edit ? esc_attr($receipt->receipt_date) : date('Y-m-d'); ?>" class="form-control shadow-sm bg-body" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control shadow-sm" name="note" placeholder="Ghi chú" rows="1"><?php echo $is_edit ? esc_textarea($receipt->note) : ''; ?></textarea>
                </div>
            </div>
            <?php if ($prefill_order_id && !$is_edit): $order = AERP_Acc_Receipt_Manager::get_prefill_from_order($prefill_order_id); ?>
                <div class="alert alert-secondary">
                    Tạo từ đơn: <strong>#<?php echo esc_html($order->order_code ?? $prefill_order_id); ?></strong>
                </div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label fw-bold">Dòng thu</label>
                <div id="receipt-lines-container" class=" border rounded">
                    <?php if (!empty($existing_lines)) : foreach ($existing_lines as $idx => $ln): ?>
                            <div class="row g-2 align-items-end mb-2 p-2 receipt-line-row">
                                <div class="col-md-4">
                                    <label class="form-label">Mã đơn</label>
                                    <select class="form-select order-select shadow-sm" name="lines[<?php echo $idx; ?>][order_id]" style="width:100%">
                                        <?php if (!empty($ln->order_id)):
                                            $pre_code = function_exists('aerp_get_order_code_by_id') ? aerp_get_order_code_by_id($ln->order_id) : '';
                                        ?>
                                            <option value="<?php echo esc_attr($ln->order_id); ?>" selected>#<?php echo esc_html($pre_code ?: $ln->order_id); ?></option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Số tiền</label>
                                    <input type="number" name="lines[<?php echo $idx; ?>][amount]" class="form-control receipt-amount shadow-sm" value="<?php echo esc_attr($ln->amount); ?>" min="0" step="0.01" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Ghi chú</label>
                                    <textarea class="form-control shadow-sm" name="lines[<?php echo $idx; ?>][note]" placeholder="Ghi chú" rows="1"><?php echo esc_textarea($ln->note); ?></textarea>
                                </div>
                                <div class="col-md-1 d-flex">
                                    <button type="button" class="btn btn-outline-danger remove-line w-100">Xóa</button>
                                </div>
                            </div>
                        <?php endforeach;
                    else: ?>
                        <div class="row g-2 align-items-end mb-2 p-2 receipt-line-row">
                            <div class="col-md-4">
                                <label class="form-label">Mã đơn</label>
                                <select class="form-select order-select shadow-sm" name="lines[0][order_id]" style="width:100%">
                                    <?php if ($prefill_order_id):
                                        $pre_code0 = function_exists('aerp_get_order_code_by_id') ? aerp_get_order_code_by_id($prefill_order_id) : '';
                                    ?>
                                        <option value="<?php echo esc_attr($prefill_order_id); ?>" selected>#<?php echo esc_html($pre_code0 ?: $prefill_order_id); ?></option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Số tiền</label>
                                <input type="number" name="lines[0][amount]" class="form-control receipt-amount shadow-sm" value="0" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Ghi chú</label>
                                <textarea type="text" class="form-control shadow-sm" name="lines[0][note]" placeholder="Ghi chú" rows="1"></textarea>
                            </div>
                            <div class="col-md-1 d-flex">
                                <button type="button" class="btn btn-outline-danger remove-line w-100">Xóa</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" id="add-receipt-line" class="btn btn-secondary mt-2">Thêm dòng</button>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tổng tiền</label>
                    <input type="number" name="total_amount" id="receipt-total" value="<?php echo $is_edit ? esc_attr($receipt->total_amount) : 0; ?>" class="form-control shadow-sm" min="0" step="0.01" required>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_acc_save_receipt" class="btn btn-primary">
                    <?= $is_edit ? 'Cập nhật' : 'Thêm mới' ?>
                </button>
                <button type="submit" name="aerp_acc_submit_receipt" value="1" class="btn btn-warning">Gửi duyệt</button>
                <?php if (aerp_user_has_role($user_id, 'admin') || aerp_user_has_role($user_id, 'accountant')): ?>
                    <button type="submit" name="aerp_acc_approve_receipt" value="1" class="btn btn-success">Xác nhận</button>
                <?php endif; ?>
                <a href="<?= home_url('/aerp-acc-receipts') ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<script>
    (function($) {
        let index = 1;
        $('#add-receipt-line').on('click', function() {
            const row = `<div class="row g-2 align-items-end mb-2 p-2 receipt-line-row">
                <div class="col-md-4">
                    <label class="form-label">Mã đơn</label>
                    <select class="form-select order-select" name="lines[${index}][order_id]" style="width:100%"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Số tiền</label>
                    <input type="number" name="lines[${index}][amount]" class="form-control receipt-amount" value="0" min="0" step="0.01" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ghi chú</label>
                    <textarea type="text" class="form-control shadow-sm" name="lines[${index}][note]" placeholder="Ghi chú" rows="1"></textarea>
                </div>
                <div class="col-md-1 d-flex">
                    <button type="button" class="btn btn-outline-danger remove-line w-100">Xóa</button>
                </div>
            </div>`;
            $('#receipt-lines-container').append(row);
            var $newSelect = $('#receipt-lines-container .receipt-line-row:last .order-select');
            initOrderSelect($newSelect);
            index++;
        });
        $(document).on('click', '.remove-line', function() {
            $(this).closest('.receipt-line-row').remove();
            recomputeTotal();
        });
        $(document).on('input', '.receipt-amount', function() {
            recomputeTotal();
        });

        function recomputeTotal() {
            let t = 0;
            $('.receipt-amount').each(function() {
                const v = parseFloat($(this).val() || 0);
                if (!isNaN(v)) t += v;
            });
            $('#receipt-total').val(t.toFixed(2));
        }
        // Init select2 cho chọn đơn hàng
        function initOrderSelect($select) {
            $select.select2({
                placeholder: 'Chọn đơn hàng',
                allowClear: true,
                ajax: {
                    url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'aerp_acc_search_orders_for_receipt',
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
                var $row = $(this).closest('.receipt-line-row');
                if (typeof data.collected_amount !== 'undefined') {
                    $row.find('.receipt-amount').val(parseFloat(data.collected_amount).toFixed(2)).trigger('input');
                }
            });
        }

        function initAllOrderSelects() {
            $('.order-select').each(function() {
                var $sel = $(this);
                if (!$sel.hasClass('select2-hidden-accessible')) {
                    initOrderSelect($sel);
                }
            });
        }

        function ensureSelect2ThenInit(attempts) {
            attempts = attempts || 0;
            if ($.fn && typeof $.fn.select2 === 'function') {
                initAllOrderSelects();
                return;
            }
            if (attempts < 50) { // retry up to ~5s
                setTimeout(function() {
                    ensureSelect2ThenInit(attempts + 1);
                }, 100);
            }
        }
        ensureSelect2ThenInit(0);
    })(jQuery);
</script>
<?php
$content = ob_get_clean();
$title = $is_edit ? 'Cập nhật phiếu thu' : 'Thêm phiếu thu';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
