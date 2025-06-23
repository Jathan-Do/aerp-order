jQuery(document).ready(function ($) {
    // Handle delete attachment
    $("#existing-attachments-container").on("click", ".delete-attachment", function () {
        const attachmentId = $(this).data("attachment-id");
        const $attachmentDiv = $(this).closest(".d-flex");

        if (confirm("Bạn có chắc chắn muốn xóa file đính kèm này không?")) {
            $.ajax({
                url: aerp_order_ajax.ajaxurl,
                type: "POST",
                data: {
                    action: "aerp_delete_order_attachment",
                    attachment_id: attachmentId,
                    _wpnonce: aerp_order_ajax._wpnonce_delete_attachment,
                },
                success: function (response) {
                    if (response.success) {
                        $attachmentDiv.remove();
                        alert(response.data);
                    } else {
                        alert("Lỗi: " + response.data);
                    }
                },
                error: function () {
                    alert("Đã xảy ra lỗi khi xóa file.");
                },
            });
        }
    });
}); 