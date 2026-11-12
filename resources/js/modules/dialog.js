/**
 * Native <dialog> helpers.
 * - <button data-dialog-open="dialog-id" [data-milestone="ulid"]> opens the dialog (showModal).
 * - [data-dialog-close] inside a dialog closes it.
 * - <dialog data-dialog-autoopen> opens on load (e.g. to show validation errors).
 * The payment link dialog (A04) also follows the chosen milestone: amount capped at the remaining balance,
 * milestone currency, single use.
 */
export function init() {
    document.querySelectorAll('[data-dialog-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const dialog = document.getElementById(trigger.dataset.dialogOpen);

            if (!dialog) {
                return;
            }

            if (trigger.dataset.milestone !== undefined) {
                const select = dialog.querySelector('[data-link-milestone]');

                if (select) {
                    select.value = trigger.dataset.milestone;
                    select.dispatchEvent(new Event('change'));
                }
            }

            dialog.showModal();
        });
    });

    document.querySelectorAll('dialog').forEach((dialog) => {
        dialog.querySelectorAll('[data-dialog-close]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });

        if (dialog.hasAttribute('data-dialog-autoopen')) {
            dialog.showModal();
        }
    });

    document.querySelectorAll('[data-payment-link-form]').forEach(initPaymentLinkForm);
}

function initPaymentLinkForm(form) {
    const milestone = form.querySelector('[data-link-milestone]');
    const amount = form.querySelector('[data-link-amount]');
    const currency = form.querySelector('[data-link-currency]');
    const usage = form.querySelector('[data-link-usage]');
    const maxHint = form.querySelector('[data-link-max]');

    if (!milestone || !amount) {
        return;
    }

    const sync = (prefill) => {
        const option = milestone.selectedOptions[0];
        const remaining = option?.dataset.remaining;

        if (remaining) {
            amount.max = remaining;
            if (prefill || !amount.value || Number(amount.value) > Number(remaining)) {
                amount.value = remaining;
            }
            if (currency && option.dataset.currency) {
                currency.value = option.dataset.currency;
            }
            if (usage) {
                usage.value = 'single';
                usage.disabled = true;
            }
            maxHint.textContent = option.dataset.maxLabel ?? '';
        } else {
            amount.removeAttribute('max');
            if (usage) {
                usage.disabled = false;
            }
            maxHint.textContent = '';
        }
    };

    milestone.addEventListener('change', () => sync(true));
    // A disabled select is not submitted; milestone links are always single use on the server anyway.
    form.addEventListener('submit', () => {
        if (usage) {
            usage.disabled = false;
        }
    });
    sync(false);
}
