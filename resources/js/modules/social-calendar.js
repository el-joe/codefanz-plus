/**
 * B07 calendar drag-to-reschedule. Approved items ([data-social-chip][draggable]) can be dropped on another day
 * ([data-calendar-day]); the item keeps its local time. The move is sent as JSON with the CSRF token and rolled
 * back when the server refuses (403 not allowed, 422 unapproved / in the past). Each item also has a "Move" form
 * that works with the keyboard and without JavaScript.
 */
import { request } from '../services/http';
import { toast } from './toast';

export function init() {
    const calendar = document.querySelector('[data-social-calendar]');

    if (!calendar) {
        return;
    }

    let dragged = null;

    calendar.querySelectorAll('[data-social-chip][draggable="true"]').forEach((chip) => {
        chip.addEventListener('dragstart', (event) => {
            dragged = chip;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', chip.dataset.rescheduleUrl);
            chip.classList.add('opacity-50');
        });
        chip.addEventListener('dragend', () => {
            chip.classList.remove('opacity-50');
            dragged = null;
        });
    });

    calendar.querySelectorAll('[data-calendar-day]').forEach((day) => {
        day.addEventListener('dragover', (event) => {
            if (dragged) {
                event.preventDefault();
                day.classList.add('ring-2', 'ring-brand', 'ring-inset');
            }
        });
        day.addEventListener('dragleave', () => day.classList.remove('ring-2', 'ring-brand', 'ring-inset'));
        day.addEventListener('drop', async (event) => {
            event.preventDefault();
            day.classList.remove('ring-2', 'ring-brand', 'ring-inset');

            const chip = dragged;

            if (!chip || chip.closest('[data-calendar-day]') === day) {
                return;
            }

            const fromList = chip.parentElement;
            const nextSibling = chip.nextElementSibling;
            day.querySelector('[data-calendar-list]').append(chip);
            chip.setAttribute('aria-busy', 'true');

            try {
                const payload = await request(chip.dataset.rescheduleUrl, {
                    method: 'POST',
                    body: { scheduled_for: `${day.dataset.calendarDay}T${chip.dataset.time}` },
                });
                chip.dataset.time = payload.post.local_time;
                const input = chip.querySelector('input[name="scheduled_for"]');
                if (input) {
                    input.value = `${payload.post.local_date}T${payload.post.local_time}`;
                }
                toast(payload.message, 'success');
            } catch (error) {
                fromList.insertBefore(chip, nextSibling);
                const firstError = Object.values(error.errors ?? {})[0]?.[0];
                toast(firstError ?? error.message ?? calendar.dataset.errorMessage, 'danger');
            } finally {
                chip.removeAttribute('aria-busy');
            }
        });
    });
}
