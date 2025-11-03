// Handle form submission dengan AJAX (optional, bisa juga tanpa AJAX)
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const messageDiv = document.getElementById('message');
    
    // Ambil pesan dari session (jika ada)
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const error = urlParams.get('error');
    
    if (success) {
        showMessage('success', decodeURIComponent(success));
        // Clear URL parameter
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    if (error) {
        showMessage('error', decodeURIComponent(error));
        // Clear URL parameter
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    // Validasi nomor telepon
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
    
    function showMessage(type, text) {
        messageDiv.className = 'message ' + type + ' show';
        messageDiv.textContent = text;
        
        // Auto hide setelah 5 detik
        setTimeout(function() {
            messageDiv.classList.remove('show');
        }, 5000);
    }
});

