/**
 * Global double-submit guard.
 *
 * When a form is submitted, the button that triggered it (the submitter, or
 * the form's first submit control as a fallback) is put into a "loading"
 * state: a spinning icon is prepended to its label, it fades, and it stops
 * accepting further clicks for the duration of the request. This prevents
 * the user from firing the same POST twice by double-clicking.
 *
 * Works for both plain full-page form posts (the button dies with the page
 * unload; a fresh render or a bfcache restore resets it) and Turbo-driven
 * submits (`turbo:submit-end` releases the lock, e.g. when the server
 * re-renders the form with validation errors instead of navigating).
 *
 * LiveComponent action buttons are handled separately by Symfony UX's own
 * request lifecycle and are intentionally ignored here (their `submit`
 * events arrive already `defaultPrevented`).
 *
 * All styling is inlined so it does not depend on any particular utility
 * class being present in the compiled CSS. Opt a control out with
 * `data-no-submit-lock`.
 */

const LOCKED_ATTR = 'data-submit-locked';
const SAFETY_TIMEOUT_MS = 20000;
const STYLE_ELEMENT_ID = 'submit-lock-styles';

function ensureStyles() {
    if (document.getElementById(STYLE_ELEMENT_ID)) {
        return;
    }
    const style = document.createElement('style');
    style.id = STYLE_ELEMENT_ID;
    style.textContent =
        '@keyframes submit-lock-spin{to{transform:rotate(360deg)}}' +
        '.submit-lock-spinner{display:inline-block;width:1em;height:1em;flex:0 0 auto;' +
        'margin-right:.5em;vertical-align:-0.125em;border:2px solid currentColor;' +
        'border-right-color:transparent;border-radius:50%;' +
        'animation:submit-lock-spin .6s linear infinite}' +
        '@media (prefers-reduced-motion:reduce){.submit-lock-spinner{animation-duration:1.5s}}';
    document.head.appendChild(style);
}

function lock(button) {
    if (!button || button.hasAttribute(LOCKED_ATTR) || button.hasAttribute('data-no-submit-lock')) {
        return;
    }
    if (button.disabled) {
        return;
    }

    ensureStyles();

    button.setAttribute(LOCKED_ATTR, '1');
    button.setAttribute('aria-busy', 'true');

    // Freeze the current box so prepending the spinner does not make the
    // button jump in size.
    const rect = button.getBoundingClientRect();
    button.dataset.submitLockMinWidth = button.style.minWidth;
    button.style.minWidth = `${Math.ceil(rect.width)}px`;

    button.dataset.submitLockOpacity = button.style.opacity;
    button.dataset.submitLockCursor = button.style.cursor;
    button.dataset.submitLockPointerEvents = button.style.pointerEvents;
    button.style.opacity = '0.65';
    button.style.cursor = 'wait';
    button.style.pointerEvents = 'none';

    button.dataset.submitLockHtml = button.innerHTML;
    const spinner = document.createElement('span');
    spinner.className = 'submit-lock-spinner';
    spinner.setAttribute('aria-hidden', 'true');
    button.insertBefore(spinner, button.firstChild);

    // Defer the actual `disabled` toggle to the next tick: a disabled
    // submitter is omitted from the serialized form body, and some forms
    // branch on which button was pressed. By the next tick the browser has
    // already built the request.
    button.dataset.submitLockTimer = String(
        window.setTimeout(() => {
            if (button.hasAttribute(LOCKED_ATTR)) {
                button.disabled = true;
            }
        }, 0),
    );

    button.dataset.submitLockSafety = String(window.setTimeout(() => unlock(button), SAFETY_TIMEOUT_MS));
}

function unlock(button) {
    if (!button?.hasAttribute(LOCKED_ATTR)) {
        return;
    }

    window.clearTimeout(Number(button.dataset.submitLockTimer));
    window.clearTimeout(Number(button.dataset.submitLockSafety));

    button.disabled = false;
    button.removeAttribute('aria-busy');

    if (button.dataset.submitLockHtml !== undefined) {
        button.innerHTML = button.dataset.submitLockHtml;
    }
    button.style.opacity = button.dataset.submitLockOpacity || '';
    button.style.cursor = button.dataset.submitLockCursor || '';
    button.style.pointerEvents = button.dataset.submitLockPointerEvents || '';
    button.style.minWidth = button.dataset.submitLockMinWidth || '';

    delete button.dataset.submitLockHtml;
    delete button.dataset.submitLockOpacity;
    delete button.dataset.submitLockCursor;
    delete button.dataset.submitLockPointerEvents;
    delete button.dataset.submitLockMinWidth;
    delete button.dataset.submitLockTimer;
    delete button.dataset.submitLockSafety;
    button.removeAttribute(LOCKED_ATTR);
}

function unlockAll(root = document) {
    root.querySelectorAll(`[${LOCKED_ATTR}]`).forEach(unlock);
}

function submitterFor(event) {
    if (event.submitter instanceof HTMLElement) {
        return event.submitter;
    }
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return null;
    }
    return form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
}

document.addEventListener(
    'submit',
    (event) => {
        // Another handler (LiveComponent, custom validation, ...) already
        // took over this submit — don't touch its button.
        if (event.defaultPrevented) {
            return;
        }
        lock(submitterFor(event));
    },
    false,
);

// Turbo re-render without navigation (typically a form coming back with
// validation errors) — release the lock so the user can correct and retry.
document.addEventListener('turbo:submit-end', () => unlockAll());

// Fresh document or back/forward cache restore: never show a stale lock.
document.addEventListener('turbo:load', () => unlockAll());
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        unlockAll();
    }
});
