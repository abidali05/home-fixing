<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('assets/img/apple-icon.png') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">
    <title>
        @yield('title', 'Home Fixing')
    </title>
    <!--     Fonts and icons     -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
    <!-- Nucleo Icons -->
    <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-svg.css" rel="stylesheet" />

    <!-- CSS Files -->
    <link id="pagestyle" href="{{ asset('assets/css/argon-dashboard.css?v=2.1.0') }}" rel="stylesheet" />
    <link href="{{ asset('assets/css/admin-dashboard-custom.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @stack('styles')
<style>
    .btn-primary {
        --bs-btn-color: #ffffff !important;
        --bs-btn-bg: #4F2396 !important;
        --bs-btn-border-color: #4F2396 !important;
        --bs-btn-hover-color: #ffffff !important;
        --bs-btn-hover-bg: #3c1973 !important;
        --bs-btn-hover-border-color: #3c1973 !important;
        --bs-btn-focus-shadow-rgb: 80, 97, 194;
        --bs-btn-active-color: #ffffff !important;
        --bs-btn-active-bg: #2b1154 !important;
        --bs-btn-active-border-color: #2b1154 !important;
        --bs-btn-active-shadow: none;
        --bs-btn-disabled-color: #ffffff !important;
        --bs-btn-disabled-bg: #4F2396 !important;
        --bs-btn-disabled-border-color: #4F2396 !important;
    }
    .btn-primary:hover, .btn.bg-gradient-primary:hover {
        background-color: #3c1973 !important;
        border-color: #3c1973 !important;
        color: #ffffff !important;
    }
    .btn-primary:active, .btn-primary.active {
        background-color: #2b1154 !important;
        border-color: #2b1154 !important;
        color: #ffffff !important;
    }
    .badge-primary {
        background-color: #4F2396 !important;
        color: #ffffff !important;
    }
    .badge-secondary {
        background-color: #F27D4B !important;
        color: #ffffff !important;
    }
    .text-primary {
        color: #4F2396 !important;
    }
    .text-secondary {
        color: #F27D4B !important;
    }
</style>

</head>

<body class="g-sidenav-show   bg-gray-100">
    <div id="uiBlocker"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5); z-index:9999;">
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%);">
            <img src="{{ asset('assets/img/loader.gif') }}" alt="Loading..." style="height:150px; width:150px;" />
        </div>
    </div>
    @include('layouts.sidebar')
    @include('layouts.header')
    @yield('content')

    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        setTimeout(() => {
            $('.alert').fadeOut('slow');
        }, 3000);
    </script>

    <!--   Core JS Files   -->
    <script src="{{ asset('assets/js/core/popper.min.js') }}"></script>
    <script src="{{ asset('assets/js/core/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/smooth-scrollbar.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/chartjs.min.js') }}"></script>



    <script>
        var win = navigator.platform.indexOf("Win") > -1;
        if (win && document.querySelector("#sidenav-scrollbar")) {
            var options = {
                damping: "0.5",
            };
            Scrollbar.init(document.querySelector("#sidenav-scrollbar"), options);
        }
    </script>

    <!-- Github buttons -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
    <!-- Control Center for Soft Dashboard: parallax effects, scripts for the example pages etc -->
    <script src="{{ asset('assets/js/argon-dashboard.min.js?v=2.1.0') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('customjs/common.js') }}"></script>
    <script>
        var base_url = "{{ url('/') }}";
        $('.select2').select2({
            width: '100%'
        });

        $(document).ready(function() {
            if ($('.main-content').length > 0 && $('#navbarBlur').length > 0) {
                if ($('.main-content').first().find('#navbarBlur').length === 0) {
                    $('.main-content').first().prepend($('#navbarBlur'));
                }
            }
        });

        $(document).on('submit', '.admin-loader-form', function () {
            $('#sidenav-main').css('z-index', '0');
            $('#uiBlocker').show();
        });

        $(document).on('change', '.admin-auto-submit', function () {
            $(this).closest('form').trigger('submit');
        });
    </script>
    @stack('scripts')
</body>

</html>
