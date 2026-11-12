/**
 * B06 composer: live character counts and previews per platform variant ([data-social-variant]). The text is
 * composed the way the server does it (caption, hashtags, and the tracked link where the platform shows it in
 * the text; X counts every link as 23 characters). The server re-validates every limit on save and on submit.
 */
const normaliseTags = (value) =>
    [...new Set(value.split(/[\s,]+/u).map((tag) => tag.trim().replace(/^#+/, '')).filter(Boolean))].map((tag) => `#${tag}`);

const trackedLink = (form, source) => {
    const link = form.querySelector('[data-social-link]')?.value.trim() ?? '';

    if (!link) {
        return null;
    }

    const params = new URLSearchParams({ utm_source: source });
    form.querySelectorAll('[data-social-utm]').forEach((input) => {
        if (input.value.trim()) {
            params.set(`utm_${input.dataset.socialUtm}`, input.value.trim());
        }
    });

    return `${link}${link.includes('?') ? '&' : '?'}${params.toString()}`;
};

const measure = (text, weight) => {
    if (!weight) {
        return [...text].length;
    }

    let links = 0;
    const withoutLinks = text.replace(/https?:\/\/\S+/gu, () => {
        links += 1;
        return '';
    });

    return [...withoutLinks].length + links * weight;
};

export function init() {
    const form = document.querySelector('[data-social-composer]');

    if (!form) {
        return;
    }

    const formatter = new Intl.NumberFormat(document.documentElement.lang || undefined);

    const updateMedia = () => {
        const thumbs = [...form.querySelectorAll('[data-social-media]:checked')].map((input) => input.dataset.thumb).filter(Boolean);

        form.querySelectorAll('[data-social-variant]').forEach((variant) => {
            const preview = form.querySelector(`[data-social-preview="${variant.dataset.account}"] [data-social-preview-media]`);
            const include = variant.querySelector('[data-social-include-media]')?.checked;

            if (!preview) {
                return;
            }

            preview.replaceChildren(
                ...(include ? thumbs.slice(0, 4) : []).map((src) => {
                    const image = document.createElement('img');
                    image.src = src;
                    image.alt = '';
                    image.className = 'aspect-video w-full rounded object-cover';
                    return image;
                }),
            );
        });
    };

    const update = (variant) => {
        const max = Number(variant.dataset.max);
        const weight = Number(variant.dataset.linkWeight) || 0;
        const text = variant.querySelector('[data-social-text]')?.value.trim() ?? '';
        const tags = normaliseTags(variant.querySelector('[data-social-hashtags]')?.value ?? '');
        const link = variant.dataset.linkPlacement === 'text' ? trackedLink(form, variant.dataset.utmSource) : null;
        const composed = [text, tags.join(' '), link ?? ''].filter(Boolean).join('\n\n');
        const length = measure(composed, weight);
        const counter = variant.querySelector('[data-social-count]');
        const enabled = variant.querySelector('[data-social-enabled]')?.checked;

        if (counter) {
            counter.textContent = `${formatter.format(length)} / ${formatter.format(max)}`;
            counter.classList.toggle('text-fg-danger', length > max);
            counter.classList.toggle('font-semibold', length > max);
        }

        variant.querySelector('[data-social-fields]')?.toggleAttribute('hidden', !enabled);

        const preview = form.querySelector(`[data-social-preview="${variant.dataset.account}"]`);

        if (preview) {
            preview.classList.toggle('hidden', !enabled);
            const previewText = preview.querySelector('[data-social-preview-text]');

            if (previewText) {
                previewText.textContent = composed;
            }
        }
    };

    const updateAll = () => {
        form.querySelectorAll('[data-social-variant]').forEach(update);
        updateMedia();
    };

    form.addEventListener('input', (event) => {
        const variant = event.target.closest('[data-social-variant]');

        if (variant) {
            update(variant);
        } else {
            updateAll();
        }
    });
    form.addEventListener('change', updateAll);
    updateAll();
}
