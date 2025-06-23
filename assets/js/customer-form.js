jQuery(document).ready(function ($) {
    let phoneIndex = $("#phone-numbers-container .input-group").length;
    if (phoneIndex === 0) {
        // If no phone fields are present (e.g., new form), initialize with one
        addPhoneField(0);
    }

    // Add Phone Field
    $("#add-phone-field").on("click", function () {
        addPhoneField(phoneIndex);
        phoneIndex++;
    });

    // Remove Phone Field
    $("#phone-numbers-container").on("click", ".remove-phone-field", function () {
        if ($("#phone-numbers-container .input-group").length > 1) {
            $(this).closest(".input-group").remove();
        } else {
            // Optionally, clear the fields instead of removing if only one is left
            $(this).closest(".input-group").find('input[type="text"]').val("");
            $(this).closest(".input-group").find('input[type="checkbox"]').prop("checked", false);
            $(this).closest(".input-group").find('input[type="hidden"]').val(""); // Clear hidden ID for existing phones
        }
    });

    function addPhoneField(index) {
        const newPhoneField = `
            <div class="input-group mb-2">
                <input type="hidden" name="phone_numbers[${index}][id]" value="0"> <!-- New field, ID 0 -->
                <input type="text" class="form-control" name="phone_numbers[${index}][number]" placeholder="Số điện thoại">
                <div class="input-group-text">
                    <input type="checkbox" name="phone_numbers[${index}][primary]" value="1"> &nbsp; Chính
                </div>
                <input type="text" class="form-control" name="phone_numbers[${index}][note]" placeholder="Ghi chú">
                <button type="button" class="btn btn-outline-danger remove-phone-field">Xóa</button>
            </div>
        `;
        $("#phone-numbers-container").append(newPhoneField);
    }

    // Handle delete attachment (for form-edit.php)
    $("#existing-attachments-container").on("click", ".delete-attachment", function () {
        const attachmentId = $(this).data("attachment-id");
        const $attachmentDiv = $(this).closest(".d-flex");

        if (confirm("Bạn có chắc chắn muốn xóa file đính kèm này không?")) {
            $.ajax({
                url: aerp_crm_ajax.ajaxurl, // WordPress global AJAX URL
                type: "POST",
                data: {
                    action: "aerp_delete_customer_attachment", // Our custom AJAX action
                    attachment_id: attachmentId,
                    _wpnonce: aerp_crm_ajax._wpnonce_delete_attachment, // Get nonce from localized script
                },
                success: function (response) {
                    if (response.success) {
                        $attachmentDiv.remove(); // Visually remove on success
                        alert(response.data); // Show success message
                    } else {
                        alert("Lỗi: " + response.data); // Show error message
                    }
                },
                error: function (xhr, status, error) {
                    console.error("AJAX Error: ", status, error);
                    alert("Đã xảy ra lỗi khi xóa file.");
                },
            });
        }
    });
    
$(document).on("click", ".copy-phone", function (e) {
    e.preventDefault();
    const phone = $(this).data("phone");
    if (navigator.clipboard) {
        navigator.clipboard.writeText(phone).then(() => {
            const $icon = $(this).find("i");
            const original = $icon.attr("data-original-class") || $icon.attr("class");
            if (!$icon.attr("data-original-class")) {
                $icon.attr("data-original-class", original);
            }
            $icon.removeClass().addClass("fas fa-check text-success");
            setTimeout(() => {
                $icon.removeClass().addClass($icon.attr("data-original-class"));
            }, 1200);
        });
    }
});

});