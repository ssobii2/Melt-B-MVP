@extends('adminlte::page')

@section('title', 'User Management - MELT-B Admin')

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1>
        <i class="fas fa-users text-primary"></i>
        User Management
        <small class="text-muted">Manage system users and roles</small>
    </h1>
    <button class="btn btn-primary" data-toggle="modal" data-target="#createUserModal">
        <i class="fas fa-user-plus"></i> Add New User
    </button>
</div>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    All Users
                </h3>
                <div class="card-tools">
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" id="searchUsers" class="form-control float-right" placeholder="Search users...">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-default">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select id="roleFilter" class="form-control">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="municipality">Municipality</option>
                            <option value="researcher">Researcher</option>
                            <option value="contractor">Contractor</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="perPage" class="form-control">
                            <option value="15">15 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                        </select>
                    </div>
                    <div class="col-md-6 text-right">
                        <button id="refreshUsers" class="btn btn-outline-primary">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="usersTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Entitlements</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="6" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading users...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="usersPagination" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-user-plus"></i> Create New User
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="createUserForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createName">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="createName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createEmail">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="createEmail" name="email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createPassword">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="createPassword" name="password" required>
                                <small class="form-text text-muted">Minimum 8 characters</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createRole">Role <span class="text-danger">*</span></label>
                                <select class="form-control" id="createRole" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="user">User</option>
                                    <option value="researcher">Researcher</option>
                                    <option value="contractor">Contractor</option>
                                    <option value="municipality">Municipality</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <!-- Contact Information Fields -->
                    <h6 class="text-muted mb-3">Contact Information (Optional)</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createPhone">Phone</label>
                                <input type="text" class="form-control" id="createPhone" name="phone" placeholder="+36 30 123 4567">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createCompany">Company</label>
                                <input type="text" class="form-control" id="createCompany" name="company" placeholder="Company Name">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createDepartment">Department</label>
                                <input type="text" class="form-control" id="createDepartment" name="department" placeholder="Department">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createAddress">Address</label>
                                <input type="text" class="form-control" id="createAddress" name="address" placeholder="Address">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-user-edit"></i> Edit User
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editUserForm">
                <input type="hidden" id="editUserId" name="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editName">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editEmail">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="editEmail" name="email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editPassword">New Password</label>
                                <input type="password" class="form-control" id="editPassword" name="password">
                                <small class="form-text text-muted">Leave blank to keep current password</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editRole">Role <span class="text-danger">*</span></label>
                                <select class="form-control" id="editRole" name="role" required>
                                    <option value="user">User</option>
                                    <option value="researcher">Researcher</option>
                                    <option value="contractor">Contractor</option>
                                    <option value="municipality">Municipality</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <!-- Contact Information Fields -->
                    <h6 class="text-muted mb-3">Contact Information (Optional)</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editPhone">Phone</label>
                                <input type="text" class="form-control" id="editPhone" name="phone" placeholder="+36 30 123 4567">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editCompany">Company</label>
                                <input type="text" class="form-control" id="editCompany" name="company" placeholder="Company Name">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editDepartment">Department</label>
                                <input type="text" class="form-control" id="editDepartment" name="department" placeholder="Department">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editAddress">Address</label>
                                <input type="text" class="form-control" id="editAddress" name="address" placeholder="Address">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- User Details Modal -->
<div class="modal fade" id="userDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-user"></i> User Details
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- User Entitlements Management Modal -->
<div class="modal fade" id="userEntitlementsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-key"></i> Manage User Entitlements
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="manageUserId">

                <!-- Add New Entitlement -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-plus"></i> Assign New Entitlement
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Available Entitlements</label>
                            <select class="form-control" id="availableEntitlements">
                                <option value="">Select an entitlement to assign...</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="assignEntitlementToUser()">
                            <i class="fas fa-plus"></i> Assign Entitlement
                        </button>
                    </div>
                </div>

                <!-- Current Entitlements -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list"></i> Current Entitlements
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="currentUserEntitlements">
                            <!-- Loaded dynamically -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('css')
<style>
    .role-badge {
        font-size: 0.8em;
        padding: 0.25em 0.6em;
        border-radius: 0.25rem;
    }

    .table td {
        vertical-align: middle;
    }

    .pagination {
        justify-content: center;
    }
</style>
@stop

