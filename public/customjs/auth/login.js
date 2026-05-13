$("#sendOtpBtn").click(function (e) {
    let form = document.getElementById("loginForm");
    let data = new FormData(form);
    let type = "POST";
    let url = "/send-otp";
    SendAjaxRequestToServer(type, url, data, "", sendOtpResponse, "", "sendOtpBtn");
});

function sendOtpResponse(response) {
    if (response.status == 200) {
        toastr.success(response.message, "", {
            timeOut: 3000,
        });

        $("#phone_number_div").addClass("d-none");
        $("#otp-section").removeClass("d-none");
        $("#uiBlocker").hide();
    }

    if (response.status == 402) {
        error = response.message;
    } else {
        error = response.responseJSON.message;
        var is_invalid = response.responseJSON.errors;

        $.each(is_invalid, function (key) {
            // Assuming 'key' corresponds to the form field name
            var inputField = $('[name="' + key + '"]');
            // Add the 'is-invalid' class to the input field's parent or any desired container
            inputField.addClass("is-invalid");
        });
    }
    toastr.error(error, "", {
        timeOut: 3000,
    });
}
