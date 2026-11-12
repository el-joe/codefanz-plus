/**
 * Copy-to-clipboard buttons: <button data-copy="text" data-copied-label="Copied">.
 */
export function init() {
    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const original = button.textContent;

            try {
                await navigator.clipboard.writeText(button.dataset.copy);
                button.textContent = button.dataset.copiedLabel ?? original;
                setTimeout(() => {
                    button.textContent = original;
                }, 2000);
            } catch {
                window.prompt('', button.dataset.copy);
            }
        });
    });
}
