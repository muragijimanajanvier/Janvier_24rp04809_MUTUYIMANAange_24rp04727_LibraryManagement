// forms.js - Form validation for signup and other forms
document.addEventListener('DOMContentLoaded', function() {
    // Signup form validation
    const signupForm = document.getElementById('signupForm');
    if (signupForm) {
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const strengthBar = document.querySelector('#passwordStrength .progress-bar');
        const strengthText = document.getElementById('passwordStrengthText');
        const passwordMatch = document.getElementById('passwordMatch');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let text = 'Weak';
                let color = 'bg-danger';
                
                // Check password strength
                if (password.length >= 6) strength++;
                if (password.length >= 8) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                
                // Set strength level
                if (strength <= 2) {
                    text = 'Weak';
                    color = 'bg-danger';
                } else if (strength <= 4) {
                    text = 'Medium';
                    color = 'bg-warning';
                } else {
                    text = 'Strong';
                    color = 'bg-success';
                }
                
                // Update UI
                if (strengthBar) {
                    strengthBar.style.width = (strength * 20) + '%';
                    strengthBar.className = 'progress-bar ' + color;
                }
                
                if (strengthText) {
                    strengthText.textContent = text;
                }
                
                // Check password match
                checkPasswordMatch();
            });
        }
        
        if (confirmInput) {
            confirmInput.addEventListener('input', checkPasswordMatch);
        }
        
        function checkPasswordMatch() {
            const password = passwordInput ? passwordInput.value : '';
            const confirm = confirmInput ? confirmInput.value : '';
            
            if (passwordMatch) {
                if (!password) {
                    passwordMatch.innerHTML = '';
                } else if (password === confirm) {
                    passwordMatch.innerHTML = '<span class="match-success"><i class="fas fa-check-circle"></i> Passwords match</span>';
                } else {
                    passwordMatch.innerHTML = '<span class="match-error"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
                }
            }
        }
        
        // Role selection styling
        const roleOptions = document.querySelectorAll('.role-option');
        roleOptions.forEach(option => {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    roleOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                }
            });
            
            // Initialize selected state
            const radio = option.querySelector('input[type="radio"]');
            if (radio && radio.checked) {
                option.classList.add('selected');
            }
        });
        
        // Form validation
        signupForm.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            this.classList.add('was-validated');
            
            // Check password match
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                const confirmField = document.getElementById('confirm_password');
                confirmField.classList.add('is-invalid');
                confirmField.nextElementSibling.textContent = 'Passwords must match';
            }
        });
        
        // Real-time validation
        const inputs = signupForm.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                this.classList.add('touched');
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('touched')) {
                    validateField(this);
                }
            });
        });
        
        function validateField(field) {
            if (field.checkValidity()) {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
            } else {
                field.classList.remove('is-valid');
                field.classList.add('is-invalid');
            }
        }
    }
    
    // Username availability check
    const usernameInput = document.getElementById('username');
    if (usernameInput) {
        let timeout;
        usernameInput.addEventListener('input', function() {
            clearTimeout(timeout);
            const username = this.value.trim();
            
            if (username.length >= 3) {
                timeout = setTimeout(() => {
                    checkUsernameAvailability(username);
                }, 500);
            }
        });
    }
    
    function checkUsernameAvailability(username) {
        // This would typically make an AJAX request to check username
        // For now, we'll just show a message
        console.log('Checking username:', username);
    }
    
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
});