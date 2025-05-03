
// 密碼顯示
document.addEventListener('DOMContentLoaded', function () {
    
    const togglePasswords = document.querySelectorAll('.toggle-password');

    togglePasswords.forEach(function (togglePassword) {
        const passwordInput = togglePassword.closest('.form-group').querySelector('input[type="password"]');
        const toggleImg = togglePassword.querySelector('.password-toggle-img');

        togglePassword.addEventListener('click', function () {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleImg.src = 'images/eye-open.png';
                toggleImg.alt = 'Hide Password';
            } else {
                passwordInput.type = 'password';
                toggleImg.src = 'images/eye-close.png';
                toggleImg.alt = 'Show Password';
            }
        });
    });
});