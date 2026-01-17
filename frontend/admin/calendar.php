<?php

if (!defined('ABSPATH')) {
    exit;
}

// Chỉ cho phép user đã đăng nhập
if (!is_user_logged_in()) {
    wp_die(__('You must be logged in to access this page.'));
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$employee = aerp_get_employee_by_user_id($user_id);
$user_fullname = $employee ? $employee->full_name : '';
// Danh sách điều kiện, chỉ cần 1 cái đúng là qua
$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
// Quyền: cho đơn giản, admin hoặc bất kỳ user đăng nhập đều xem được lịch của mình

// Tự động trigger cron nhắc lịch khi load trang calendar (để đảm bảo không bỏ sót)
if (function_exists('aerp_run_calendar_reminders')) {
    aerp_run_calendar_reminders();
}

global $wpdb;

// Lọc theo tháng (mặc định: tháng hiện tại)
$month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : date('Y-m');

$date_now = (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('H:i');
$colors = $wpdb->get_col("SELECT DISTINCT color FROM {$wpdb->prefix}aerp_calendar_events WHERE color IS NOT NULL AND color <> '' ORDER BY color ASC");

// Xử lý edit action
$edit_id = isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) ? absint($_GET['id']) : 0;
$edit_event = null;
if ($edit_id) {
    $edit_event = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aerp_calendar_events WHERE id = %d",
        $edit_id
    ));
}

// Khởi tạo table với filter theo tháng
$table = new AERP_Calendar_Event_Table();
$table->set_filters(['month' => $month]);
$table->process_bulk_action();

$message = get_transient('aerp_calendar_message');
if ($message) {
    delete_transient('aerp_calendar_message');
}

ob_start();
?>
<style>
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
    <h2>Lịch công việc</h2>
    <div class="user-info d-flex align-items-center justify-content-end gap-2">
        <button id="aerp-trigger-reminders-btn" class="btn btn-sm btn-info" title="Kiểm tra nhắc lịch ngay">
            <i class="fas fa-bell"></i> Kiểm tra nhắc lịch
        </button>
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
        ['label' => 'Lịch công việc']
    ]);
}
?>

