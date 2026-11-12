/**
 * Light/dark theme toggle. The preference is a per-browser convenience stored in localStorage.
 */
const KEY = 'codefanz-theme';

export function applyStoredTheme() {
    let stored = null;

    try {
        stored = localStorage.getItem(KEY);
    } catch {
        stored = null;
    }

    const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.classList.toggle('dark', dark);
}

export function init() {
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const dark = !document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', dark);

            try {
                localStorage.setItem(KEY, dark ? 'dark' : 'light');
            } catch {
                // Storage unavailable (private mode); the toggle still works for this page view.
            }
        });
    });
}
