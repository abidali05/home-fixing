$(function() {
    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
        }
    });
});

$(document).on('keyup', "[type=number], [type=email]", function (e) {
    if ($(this).attr('maxlength')) {
        if (this.value.length > this.maxLength) {
            this.value = this.value.slice(0, this.maxLength);
        }
    }
});

$(document).ready(function(){
    toastr.options = {
        timeOut : 0,
        extendedTimeOut : 100,
        tapToDismiss : true,
        debug : false,
        fadeOut: 10,
        positionClass : "toast-top-center"
    };

    // Show the UI blocker when user-initiated AJAX starts (skipping silent background tasks)
    $(document).ajaxSend(function(event, xhr, settings) {
        if (settings && settings.url && (settings.url.indexOf('sidebar-notifications') !== -1 || settings.silent === true)) {
            return;
        }
        $('#sidenav-main').css('z-index', '0');
        $('#uiBlocker').show();
    });

    // Hide the UI blocker when AJAX completes
    $(document).ajaxComplete(function(event, xhr, settings) {
        setTimeout(function(){
            $('#uiBlocker').hide();
            $('#sidenav-main').css('z-index', '999');
        }, 300);
    });
});

function SendAjaxRequestToServer(
    requestType = "GET",
    url,
    data,
    dataType = "json",
    callBack = "",
    spinner_button,
    submit_button
) {
    // console.log(data, url, dataType);
    $.ajax({
        type: requestType,
        url: base_url+url,
        data: data,
        dataType: dataType,
        processData: false,
        contentType: false,
        beforeSend: function (response) {
            $(spinner_button).toggle();
            $(submit_button).attr('disabled', true);
            // $(submit_button).toggle();
        },
        success: function (response) {
            if (typeof callBack === "function") {
                callBack(response);
            } else {
                console.log("error");
            }
        },
        complete: function (data) {
            $(spinner_button).toggle();
            $(submit_button).attr('disabled', false);
            // $(submit_button).toggle();
        },
        error: function (response) {
            if (typeof callBack === "function") {
                callBack(response);
            } else {
                console.log("error");
            }
        },
    });
}

