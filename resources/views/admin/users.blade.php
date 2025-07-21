@extends('admin.layouts.app')

@section('title', 'User Management - MELT-B Admin')

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1>
        <i class="fas fa-users text-primary"></i>
        User Management
        <small class="text-muted">Manage system users and their access</small>
    </h1>
    <button class="btn btn-primary" data-toggle="modal" data-target="#createUserModal">
        <i class="fas fa-plus"></i> Add New User
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

@push('js')
<script>
    // Set admin token for use in external JS file
    window.adminToken = '{{ session("admin_token") }}';
</script>
@vite(['resources/js/admin/users.js'])
@endpush