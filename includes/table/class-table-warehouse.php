<?php
if (!defined('ABSPATH')) exit;
class AERP_Warehouse_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        global $wpdb;
        parent::__construct([
            'table_name' => $wpdb->prefix . 'aerp_warehouses',
            'columns' => [
                'name' => 'Tên kho',
                'work_location_id' => 'Vị trí',
                'warehouse_type' => 'Loại kho',
                'owner_user_id' => 'Người quản lí',
                'created_at' => 'Ngày tạo',
            ],
            'sortable_columns' => ['id', 'name', 'work_location_id'],
            'searchable_columns' => ['name', 'work_location_id'],
            'primary_key' => 'id',
            'per_page' => 20,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-warehouses'),
            'delete_item_callback' => ['AERP_Warehouse_Manager', 'delete_by_id'],
            'nonce_action_prefix' => 'delete_warehouse_',
            'message_transient_key' => 'aerp_warehouse_message',
            'hidden_columns_option_key' => 'aerp_warehouse_table_hidden_columns',
            'ajax_action' => 'aerp_warehouse_filter_warehouses',
            'table_wrapper' => '#aerp-warehouse-table-wrapper',
        ]);
    }
    protected function get_extra_filters()
    {
        global $wpdb;
        $filters = [];
        $params = [];

        // Nếu là admin thì không filter gì cả, được thấy hết
        if (!empty($this->filters['manager_user_id'])) {
            $user_id = (int)$this->filters['manager_user_id'];
            if (function_exists('aerp_user_has_role') && aerp_user_has_role($user_id, 'admin')) {
                // Admin: không filter, thấy tất cả
            } else {
                // Lấy kho chi nhánh mà user quản lý
                $branch_warehouse_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT warehouse_id FROM {$wpdb->prefix}aerp_warehouse_managers 
                     WHERE user_id = %d AND manager_type = 'branch_manager'",
                    $user_id
                ));

                // Lấy kho cá nhân của user
                $personal_warehouse_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}aerp_warehouses 
                     WHERE warehouse_type = 'personal' AND owner_user_id = %d",
                    $user_id
                ));

                // Gộp tất cả warehouse IDs
                $all_warehouse_ids = array_merge($branch_warehouse_ids, $personal_warehouse_ids);

                if (!empty($all_warehouse_ids)) {
                    $placeholders = implode(',', array_fill(0, count($all_warehouse_ids), '%d'));
                    $filters[] = "id IN ($placeholders)";
                    $params = array_merge($params, $all_warehouse_ids);
                } else {
                    // Nếu không có kho nào thì trả về điều kiện không có kết quả
                    $filters[] = "0=1";
                }
            }
        }

        if (!empty($this->filters['warehouse_type'])) {
            $filters[] = "warehouse_type = %s";
            $params[] = $this->filters['warehouse_type'];
        }

        return [$filters, $params];
    }
    protected function column_work_location_id($item)
    {
        $location = AERP_Work_Location_Manager::get_by_id($item->work_location_id);
        return $location ? esc_html($location->name) : '--';
    }
    protected function column_owner_user_id($item)
    {
        global $wpdb;

        if ($item->warehouse_type === 'personal' && !empty($item->owner_user_id)) {
            // Lấy tên nhân viên từ owner_user_id
            if (function_exists('aerp_get_employee_name')) {
                $owner_name = aerp_get_employee_name($item->owner_user_id);
                return $owner_name ? esc_html($owner_name) : '--';
            }
            return esc_html($item->owner_user_id);
        } elseif ($item->warehouse_type === 'branch') {
            // Lấy danh sách user_id quản lý kho chi nhánh này
            $manager_user_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}aerp_warehouse_managers WHERE warehouse_id = %d AND manager_type = 'branch_manager'",
                $item->id
            ));
            if (!empty($manager_user_ids)) {
                $names = [];
                foreach ($manager_user_ids as $user_id) {
                    if (function_exists('aerp_get_employee_name')) {
                        $name = aerp_get_employee_name($user_id);
                        $names[] = $name ? esc_html($name) : esc_html($user_id);
                    } else {
                        $names[] = esc_html($user_id);
                    }
                }
                return implode(', ', $names);
            } else {
                return '--';
            }
        }
        return '--';
    }

    protected function column_warehouse_type($item)
    {
        $types = [
            'branch'   => 'Kho chi nhánh',
            'personal' => 'Kho cá nhân',
        ];
        $type = $item->warehouse_type ?? '';
        return isset($types[$type]) ? esc_html($types[$type]) : esc_html($type);
    }
}
