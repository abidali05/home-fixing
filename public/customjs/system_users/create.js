$("#email").change(function (e) {
    e.preventDefault();
    let data = new FormData();
    data.append("email", $(this).val());
    let type = "POST";
    let url = "/check-email-availability";
    SendAjaxRequestToServer(type, url, data, "", chckEmailResponse, "", "");
});

function chckEmailResponse(response) {
    if (response.status == 200) {
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

$("#phone").change(function (e) {
    e.preventDefault();
    let data = new FormData();
    data.append("phone", $(this).val());
    let type = "POST";
    let url = "/check-phone-availability";
    SendAjaxRequestToServer(type, url, data, "", chckphoneResponse, "", "");
});

function chckphoneResponse(response) {
    if (response.status == 200) {
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
