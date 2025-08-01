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
    let perPage = 20;
    let filters = {
        action: '',
        target_type: '',
        date_from: '',
        date_to: ''
    };

    // Load data on page load
    loadAuditLogs();
    loadActions();
    loadTargetTypes();

    // Filter functionality
    $('#actionFilter, #targetTypeFilter, #dateFromFilter, #dateToFilter, #perPage').on('change', function() {
        filters.action = $('#actionFilter').val();
        filters.target_type = $('#targetTypeFilter').val();
        filters.date_from = $('#dateFromFilter').val();
        filters.date_to = $('#dateToFilter').val();
        perPage = $('#perPage').val();
        currentPage = 1;
        loadAuditLogs();
    });

    // Clear filters button
    $('#clearFilters').on('click', function() {
        $('#actionFilter').val('');
        $('#targetTypeFilter').val('');
        $('#dateFromFilter').val('');
        $('#dateToFilter').val('');
        filters = {
            action: '',
            target_type: '',
            date_from: '',
            date_to: ''
        };
        currentPage = 1;
        loadAuditLogs();
    });

    // Refresh button
    $('#refreshLogs').on('click', function() {
        loadAuditLogs();
    });

    // View stats button
    $('#viewStats').on('click', function() {
        loadStats();
    });

    // Load actions for filter dropdown
    function loadActions() {
        adminTokenHandler.get('/api/admin/audit-logs/actions')
            .done(function(response) {
                let options = '<option value="">All Actions</option>';
                response.actions.forEach(function(action) {
                    const formattedAction = formatAction(action);
                    options += `<option value="${action}">${formattedAction}</option>`;
                });
                $('#actionFilter').html(options);
            });
    }

    // Load target types for filter dropdown
    function loadTargetTypes() {
        adminTokenHandler.get('/api/admin/audit-logs/target-types')
            .done(function(response) {
                let options = '<option value="">All Target Types</option>';
                response.target_types.forEach(function(targetType) {
                    const formattedType = formatTargetType(targetType);
                    options += `<option value="${targetType}">${formattedType}</option>`;
                });
                $('#targetTypeFilter').html(options);
            });
    }

    // Load audit logs function
    function loadAuditLogs() {
        const params = new URLSearchParams({
            page: currentPage,
            per_page: perPage,
            action: filters.action,
            target_type: filters.target_type,
            date_from: filters.date_from,
            date_to: filters.date_to
        });

        adminTokenHandler.get('/api/admin/audit-logs?' + params.toString())
            .done(function(response) {
                renderAuditLogsTable(response.data);
                renderPagination(response);
            })
            .fail(function(xhr) {
                console.error('Error loading audit logs:', xhr);
                $('#auditLogsTableBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading audit logs</td></tr>');
            });
    }

    // Load statistics
    function loadStats() {
        adminTokenHandler.get('/api/admin/audit-logs/stats')
            .done(function(response) {
                renderStats(response);
            })
            .fail(function(xhr) {
                $('#statsContent').html('<p class="text-danger">Error loading statistics</p>');
            });
    }

    // Render audit logs table
    function renderAuditLogsTable(auditLogs) {
        let html = '';
        auditLogs.forEach(function(log) {
            const actionColor = getActionColor(log.action);
            const formattedAction = formatAction(log.action);
            const userName = log.user ? log.user.name : 'System';
            const userEmail = log.user ? log.user.email : '';
            const targetInfo = getTargetInfo(log);
            const userAgentShort = log.user_agent ? log.user_agent.substring(0, 30) + '...' : '';

            html += `
            <tr>
                <td>
                    <small>${formatDateTime(log.created_at)}</small>
                </td>
                <td>
                    <strong>${userName}</strong>
                    ${userEmail ? `<br><small class="text-muted">${userEmail}</small>` : ''}
                </td>
                <td>
                    <span class="badge badge-${actionColor}">${formattedAction}</span>
                </td>
                <td class="text-truncate" style="max-width: 100px;" title="${targetInfo.full}">
                    ${targetInfo.short}
                </td>
                <td>
                    <small>${log.ip_address || 'N/A'}</small>
                </td>
                <td class="text-truncate" style="max-width: 150px;" title="${log.user_agent || 'N/A'}">
                    <small>${userAgentShort}</small>
                </td>
                <td>
                    <button class="btn btn-info btn-xs" onclick="viewAuditLog(${log.id})" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
        });
        $('#auditLogsTableBody').html(html);
    }

    // Render statistics
    function renderStats(stats) {
        let html = `
        <div class="row">
            <div class="col-md-6">
                <h5>Overview</h5>
                <p><strong>Total Entries:</strong> ${stats.total_entries}</p>
                <p><strong>Recent Entries (24h):</strong> ${stats.recent_entries}</p>
                
                <h5>Top Actions</h5>
                <ul class="list-group">
    `;

        Object.entries(stats.by_action).forEach(([action, count]) => {
            html += `<li class="list-group-item d-flex justify-content-between align-items-center">
            ${formatAction(action)}
            <span class="badge badge-${getActionColor(action)} badge-pill">${count}</span>
        </li>`;
        });

        html += `
                </ul>
            </div>
            <div class="col-md-6">
                <h5>Top Users</h5>
                <div class="list-group">
    `;

        stats.by_user.forEach(function(item) {
            html += `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                ${item.user_name}
                <span class="badge badge-info badge-pill">${item.count}</span>
            </div>
        `;
        });

        html += `
                </div>
                
                <h5>Recent Activities</h5>
                <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
    `;

        stats.recent_activities.forEach(function(log) {
            html += `
            <div class="list-group-item list-group-item-action p-2">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">${formatAction(log.action)}</h6>
                    <small>${formatDateTime(log.created_at)}</small>
                </div>
                <p class="mb-1"><strong>${log.user?.name || 'System'}</strong></p>
                <small>Target: ${formatTargetType(log.target_type)} ID: ${log.target_id || 'N/A'}</small>
            </div>
        `;
        });

        html += `
                </div>
            </div>
        </div>
    `;

        $('#statsContent').html(html);
    }

    // Render pagination
    function renderPagination(response) {
        let html = '<nav><ul class="pagination pagination-sm">';

        if (response.current_page > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${response.current_page - 1})">Previous</a></li>`;
        }

        for (let i = 1; i <= response.last_page; i++) {
            const active = i === response.current_page ? 'active' : '';
            html += `<li class="page-item ${active}"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
        }

        if (response.current_page < response.last_page) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${response.current_page + 1})">Next</a></li>`;
        }

        html += '</ul></nav>';
        $('#auditLogsPagination').html(html);
    }

    // Helper functions
    function getActionColor(action) {
        if (action.includes('login')) return 'success';
        if (action.includes('logout')) return 'secondary';
        if (action.includes('created')) return 'primary';
        if (action.includes('updated')) return 'warning';
        if (action.includes('deleted')) return 'danger';
        if (action.includes('assigned') || action.includes('removed')) return 'info';
        return 'secondary';
    }

    function formatAction(action) {
        return action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    function formatTargetType(targetType) {
        if (!targetType) return 'N/A';
        return targetType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    function getTargetInfo(log) {
        const targetType = formatTargetType(log.target_type);
        const targetId = log.target_id || 'N/A';
        const full = `${targetType} (ID: ${targetId})`;
        const short = targetType;
        return {
            full,
            short
        };
    }

    // Global functions for buttons
    window.changePage = function(page) {
        currentPage = page;
        loadAuditLogs();
    };

    window.viewAuditLog = function(logId) {
        adminTokenHandler.get(`/api/admin/audit-logs/${logId}`)
            .done(function(response) {
                const log = response.audit_log;
                let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h5>Basic Information</h5>
                        <table class="table table-sm">
                            <tr>
                                <th>Timestamp:</th>
                                <td>${formatDateTime(log.created_at)}</td>
                            </tr>
                            <tr>
                                <th>User:</th>
                                <td>${log.user ? `${log.user.name} (${log.user.email})` : 'System'}</td>
                            </tr>
                            <tr>
                                <th>Action:</th>
                                <td><span class="badge badge-${getActionColor(log.action)}">${formatAction(log.action)}</span></td>
                            </tr>
                            <tr>
                                <th>Target Type:</th>
                                <td>${formatTargetType(log.target_type)}</td>
                            </tr>
                            <tr>
                                <th>Target ID:</th>
                                <td>${log.target_id || 'N/A'}</td>
                            </tr>
                            <tr>
                                <th>IP Address:</th>
                                <td>${log.ip_address || 'N/A'}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>User Agent</h5>
                        <div class="bg-light border rounded p-3 font-monospace small" style="max-height: 200px; overflow-y: auto; white-space: pre-wrap;">
${log.user_agent || 'N/A'}
                        </div>
                    </div>
                </div>
            `;

                if (log.old_values || log.new_values) {
                    html += `
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Old Values</h5>
                            <div class="bg-light border rounded p-3 font-monospace small" style="max-height: 200px; overflow-y: auto; white-space: pre-wrap;">
${log.old_values ? JSON.stringify(log.old_values, null, 2) : 'None'}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5>New Values</h5>
                            <div class="bg-light border rounded p-3 font-monospace small" style="max-height: 200px; overflow-y: auto; white-space: pre-wrap;">
${log.new_values ? JSON.stringify(log.new_values, null, 2) : 'None'}
                            </div>
                        </div>
                    </div>
                `;
                }

                $('#auditLogDetailsContent').html(html);
                $('#auditLogDetailsModal').modal('show');
            })
            .fail(function(xhr) {
                toastr.error('Error loading audit log details');
            });
    };
});
