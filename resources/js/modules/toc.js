/**
 * Table-of-contents scroll spy (blog article "On this page", legal "Contents"): marks the link of the section
 * being read with aria-current="location" so the Figma active state (brand bar + text) follows the reader.
 */
export function init() {
    document.querySelectorAll('[data-toc], [data-legal-toc]').forEach((toc) => {
        const links = [...toc.querySelectorAll('a[href^="#"]')];
        const targets = links
            .map((link) => document.getElementById(decodeURIComponent(link.hash.slice(1))))
            .filter(Boolean);

        if (targets.length === 0 || !('IntersectionObserver' in window)) {
            return;
        }

        const setCurrent = (id) => {
            links.forEach((link) => {
                if (decodeURIComponent(link.hash.slice(1)) === id) {
                    link.setAttribute('aria-current', 'location');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        };

        const visible = new Set();
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => (entry.isIntersecting ? visible.add(entry.target) : visible.delete(entry.target)));
            const first = targets.find((target) => visible.has(target));

            if (first) {
                setCurrent(first.id);
            }
        }, { rootMargin: '-96px 0px -55% 0px' });

        targets.forEach((target) => observer.observe(target));
        setCurrent(targets[0].id);
    });
}
