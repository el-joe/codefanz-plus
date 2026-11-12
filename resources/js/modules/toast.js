/**
 * Toast notifications rendered into the [data-toast-region] live region.
 */
export function toast(message, tone = 'success', timeout = 5000) {
    const region = document.querySelector('[data-toast-region]');

    if (!region || !message) {
        return;
    }

    const tones = {
        success: 'border-line-success',
        danger: 'border-line-danger',
        info: 'border-line-info',
    };

    const item = document.createElement('div');
    item.setAttribute('role', tone === 'danger' ? 'alert' : 'status');
    item.className = `pointer-events-auto w-full max-w-sm rounded-lg border-s-4 ${tones[tone] ?? tones.info} bg-surface-raised px-4 py-3 text-sm text-fg shadow-lg`;
    item.textContent = message;
    region.append(item);

    if (tone !== 'danger') {
        setTimeout(() => item.remove(), timeout);
    }
}

export function init() {
    document.querySelectorAll('[data-flash]').forEach((node) => {
        toast(node.dataset.flash, node.dataset.tone ?? 'success');
        node.remove();
    });
}
