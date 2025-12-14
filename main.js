// Main JavaScript file for interactive features

// DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Search form enhancement
    const searchForm = document.querySelector('form[role="search"]');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="search"]');
            if (searchInput.value.trim().length < 2 && searchInput.value.trim() !== '') {
                e.preventDefault();
                alert('Please enter at least 2 characters to search');
                searchInput.focus();
            }
        });
    }

    // Borrow/Return button handlers
    document.querySelectorAll('.borrow-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to borrow this book?')) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('.return-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to return this book?')) {
                e.preventDefault();
            }
        });
    });

    // Mark as read tracking
    document.querySelectorAll('.mark-read-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const bookId = this.dataset.bookId;
            markAsRead(bookId);
        });
    });

    // Request approval/rejection
    document.querySelectorAll('.approve-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Approve this borrowing request?')) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('.reject-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Reject this borrowing request?')) {
                e.preventDefault();
            }
        });
    });
});

// Mark book as read (AJAX)
function markAsRead(bookId) {
    fetch('ajax/mark_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ book_id: bookId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Book marked as read!', 'success');
            // Update UI
            const btn = document.querySelector(`.mark-read-btn[data-book-id="${bookId}"]`);
            if (btn) {
                btn.innerHTML = '<i class="fas fa-check"></i> Read';
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-success');
                btn.disabled = true;
            }
        } else {
            showToast('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        showToast('Network error', 'danger');
    });
}

// Show toast notification
function showToast(message, type = 'info') {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }

    // Create toast
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    toastContainer.appendChild(toast);

    // Show toast
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();

    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', function () {
        toast.remove();
    });
}

// Book availability check
function checkAvailability(bookId, callback) {
    fetch(`ajax/check_availability.php?book_id=${bookId}`)
        .then(response => response.json())
        .then(data => callback(data))
        .catch(error => console.error('Error:', error));
}

// Request book borrowing
function requestBook(bookId) {
    if (!confirm('Request to borrow this book?')) return;
    
    fetch('ajax/request_borrow.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ book_id: bookId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Borrowing request submitted!', 'success');
            // Update button
            const btn = document.querySelector(`.borrow-btn[data-book-id="${bookId}"]`);
            if (btn) {
                btn.innerHTML = '<i class="fas fa-clock"></i> Pending';
                btn.classList.remove('btn-success');
                btn.classList.add('btn-warning');
                btn.disabled = true;
            }
        } else {
            showToast('Error: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        showToast('Network error', 'danger');
    });
}

// Real-time notification check (for lenders)
function checkNotifications() {
    fetch('ajax/check_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.pending_requests > 0) {
                // Update badge
                const badge = document.querySelector('.requests-badge');
                if (badge) {
                    badge.textContent = data.pending_requests;
                    badge.style.animation = 'pulse 1s infinite';
                }
                
                // Show notification if new requests
                if (data.new_requests > 0) {
                    showToast(`You have ${data.new_requests} new borrowing request(s)`, 'info');
                }
            }
        })
        .catch(error => console.error('Notification check failed:', error));
}

// Check notifications every 30 seconds
setInterval(checkNotifications, 30000);

// Initialize notifications on page load
if (document.querySelector('.requests-badge')) {
    checkNotifications();
}