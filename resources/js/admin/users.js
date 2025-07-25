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
                'Authorization': 'Bearer ' + window.adminToken,
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
            const isVerified = user.email_verified_at !== null;
            const verificationBadge = isVerified 
                ? '<span class="badge badge-success"><i class="fas fa-check"></i> Verified</span>'
                : '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Unverified</span>';

            html += `
            <tr>
                <td><strong>${user.name}</strong></td>
                <td>
                    ${user.email}
                    <br><small>${verificationBadge}</small>
                </td>
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
                        ${!isVerified ? `
                        <button class="btn btn-success" onclick="verifyUserEmail(${user.id}, '${user.name}')" title="Verify Email">
                            <i class="fas fa-check-circle"></i>
                        </button>
                        ` : ''}
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
                'Authorization': 'Bearer ' + window.adminToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(formData),
            success: function(response) {
                $('#createUserModal').modal('hide');
                $('#createUserForm')[0].reset();
                loadUsers();
                toastr.success('User created successfully!');
            },
            error: function(xhr) {
                const errors = xhr.responseJSON?.errors;
                if (errors) {
                    let errorMessage = 'Validation errors:\n';
                    Object.keys(errors).forEach(key => {
                        errorMessage += `- ${errors[key][0]}\n`;
                    });
                    toastr.error(errorMessage);
                } else {
                    toastr.error('Error creating user');
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
                'Authorization': 'Bearer ' + window.adminToken,
                'Accept': 'application/json'
            },
            success: function(response) {
                const user = response.user;
                const isVerified = user.email_verified_at !== null;
                const verificationStatus = isVerified 
                    ? '<span class="badge badge-success"><i class="fas fa-check"></i> Verified</span>'
                    : '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Unverified</span>';
                
                let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h5>User Information</h5>
                        <p><strong>Name:</strong> ${user.name}</p>
                        <p><strong>Email:</strong> ${user.email} ${verificationStatus}</p>
                        ${!isVerified ? `
                        <p>
                            <button class="btn btn-sm btn-success" onclick="verifyUserEmail(${user.id}, '${user.name}')">
                                <i class="fas fa-check-circle"></i> Verify Email Manually
                            </button>
                        </p>
                        ` : `
                        <p><strong>Email Verified:</strong> ${formatDateTime(user.email_verified_at)}</p>
                        `}
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
                'Authorization': 'Bearer ' + window.adminToken,
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
        showDeleteConfirm(userName, function() {
            $.ajax({
                url: `/api/admin/users/${userId}`,
                method: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + window.adminToken,
                    'Accept': 'application/json'
                },
                success: function(response) {
                    loadUsers();
                    toastr.success('User deleted successfully!');
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error deleting user');
                }
            });
        });
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
                'Authorization': 'Bearer ' + window.adminToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(formData),
            success: function(response) {
                $('#editUserModal').modal('hide');
                loadUsers();
                toastr.success('User updated successfully!');
            },
            error: function(xhr) {
                const errors = xhr.responseJSON?.errors;
                if (errors) {
                    let errorMessage = 'Validation errors:<br>';
                    Object.keys(errors).forEach(key => {
                        errorMessage += `• ${errors[key][0]}<br>`;
                    });
                    toastr.error(errorMessage);
                } else {
                    toastr.error('Error updating user');
                }
            }
        });
    });

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
                'Authorization': 'Bearer ' + window.adminToken,
                'Accept': 'application/json'
            },
            success: function(response) {
                let html = '<option value="">Select an entitlement to assign...</option>';

                // Get current user's entitlements to filter them out
                $.ajax({
                    url: `/api/admin/users/${userId}`,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + window.adminToken,
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
                'Authorization': 'Bearer ' + window.adminToken,
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
            toastr.warning('Please select an entitlement to assign.');
            return;
        }

        $.ajax({
            url: `/api/admin/users/${userId}/entitlements/${entitlementId}`,
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + window.adminToken,
                'Accept': 'application/json'
            },
            success: function(response) {
                toastr.success('Entitlement assigned successfully!');
                loadAvailableEntitlements(userId);
                loadCurrentUserEntitlements(userId);
                loadUsers(); // Refresh the main table
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error assigning entitlement');
            }
        });
    };

    window.removeUserEntitlement = function(userId, entitlementId) {
        showRemoveConfirm('this entitlement from the user', function() {
            $.ajax({
                url: `/api/admin/users/${userId}/entitlements/${entitlementId}`,
                method: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + window.adminToken,
                    'Accept': 'application/json'
                },
                success: function(response) {
                    toastr.success('Entitlement removed successfully!');
                    loadUsers(); // Refresh the main table

                    // If user details modal is open, refresh it
                    if ($('#userDetailsModal').hasClass('show')) {
                        viewUser(userId);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error removing entitlement');
                }
            });
        });
    };

    window.removeUserEntitlementFromModal = function(userId, entitlementId) {
        showRemoveConfirm('this entitlement from the user', function() {
            $.ajax({
                url: `/api/admin/users/${userId}/entitlements/${entitlementId}`,
                method: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + window.adminToken,
                    'Accept': 'application/json'
                },
                success: function(response) {
                    toastr.success('Entitlement removed successfully!');
                    loadAvailableEntitlements(userId);
                    loadCurrentUserEntitlements(userId);
                    loadUsers(); // Refresh the main table
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error removing entitlement');
                }
            });
        });
    };

    // Email verification function
    window.verifyUserEmail = function(userId, userName) {
        Swal.fire({
            title: 'Verify Email',
            text: `Are you sure you want to manually verify the email for ${userName}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, verify it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/api/admin/users/${userId}/verify-email`,
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + window.adminToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    success: function(response) {
                        toastr.success('Email verified successfully!');
                        loadUsers(); // Refresh the main table
                        
                        // If user details modal is open, refresh it
                        if ($('#userDetailsModal').hasClass('show')) {
                            viewUser(userId);
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Error verifying email');
                    }
                });
            }
        });
    };
});