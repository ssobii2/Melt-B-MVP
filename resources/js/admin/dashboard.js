// Check token validity every 2 minutes
setInterval(function() {
    $.ajax({
        url: '/api/user',
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + window.adminToken,
            'Accept': 'application/json'
        },
        success: function(response) {
            // Token is still valid
        },
        error: function(xhr) {
            // Let the global error handler deal with 401 errors
            if (xhr.status === 401) {
            }
        }
    });
}, 120000); // Check every 2 minutes

// Auto-refresh dashboard every 2 minutes
setTimeout(function() {
    location.reload();
}, 120000);
