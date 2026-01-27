const copyToClipboard = (text) => {
    if (!navigator.clipboard?.writeText) {
        console.error('Clipboard API not supported');
        return Promise.reject(new Error('Clipboard API not supported'));
    }

    return navigator.clipboard.writeText(text);
};

const showCopySuccess = (button) => {
    const originalText = button.textContent;
    button.textContent = '✓ Copied!';
    button.disabled = true;

    setTimeout(() => {
        button.textContent = originalText;
        button.disabled = false;
    }, 2000);
};

const handleRecoveryCodes = () => {
    const button = document.querySelector('.andromeda-copy-codes');
    if (!button) return;

    const clipboardText = button.getAttribute('data-clipboard-text') || '';

    button.addEventListener('click', () => {
        copyToClipboard(clipboardText);
        showCopySuccess(button);
    });
};

document.addEventListener('DOMContentLoaded', handleRecoveryCodes);
