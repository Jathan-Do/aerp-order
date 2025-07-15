<?php
if (!defined('ABSPATH')) exit;

class AERP_Product_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_products',
            'columns' => [
                'id' => 'ID',
                'name' => 'Tên sản phẩm',
                'sku' => 'Mã SKU',
                'category_id' => 'Danh mục',
                'unit_id' => 'Đơn vị tính',
                'price' => 'Giá bán lẻ',
                'whole_price' => 'Giá bán sỉ',
                'created_at' => 'Ngày tạo',
            ],
            'sortable_columns' => ['id', 'name', 'sku', 'category_id', 'unit_id', 'price', 'whole_price', 'created_at'],
            'searchable_columns' => ['name', 'sku'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-products'),
            'delete_item_callback' => ['AERP_Product_Manager', 'delete_product_by_id'],
            'nonce_action_prefix' => 'delete_product_',
            'message_transient_key' => 'aerp_product_message',
            'hidden_columns_option_key' => 'aerp_product_table_hidden_columns',
            'ajax_action' => 'aerp_product_filter_products',
            'table_wrapper' => '#aerp-product-table-wrapper',
        ]);
    }
    protected function column_price($item)
    {
        return number_format($item->price, 0) . ' đ';
    }
    protected function column_whole_price($item)
    {
        return number_format($item->whole_price, 0) . ' đ';
    }
    protected function column_category_id($item)
    {
        return $item->category_id ? esc_html(AERP_Product_Manager::get_category_name($item->category_id)) : '';
    }
    protected function column_unit_id($item)
    {
        return $item->unit_id ? esc_html(AERP_Product_Manager::get_unit_name($item->unit_id)) : '';
    }
    public function set_filters($filters = [])
    {
        parent::set_filters($filters); // Gọi cha để xử lý đầy đủ orderby, order, paged, search_term
    }
}
