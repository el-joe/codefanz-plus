/**
 * Print buttons on invoices and receipts: <button data-print>.
 */
export function init() {
    document.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });
}
