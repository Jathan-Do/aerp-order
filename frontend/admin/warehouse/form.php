<?php
if (!defined('ABSPATH')) exit;
// Get current user
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

if (!is_user_logged_in()) {
    wp_die(__('You must be logged in to access this page.'));
}

// Danh sách điều kiện, chỉ cần 1 cái đúng là qua
$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'department_lead'),
    aerp_user_has_permission($user_id,'warehouse_add'),
    aerp_user_has_permission($user_id,'warehouse_edit'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
AERP_Warehouse_Manager::handle_form_submit();
$is_edit = isset($_GET['id']);
$warehouse = $is_edit ? AERP_Warehouse_Manager::get_by_id($_GET['id']) : null;


// Lấy danh sách user đang quản lý kho (nếu là edit)
$user_ids_selected = [];
$selected_users = [];
if ($is_edit && $warehouse) {
    global $wpdb;
    $user_ids_selected = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->prefix}aerp_warehouse_managers WHERE warehouse_id = %d",
        $warehouse->id
    ));
    if (!empty($user_ids_selected)) {
        $placeholders = implode(',', array_fill(0, count($user_ids_selected), '%d'));
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT e.id, e.full_name, wl.name as work_location_name
             FROM {$wpdb->prefix}aerp_hrm_employees e
             LEFT JOIN {$wpdb->prefix}aerp_hrm_work_locations wl ON e.work_location_id = wl.id
             WHERE e.id IN ($placeholders)",
            ...$user_ids_selected
        ));
        foreach ($users as $u) {
            $display = $u->full_name;
            if (!empty($u->work_location_name)) {
                $display .= ' - ' . $u->work_location_name;
            }
            $selected_users[] = [
                'id' => $u->id,
                'text' => $display,
            ];
        }
    }
}
ob_start();
?>
<style>
    /* Fix select2 multi search field luôn hiện đúng */
    .select2-container--default .select2-selection--multiple {
        min-height: 40px !important;
        border: 1px solid #ced4da !important;
        border-radius: 4px !important;
        background: #fff !important;
        padding: 0.375rem 0.75rem !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
    }

    .select2-container--default .select2-selection--multiple .select2-search__field {
        width: 100% !important;
        min-width: 120px !important;
        margin-top: 4px !important;
        margin-bottom: 0 !important;
        margin-left: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        display: inline-block !important;
        height: 25px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        gap: 2px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice,
    .select2-selection.select2-selection--multiple.select2-selection--clearable ul {
        margin: 0;
    }
    .select2-container--default .select2-selection--single {
        width: 100% !important;
        border: 1px solid #dee2e6 !important;
        border-radius: 0.375rem !important;
        height: 38px !important;
        min-height: 38px !important;
        padding: 6px 12px !important;
        background: #fff !important;
        font-size: 1rem !important;
        box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075) !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 24px !important;
        padding-left: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
        right: 0.75rem !important;
    }
</style>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?php echo $is_edit ? 'Sửa kho' : 'Thêm kho'; ?></h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Quản lý kho', 'url' => home_url('/aerp-warehouses')],
        ['label' => ($is_edit ? 'Sửa kho' : 'Thêm kho')]
    ]);
}
?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_warehouse_action', 'aerp_save_warehouse_nonce'); ?>
            <?php if ($is_edit): ?><input type="hidden" name="id" value="<?php echo esc_attr($warehouse->id); ?>"><?php endif; ?>
            <div class="mb-3">
                <label class="form-label">Tên kho</label>
                <input type="text" name="name" class="form-control shadow-sm" value="<?php echo esc_attr($warehouse->name ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Loại kho</label>
                <select name="warehouse_type" class="form-select shadow-sm warehouse-type-select" required>
                    <option value="branch" <?php echo ($warehouse->warehouse_type ?? 'branch') === 'branch' ? 'selected' : ''; ?>>Kho chi nhánh</option>
                    <option value="personal" <?php echo ($warehouse->warehouse_type ?? '') === 'personal' ? 'selected' : ''; ?>>Kho cá nhân</option>
                </select>
            </div>
            <div class="mb-3 branch-fields" id="branch-fields">
                <label class="form-label">Chi nhánh</label>
                <select name="work_location_id" class="form-select shadow-sm work-location-select">
                    <?php $locations = aerp_get_work_locations();
                    aerp_safe_select_options($locations, $warehouse->work_location_id ?? '', 'id', 'name', true);
                    ?>
                </select>
            </div>
            <div class="mb-3 personal-fields" id="personal-fields" style="display:none;">
                <label class="form-label">Chủ sở hữu kho</label>
                <select name="owner_user_id" class="owner-user-select">
                </select>
            </div>
            <div class="mb-3 branch-manager-fields" id="branch-manager-fields">
                <label class="form-label">Người quản lý kho</label>
                <select name="user_ids[]" class="user-select" multiple>
                    <!-- Option sẽ được load động bằng JS -->
                </select>
            </div>
            <button type="submit" name="aerp_save_warehouse" class="btn btn-primary"><?php echo $is_edit ? 'Cập nhật' : 'Thêm mới'; ?></button>
            <a href="<?php echo home_url('/aerp-warehouses'); ?>" class="btn btn-secondary">Quay lại</a>
        </form>
    </div>
</div>
<?php if (!empty($selected_users)) : ?>
    <script>
        window.selectedWarehouseManagers = <?php echo json_encode($selected_users); ?>;
        console.log('selectedWarehouseManagers:', window.selectedWarehouseManagers);
    </script>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
    // Toggle fields based on warehouse type
    $('.warehouse-type-select').on('change', function() {
        const warehouseType = $(this).val();
        
        if (warehouseType === 'branch') {
            $('#branch-fields').show();
            $('#personal-fields').hide();
            $('#branch-manager-fields').show();
            $('.work-location-select').prop('required', true);
            $('.owner-user-select').prop('required', false);
            $('.user-select').prop('required', true);
        } else if (warehouseType === 'personal') {
            $('#branch-fields').hide();
            $('#personal-fields').show();
            $('#branch-manager-fields').hide();
            $('.work-location-select').prop('required', false);
            $('.owner-user-select').prop('required', true);
            $('.user-select').prop('required', false);
        }
    });
    
    // Initialize on page load
    $('.warehouse-type-select').trigger('change');
    
    // Initialize owner user select
    $('.owner-user-select').select2({
        placeholder: "Chọn chủ sở hữu kho",
        allowClear: true,
        width: "100%",
        ajax: {
            url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
            dataType: "json",
            delay: 250,
            data: function(params) {
                return {
                    action: "aerp_order_search_employees",
                    q: params.term,
                };
            },
            processResults: function(data) {
                return { results: data };
            },
            cache: true,
        },
        minimumInputLength: 0,
    });
    
    // Set selected owner if editing
    <?php if ($is_edit && $warehouse && $warehouse->warehouse_type === 'personal' && $warehouse->owner_user_id): ?>
    $(document).ready(function() {
        // Tìm employee_id từ owner_user_id
        $.ajax({
            url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
            dataType: "json",
            data: { 
                action: "aerp_order_search_employees",
                employee_id: <?php echo $warehouse->owner_user_id; ?>
            },
        }).then(function(data) {
            if (data && data.length) {
                const item = data[0];
                const option = new Option(item.text, item.id, true, true);
                $('.owner-user-select').append(option).trigger('change');
            }
        });
    });
    <?php endif; ?>
});
</script>
<?php
$content = ob_get_clean();
$title = $is_edit ? 'Sửa kho' : 'Thêm kho';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
