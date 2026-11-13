/**
 * Copy-to-clipboard buttons: <button data-copy="text" data-copied-label="Copied">.
 * When the button holds an icon, put the text in a [data-copy-text] child so only that label changes.
 */
export function init() {
    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const label = button.querySelector('[data-copy-text]') ?? button;
            const original = label.textContent;

            try {
                await navigator.clipboard.writeText(button.dataset.copy);
                label.textContent = button.dataset.copiedLabel ?? original;
                setTimeout(() => {
                    label.textContent = original;
                }, 2000);
            } catch {
                window.prompt('', button.dataset.copy);
            }
        });
    });
}
