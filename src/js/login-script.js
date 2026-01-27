const handle2FA = () => {
    const input = document.querySelector('#andromeda_2fa_code');
    if (!input) return;

    input.focus();

    input.addEventListener('input', (event) => {
        const value = event.target.value;

        if (value.length === 6 && /^\d{6}$/.test(value)) {
            setTimeout(() => {
                document.querySelector('#loginform').submit();
            }, 300);
        }

        const normalizedValue = value.replace(/[^A-Za-z0-9]/g, '');
        if (
            normalizedValue.length === 12 &&
            /^[A-Za-z0-9]{12}$/.test(normalizedValue)
        ) {
            setTimeout(() => {
                document.querySelector('#loginform').submit();
            }, 300);
        }
    });
};

document.addEventListener('DOMContentLoaded', handle2FA);
