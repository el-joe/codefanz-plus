/**
 * Multi-step form. Markup: <form data-stepper data-initial-step="N"> holds fieldsets [data-step="1..n"],
 * an optional [data-stepper-progress] list of [data-stepper-indicator="n"], and [data-stepper-back],
 * [data-stepper-next], [data-stepper-submit] buttons. Without JavaScript every step shows as one long
 * form. Each step is checked with the browser's constraint validation before moving on; the server
 * still validates everything on submit. Groups marked [data-required-group] need at least one checkbox.
 */
export function init() {
    initFileDropzones();

    document.querySelectorAll('form[data-stepper]').forEach((form) => {
        const steps = [...form.querySelectorAll('[data-step]')];
        const back = form.querySelector('[data-stepper-back]');
        const next = form.querySelector('[data-stepper-next]');
        const submit = form.querySelector('[data-stepper-submit]');
        const progress = form.querySelector('[data-stepper-progress]');
        const status = form.querySelector('[data-stepper-status]');

        if (steps.length < 2 || !next || !submit) {
            return;
        }

        let current = Math.min(Math.max(Number(form.dataset.initialStep) || 1, 1), steps.length);

        const show = (number, focus = true) => {
            current = number;
            steps.forEach((step, index) => {
                step.hidden = index + 1 !== current;
            });
            back.hidden = current === 1;
            next.hidden = current === steps.length;
            submit.hidden = current !== steps.length;

            progress?.querySelectorAll('[data-stepper-indicator]').forEach((indicator) => {
                const value = Number(indicator.dataset.stepperIndicator);
                indicator.dataset.state = value === current ? 'current' : value < current ? 'done' : 'upcoming';
                if (value === current) {
                    indicator.setAttribute('aria-current', 'step');
                } else {
                    indicator.removeAttribute('aria-current');
                }
            });

            const heading = steps[current - 1].querySelector('legend');
            if (status) {
                status.textContent = `${current} / ${steps.length} — ${heading?.textContent.trim() ?? ''}`;
            }

            if (focus) {
                const invalid = steps[current - 1].querySelector('[aria-invalid="true"]');
                (invalid ?? steps[current - 1].querySelector('input:not([type=hidden]), select, textarea'))?.focus();
            }
        };

        const groupIsValid = (group) => {
            const boxes = [...group.querySelectorAll('input[type=checkbox]')];
            const valid = boxes.length === 0 || boxes.some((box) => box.checked);
            boxes[0]?.setCustomValidity(valid ? '' : group.dataset.requiredMessage ?? ' ');
            return valid;
        };

        const filesAreValid = (step) => {
            const input = step.querySelector('input[type=file][data-max-files]');
            if (!input) {
                return true;
            }
            const valid = input.files.length <= Number(input.dataset.maxFiles);
            input.setCustomValidity(valid ? '' : input.dataset.maxFilesMessage ?? '');
            return valid;
        };

        const validateStep = (number, report = true) => {
            const step = steps[number - 1];
            step.querySelectorAll('[data-required-group]').forEach(groupIsValid);
            filesAreValid(step);
            const fields = [...step.querySelectorAll('input, select, textarea')].filter((field) => field.type !== 'hidden');
            const firstInvalid = fields.find((field) => !field.checkValidity());

            if (firstInvalid) {
                if (report) {
                    firstInvalid.reportValidity();
                    firstInvalid.focus();
                }
                return false;
            }

            return true;
        };

        form.querySelectorAll('[data-required-group] input[type=checkbox]').forEach((box) => {
            box.addEventListener('change', () => groupIsValid(box.closest('[data-required-group]')));
        });

        next.addEventListener('click', () => {
            if (validateStep(current)) {
                show(current + 1);
            }
        });

        back.addEventListener('click', () => show(current - 1));

        form.addEventListener('submit', (event) => {
            const invalidStep = steps.findIndex((step, index) => !validateStep(index + 1, false)) + 1;

            if (invalidStep > 0) {
                event.preventDefault();
                show(invalidStep, false);
                validateStep(invalidStep);
                return;
            }

            submit.disabled = true;
        });

        progress?.classList.replace('hidden', 'flex');
        show(current, form.dataset.initialStep !== '1');
    });
}

/**
 * Figma upload dropzone: the native file input covers the zone (so click, keyboard and drag-and-drop stay native)
 * and [data-file-list] lists the chosen file names.
 */
function initFileDropzones() {
    document.querySelectorAll('input[type=file][data-file-list]').forEach((input) => {
        const list = document.getElementById(input.dataset.fileList);
        const zone = input.closest('[data-dropzone]');

        if (!list) {
            return;
        }

        input.addEventListener('change', () => {
            list.replaceChildren(...[...input.files].map((file) => {
                const item = document.createElement('li');
                item.textContent = file.name;
                return item;
            }));
            list.hidden = input.files.length === 0;
        });

        ['dragenter', 'dragover'].forEach((type) => input.addEventListener(type, () => zone?.setAttribute('data-dragging', '')));
        ['dragleave', 'drop'].forEach((type) => input.addEventListener(type, () => zone?.removeAttribute('data-dragging')));
    });
}
