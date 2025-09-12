<?php
if (!defined('ABSPATH')) exit;

class AERP_Acc_Payment_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_acc_payments',
            'columns' => [
                'code' => 'Mã',
                'payment_date' => 'Ngày',
                'category_id' => 'Loại phiếu (dòng)', 
                'payee_type' => 'Loại chi', 
                'payer_employee_id' => 'Người chi',
                'payee' => 'Người nhận',
                'payment_method' => 'PT thanh toán',
                'bank_account' => 'Số TK',
                'content' => 'Nội dung',
                'note' => 'Ghi chú',
                'total_amount' => 'Tổng tiền',
                'status' => 'Trạng thái',
                'created_at' => 'Ngày tạo',
                'action' => 'Thao tác',
            ],
            'sortable_columns' => ['code','payment_date','total_amount','status','created_at'],
            'searchable_columns' => ['code','note'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => [],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-acc-payments'),
            'delete_item_callback' => ['AERP_Acc_Payment_Manager', 'delete_by_id'],
            'nonce_action_prefix' => 'delete_acc_payment_',
            'message_transient_key' => 'aerp_acc_payment_message',
            'hidden_columns_option_key' => 'aerp_acc_payment_table_hidden_columns',
            'ajax_action' => 'aerp_acc_payment_filter_payments',
            'table_wrapper' => '#aerp-acc-payment-table-wrapper',
        ]);
    }
    protected function get_extra_filters()
    {
        $filters = [];
        $params = [];
        if (!empty($this->filters['status'])) {
            $filters[] = "status = %s";
            $params[] = $this->filters['status'];
        }
        if (!empty($this->filters['date_from'])) {
            $filters[] = "payment_date >= %s";
            $params[] = $this->filters['date_from'];
        }
        if (!empty($this->filters['date_to'])) {
            $filters[] = "payment_date <= %s";
            $params[] = $this->filters['date_to'];
        }
        if (!empty($this->filters['employee_id'])) {
            $filters[] = "payer_employee_id = %d";
            $params[] = (int)$this->filters['employee_id'];
        }
        return [$filters, $params];
    }
    protected function column_total_amount($item)
    {
        return number_format($item->total_amount, 0, ',', '.') . ' đ';
    }
    protected function column_category_id($item)
    {
        // Hiển thị các danh mục theo dòng (distinct) nếu có, fallback về category_id tổng
        global $wpdb;
        $lineCats = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT category_id FROM {$wpdb->prefix}aerp_acc_payment_lines WHERE payment_id = %d AND category_id IS NOT NULL",
            $item->id
        ));
        if (!empty($lineCats)) {
            $names = [];
            foreach ($lineCats as $cid) {
                $cid = (int)$cid;
                if ($cid > 0) {
                    $label = '';
                    if (class_exists('AERP_Acc_Category_Manager')) {
                        $label = AERP_Acc_Category_Manager::get_name($cid);
                    }
                    if ($label === '' || $label === null) {
                        $label = '#' . $cid;
                    }
                    // Append (Có hạch toán) nếu danh mục có is_accounted = 1
                    $is_acc = $wpdb->get_var($wpdb->prepare(
                        "SELECT is_accounted FROM {$wpdb->prefix}aerp_acc_categories WHERE id = %d",
                        $cid
                    ));
                    if (!empty($is_acc)) {
                        $label .= ' (Có hạch toán)';
                    }
                    $names[] = esc_html($label);
                }
            }
            return implode(', ', $names);
        }
        // Fallback: nếu chưa có line hoặc chưa migrate, dùng category_id ở bảng tổng
        if (!empty($item->category_id)) {
            $cid = (int)$item->category_id;
            $label = class_exists('AERP_Acc_Category_Manager') ? (AERP_Acc_Category_Manager::get_name($cid) ?: ('#' . $cid)) : ('#' . $cid);
            $is_acc = $wpdb->get_var($wpdb->prepare(
                "SELECT is_accounted FROM {$wpdb->prefix}aerp_acc_categories WHERE id = %d",
                $cid
            ));
            if (!empty($is_acc)) {
                $label .= ' (Có hạch toán)';
            }
            return esc_html($label);
        }
        return '';
    }
    protected function column_payee_type($item)
    {
        $map = [
            'employee' => 'Nhân viên',
            'supplier' => 'Nhà cung cấp',
            'customer' => 'Khách hàng',
            'other' => 'Khác',
        ];
        $t = isset($item->payee_type) ? $item->payee_type : '';
        return esc_html($map[$t] ?? $t);
    }
    protected function column_payer_employee_id($item)
    {
        if (empty($item->payer_employee_id)) return '<span class="text-muted">-</span>';
        global $wpdb;
        $name = $wpdb->get_var($wpdb->prepare(
            "SELECT full_name FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d",
            $item->payer_employee_id
        ));
        return $name ? esc_html($name) : '<span class="text-muted">#' . intval($item->payer_employee_id) . '</span>';
    }
    protected function column_payee($item)
    {
        global $wpdb;
        // Ưu tiên hiển thị theo dữ liệu thực tế: có ID nhân viên -> lấy nhân viên; nếu không, có NCC -> lấy NCC; nếu không, dùng text
        if (!empty($item->payee_employee_id)) {
            $name = $wpdb->get_var($wpdb->prepare(
                "SELECT full_name FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d",
                $item->payee_employee_id
            ));
            return $name ? esc_html($name) : '<span class="text-muted">#' . intval($item->payee_employee_id) . '</span>';
        }
        if (!empty($item->supplier_id)) {
            $name = $wpdb->get_var($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}aerp_suppliers WHERE id = %d",
                $item->supplier_id
            ));
            return $name ? esc_html($name) : '<span class="text-muted">#' . intval($item->supplier_id) . '</span>';
        }
        if (!empty($item->customer_id)) {
            $name = $wpdb->get_var($wpdb->prepare(
                "SELECT full_name FROM {$wpdb->prefix}aerp_crm_customers WHERE id = %d",
                $item->customer_id
            ));
            return $name ? esc_html($name) : '<span class="text-muted">#' . intval($item->customer_id) . '</span>';
        }
        return !empty($item->payee_text) ? esc_html($item->payee_text) : '<span class="text-muted">-</span>';
    }
    protected function column_payment_method($item)
    {
        $map = [
            'cash' => 'Tiền mặt',
            'bank_transfer' => 'Chuyển khoản',
            'card' => 'Thẻ',
            'other' => 'Khác',
        ];
        $method = isset($item->payment_method) ? $item->payment_method : 'cash';
        return esc_html($map[$method] ?? $method);
    }
    protected function column_content($item)
    {
        global $wpdb;
        // Lấy tất cả các dòng payment_lines cho payment này, nối description lại
        $lines = $wpdb->get_col($wpdb->prepare(
            "SELECT description FROM {$wpdb->prefix}aerp_acc_payment_lines WHERE payment_id = %d AND description IS NOT NULL AND description != ''",
            $item->id
        ));
        if (!empty($lines)) {
            // Ghép các description lại, mỗi dòng 1 dòng
            return esc_html(implode(", ", $lines));
        }
        return '<span class="text-muted">-</span>';
    }
    protected function column_bank_account($item)
    {
        return !empty($item->bank_account) ? esc_html($item->bank_account) : '<span class="text-muted">-</span>';
    }
    protected function column_status($item)
    {
        $st = isset($item->status) ? $item->status : 'draft';
        $map = [
            'draft' => '<span class="badge bg-secondary">Nháp</span>',
            'confirmed' => '<span class="badge bg-primary">Đã xác nhận</span>',
            'paid' => '<span class="badge bg-success">Đã chi</span>',
        ];
        return $map[$st] ?? esc_html($st);
    }
    protected function column_action($item)
    {
        $user_id = get_current_user_id();
        $is_admin = function_exists('aerp_user_has_role') ? aerp_user_has_role($user_id, 'admin') : false;
        $is_accountant = function_exists('aerp_user_has_role') ? aerp_user_has_role($user_id, 'accountant') : false;
        $id = intval($item->id);
        $current_status = isset($item->status) ? $item->status : '';
        $edit_url = add_query_arg(['action' => 'edit', 'id' => $id], $this->base_url);
        $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $id], $this->base_url), $this->nonce_action_prefix . $id);

        $buttons = [];

        // Nếu là admin thì luôn hiện nút edit và xóa bình thường
        if ($is_admin) {
            $buttons[] = sprintf(
                '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Chỉnh sửa" href="%s" class="btn btn-sm btn-success mb-2"><i class="fas fa-edit"></i></a>',
                esc_url($edit_url)
            );
            $buttons[] = sprintf(
                '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Xóa" href="%s" class="btn btn-sm btn-danger mb-2" onclick="return confirm(\'Bạn có chắc muốn xóa?\')"><i class="fas fa-trash"></i></a>',
                esc_url($delete_url)
            );
        } elseif ($is_accountant) {
            // Kế toán: không được xóa khi đã paid
            $buttons[] = sprintf(
                '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Chỉnh sửa" href="%s" class="btn btn-sm btn-success mb-2"><i class="fas fa-edit"></i></a>',
                esc_url($edit_url)
            );
            if ($current_status === 'paid') {
                $buttons[] = '<button class="btn btn-sm btn-danger mb-2" disabled data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Không thể xóa phiếu đã chi"><i class="fas fa-trash"></i></button>';
            } else {
                $buttons[] = sprintf(
                    '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Xóa" href="%s" class="btn btn-sm btn-danger mb-2" onclick="return confirm(\'Bạn có chắc muốn xóa?\')"><i class="fas fa-trash"></i></a>',
                    esc_url($delete_url)
                );
            }
        } else {
            // Nếu trạng thái đã xác nhận thì disable nút edit cho user khác
            if ($current_status === 'confirmed' || $current_status === 'paid') {
                $buttons[] = '<button class="btn btn-sm btn-success mb-2" disabled data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Không thể chỉnh sửa phiếu đã duyệt"><i class="fas fa-edit"></i></button>';
                // Đã chi: không xóa cho user thường
                if ($current_status === 'paid') {
                    $buttons[] = '<button class="btn btn-sm btn-danger mb-2" disabled data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Không thể xóa phiếu đã chi"><i class="fas fa-trash"></i></button>';
                } else {
                    $buttons[] = sprintf(
                        '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Xóa" href="%s" class="btn btn-sm btn-danger mb-2" onclick="return confirm(\'Bạn có chắc muốn xóa?\')"><i class="fas fa-trash"></i></a>',
                        esc_url($delete_url)
                    );
                }
            } else {
                $buttons[] = sprintf(
                    '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Chỉnh sửa" href="%s" class="btn btn-sm btn-success mb-2"><i class="fas fa-edit"></i></a>',
                    esc_url($edit_url)
                );
                $buttons[] = sprintf(
                    '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Xóa" href="%s" class="btn btn-sm btn-danger mb-2" onclick="return confirm(\'Bạn có chắc muốn xóa?\')"><i class="fas fa-trash"></i></a>',
                    esc_url($delete_url)
                );
            }
        }

        return implode(' ', $buttons);
    }
}



