// borrow_requests.js
document.addEventListener('DOMContentLoaded', function() {
    // Confirm actions before submitting
    const actionForms = document.querySelectorAll('form button[onclick*="confirm"]');
    actionForms.forEach(button => {
        button.addEventListener('click', function(e) {
            const action = this.value || this.name;
            const actionText = getActionText(action);
            
            if (!confirm(`Are you sure you want to ${actionText} this request?`)) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            this.disabled = true;
            
            // Restore button after 3 seconds if form doesn't submit
            setTimeout(() => {
                this.innerHTML = originalHTML;
                this.disabled = false;
            }, 3000);
        });
    });
    
    // Auto-refresh page every 30 seconds if there are pending requests
    const pendingBadge = document.querySelector('.badge.bg-warning');
    if (pendingBadge) {
        const pendingCount = parseInt(pendingBadge.textContent) || 0;
        if (pendingCount > 0) {
            setTimeout(() => {
                window.location.reload();
            }, 30000); // 30 seconds
        }
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Filter form auto-submit
    const filterSelects = document.querySelectorAll('.form-control[onchange*="submit"]');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Add loading indicator
            const submitBtn = this.closest('form').querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
                submitBtn.disabled = true;
                
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 2000);
            }
        });
    });
});

function getActionText(action) {
    const actions = {
        'approve': 'approve',
        'reject': 'reject',
        'cancel': 'cancel'
    };
    return actions[action] || action;
}

// Export to CSV function
function exportRequestsToCSV() {
    const table = document.querySelector('table');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const rowData = [];
        const cols = row.querySelectorAll('td, th');
        
        cols.forEach(col => {
            // Remove HTML and get text content
            let text = col.textContent.trim();
            // Clean up text (remove extra spaces, newlines)
            text = text.replace(/\s+/g, ' ');
            // Escape quotes and wrap in quotes if contains comma
            if (text.includes(',')) {
                text = '"' + text.replace(/"/g, '""') + '"';
            }
            rowData.push(text);
        });
        
        csv.push(rowData.join(','));
    });
    
    // Create download link
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    
    a.href = url;
    a.download = 'borrow_requests_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}