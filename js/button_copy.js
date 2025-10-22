<!--Injected by UserName External Modules-->
function unc_copy_pre_text(elementId, buttonElement) {
    const copyText = document.getElementById(elementId);
    if (!copyText) {
        if (buttonElement) {
            buttonElement.style.display = 'none'; // Hide the button
        }
        return;
    } else if (!buttonElement) {
        return;
    }

    navigator.clipboard.writeText(copyText.innerText)
        .then(() => {
            const originalText = buttonElement.innerText;
            buttonElement.innerText = 'Copied!';
            setTimeout(() => {
                buttonElement.innerText = originalText;
            }, 2500);
        })
        .catch(() => {
            const originalText = buttonElement.innerText;
            buttonElement.innerText = 'Not copied';
            setTimeout(() => {
                buttonElement.innerText = originalText;
            }, 2500);
        });
}