// my_books.js
document.addEventListener('DOMContentLoaded', function() {
    // Confirm before deleting book
    const deleteForms = document.querySelectorAll('form[onsubmit*="confirm"]');
    deleteForms.forEach(form => {
        const originalSubmit = form.onsubmit;
        form.onsubmit = function(e) {
            if (!confirm('Are you sure you want to delete this book? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
            return originalSubmit.call(this, e);
        };
    });
    
    // Status change confirmation
    const statusSelects = document.querySelectorAll('select[name="status"]');
    statusSelects.forEach(select => {
        const originalForm = select.closest('form');
        select.addEventListener('change', function() {
            if (confirm('Change book status?')) {
                originalForm.submit();
            } else {
                // Reset to original value
                this.form.reset();
            }
        });
    });
    
    // Search input debounce
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                // Auto-submit after 500ms of no typing
                this.closest('form').submit();
            }, 500);
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});