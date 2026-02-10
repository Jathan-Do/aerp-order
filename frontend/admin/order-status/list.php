<?php
if (!defined('ABSPATH')) exit;
// Get current user
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$employee = aerp_get_employee_by_user_id($user_id);
$user_fullname = $employee ? $employee->full_name : '';

if (!is_user_logged_in()) {
    wp_die(__('You must be logged in to access this page.'));
}

// Danh sách điều kiện, chỉ cần 1 cái đúng là qua
$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'department_lead'),
    aerp_user_has_permission($user_id,'order_status_view'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$table = new AERP_Order_Status_Table();
$table->process_bulk_action();
global $wpdb;
$colors = $wpdb->get_col("SELECT DISTINCT color FROM {$wpdb->prefix}aerp_order_statuses WHERE color IS NOT NULL AND color <> '' ORDER BY color ASC");
ob_start();
?>
<style>
    /* Swatch inside select option (for non-Select2 browsers this is best-effort) */
    .color-option-swatch {
        display: inline-block;
        width: 12px;
        height: 12px;
        border: 1px solid #ced4da;
        border-radius: 2px;
        margin-right: 6px;
        vertical-align: -1px;
    }

    .select2-container--default .select2-selection--single {
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
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-5">
    <h2>Quản lý trạng thái đơn hàng</h2>
    <div class="user-info text-end">
        Hi, <?php echo esc_html($user_fullname); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Đăng xuất
        </a>
    </div>
</div>
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Quản lý đơn hàng', 'url' => home_url('/aerp-order-orders')],
        ['label' => 'Quản lý trạng thái đơn hàng']
    ]);
}
?>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0">Danh sách trạng thái đơn hàng</h5>
        <a href="<?php echo esc_url(home_url('/aerp-order-statuses/?action=add')); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Thêm mới
        </a>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form id="aerp-order-status-filter-form" class="row g-2 mb-3 aerp-table-ajax-form" data-table-wrapper="#aerp-order-status-table-wrapper" data-ajax-action="aerp_order_status_filter_statuses">
            <div class="col-12 col-md-3 mb-2">
            <label for="filter-color" class="form-label mb-1">Màu sắc</label>
                <select id="filter-color" name="color" class="form-select shadow-sm color-select">
                    <option value="">Tất cả màu</option>
                    <?php
                    if (!empty($colors)) {
                        foreach ($colors as $color) {
                            printf('<option value="%s" data-color="%s">%s</option>', esc_attr($color), esc_attr($color), esc_html($color));
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary w-100">Lọc</button>
            </div>
        </form>
        <?php $message = get_transient('aerp_order_status_message');
        if ($message) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
                . esc_html($message) .
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            delete_transient('aerp_order_status_message');
        } ?>
        <div id="aerp-order-status-table-wrapper">
            <?php $table->render(); ?>
        </div>
        <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-outline-secondary" style="width: fit-content;">
            <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
        </a>
    </div>
</div>
<script>
    jQuery(function($) {
        function formatColorOption(option) {
            if (!option.id) {
                return option.text;
            }
            var color = $(option.element).data('color');
            if (!color) {
                return option.text;
            }
            var $swatch = $('<span>').addClass('color-option-swatch').css('background', color);
            var $label = $('<span>').text(' ' + option.text);
            var $container = $('<span>').append($swatch).append($label);
            return $container;
        }
        if ($.fn.select2) {
            $('.color-select').select2({
                width: '100%',
                templateResult: formatColorOption,
                templateSelection: formatColorOption,
                escapeMarkup: function(markup) {
                    return markup;
                }
            });
        }
    });
</script>
<?php
$content = ob_get_clean();
$title = 'Quản lý trạng thái đơn hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php'); 