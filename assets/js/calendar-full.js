(function($) {
    'use strict';

    function initFullCalendar() {
        var calendarEl = document.getElementById('aerp-fullcalendar');
        if (!calendarEl || typeof FullCalendar === 'undefined') {
            return;
        }

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'vi',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            buttonText: {
                today: 'Hôm nay',
                month: 'Tháng',
                week: 'Tuần',
                day: 'Ngày',
                list: 'Danh sách'
            },
            firstDay: 1,
            navLinks: true,
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            events: function(info, successCallback, failureCallback) {
                $.ajax({
                    url: (typeof aerp_ajax !== 'undefined' ? aerp_ajax.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '')),
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        action: 'aerp_calendar_get_events',
                        start: info.startStr,
                        end: info.endStr
                    },
                    success: function(response) {
                        if ($.isArray(response)) {
                            successCallback(response);
                        } else if (response && response.success && $.isArray(response.data)) {
                            successCallback(response.data);
                        } else {
                            successCallback([]);
                        }
                    },
                    error: function() {
                        failureCallback('Failed to load events');
                    }
                });
            },
            eventClick: function(info) {
                if (info.event.url) {
                    window.open(info.event.url, '_blank');
                    info.jsEvent.preventDefault();
                }
            },
            dateClick: function(info) {
                // Khi click vào ngày trên lịch, auto set ngày bắt đầu của form tạo sự kiện
                var $startDate = $('input[name=\"start_date\"]');
                if ($startDate.length) {
                    $startDate.val(info.dateStr);
                    $('html, body').animate({
                        scrollTop: $startDate.offset().top - 120
                    }, 300);
                }
            }
        });

        calendar.render();
    }

    $(document).ready(function() {
        initFullCalendar();
    });

})(jQuery);


