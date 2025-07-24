@extends('adminlte::page')

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
        // Configure Toastr with consistent settings across all admin pages
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
        let sessionExpiredShown = false; // Prevent multiple dialogs
        
        $(document).ajaxError(function(event, xhr, settings, thrownError) {
            // Handle 401 Unauthorized errors globally
            if (xhr.status === 401 && !sessionExpiredShown) {
                sessionExpiredShown = true;
                
                // Secure session expiry handling with auto-redirect
                let countdown = 10; // 10 seconds countdown
                let timerInterval;
                
                Swal.fire({
                    title: 'Session Expired',
                    html: `Your login session has expired, please login again.<br><br>
                           You will be automatically redirected to the login page in <b>${countdown}</b> seconds.<br><br>
                           <small>Click "Login Now" to redirect immediately.</small>`,
                    icon: 'warning',
                    showCancelButton: false,
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'Login Now',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    timer: countdown * 1000,
                    timerProgressBar: true,
                    didOpen: () => {
                        const content = Swal.getHtmlContainer();
                        const b = content.querySelector('b');
                        
                        timerInterval = setInterval(() => {
                            countdown--;
                            b.textContent = countdown;
                            
                            if (countdown <= 0) {
                                clearInterval(timerInterval);
                            }
                        }, 1000);
                    },
                    willClose: () => {
                        clearInterval(timerInterval);
                    }
                }).then((result) => {
                    // Regardless of user action, perform secure logout and redirect
                    performSecureLogout();
                });
                
                // Also auto-redirect after timer expires
                setTimeout(() => {
                    if (sessionExpiredShown) {
                        performSecureLogout();
                    }
                }, countdown * 1000);
            }
            
            /**
             * Perform secure logout with proper server-side session invalidation
             */
            function performSecureLogout() {
                // Disable all user interactions during logout
                $('body').css('pointer-events', 'none');
                
                // Show loading indicator
                Swal.fire({
                    title: 'Logging out...',
                    text: 'Please wait while we securely log you out.',
                    icon: 'info',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Perform server-side logout to invalidate session
                $.ajax({
                    url: '/admin/logout',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    timeout: 5000, // 5 second timeout
                    complete: function(xhr) {
                        // Clear any client-side storage
                        if (typeof(Storage) !== "undefined") {
                            localStorage.clear();
                            sessionStorage.clear();
                        }
                        
                        // Force redirect regardless of logout response
                        // This ensures user is redirected even if logout fails
                        window.location.replace('/admin/login');
                    },
                    error: function() {
                        // Even if logout fails, redirect for security
                        window.location.replace('/admin/login');
                    }
                });
            }
            // Handle 403 Forbidden errors
            if (xhr.status === 403) {
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