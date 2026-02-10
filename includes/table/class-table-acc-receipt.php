<?php
if (!defined('ABSPATH')) exit;

class AERP_Acc_Receipt_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_acc_receipts',
            'columns' => [
                'code' => 'Mã',
                'receipt_date' => 'Ngày thu',
                'total_amount' => 'Tổng tiền',
                'status' => 'Trạng thái',
                'created_by' => 'Người tạo',
                'approved_by' => 'Người duyệt',
                'order_row'=>'Đơn hàng từng dòng',
                'note' => 'Ghi chú tổng',
                'created_at' => 'Ngày tạo',
                'action' => 'Thao tác',
            ],
            'sortable_columns' => ['code','receipt_date','total_amount','status','created_at'],
            'searchable_columns' => ['code','note'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => [],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-acc-receipts'),
            'delete_item_callback' => ['AERP_Acc_Receipt_Manager', 'delete_by_id'],
            'nonce_action_prefix' => 'delete_acc_receipt_',
            'message_transient_key' => 'aerp_acc_receipt_message',
            'hidden_columns_option_key' => 'aerp_acc_receipt_table_hidden_columns',
            'ajax_action' => 'aerp_acc_receipt_filter_receipts',
            'table_wrapper' => '#aerp-acc-receipt-table-wrapper',
        ]);
    }
    protected function get_extra_filters()
    {
        global $wpdb;
        $filters = [];
        $params = [];

        $current_user_id = get_current_user_id();

        $is_admin = function_exists('aerp_user_has_role') ? aerp_user_has_role($current_user_id, 'admin') : false;
        $is_accountant = function_exists('aerp_user_has_role') ? aerp_user_has_role($current_user_id, 'accountant') : false;
        $is_department_lead = function_exists('aerp_user_has_role') ? aerp_user_has_role($current_user_id, 'department_lead') : false;

        // Admin và Kế toán: không giới hạn theo người/chi nhánh (nhưng vẫn áp dụng filter trạng thái ở cuối)
        if (!($is_admin || $is_accountant)) {
            // Lấy employee của user hiện tại
            $current_employee = $wpdb->get_row($wpdb->prepare(
                "SELECT id, work_location_id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
                $current_user_id
            ));
            // Nếu là trưởng phòng: xem các phiếu do nhân viên trong chi nhánh tạo
            if ($is_department_lead && $current_employee && !empty($current_employee->work_location_id)) {
                $branch_employee_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE work_location_id = %d",
                    $current_employee->work_location_id
                ));
                if (!empty($branch_employee_ids)) {
                    $placeholders = implode(',', array_fill(0, count($branch_employee_ids), '%d'));
                    $filters[] = "created_by IN ($placeholders)";
                    $params = array_merge($params, array_map('intval', $branch_employee_ids));
                } else {
                    // Nếu chi nhánh không có nhân viên nào: chỉ xem phiếu mình tạo
                    $created_by_id = $current_employee ? intval($current_employee->id) : 0;
                    $filters[] = "created_by = %d";
                    $params[] = $created_by_id;
                }
            } else {
                // Mặc định: chỉ xem các phiếu mình tạo
                $created_by_id = $current_employee ? intval($current_employee->id) : 0;
                $filters[] = "created_by = %d";
                $params[] = $created_by_id;
            }
        }
        if (!empty($this->filters['date_from'])) {
            $filters[] = "receipt_date >= %s";
            $params[] = $this->filters['date_from'];
        }
        if (!empty($this->filters['date_to'])) {
            $filters[] = "receipt_date <= %s";
            $params[] = $this->filters['date_to'];
        }
        if (!empty($this->filters['status'])) {
            $filters[] = "status = %s";
            $params[] = $this->filters['status'];
        }
        return [$filters, $params];
    }
    protected function column_total_amount($item)
    {
        return number_format($item->total_amount, 0, ',', '.') . ' đ';
    }
    protected function column_status($item)
    {
        switch ($item->status) {
            case 'approved':
                return '<span class="badge bg-success">Đã duyệt</span>';
            case 'submitted':
                return '<span class="badge bg-primary">Chờ duyệt</span>';
            case 'draft':
                return '<span class="badge bg-secondary">Nháp</span>';
            case 'rejected':
                return '<span class="badge bg-danger">Từ chối</span>';
            case 'cancelled':
                return '<span class="badge bg-dark">Đã hủy</span>';
            default:
                return '<span class="badge bg-secondary">Không xác định</span>';
        }
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

        // Nếu là admin hoặc accountant thì luôn hiện nút edit và xóa bình thường
        if ($is_admin || $is_accountant) {
            $buttons[] = sprintf(
                '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Chỉnh sửa" href="%s" class="btn btn-sm btn-success mb-2"><i class="fas fa-edit"></i></a>',
                esc_url($edit_url)
            );
            $buttons[] = sprintf(
                '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Xóa" href="%s" class="btn btn-sm btn-danger mb-2" onclick="return confirm(\'Bạn có chắc muốn xóa?\')"><i class="fas fa-trash"></i></a>',
                esc_url($delete_url)
            );
        } else {
            // Nếu trạng thái đã là approved thì disable nút edit và xóa cho user khác
            if ($current_status === 'approved') {
                $buttons[] = '<button class="btn btn-sm btn-success mb-2" disabled data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Không thể chỉnh sửa phiếu đã duyệt"><i class="fas fa-edit"></i></button>';
                $buttons[] = '<button class="btn btn-sm btn-danger mb-2" disabled data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Không thể xóa phiếu đã duyệt"><i class="fas fa-trash"></i></button>';
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
    protected function column_created_by($item)
    {
        // $item->created_by là employee_id
        if (empty($item->created_by)) {
            return '<span class="text-muted">-</span>';
        }
        global $wpdb;
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT full_name FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d",
            $item->created_by
        ));
        if ($employee && !empty($employee->full_name)) {
            return esc_html($employee->full_name);
        }
        return '<span class="text-muted">#' . intval($item->created_by) . '</span>';
    }

    protected function column_approved_by($item)
    {
        // $item->approved_by là employee_id
        if (empty($item->approved_by)) {
            return '<span class="text-muted">-</span>';
        }
        global $wpdb;
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT full_name FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d",
            $item->approved_by
        ));
        if ($employee && !empty($employee->full_name)) {
            return esc_html($employee->full_name);
        }
        return '<span class="text-muted">#' . intval($item->approved_by) . '</span>';
    }
    protected function column_order_row($item)
    {
        if (empty($item->id)) {
            return '<span class="text-muted">-</span>';
        }
        
        global $wpdb;
        $lines = $wpdb->get_results($wpdb->prepare(
            "SELECT order_id, note FROM {$wpdb->prefix}aerp_acc_receipt_lines WHERE receipt_id = %d ORDER BY id ASC",
            $item->id
        ));
        
        if (empty($lines)) {
            return '<span class="text-muted">-</span>';
        }
        
        $order_links = [];
        foreach ($lines as $line) {
            $display_text = '';
            
            // Hiển thị mã đơn hàng nếu có
            if ($line->order_id) {
                $order_code = $wpdb->get_var($wpdb->prepare(
                    "SELECT order_code FROM {$wpdb->prefix}aerp_order_orders WHERE id = %d",
                    $line->order_id
                ));
                if ($order_code) {
                    $display_text = '<a href="' . home_url('/aerp-order-orders/' . $line->order_id) . '" class="text-primary">' . esc_html($order_code) . '</a>';
                } else {
                    $display_text = '<span class="text-muted">#' . intval($line->order_id) . '</span>';
                }
            }
            
            // Thêm ghi chú nếu có
            if (!empty($line->note)) {
                if (!empty($display_text)) {
                    $display_text .= ' - ' . esc_html($line->note);
                } else {
                    $display_text = esc_html($line->note);
                }
            }
            
            if (!empty($display_text)) {
                $order_links[] = $display_text;
            }
        }
        
        return implode('<br>', $order_links);
    }
}


