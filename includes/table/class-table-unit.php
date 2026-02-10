<?php
if (!defined('ABSPATH')) exit;

class AERP_Unit_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_units',
            'columns' => [
                // 'id' => 'ID',
                'name' => 'Tên đơn vị',
                'symbol' => 'Ký hiệu',
            ],
            'sortable_columns' => ['id', 'name', 'symbol'],
            'searchable_columns' => ['name', 'symbol'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-units'),
            'delete_item_callback' => ['AERP_Unit_Manager', 'delete_unit_by_id'],
            'nonce_action_prefix' => 'delete_unit_',
            'message_transient_key' => 'aerp_unit_message',
            'hidden_columns_option_key' => 'aerp_unit_table_hidden_columns',
            'ajax_action' => 'aerp_unit_filter_units',
            'table_wrapper' => '#aerp-unit-table-wrapper',
        ]);
    }
} 