<?php if (!empty($message)) : ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo esc_html($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-8 mb-3">
        <div class="card shadow-sm mb-3">
            <div class="card-header">
                <h5 class="mb-0">Lịch tháng</h5>
            </div>
            <div class="card-body">
                <div id="aerp-fullcalendar"></div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo $edit_event ? 'Chỉnh sửa sự kiện' : 'Thêm sự kiện mới'; ?></h5>
                <?php if ($edit_event) : ?>
                    <a href="<?php echo esc_url(home_url('/aerp-calendar')); ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times"></i> Hủy
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <?php wp_nonce_field('aerp_save_event_action', 'aerp_save_event_nonce'); ?>
                    <input type="hidden" name="aerp_save_event" value="1">
                    <?php if ($edit_event) : ?>
                        <input type="hidden" name="event_id" value="<?php echo esc_attr($edit_event->id); ?>">
                    <?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label mb-1">Tiêu đề *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo $edit_event ? esc_attr($edit_event->title) : ''; ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Loại sự kiện</label>
                        <select name="event_type" class="form-select">
                            <option value="appointment" <?php echo ($edit_event && $edit_event->event_type === 'appointment') ? 'selected' : ''; ?>>Lịch hẹn</option>
                            <option value="delivery" <?php echo ($edit_event && $edit_event->event_type === 'delivery') ? 'selected' : ''; ?>>Giao hàng</option>
                            <option value="meeting" <?php echo ($edit_event && $edit_event->event_type === 'meeting') ? 'selected' : ''; ?>>Cuộc họp</option>
                            <option value="reminder" <?php echo ($edit_event && $edit_event->event_type === 'reminder') ? 'selected' : ''; ?>>Nhắc nhở</option>
                            <option value="other" <?php echo ($edit_event && $edit_event->event_type === 'other') ? 'selected' : ''; ?>>Khác</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Khách hàng</label>
                        <select name="customer_id" id="calendar-customer-select" class="form-select customer-select" data-selected="<?php echo $edit_event ? esc_attr($edit_event->customer_id) : ''; ?>">
                            <option value="">-- Chọn khách hàng --</option>
                            <?php
                            if (function_exists('aerp_get_customers')) {
                                $customers = aerp_get_customers();
                                if ($customers) {
                                    foreach ($customers as $c) {
                                        $selected = ($edit_event && $edit_event->customer_id == $c->id) ? 'selected' : '';
                                        printf(
                                            '<option value="%d" %s>%s%s</option>',
                                            $c->id,
                                            $selected,
                                            esc_html($c->full_name),
                                            !empty($c->customer_code) ? ' (' . esc_html($c->customer_code) . ')' : ''
                                        );
                                    }
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Đơn hàng</label>
                        <select name="order_id" id="calendar-order-select" class="form-select order-select" data-selected="<?php echo $edit_event ? esc_attr($edit_event->order_id) : ''; ?>">
                            <option value="">-- Chọn đơn hàng --</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Ngày bắt đầu *</label>
                        <?php
                        $start_date = $edit_event ? date('Y-m-d', strtotime($edit_event->start_date)) : date('Y-m-d');
                        ?>
                        <input type="date" name="start_date" class="form-control" value="<?php echo esc_attr($start_date); ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Giờ bắt đầu</label>
                        <?php
                        $start_time = $edit_event ? date('H:i', strtotime($edit_event->start_date)) : $date_now;
                        ?>
                        <input type="time" name="start_time" class="form-control" value="<?php echo esc_attr($start_time); ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Ngày kết thúc</label>
                        <?php
                        $end_date = $edit_event && $edit_event->end_date ? date('Y-m-d', strtotime($edit_event->end_date)) : '';
                        ?>
                        <input type="date" name="end_date" class="form-control" value="<?php echo esc_attr($end_date); ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Giờ kết thúc</label>
                        <?php
                        $end_time = $edit_event && $edit_event->end_date ? date('H:i', strtotime($edit_event->end_date)) : '';
                        ?>
                        <input type="time" name="end_time" class="form-control" value="<?php echo esc_attr($end_time); ?>">
                    </div>
                    <div class="mb-2 form-check">
                        <input type="checkbox" class="form-check-input" id="is_all_day" name="is_all_day" value="1" <?php echo ($edit_event && $edit_event->is_all_day) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_all_day">Cả ngày</label>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Địa điểm</label>
                        <input type="text" name="location" class="form-control" value="<?php echo $edit_event ? esc_attr($edit_event->location) : ''; ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Màu hiển thị</label>
                        <input type="color" name="color" class="form-control form-control-color" value="<?php echo $edit_event ? esc_attr($edit_event->color ?: '#007cba') : '#007cba'; ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Nhắc trước (phút)</label>
                        <input type="number" name="reminder_minutes" class="form-control" placeholder="Ví dụ: 30" value="<?php echo $edit_event ? esc_attr($edit_event->reminder_minutes) : ''; ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Ghi chú</label>
                        <textarea name="description" rows="3" class="form-control"><?php echo $edit_event ? esc_textarea($edit_event->description) : ''; ?></textarea>
                    </div>
                    <div class="d-grid mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> <?php echo $edit_event ? 'Cập nhật' : 'Lưu'; ?> sự kiện
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <h5 class="mb-0">Sự kiện trong tháng</h5>
                <form method="get" class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 me-1">Tháng:</label>
                    <input type="month" name="month" class="form-control" style="max-width: 180px;" value="<?php echo esc_attr($month); ?>">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Xem</button>
                    <a href="<?php echo esc_url(home_url('/aerp-calendar')); ?>" class="btn btn-outline-secondary btn-sm">Tháng hiện tại</a>
                </form>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form id="aerp-calendar-event-filter-form" class="row g-2 mb-3 aerp-table-ajax-form" data-table-wrapper="#aerp-calendar-event-table-wrapper" data-ajax-action="aerp_calendar_filter_events">
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
                    <div class="col-12 col-md-3 mb-2">
                        <label for="filter-date-from" class="form-label mb-1">Từ ngày</label>
                        <input type="date" id="filter-date-from" name="date_from" class="form-control shadow-sm bg-body">
                    </div>
                    <div class="col-12 col-md-3 mb-2">
                        <label for="filter-date-to" class="form-label mb-1">Đến ngày</label>
                        <input type="date" id="filter-date-to" name="date_to" class="form-control shadow-sm bg-body">
                    </div>
                    <div class="col-12 col-md-1 d-flex align-items-end mb-2">
                        <button type="submit" class="btn btn-primary w-100">Lọc</button>
                    </div>
                </form>
                <div id="aerp-calendar-event-table-wrapper">
                    <?php $table->render(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        // Nút trigger nhắc lịch thủ công
        $('#aerp-trigger-reminders-btn').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang kiểm tra...');

            $.ajax({
                url: aerp_ajax.ajax_url || ajaxurl,
                type: 'POST',
                data: {
                    action: 'aerp_trigger_calendar_reminders'
                },
                success: function(response) {
                    if (response.success) {
                        // Đợi một chút để đảm bảo notification đã được tạo
                        setTimeout(function() {
                            // Reset state để force hiển thị toast cho notifications mới
                            if (typeof window.resetNotificationState === 'function') {
                                window.resetNotificationState();
                            }
                            // Gọi checkNotifications từ notifications.js nếu có
                            if (typeof window.checkNotifications === 'function') {
                                window.checkNotifications();
                            } else if (typeof jQuery !== 'undefined') {
                                // Trigger custom event để notifications.js lắng nghe
                                jQuery(document).trigger('aerp:check-notifications');
                            }
                            $btn.prop('disabled', false).html('<i class="fas fa-bell"></i> Kiểm tra nhắc lịch');
                        }, 1000);
                    } else {
                        alert('Lỗi: ' + (response.data || 'Không thể kiểm tra'));
                        $btn.prop('disabled', false).html('<i class="fas fa-bell"></i> Kiểm tra nhắc lịch');
                    }
                },
                error: function() {
                    alert('Lỗi kết nối!');
                    $btn.prop('disabled', false).html('<i class="fas fa-bell"></i> Kiểm tra nhắc lịch');
                }
            });
        });

        // Khởi tạo Select2 cho khách hàng
        var $customerSelect = $('#calendar-customer-select');
        var selectedCustomerId = $customerSelect.data('selected') || $customerSelect.val();

        $customerSelect.select2({
            placeholder: '-- Chọn khách hàng --',
            allowClear: true,
            ajax: {
                url: aerp_ajax.ajax_url || ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'aerp_order_search_customers',
                        q: params.term || ''
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                cache: true
            }
        });

        // Set giá trị đã chọn nếu có (khi edit)
        if (selectedCustomerId) {
            $customerSelect.val(selectedCustomerId).trigger('change');
        }

        // Khởi tạo Select2 cho đơn hàng
        var $orderSelect = $('#calendar-order-select');
        var selectedOrderId = $orderSelect.data('selected');
        var selectedCustomerIdForOrder = selectedCustomerId || $customerSelect.val();

        // Function để khởi tạo Select2 đơn hàng
        function initOrderSelect(customerId) {
            if (customerId) {
                $orderSelect.prop('disabled', false);

                // Destroy Select2 cũ nếu đã tồn tại
                if ($orderSelect.hasClass('select2-hidden-accessible')) {
                    $orderSelect.select2('destroy');
                }

                // Khởi tạo Select2 mới với AJAX filter theo customer_id
                $orderSelect.select2({
                    placeholder: '-- Chọn đơn hàng --',
                    allowClear: true,
                    ajax: {
                        url: aerp_ajax.ajax_url || ajaxurl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'aerp_search_orders_by_customer',
                                customer_id: customerId,
                                q: params.term || ''
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    }
                });

                // Set giá trị đã chọn nếu có (khi edit)
                if (selectedOrderId) {
                    // Load order để hiển thị trong Select2
                    $.ajax({
                        url: aerp_ajax.ajax_url || ajaxurl,
                        data: {
                            action: 'aerp_search_orders_by_customer',
                            customer_id: customerId,
                            id: selectedOrderId
                        },
                        success: function(data) {
                            if (data && data.length > 0) {
                                var option = new Option(data[0].text, data[0].id, true, true);
                                $orderSelect.append(option).trigger('change');
                            }
                        }
                    });
                }
            } else {
                // Disable Select2 đơn hàng nếu không chọn khách hàng
                if ($orderSelect.hasClass('select2-hidden-accessible')) {
                    $orderSelect.select2('destroy');
                }
                $orderSelect.prop('disabled', true);
            }
        }

        // Khởi tạo ban đầu
        if (selectedCustomerIdForOrder) {
            initOrderSelect(selectedCustomerIdForOrder);
        } else {
            $orderSelect.prop('disabled', true);
        }

        // Khi khách hàng thay đổi
        $customerSelect.on('change', function() {
            var customerId = $(this).val();
            // Xóa giá trị đơn hàng cũ
            $orderSelect.val(null).trigger('change');
            initOrderSelect(customerId);
        });

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
$title = 'Lịch công việc';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
