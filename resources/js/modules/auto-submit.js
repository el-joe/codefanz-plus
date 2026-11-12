/**
 * Submits filter forms when a control changes: <form data-auto-submit>.
 */
export function init() {
    document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        form.querySelectorAll('select, input[type="checkbox"], input[type="radio"]').forEach((control) => {
            control.addEventListener('change', () => form.requestSubmit());
        });
    });
}
