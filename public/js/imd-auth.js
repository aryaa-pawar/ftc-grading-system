// assets/js/main.js - Interactive UI Scripts

document.addEventListener('DOMContentLoaded', function () {
    // Resend OTP Cooldown Timer
    const resendBtn = document.getElementById('resend-otp-btn');
    if (resendBtn) {
        let cooldown = parseInt(resendBtn.getAttribute('data-cooldown') || '0', 10);
        if (cooldown > 0) {
            resendBtn.classList.add('disabled');
            resendBtn.style.pointerEvents = 'none';
            
            const timer = setInterval(() => {
                cooldown--;
                resendBtn.innerText = `Resend OTP (${cooldown}s)`;
                if (cooldown <= 0) {
                    clearInterval(timer);
                    resendBtn.innerText = 'Resend OTP';
                    resendBtn.classList.remove('disabled');
                    resendBtn.style.pointerEvents = 'auto';
                }
            }, 1000);
        }
    }
});
