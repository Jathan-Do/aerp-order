<?php
if (!defined('ABSPATH')) exit;

class AERP_Device_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_order_devices',
            'columns' => [
                'id' => 'ID',
                'device_name' => 'Tên thiết bị',
                'serial_number' => 'Serial/IMEI',
                'status' => 'Tình trạng',
                'note' => 'Ghi chú',
                'partner_id' => 'Nhà cung cấp',
            ],
            'sortable_columns' => ['id', 'device_name', 'serial_number', 'status', 'partner_id'],
            'searchable_columns' => ['device_name', 'serial_number', 'status', 'note', 'partner_id'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-devices'),
            'delete_item_callback' => ['AERP_Device_Manager', 'delete_device_by_id'],
            'nonce_action_prefix' => 'delete_device_',
            'message_transient_key' => 'aerp_device_message',
            'hidden_columns_option_key' => 'aerp_device_table_hidden_columns',
            'ajax_action' => 'aerp_device_filter_devices',
            'table_wrapper' => '#aerp-device-table-wrapper',
        ]);
    }
    protected function column_partner_id($item)
    {
        return AERP_Supplier_Manager::get_by_id($item->partner_id)->name;
    }
    protected function get_extra_filters()
    {
        $filters = [];
        $params = [];

        if (!empty($this->filters['partner_id'])) {
            $filters[] = 'partner_id = %s';
            $params[] = $this->filters['partner_id'];
        }

        return [$filters, $params];
    }

} 