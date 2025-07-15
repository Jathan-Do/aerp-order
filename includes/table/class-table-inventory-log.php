<?php
if (!defined('ABSPATH')) exit;

class AERP_Inventory_Log_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_inventory_logs',
            'columns' => [
                'id' => 'ID',
                'product_id' => 'Sản phẩm',
                'type' => 'Loại phiếu',
                'quantity' => 'Số lượng',
                'note' => 'Ghi chú',
                'status' => 'Trạng thái',
                'created_by' => 'Người tạo',
                'created_at' => 'Ngày tạo',
                'action' => 'Thao tác',
            ],
            'sortable_columns' => ['id', 'product_id', 'note', 'quantity', 'type', 'status'],
            'searchable_columns' => ['product_id', 'type', 'quantity'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => [],
            'bulk_actions' => ['delete'],
            'delete_item_callback' => ['AERP_Inventory_Log_Manager', 'delete_log_by_id'],
            'nonce_action_prefix' => 'delete_inventory_log_',
            'base_url' => home_url('/aerp-inventory-logs'),
            'message_transient_key' => 'aerp_inventory_log_message',
            'hidden_columns_option_key' => 'aerp_inventory_log_table_hidden_columns',
            'ajax_action' => 'aerp_inventory_log_filter_inventory_logs',
            'table_wrapper' => '#aerp-inventory-log-table-wrapper',
        ]);
    }
    protected function column_action($item)
    {
        $id = intval($item->id);

        if ($item->status === 'confirmed') {
            return '<a href="#" class="btn btn-sm btn-success disabled mb-2 mb-md-0"><i class="fas fa-edit"></i></a> 
         <a href="#" class="btn btn-sm btn-danger disabled"><i class="fas fa-trash"></i></a>';
        }

        $edit_url = $item->type === 'stocktake'
            ? home_url('/aerp-stocktake/?action=edit&edit=' . $id)
            : add_query_arg(['action' => 'edit', 'edit' => $id], $this->base_url);

        $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $id], $this->base_url), $this->nonce_action_prefix . $id);

        return sprintf(
            '<a href="%s" class="btn btn-sm btn-success mb-2 mb-md-0"><i class="fas fa-edit"></i></a> 
         <a href="%s" class="btn btn-sm btn-danger" onclick="return confirm(\'Bạn có chắc muốn xóa?\')"><i class="fas fa-trash"></i></a>',
            esc_url($edit_url),
            esc_url($delete_url)
        );
    }
    protected function column_product_id($item)
    {
        $product = function_exists('aerp_get_product') ? aerp_get_product($item->product_id) : null;
        return $product ? esc_html($product->name) : 'ID: ' . intval($item->product_id);
    }
    protected function column_type($item)
    {
        return $item->type === 'import' ? '<span class="badge bg-success">Nhập kho</span>' : ($item->type === 'export' ? '<span class="badge bg-danger">Xuất kho</span>' : '<span class="badge bg-warning">Kiểm kho</span>');
    }
    protected function column_status($item)
    {
        return $item->status === 'confirmed'
            ? '<span class="badge bg-primary">Đã xác nhận</span>'
            : '<span class="badge bg-secondary">Nháp</span>';
    }
    protected function column_created_by($item)
    {
        $user = get_userdata($item->created_by);
        return $user ? esc_html($user->display_name) : 'ID: ' . intval($item->created_by);
    }
    protected function get_extra_search_conditions($search_term)
    {
        global $wpdb;
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        // Join sang bảng trạng thái để search theo tên
        $extra = [];
        $params = [];
        $extra[] = "product_id IN (SELECT id FROM {$wpdb->prefix}aerp_products WHERE name LIKE %s)";
        $params[] = $search_term;
        $extra[] = "type LIKE %s";
        $params[] = $search_term;
        return [$extra, $params];
    }
    protected function get_extra_filters()
    {
        $filters = [];
        $params = [];
        if (!empty($this->filters['status'])) {
            $filters[] = "status = %s";
            $params[] = $this->filters['status'];
        }
        if (!empty($this->filters['type'])) {
            $filters[] = "type = %s";
            $params[] = $this->filters['type'];
        }
        return [$filters, $params];
    }
}
