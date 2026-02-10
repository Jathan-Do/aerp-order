<?php
if (!defined('ABSPATH')) exit;

class AERP_Acc_Category_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_acc_categories',
            'columns' => [
                'name' => 'Tên',
                'code' => 'Mã',
                'is_accounted' => 'Hạch toán',
                'active' => 'Kích hoạt',
                'created_at' => 'Ngày tạo',
            ],
            'sortable_columns' => ['name','code','created_at'],
            'searchable_columns' => ['name','code'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit','delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-acc-categories'),
            'delete_item_callback' => ['AERP_Acc_Category_Manager', 'delete_by_id'],
            'nonce_action_prefix' => 'delete_acc_category_',
            'message_transient_key' => 'aerp_acc_category_message',
            'hidden_columns_option_key' => 'aerp_acc_category_table_hidden_columns',
            'ajax_action' => 'aerp_acc_filter_categories',
            'table_wrapper' => '#aerp-acc-category-table-wrapper',
        ]);
    }
    protected function column_is_accounted($item)
    {
        return !empty($item->is_accounted) ? '<span class="badge bg-success">Có</span>' : '<span class="badge bg-secondary">Không</span>';
    }
    protected function column_active($item)
    {
        return (isset($item->active) && !$item->active) ? '<span class="badge bg-secondary">Không</span>' : '<span class="badge bg-success">Có</span>';
    }
}


