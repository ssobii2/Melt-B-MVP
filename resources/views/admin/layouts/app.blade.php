@extends('adminlte::page')

@section('css')
    {{-- Include toastr and SweetAlert2 CSS --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    
    {{-- Additional CSS can be added here --}}
    @stack('css')
@stop

@section('js')
    {{-- Include toastr and SweetAlert2 JS --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
        $(document).ajaxError(function(event, xhr, settings, thrownError) {
            // Handle 401 Unauthorized errors globally
            if (xhr.status === 401) {
                // Automatically logout and redirect to admin login
                $.ajax({
                    url: '/admin/logout',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    complete: function() {
                        // Redirect to admin login regardless of logout response
                        window.location.href = '/admin/login';
                    }
                });
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