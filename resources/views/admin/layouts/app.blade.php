@extends('adminlte::page')

@section('adminlte_css_pre')
    {{-- CSRF Token Meta Tag --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('css')
    {{-- Include toastr and SweetAlert2 CSS --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    
    {{-- MapLibre GL JS CSS --}}
    <link href="https://unpkg.com/maplibre-gl@^5.6.1/dist/maplibre-gl.css" rel="stylesheet" />
    
    {{-- Additional CSS can be added here --}}
    @stack('css')
@stop

@section('js')
    {{-- Include toastr and SweetAlert2 JS --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    {{-- MapLibre GL JS --}}
    <script src="https://unpkg.com/maplibre-gl@^5.6.1/dist/maplibre-gl.js"></script>
    
    <script>
        // Global admin token
        window.adminToken = '{{ session("admin_token") }}';
        
        // Toastr configuration
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": false,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        // Utility functions for consistent alert handling
        window.showSuccessAlert = function(message) {
            toastr.success(message);
        };

        window.showErrorAlert = function(message) {
            toastr.error(message);
        };

        window.showWarningAlert = function(message) {
            toastr.warning(message);
        };

        window.showInfoAlert = function(message) {
            toastr.info(message);
        };

        // Enhanced confirmation dialog with SweetAlert2
        window.showConfirmDialog = function(message, callback, title = 'Confirm Action', type = 'warning') {
            Swal.fire({
                title: title,
                text: message,
                icon: type,
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, proceed!',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed && typeof callback === 'function') {
                    callback();
                }
            });
        };

        // Specific confirmation dialogs for common actions
        window.showDeleteConfirm = function(itemName, callback) {
            Swal.fire({
                title: 'Are you sure?',
                text: `Do you want to delete "${itemName}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed && typeof callback === 'function') {
                    callback();
                }
            });
        };

        window.showRemoveConfirm = function(itemName, callback) {
            Swal.fire({
                title: 'Remove Confirmation',
                text: `Are you sure you want to remove "${itemName}"? This will immediately revoke access.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, remove it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed && typeof callback === 'function') {
                    callback();
                }
            });
        };

        // Global AJAX setup for handling authentication errors
        let logoutInProgress = false; // Prevent multiple logout attempts
        
        /**
         * Simple secure logout - just invalidate session and redirect
         */
        function performSecureLogout() {
            // Prevent multiple simultaneous logout attempts
            if (logoutInProgress) {
                return;
            }
            logoutInProgress = true;
            
            // Clear any client-side storage
            if (typeof(Storage) !== "undefined") {
                localStorage.clear();
                sessionStorage.clear();
            }
            
            // Perform server-side logout to invalidate session
            $.ajax({
                url: '/admin/logout',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                timeout: 3000,
                complete: function() {
                    // Always redirect to login page
                    window.location.replace('/admin/login');
                },
                error: function() {
                    // Even if logout fails, redirect for security
                    window.location.replace('/admin/login');
                }
            });
        }
        
        // Setup CSRF token for all AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        
        $(document).ajaxError(function(event, xhr, settings, thrownError) {
            // Handle 401 Unauthorized errors - immediate logout
            if (xhr.status === 401 && !logoutInProgress) {
                performSecureLogout();
            }
            // Handle 419 CSRF Token Mismatch - refresh page to get new token
            else if (xhr.status === 419 && !logoutInProgress) {
                window.location.reload();
            }
            // Handle 403 Forbidden errors
            else if (xhr.status === 403) {
                toastr.error('Access denied. You do not have permission to perform this action.');
            }
            // Handle 500 Internal Server Error
            else if (xhr.status === 500) {
                toastr.error('Internal server error. Please try again later.');
            }
            // Handle network errors
            else if (xhr.status === 0 && thrownError !== 'abort') {
                toastr.error('Network error. Please check your connection.');
            }
        });
    </script>
    
    {{-- Additional JavaScript can be added here --}}
    @stack('js')
@stop