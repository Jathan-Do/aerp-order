<?php
if (!defined('ABSPATH')) exit;

class AERP_Category_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_product_categories',
            'columns' => [
                'id' => 'ID',
                'name' => 'Tên danh mục',
                'parent_id' => 'Danh mục cha',
            ],
            'sortable_columns' => ['id', 'name', 'parent_id'],
            'searchable_columns' => ['name'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-product-categories'),
            'delete_item_callback' => ['AERP_Category_Manager', 'delete_category_by_id'],
            'nonce_action_prefix' => 'delete_category_',
            'message_transient_key' => 'aerp_category_message',
            'hidden_columns_option_key' => 'aerp_category_table_hidden_columns',
            'ajax_action' => 'aerp_category_filter_categories',
            'table_wrapper' => '#aerp-category-table-wrapper',
        ]);
    }
    protected function column_parent_id($item)
    {
        if (!$item->parent_id) return '-';
        global $wpdb;
        $table = $GLOBALS['wpdb']->prefix . 'aerp_product_categories';
        $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $table WHERE id = %d", $item->parent_id));
        return $name ? esc_html($name) : '-';
    }
} 