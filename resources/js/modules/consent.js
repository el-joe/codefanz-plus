/**
 * Cookie consent banner ([data-consent-banner]). Stores the choice in a first-party cookie, hides the banner and,
 * only after an analytics opt-in, loads GA4 with consent mode (defaults denied, analytics granted).
 * Without JavaScript the banner's forms post the same choice to the server.
 */
function loadAnalytics(measurementId) {
    if (!measurementId || window.gtag) {
        return;
    }

    window.dataLayer = window.dataLayer || [];
    window.gtag = function gtag() {
        window.dataLayer.push(arguments);
    };
    window.gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied' });
    window.gtag('consent', 'update', { analytics_storage: 'granted' });
    window.gtag('js', new Date());
    window.gtag('config', measurementId, { anonymize_ip: true });

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
    document.head.appendChild(script);
}

export function init() {
    const banner = document.querySelector('[data-consent-banner]');

    if (!banner) {
        return;
    }

    const opener = document.querySelector('[data-consent-open]');
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';

    banner.querySelectorAll('[data-consent-choice]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const choice = button.dataset.consentChoice;
            document.cookie = `${banner.dataset.cookie}=${choice}; Max-Age=${banner.dataset.maxAge}; Path=/; SameSite=Lax${secure}`;
            banner.hidden = true;

            if (choice === 'analytics') {
                loadAnalytics(banner.dataset.gaId);
            } else if (window.gtag) {
                window.gtag('consent', 'update', { analytics_storage: 'denied' });
            }

            opener?.focus();
        });
    });

    if (opener) {
        opener.hidden = false;
        opener.addEventListener('click', () => {
            banner.hidden = false;
            banner.querySelector('[data-consent-choice]')?.focus();
        });
    }
}
