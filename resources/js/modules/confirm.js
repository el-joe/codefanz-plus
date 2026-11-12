/**
 * Native <dialog> confirmation for destructive or irreversible form submissions.
 * Usage: <form data-confirm="Message" data-confirm-label="Delete">.
 */
export function init() {
    const dialog = document.querySelector('[data-confirm-dialog]');

    if (!dialog) {
        return;
    }

    const message = dialog.querySelector('[data-confirm-message]');
    const accept = dialog.querySelector('[data-confirm-accept]');
    let pendingForm = null;

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();
            pendingForm = form;
            message.textContent = form.dataset.confirm;
            accept.textContent = form.dataset.confirmLabel ?? accept.dataset.defaultLabel;
            dialog.showModal();
        });
    });

    accept.addEventListener('click', () => {
        if (pendingForm) {
            pendingForm.dataset.confirmed = 'true';
            pendingForm.requestSubmit();
        }
        dialog.close();
    });
}
