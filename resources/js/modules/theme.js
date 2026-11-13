/**
 * Light/dark theme toggle. The preference is a per-browser convenience stored in localStorage.
 */
const KEY = 'codefanz-theme';

export function applyStoredTheme() {
    // Only the admin and portal offer a theme toggle; the public site always uses the designed light theme.
    if (!document.querySelector('meta[name="codefanz-themeable"]')) {
        document.documentElement.classList.remove('dark');

        return;
    }

    let stored = null;

    try {
        stored = localStorage.getItem(KEY);
    } catch {
        stored = null;
    }

    // Light is the default; dark applies only after the user explicitly chose it.
    document.documentElement.classList.toggle('dark', stored === 'dark');
}

// Toggle buttons expose the current state to assistive technology: pressed = dark theme on (WCAG 4.1.2).
const syncPressed = () => {
    const dark = document.documentElement.classList.contains('dark');
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => button.setAttribute('aria-pressed', String(dark)));
};

export function init() {
    syncPressed();

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const dark = !document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', dark);
            syncPressed();

            try {
                localStorage.setItem(KEY, dark ? 'dark' : 'light');
            } catch {
                // Storage unavailable (private mode); the toggle still works for this page view.
            }
        });
    });
}
