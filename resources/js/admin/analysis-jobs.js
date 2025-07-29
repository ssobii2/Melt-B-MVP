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
    let statusFilter = '';

    // Load jobs on page load
    loadJobs();

    // Auto-refresh every 30 seconds for running jobs
    setInterval(function() {
        loadJobs();
    }, 30000);

    // Search functionality
    let searchTimeout;
    $('#searchJobs').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            searchTerm = $('#searchJobs').val();
            currentPage = 1;
            loadJobs();
        }, 500);
    });

    // Filter functionality
    $('#statusFilter, #perPage').on('change', function() {
        statusFilter = $('#statusFilter').val();
        perPage = $('#perPage').val();
        currentPage = 1;
        loadJobs();
    });

    // Refresh button
    $('#refreshJobs').on('click', function() {
        loadJobs();
    });

    // View stats button
    $('#viewStats').on('click', function() {
        loadStats();
    });

    // Load jobs function
    function loadJobs() {
        const params = new URLSearchParams({
            page: currentPage,
            per_page: perPage,
            search: searchTerm,
            status: statusFilter
        });

        adminTokenHandler.get('/api/admin/analysis-jobs?' + params.toString(), {
            headers: {
                'Accept': 'application/json'
            }
        })
        .done(function(response) {
            renderJobsTable(response.data);
            renderPagination(response);
        })
        .fail(function(xhr) {
            console.error('Error loading analysis jobs:', xhr);
            $('#jobsTableBody').html('<tr><td colspan="8" class="text-center text-danger">Error loading analysis jobs</td></tr>');
        });
    }

    // Load statistics
    function loadStats() {
        adminTokenHandler.get('/api/admin/analysis-jobs/stats', {
            headers: {
                'Accept': 'application/json'
            }
        })
        .done(function(response) {
            renderStats(response);
        })
        .fail(function(xhr) {
            $('#statsContent').html('<p class="text-danger">Error loading statistics</p>');
        });
    }

    // Render jobs table
    function renderJobsTable(jobs) {
        let html = '';
        
        // Safety check for jobs array
        if (!jobs || !Array.isArray(jobs)) {
            $('#jobsTableBody').html('<tr><td colspan="8" class="text-center text-muted">No analysis jobs found</td></tr>');
            return;
        }
        
        jobs.forEach(function(job) {
            const statusBadge = getStatusBadge(job.status);
            const inputSource = job.input_source_links ? 
                (Array.isArray(job.input_source_links) ? job.input_source_links.join(', ') : job.input_source_links) : 'N/A';
            const truncatedInput = inputSource.length > 30 ? inputSource.substring(0, 30) + '...' : inputSource;
            const outputCsv = job.output_csv_url ? 
                `<a href="${job.output_csv_url}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a>` : 
                'N/A';

            html += `
            <tr>
                <td><strong>#${job.id}</strong></td>
                <td>${statusBadge}</td>
                <td><code>${job.external_job_id || 'N/A'}</code></td>
                <td class="text-truncate" style="max-width: 150px;" title="${inputSource}">${truncatedInput}</td>
                <td>${outputCsv}</td>
                <td><small>${formatDateTime(job.started_at)}</small></td>
                <td><small>${formatDateTime(job.completed_at)}</small></td>
                <td>
                    <button class="btn btn-info btn-sm" onclick="viewJob(${job.id})" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
        });
        $('#jobsTableBody').html(html);
    }

    // Get status badge
    function getStatusBadge(status) {
        const badges = {
            'pending': '<span class="badge badge-secondary"><i class="fas fa-clock"></i> Pending</span>',
            'running': '<span class="badge badge-primary"><i class="fas fa-spinner fa-spin"></i> Running</span>',
            'completed': '<span class="badge badge-success"><i class="fas fa-check"></i> Completed</span>',
            'failed': '<span class="badge badge-danger"><i class="fas fa-times"></i> Failed</span>',
            'cancelled': '<span class="badge badge-warning"><i class="fas fa-ban"></i> Cancelled</span>'
        };
        return badges[status] || `<span class="badge badge-secondary">${status}</span>`;
    }

    // Render statistics
    function renderStats(stats) {
        let html = `
        <div class="row">
            <div class="col-md-6">
                <h5>Overview</h5>
                <p><strong>Total Jobs:</strong> ${stats.total_jobs || 0}</p>
                <p><strong>Completed:</strong> ${stats.completed_jobs || 0}</p>
                <p><strong>Failed:</strong> ${stats.failed_jobs || 0}</p>
                <p><strong>Running:</strong> ${stats.running_jobs || 0}</p>
                <p><strong>Pending:</strong> ${stats.pending_jobs || 0}</p>
                
                <h5>Success Rate</h5>
                <div class="progress mb-3">
                    <div class="progress-bar bg-success" style="width: ${stats.success_rate || 0}%">
                        ${stats.success_rate || 0}%
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <h5>Recent Jobs</h5>
                <div class="list-group">
    `;

        // Safety check for recent_jobs array
        if (stats.recent_jobs && Array.isArray(stats.recent_jobs)) {
            stats.recent_jobs.forEach(function(job) {
                const statusBadge = getStatusBadge(job.status);
                html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Job #${job.id}</strong>
                        <br><small>${formatDateTime(job.created_at)}</small>
                    </div>
                    ${statusBadge}
                </div>
            `;
            });
        } else {
            html += `
            <div class="list-group-item">
                <small class="text-muted">No recent jobs available</small>
            </div>
        `;
        }

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

        // Previous button
        if (response.current_page > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${response.current_page - 1})">Previous</a></li>`;
        }

        // Page numbers
        for (let i = 1; i <= response.last_page; i++) {
            const active = i === response.current_page ? 'active' : '';
            html += `<li class="page-item ${active}"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
        }

        // Next button
        if (response.current_page < response.last_page) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${response.current_page + 1})">Next</a></li>`;
        }

        html += '</ul></nav>';
        $('#jobsPagination').html(html);
    }

    // Global functions for pagination and actions
    window.changePage = function(page) {
        currentPage = page;
        loadJobs();
    };

    window.viewJob = function(jobId) {
        adminTokenHandler.get(`/api/admin/analysis-jobs/${jobId}`, {
            headers: {
                'Accept': 'application/json'
            }
        })
        .done(function(job) {
                // Populate modal with job data
                $('#detailJobId').text(job.id);
                $('#detailStatus').html(getStatusBadge(job.status));
                $('#detailExternalJobId').text(job.external_job_id || 'N/A');
                $('#detailStartedAt').text(formatDateTime(job.started_at));
                $('#detailCompletedAt').text(formatDateTime(job.completed_at));
                $('#detailCreated').text(formatDateTime(job.created_at));
                
                // Input source links
                if (job.input_source_links) {
                    const links = Array.isArray(job.input_source_links) ? job.input_source_links : [job.input_source_links];
                    const linkHtml = links.map(link => `<a href="${link}" target="_blank" class="btn btn-sm btn-outline-primary mb-1">${link}</a>`).join('<br>');
                    $('#detailInputSource').html(linkHtml);
                } else {
                    $('#detailInputSource').text('N/A');
                }
                
                // Output CSV
                if (job.output_csv_url) {
                    $('#detailOutputCsv').html(`<a href="${job.output_csv_url}" target="_blank" class="btn btn-sm btn-success"><i class="fas fa-download"></i> Download CSV</a>`);
                } else {
                    $('#detailOutputCsv').text('N/A');
                }
                
                // Metadata
                if (job.metadata) {
                    $('#detailMetadata').html(`<pre class="font-monospace small" style="max-height: 200px; overflow-y: auto;">${JSON.stringify(job.metadata, null, 2)}</pre>`);
                } else {
                    $('#detailMetadata').html('<small class="text-muted">No metadata available</small>');
                }
                
                // Error message
                if (job.error_message) {
                    $('#detailErrorMessage').text(job.error_message);
                    $('#errorSection').show();
                } else {
                    $('#errorSection').hide();
                }

                $('#jobDetailsModal').modal('show');
        })
        .fail(function(xhr) {
            toastr.error('Error loading job details: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : xhr.statusText));
        });
    };
});
