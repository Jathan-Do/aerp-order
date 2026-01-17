/**
 * Realtime Notifications System
 */
(function($) {
    'use strict';
    
    let lastCheckTime = '';
    let pollInterval = null;
    let displayedToastIds = new Set(); // Lưu danh sách notification IDs đã hiển thị toast
    let toastCount = 0; // Đếm số toast hiện có
    const POLL_INTERVAL = 3000; // 3 giây - kiểm tra thường xuyên hơn
    const POLL_INTERVAL_BACKGROUND = 10000; // 10 giây khi tab không active
    const TOAST_TOP_START = 40; // Vị trí top của toast đầu tiên (px)
    const TOAST_SPACING = 10; // Khoảng cách giữa các toast (px)
    
    // Khởi tạo hệ thống thông báo
    function initNotifications() {
        if (!isUserLoggedIn()) {
            return;
        }
        
        // Tạo UI thông báo
        createNotificationUI();
        
        // Load thông báo ban đầu
        checkNotifications();
        
        // Bắt đầu polling
        startPolling();
        
        // Xử lý click vào icon thông báo
        $(document).on('click', '#aerp-notification-bell', function(e) {
            e.preventDefault();
            toggleNotificationDropdown();
        });
        
        // Đánh dấu đã đọc khi click vào thông báo
        $(document).on('click', '.aerp-notification-item', function() {
            const notificationId = $(this).data('notification-id');
            if (notificationId) {
                markNotificationRead(notificationId);
            }
        });
        
        // Đánh dấu tất cả đã đọc
        $(document).on('click', '#aerp-mark-all-read', function(e) {
            e.preventDefault();
            markAllNotificationsRead();
        });
        
        // Xóa một thông báo
        $(document).on('click', '.aerp-delete-notification', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Ngăn trigger click vào notification item
            const notificationId = $(this).closest('.aerp-notification-item').data('notification-id');
            if (notificationId && confirm('Bạn có chắc muốn xóa thông báo này?')) {
                deleteNotification(notificationId);
            }
        });
        
        // Xóa tất cả thông báo
        $(document).on('click', '#aerp-delete-all', function(e) {
            e.preventDefault();
            if (confirm('Bạn có chắc muốn xóa tất cả thông báo?')) {
                deleteAllNotifications();
            }
        });
        
        // Đóng dropdown khi click bên ngoài
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#aerp-notification-container').length) {
                $('#aerp-notification-dropdown').hide();
            }
        });
    }
    
    // Tạo UI thông báo
    function createNotificationUI() {
        if ($('#aerp-notification-container').length) {
            return; // Đã có rồi
        }
        
        const html = `
            <div id="aerp-notification-container" style="position: absolute; right: 20px; z-index: 9999;">
                <button id="aerp-notification-bell" class="btn btn-light position-relative shadow" style="border-radius: 50%; width: 45px; height: 45px; padding: 0;">
                    <i class="fas fa-bell"></i>
                    <span id="aerp-notification-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: none;">
                        0
                    </span>
                </button>
                <div id="aerp-notification-dropdown" style="display: none; position: absolute; top: 55px; right: 0; width: 350px; max-width: 90vw; background: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); max-height: 500px; overflow-y: auto;">
                    <div style="padding: 15px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong>Thông báo</strong>
                            <div style="display: flex; gap: 8px;">
                                <button id="aerp-mark-all-read" class="btn btn-sm btn-link" style="padding: 0; font-size: 12px;">Đánh dấu đã đọc</button>
                                <button id="aerp-delete-all" class="btn btn-sm btn-link text-danger" style="padding: 0; font-size: 12px;">Xóa tất cả</button>
                            </div>
                        </div>
                    </div>
                    <div id="aerp-notification-list" style="padding: 0;">
                        <div style="padding: 20px; text-align: center; color: #999;">Đang tải...</div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(html);
    }
    
    // Kiểm tra thông báo mới
    function checkNotifications() {
        $.ajax({
            url: aerp_ajax.ajax_url || ajaxurl,
            type: 'POST',
            data: {
                action: 'aerp_get_notifications',
                last_check: lastCheckTime
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    const previousTimestamp = lastCheckTime;
                    lastCheckTime = data.timestamp;
                    
                    // Cập nhật badge
                    updateNotificationBadge(data.unread_count);
                    
                    // Xử lý notifications
                    if (data.notifications && data.notifications.length > 0) {
                        if (!previousTimestamp) {
                            // Lần đầu tiên hoặc sau khi reset: chỉ hiển thị toast cho notifications mới (< 5 phút)
                            // Notifications cũ (> 5 phút) sẽ không hiển thị toast
                            const maxAgeMs = 5 * 60 * 1000; // 5 phút
                            
                            // Lấy thời gian hiện tại từ server (đã là HCM timezone)
                            const serverNow = parseHCMDateTime(data.timestamp).getTime();
                            
                            let toastShown = 0;
                            let skippedOld = 0;
                            let skippedAlreadyShown = 0;
                            
                            data.notifications.forEach(function(notif) {
                                // Nếu đã hiển thị toast rồi, bỏ qua
                                if (displayedToastIds.has(notif.id)) {
                                    skippedAlreadyShown++;
                                    return;
                                }
                                
                                // So sánh thời gian: cả serverTimestamp và created_at đều là HCM time (sau khi sửa backend)
                                const serverNowHCM = parseHCMDateTime(data.timestamp).getTime();
                                const notifTimeHCM = parseHCMDateTime(notif.created_at).getTime();
                                const ageMs = serverNowHCM - notifTimeHCM;
                                
                                // Nếu notification cũ hơn 5 phút, đánh dấu vào displayedToastIds (không hiển thị toast)
                                if (ageMs > maxAgeMs) {
                                    displayedToastIds.add(notif.id);
                                    skippedOld++;
                                } else {
                                    // Notification mới (< 5 phút), hiển thị toast
                                    showToastNotification(notif);
                                    displayedToastIds.add(notif.id);
                                    toastShown++;
                                }
                            });
                            
                        } else {
                            // Các lần sau (polling tự động): chỉ hiển thị toast cho notifications thực sự mới
                            const newNotifications = data.notifications.filter(function(notif) {
                                // Chỉ hiển thị nếu:
                                // 1. Chưa được hiển thị toast trước đó
                                // 2. Và created_at phải sau previousTimestamp
                                if (displayedToastIds.has(notif.id)) {
                                    return false; // Đã hiển thị rồi
                                }
                                
                                const notifTime = parseHCMDateTime(notif.created_at).getTime();
                                const prevTime = parseHCMDateTime(previousTimestamp).getTime();
                                return notifTime > prevTime; // Chỉ hiển thị notifications mới hơn
                            });
                            
                            // Hiển thị toast cho notifications mới
                            if (newNotifications.length > 0) {
                                displayNewNotifications(newNotifications);
                                // Đánh dấu đã hiển thị
                                newNotifications.forEach(function(notif) {
                                    displayedToastIds.add(notif.id);
                                });
                            }
                            
                            // Đánh dấu các notifications cũ (không hiển thị toast) vào displayedToastIds
                            // để tránh hiển thị lại nếu chúng vẫn còn trong danh sách
                            data.notifications.forEach(function(notif) {
                                if (!displayedToastIds.has(notif.id)) {
                                    const notifTime = parseHCMDateTime(notif.created_at).getTime();
                                    const prevTime = parseHCMDateTime(previousTimestamp).getTime();
                                    if (notifTime <= prevTime) {
                                        displayedToastIds.add(notif.id);
                                    }
                                }
                            });
                        }
                    }
                    
                    // Cập nhật danh sách nếu dropdown đang mở
                    if ($('#aerp-notification-dropdown').is(':visible')) {
                        loadAllNotifications();
                    }
                }
            },
            error: function() {
                console.error('Error checking notifications');
            }
        });
    }
    
    // Tải tất cả thông báo (cả đã đọc và chưa đọc)
    function loadAllNotifications() {
        $.ajax({
            url: aerp_ajax.ajax_url || ajaxurl,
            type: 'POST',
            data: {
                action: 'aerp_get_all_notifications'
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    renderNotificationList(data.notifications || []);
                    // Cập nhật badge
                    updateNotificationBadge(data.unread_count || 0);
                }
            }
        });
    }
    
    // Hiển thị thông báo mới
    function displayNewNotifications(notifications) {
        notifications.forEach(function(notif) {
            // Hiển thị toast notification
            showToastNotification(notif);
        });
    }
    
    // Hiển thị toast notification
    function showToastNotification(notif) {
        console.log('showToastNotification called for:', notif.id, notif.title);
        
        // Tính toán vị trí top dựa trên toast cuối cùng (nếu có)
        let currentTop = TOAST_TOP_START;
        const existingToasts = $('.aerp-toast-notification');
        
        if (existingToasts.length > 0) {
            // Lấy toast cuối cùng (toast mới nhất)
            const lastToast = existingToasts.last();
            const lastTop = parseInt(lastToast.css('top')) || TOAST_TOP_START;
            const lastHeight = lastToast.outerHeight() || 0;
            // Vị trí mới = vị trí toast cuối + chiều cao toast cuối + khoảng cách
            currentTop = lastTop + lastHeight + TOAST_SPACING;
        }
        
        console.log('Creating toast at top:', currentTop);
        
        const toast = $(`
            <div class="aerp-toast-notification" data-toast-index="${toastCount}" style="position: fixed; top: ${currentTop}px; right: 20px; background: white; border-left: 4px solid #007cba; padding: 15px; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 300px; max-width: 400px; z-index: 10000; transition: top 0.3s ease;">
                <div style="font-weight: bold; margin-bottom: 5px;">${escapeHtml(notif.title)}</div>
                ${notif.message ? '<div style="color: #666; font-size: 13px;">' + escapeHtml(notif.message) + '</div>' : ''}
                ${notif.link_url ? '<div style="margin-top: 8px;"><a href="' + escapeHtml(notif.link_url) + '" style="color: #007cba; text-decoration: none; font-size: 12px;">Xem chi tiết →</a></div>' : ''}
            </div>
        `);
        
        $('body').append(toast);
        toastCount++;
        
        // Tự động ẩn sau 5 giây
        setTimeout(function() {
            toast.fadeOut(300, function() {
                const toastIndex = parseInt(toast.data('toast-index'));
                $(this).remove();
                toastCount--;
                
                // Điều chỉnh lại vị trí các toast còn lại
                adjustToastPositions();
            });
        }, 5000);
        
        // Click để đánh dấu đã đọc và chuyển trang
        toast.on('click', function() {
            if (notif.id) {
                markNotificationRead(notif.id);
            }
            if (notif.link_url) {
                window.location.href = notif.link_url;
            }
        });
    }
    
    // Điều chỉnh lại vị trí các toast sau khi một toast bị xóa
    function adjustToastPositions() {
        let currentTop = TOAST_TOP_START;
        $('.aerp-toast-notification').each(function() {
            $(this).css('top', currentTop + 'px');
            currentTop += $(this).outerHeight() + TOAST_SPACING;
        });
    }
    
    // Render danh sách thông báo
    function renderNotificationList(notifications) {
        const $list = $('#aerp-notification-list');
        
        if (notifications.length === 0) {
            $list.html('<div style="padding: 20px; text-align: center; color: #999;">Không có thông báo nào</div>');
            return;
        }
        
        let html = '';
        notifications.forEach(function(notif) {
            const timeAgo = getTimeAgo(notif.created_at);
            const isRead = notif.is_read == 1 || notif.is_read === true;
            
            // Style khác nhau cho đã đọc và chưa đọc
            const bgColor = isRead ? '#f8f9fa' : '#e7f3ff'; // Chưa đọc: xanh nhạt, đã đọc: xám nhạt
            const borderLeft = isRead ? 'none' : '4px solid #007cba'; // Chưa đọc có border xanh
            const fontWeight = isRead ? '400' : '600'; // Chưa đọc đậm hơn
            const titleColor = isRead ? '#666' : '#333'; // Chưa đọc đậm hơn
            
            html += `
                <div class="aerp-notification-item" data-notification-id="${notif.id}" data-is-read="${isRead ? '1' : '0'}" 
                     style="padding: 12px 15px; margin-bottom: 4px; border-bottom: 1px solid rgb(226, 224, 224); border-left: ${borderLeft}; cursor: pointer; transition: background 0.2s; position: relative; background-color: ${bgColor};" 
                     onmouseover="this.style.backgroundColor='#e9ecef'" 
                     onmouseout="this.style.backgroundColor='${bgColor}'">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                ${!isRead ? '<span style="display: inline-block; width: 8px; height: 8px; background-color: #007cba; border-radius: 50%; flex-shrink: 0;"></span>' : ''}
                                <div style="font-weight: ${fontWeight}; color: ${titleColor};">${escapeHtml(notif.title)}</div>
                            </div>
                            ${notif.message ? '<div style="color: #666; font-size: 13px; margin-bottom: 4px; margin-left: ' + (isRead ? '0' : '16') + 'px;">' + escapeHtml(notif.message) + '</div>' : ''}
                            ${notif.link_url ? '<div style="margin-left: ' + (isRead ? '0' : '16') + 'px;"><a href="' + escapeHtml(notif.link_url) + '" style="color: #007cba; text-decoration: none; font-size: 12px;">Xem chi tiết →</a></div>' : ''}
                            <div style="font-size: 11px; color: #999; margin-left: ${isRead ? '0' : '16'}px;">${timeAgo}</div>

                        </div>
                        <button class="aerp-delete-notification btn btn-sm btn-link text-danger" style="padding: 4px 8px; font-size: 14px; line-height: 1; flex-shrink: 0;" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        $list.html(html);
    }
    
    // Cập nhật badge số lượng
    function updateNotificationBadge(count) {
        const $badge = $('#aerp-notification-badge');
        if (count > 0) {
            $badge.text(count > 99 ? '99+' : count).show();
        } else {
            $badge.hide();
        }
    }
    
    // Toggle dropdown
    function toggleNotificationDropdown() {
        const $dropdown = $('#aerp-notification-dropdown');
        if ($dropdown.is(':visible')) {
            $dropdown.hide();
        } else {
            $dropdown.show();
            loadAllNotifications();
        }
    }
    
    // Đánh dấu đã đọc
    function markNotificationRead(notificationId) {
        $.ajax({
            url: aerp_ajax.ajax_url || ajaxurl,
            type: 'POST',
            data: {
                action: 'aerp_mark_notification_read',
                notification_id: notificationId
            },
            success: function() {
                // Cập nhật lại danh sách (không xóa, chỉ đánh dấu đã đọc)
                if ($('#aerp-notification-dropdown').is(':visible')) {
                    loadAllNotifications();
                }
                checkNotifications();
            }
        });
    }
    
    // Đánh dấu tất cả đã đọc
    function markAllNotificationsRead() {
        $.ajax({
            url: aerp_ajax.ajax_url || ajaxurl,
            type: 'POST',
            data: {
                action: 'aerp_mark_all_notifications_read'
            },
            success: function() {
                // Cập nhật lại danh sách (không xóa, chỉ đánh dấu đã đọc)
                if ($('#aerp-notification-dropdown').is(':visible')) {
                    loadAllNotifications();
                }
                checkNotifications();
            }
        });
    }
    
    // Xóa một thông báo
    function deleteNotification(notificationId) {
        $.ajax({
            url: aerp_ajax.ajax_url || ajaxurl,
            type: 'POST',
            data: {
                action: 'aerp_delete_notification',
                notification_id: notificationId
            },
            success: function() {
                // Xóa item khỏi danh sách
                $('.aerp-notification-item[data-notification-id="' + notificationId + '"]').fadeOut(300, function() {
                    $(this).remove();
                    // Kiểm tra nếu không còn notification nào
                    if ($('.aerp-notification-item').length === 0) {
                        $('#aerp-notification-list').html('<div style="padding: 20px; text-align: center; color: #999;">Không có thông báo nào</div>');
                    }
                });
                // Cập nhật badge
                checkNotifications();
            },
            error: function() {
                alert('Không thể xóa thông báo');
            }
        });
    }
    
    // Xóa tất cả thông báo
    function deleteAllNotifications() {
        $.ajax({
            url: aerp_ajax.ajax_url || ajaxurl,
            type: 'POST',
            data: {
                action: 'aerp_delete_all_notifications'
            },
            success: function() {
                // Xóa tất cả items khỏi danh sách
                $('#aerp-notification-list').html('<div style="padding: 20px; text-align: center; color: #999;">Không có thông báo nào</div>');
                // Cập nhật badge
                checkNotifications();
            },
            error: function() {
                alert('Không thể xóa tất cả thông báo');
            }
        });
    }
    
    // Bắt đầu polling
    function startPolling(interval) {
        if (pollInterval) {
            clearInterval(pollInterval);
        }
        const pollTime = interval || POLL_INTERVAL;
        pollInterval = setInterval(checkNotifications, pollTime);
    }
    
    // Dừng polling
    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }
    
    // Helper functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Parse datetime string - có thể là HCM hoặc UTC tùy theo nguồn
    // Format: 'YYYY-MM-DD HH:mm:ss'
    // Nếu từ created_at (MySQL), có thể là UTC
    // Nếu từ server timestamp (PHP), là HCM
    function parseHCMDateTime(dateString) {
        // Parse datetime string
        const parts = dateString.match(/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/);
        if (!parts) {
            // Fallback về cách parse thông thường
            return new Date(dateString);
        }
        
        const year = parseInt(parts[1]);
        const month = parseInt(parts[2]) - 1; // Month is 0-indexed
        const day = parseInt(parts[3]);
        const hour = parseInt(parts[4]);
        const minute = parseInt(parts[5]);
        const second = parseInt(parts[6]);
        
        // Coi datetime string là HCM time (UTC+7), convert sang UTC
        // HCM = UTC+7, nên cần trừ 7 giờ để có UTC
        const utcDate = new Date(Date.UTC(year, month, day, hour - 7, minute, second));
        return utcDate;
    }
    
    // Parse datetime string như UTC (nếu MySQL lưu UTC)
    function parseUTCDateTime(dateString) {
        const parts = dateString.match(/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/);
        if (!parts) {
            return new Date(dateString);
        }
        
        const year = parseInt(parts[1]);
        const month = parseInt(parts[2]) - 1;
        const day = parseInt(parts[3]);
        const hour = parseInt(parts[4]);
        const minute = parseInt(parts[5]);
        const second = parseInt(parts[6]);
        
        // Coi như UTC time
        return new Date(Date.UTC(year, month, day, hour, minute, second));
    }
    
    // So sánh thời gian HCM: trả về milliseconds (dương = notification cũ hơn, âm = notification mới hơn)
    // Cả hai đều được convert về UTC để so sánh chính xác
    function compareHCMTime(hcmDateTimeString) {
        // Parse notification time từ HCM string -> UTC timestamp
        const notifTimeUTC = parseHCMDateTime(hcmDateTimeString).getTime();
        
        // Lấy thời gian hiện tại (UTC timestamp)
        const nowUTC = new Date().getTime();
        
        // So sánh: thời gian hiện tại (UTC) - thời gian notification (UTC)
        // Dương = notification cũ hơn hiện tại
        return nowUTC - notifTimeUTC;
    }
    
    function getTimeAgo(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const diff = Math.floor((now - date) / 1000); // seconds
        
        if (diff < 60) return 'Vừa xong';
        if (diff < 3600) return Math.floor(diff / 60) + ' phút trước';
        if (diff < 86400) return Math.floor(diff / 3600) + ' giờ trước';
        if (diff < 604800) return Math.floor(diff / 86400) + ' ngày trước';
        return date.toLocaleDateString('vi-VN');
    }
    
    function isUserLoggedIn() {
        // Kiểm tra xem user đã đăng nhập chưa
        // Có thể kiểm tra qua cookie hoặc biến global
        return typeof aerp_ajax !== 'undefined' || typeof ajaxurl !== 'undefined';
    }
    
    // Export checkNotifications và các hàm để reset state
    window.checkNotifications = checkNotifications;
    window.resetNotificationState = function() {
        lastCheckTime = '';
        displayedToastIds.clear();
        console.log('Reset notification state: lastCheckTime cleared, displayedToastIds cleared');
    };
    
    // Lắng nghe custom event để check notifications
    $(document).on('aerp:check-notifications', function() {
        checkNotifications();
    });
    
    // Khởi tạo khi document ready
    $(document).ready(function() {
        initNotifications();
    });
    
    // Điều chỉnh polling khi tab không active (giảm tần suất nhưng vẫn chạy)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Khi tab không active, vẫn polling nhưng chậm hơn
            startPolling(POLL_INTERVAL_BACKGROUND);
        } else {
            // Khi tab active lại, polling nhanh và check ngay
            startPolling(POLL_INTERVAL);
            checkNotifications(); // Check ngay khi quay lại
        }
    });
    
    // Đảm bảo polling luôn chạy (fallback nếu visibilitychange không hoạt động)
    setInterval(function() {
        if (!pollInterval) {
            startPolling();
        }
    }, 60000); // Kiểm tra mỗi phút để đảm bảo polling vẫn chạy
    
})(jQuery);



