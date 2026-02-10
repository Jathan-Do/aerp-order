<?php
if (!defined('ABSPATH')) exit;

class AERP_Device_Progress_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_order_device_progresses',
            'columns' => [
                'name' => 'Tên tiến độ',
                'description' => 'Mô tả',
                'color' => 'Màu sắc',
                'is_active' => 'Trạng thái',
            ],
            'sortable_columns' => ['id', 'name', 'is_active'],
            'searchable_columns' => ['name', 'description', 'color'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-device-progresses'),
            'delete_item_callback' => ['AERP_Device_Progress_Manager', 'delete_progress_by_id'],
            'nonce_action_prefix' => 'delete_progress_',
            'message_transient_key' => 'aerp_device_progress_message',
            'hidden_columns_option_key' => 'aerp_device_progress_table_hidden_columns',
            'ajax_action' => 'aerp_device_progress_filter',
            'table_wrapper' => '#aerp-device-progress-table-wrapper',
        ]);
    }

    protected function column_color($item)
    {
        $color = $item->color ?? '#007cba';
        return sprintf(
            '<div class="d-flex align-items-center">
                <div class="color-preview me-2" style="width: 20px; height: 20px; background-color: %s; border-radius: 4px; border: 1px solid #ddd;"></div>
                <span>%s</span>
            </div>',
            esc_attr($color),
            esc_html($color)
        );
    }

    protected function column_is_active($item)
    {
        $is_active = $item->is_active ?? 1;
        if ($is_active) {
            return '<span class="badge bg-success">Bật</span>';
        } else {
            return '<span class="badge bg-secondary">Tắt</span>';
        }
    }

    protected function get_extra_filters()
    {
        $filters = [];
        $params = [];

        if (isset($this->filters['is_active']) && $this->filters['is_active'] !== '' && $this->filters['is_active'] !== null) {
            $filters[] = 'is_active = %d';
            $params[] = (int)$this->filters['is_active'];
        }
        if (!empty($this->filters['color'])) {
            $filters[] = 'color = %s';
            $params[] = sanitize_text_field($this->filters['color']);
        }

        return [$filters, $params];
    }
}
