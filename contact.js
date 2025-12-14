// contact.js - Contact Form JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Character counter for message
    const messageInput = document.getElementById('message');
    const charCount = document.getElementById('charCount');
    
    if (messageInput && charCount) {
        messageInput.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = `${length}/500 characters`;
            
            if (length > 450) {
                charCount.classList.add('char-limit-warning');
                charCount.classList.remove('char-limit-ok');
            } else if (length >= 10) {
                charCount.classList.remove('char-limit-warning');
                charCount.classList.add('char-limit-ok');
            } else {
                charCount.classList.remove('char-limit-warning', 'char-limit-ok');
            }
            
            // Limit to 500 characters
            if (length > 500) {
                this.value = this.value.substring(0, 500);
                charCount.textContent = '500/500 characters';
                charCount.classList.add('char-limit-warning');
            }
        });
        
        // Initialize counter
        messageInput.dispatchEvent(new Event('input'));
    }
    
    // Form validation
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            // Check message length
            const message = document.getElementById('message').value.trim();
            if (message.length < 10) {
                e.preventDefault();
                alert('Message must be at least 10 characters long.');
                document.getElementById('message').focus();
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                submitBtn.disabled = true;
            }
        });
    }
    
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
    
    // Auto-focus on first invalid field
    const invalidFields = contactForm.querySelectorAll('.is-invalid');
    if (invalidFields.length > 0) {
        invalidFields[0].focus();
    }
});