@section('js')
<script>
    // Global timezone handling functions
    window.formatDateTime = function(dateString, options = {}) {
        if (!dateString) return 'Never';

        const date = new Date(dateString);
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZoneName: 'short'
        };

        return date.toLocaleString(undefined, {
            ...defaultOptions,
            ...options
        });
    };

    window.formatDate = function(dateString, options = {}) {
        if (!dateString) return 'Never';

        const date = new Date(dateString);
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        };

        return date.toLocaleDateString(undefined, {
            ...defaultOptions,
            ...options
        });
    };

    $(document).ready(function() {
        let currentPage = 1;
        let perPage = 15;
        let searchTerm = '';
        let roleFilter = '';

        // Load users on page load
        loadUsers();

        // Search functionality
        let searchTimeout;
        $('#searchUsers').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                searchTerm = $('#searchUsers').val();
                currentPage = 1;
                loadUsers();
            }, 500);
        });

        // Filter functionality
        $('#roleFilter, #perPage').on('change', function() {
            roleFilter = $('#roleFilter').val();
            perPage = $('#perPage').val();
            currentPage = 1;
            loadUsers();
        });

        // Refresh button
        $('#refreshUsers').on('click', function() {
            loadUsers();
        });

        // Load users function
        function loadUsers() {
            const params = new URLSearchParams({
                page: currentPage,
                per_page: perPage,
                search: searchTerm,
                role: roleFilter
            });

            $.ajax({
                url: '/api/admin/users?' + params.toString(),
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    renderUsersTable(response.data);
                    renderPagination(response.meta);
                },
                error: function(xhr) {
                    console.error('Error loading users:', xhr);
                    $('#usersTableBody').html('<tr><td colspan="6" class="text-center text-danger">Error loading users</td></tr>');
                }
            });
        }

        // Render users table
        function renderUsersTable(users) {
            let html = '';
            users.forEach(function(user) {
                const entitlementsCount = user.entitlements ? user.entitlements.length : 0;
                const roleColor = getRoleColor(user.role);

                html += `
                <tr>
                    <td><strong>${user.name}</strong></td>
                    <td>${user.email}</td>
                    <td><span class="badge badge-${roleColor}">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</span></td>
                    <td><span class="badge badge-info">${entitlementsCount}</span></td>
                    <td><small>${formatDate(user.created_at)}</small></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info" onclick="viewUser(${user.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-warning" onclick="editUser(${user.id})" title="Edit User">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger" onclick="deleteUser(${user.id}, '${user.name}')" title="Delete User">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            });
            $('#usersTableBody').html(html);
        }

        // Render pagination
        function renderPagination(meta) {
            let html = '<nav><ul class="pagination pagination-sm">';

            // Previous button
            if (meta.current_page > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${meta.current_page - 1})">Previous</a></li>`;
            }

            // Page numbers
            for (let i = 1; i <= meta.last_page; i++) {
                const active = i === meta.current_page ? 'active' : '';
                html += `<li class="page-item ${active}"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
            }

            // Next button
            if (meta.current_page < meta.last_page) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${meta.current_page + 1})">Next</a></li>`;
            }

            html += '</ul></nav>';
            $('#usersPagination').html(html);
        }

        // Helper functions
        function getRoleColor(role) {
            const colors = {
                'admin': 'danger',
                'municipality': 'warning',
                'researcher': 'info',
                'contractor': 'success',
                'user': 'secondary'
            };
            return colors[role] || 'secondary';
        }

        // Create user form
        $('#createUserForm').on('submit', function(e) {
            e.preventDefault();

            const formData = {
                name: $('#createName').val(),
                email: $('#createEmail').val(),
                password: $('#createPassword').val(),
                role: $('#createRole').val(),
                phone: $('#createPhone').val(),
                company: $('#createCompany').val(),
                department: $('#createDepartment').val(),
                address: $('#createAddress').val()
            };

            $.ajax({
                url: '/api/admin/users',
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify(formData),
                success: function(response) {
                    $('#createUserModal').modal('hide');
                    $('#createUserForm')[0].reset();
                    loadUsers();
                    showAlert('success', 'User created successfully!');
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.errors;
                    if (errors) {
                        let errorMessage = 'Validation errors:\n';
                        Object.keys(errors).forEach(key => {
                            errorMessage += `- ${errors[key][0]}\n`;
                        });
                        showAlert('danger', errorMessage);
                    } else {
                        showAlert('danger', 'Error creating user');
                    }
                }
            });
        });

        // Global functions for buttons
        window.changePage = function(page) {
            currentPage = page;
            loadUsers();
        };

        window.viewUser = function(userId) {
            // Load user details
            $.ajax({
                url: `/api/admin/users/${userId}`,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    const user = response.user;
                    let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h5>User Information</h5>
                            <p><strong>Name:</strong> ${user.name}</p>
                            <p><strong>Email:</strong> ${user.email}</p>
                            <p><strong>Role:</strong> <span class="badge badge-${getRoleColor(user.role)}">${user.role}</span></p>
                            <p><strong>Created:</strong> ${formatDateTime(user.created_at)}</p>
                            
                            ${user.contact_info ? `
                                <p><strong>Phone:</strong> ${user.contact_info.phone || 'Not provided'}</p>
                                <p><strong>Company:</strong> ${user.contact_info.company || 'Not provided'}</p>
                                <p><strong>Department:</strong> ${user.contact_info.department || 'Not provided'}</p>
                                <p><strong>Address:</strong> ${user.contact_info.address || 'Not provided'}</p>
                            ` : `
                                <p><strong>Phone:</strong> Not provided</p>
                                <p><strong>Company:</strong> Not provided</p>
                                <p><strong>Department:</strong> Not provided</p>
                                <p><strong>Address:</strong> Not provided</p>
                            `}
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5>Entitlements (${user.entitlements ? user.entitlements.length : 0})</h5>
                                <button class="btn btn-sm btn-primary" onclick="manageUserEntitlements(${user.id})">
                                    <i class="fas fa-plus"></i> Manage Access
                                </button>
                            </div>
                            <div class="list-group">
                `;

                    if (user.entitlements && user.entitlements.length > 0) {
                        user.entitlements.forEach(function(entitlement) {
                            html += `
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${entitlement.type}</strong> - ${entitlement.dataset?.name || 'Unknown Dataset'}
                                        <br><small>Expires: ${formatDate(entitlement.expires_at)}</small>
                                    </div>
                                    <button class="btn btn-sm btn-danger" onclick="removeUserEntitlement(${user.id}, ${entitlement.id})">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                        });
                    } else {
                        html += `
                        <div class="list-group-item text-muted">
                            <em>No entitlements assigned</em>
                            <br><small>Click "Manage Access" to assign entitlements to this user.</small>
                        </div>
                        `;
                    }

                    html += `
                            </div>
                        </div>
                    </div>
                `;

                    $('#userDetailsContent').html(html);
                    $('#userDetailsModal').modal('show');
                }
            });
        };

        window.editUser = function(userId) {
            // Load user data for editing
            $.ajax({
                url: `/api/admin/users/${userId}`,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    const user = response.user;
                    $('#editUserId').val(user.id);
                    $('#editName').val(user.name);
                    $('#editEmail').val(user.email);
                    $('#editRole').val(user.role);

                    // Populate contact info fields
                    const contactInfo = user.contact_info || {};
                    $('#editPhone').val(contactInfo.phone || '');
                    $('#editCompany').val(contactInfo.company || '');
                    $('#editDepartment').val(contactInfo.department || '');
                    $('#editAddress').val(contactInfo.address || '');

                    $('#editUserModal').modal('show');
                }
            });
        };

        window.deleteUser = function(userId, userName) {
            if (confirm(`Are you sure you want to delete user "${userName}"?`)) {
                $.ajax({
                    url: `/api/admin/users/${userId}`,
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        loadUsers();
                        showAlert('success', 'User deleted successfully!');
                    },
                    error: function(xhr) {
                        showAlert('danger', xhr.responseJSON?.message || 'Error deleting user');
                    }
                });
            }
        };

        // Edit user form
        $('#editUserForm').on('submit', function(e) {
            e.preventDefault();

            const userId = $('#editUserId').val();
            const formData = {
                name: $('#editName').val(),
                email: $('#editEmail').val(),
                role: $('#editRole').val(),
                phone: $('#editPhone').val(),
                company: $('#editCompany').val(),
                department: $('#editDepartment').val(),
                address: $('#editAddress').val()
            };

            if ($('#editPassword').val()) {
                formData.password = $('#editPassword').val();
            }

            $.ajax({
                url: `/api/admin/users/${userId}`,
                method: 'PUT',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify(formData),
                success: function(response) {
                    $('#editUserModal').modal('hide');
                    loadUsers();
                    showAlert('success', 'User updated successfully!');
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.errors;
                    if (errors) {
                        let errorMessage = 'Validation errors:<br>';
                        Object.keys(errors).forEach(key => {
                            errorMessage += `• ${errors[key][0]}<br>`;
                        });
                        showModalAlert('editUserModal', 'danger', errorMessage);
                    } else {
                        showModalAlert('editUserModal', 'danger', 'Error updating user');
                    }
                }
            });
        });

        function showAlert(type, message) {
            const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
            $('.content-header').after(alertHtml);

            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        }

        function showModalAlert(modalId, type, message) {
            // Remove any existing alerts in the modal
            $(`#${modalId} .modal-alert`).remove();

            const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show modal-alert" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
            $(`#${modalId} .modal-body`).prepend(alertHtml);

            // Auto-hide after 8 seconds
            setTimeout(function() {
                $(`#${modalId} .modal-alert`).alert('close');
            }, 8000);
        }

        // User entitlement management functions
        window.manageUserEntitlements = function(userId) {
            $('#manageUserId').val(userId);
            loadAvailableEntitlements(userId);
            loadCurrentUserEntitlements(userId);
            $('#userEntitlementsModal').modal('show');
        };

        function loadAvailableEntitlements(userId) {
            $.ajax({
                url: '/api/admin/entitlements',
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    let html = '<option value="">Select an entitlement to assign...</option>';

                    // Get current user's entitlements to filter them out
                    $.ajax({
                        url: `/api/admin/users/${userId}`,
                        method: 'GET',
                        headers: {
                            'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                            'Accept': 'application/json'
                        },
                        success: function(userResponse) {
                            const userEntitlementIds = userResponse.user.entitlements?.map(e => e.id) || [];

                            response.data.forEach(function(entitlement) {
                                if (!userEntitlementIds.includes(entitlement.id)) {
                                    html += `<option value="${entitlement.id}">
                                        ${entitlement.type} - ${entitlement.dataset?.name || 'Unknown Dataset'}
                                        ${entitlement.expires_at ? ` (Expires: ${formatDate(entitlement.expires_at)})` : ' (No Expiry)'}
                                    </option>`;
                                }
                            });

                            $('#availableEntitlements').html(html);
                        }
                    });
                }
            });
        }

        function loadCurrentUserEntitlements(userId) {
            $.ajax({
                url: `/api/admin/users/${userId}`,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    const entitlements = response.user.entitlements || [];
                    let html = '';

                    if (entitlements.length > 0) {
                        entitlements.forEach(function(entitlement) {
                            html += `
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${entitlement.type}</strong> - ${entitlement.dataset?.name || 'Unknown Dataset'}
                                        <br><small class="text-muted">
                                            Expires: ${formatDate(entitlement.expires_at)}
                                        </small>
                                    </div>
                                    <button class="btn btn-sm btn-danger" onclick="removeUserEntitlementFromModal(${userId}, ${entitlement.id})">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                </div>
                            </div>
                            `;
                        });
                    } else {
                        html = '<div class="text-center text-muted p-3"><em>No entitlements assigned</em></div>';
                    }

                    $('#currentUserEntitlements').html(html);
                }
            });
        }

        window.assignEntitlementToUser = function() {
            const userId = $('#manageUserId').val();
            const entitlementId = $('#availableEntitlements').val();

            if (!entitlementId) {
                showModalAlert('userEntitlementsModal', 'warning', 'Please select an entitlement to assign.');
                return;
            }

            $.ajax({
                url: `/api/admin/users/${userId}/entitlements/${entitlementId}`,
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    showAlert('success', 'Entitlement assigned successfully!');
                    loadAvailableEntitlements(userId);
                    loadCurrentUserEntitlements(userId);
                    loadUsers(); // Refresh the main table
                },
                error: function(xhr) {
                    showAlert('danger', xhr.responseJSON?.message || 'Error assigning entitlement');
                }
            });
        };

        window.removeUserEntitlement = function(userId, entitlementId) {
            if (confirm('Are you sure you want to remove this entitlement from the user?')) {
                $.ajax({
                    url: `/api/admin/users/${userId}/entitlements/${entitlementId}`,
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        showAlert('success', 'Entitlement removed successfully!');
                        loadUsers(); // Refresh the main table

                        // If user details modal is open, refresh it
                        if ($('#userDetailsModal').hasClass('show')) {
                            viewUser(userId);
                        }
                    },
                    error: function(xhr) {
                        showAlert('danger', xhr.responseJSON?.message || 'Error removing entitlement');
                    }
                });
            }
        };

        window.removeUserEntitlementFromModal = function(userId, entitlementId) {
            if (confirm('Are you sure you want to remove this entitlement from the user?')) {
                $.ajax({
                    url: `/api/admin/users/${userId}/entitlements/${entitlementId}`,
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        showAlert('success', 'Entitlement removed successfully!');
                        loadAvailableEntitlements(userId);
                        loadCurrentUserEntitlements(userId);
                        loadUsers(); // Refresh the main table
                    },
                    error: function(xhr) {
                        showAlert('danger', xhr.responseJSON?.message || 'Error removing entitlement');
                    }
                });
            }
        };
    });
</script>
@stop