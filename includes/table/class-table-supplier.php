<?php
if (!defined('ABSPATH')) exit;
class AERP_Supplier_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_suppliers',
            'columns' => [
                'name' => 'Tên nhà cung cấp',
                'phone' => 'Số điện thoại',
                'email' => 'Email',
                'address' => 'Địa chỉ',
                'note' => 'Ghi chú',
                'created_at' => 'Ngày tạo',
            ],
            'sortable_columns' => ['id', 'name', 'phone', 'email', 'created_at'],
            'searchable_columns' => ['name', 'phone', 'email', 'address'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-suppliers'),
            'delete_item_callback' => ['AERP_Supplier_Manager', 'delete'],
            'nonce_action_prefix' => 'delete_supplier_',
            'message_transient_key' => 'aerp_supplier_message',
            'hidden_columns_option_key' => 'aerp_supplier_table_hidden_columns',
            'ajax_action' => 'aerp_supplier_filter_suppliers',
            'table_wrapper' => '#aerp-supplier-table-wrapper',
        ]);
    }
} 