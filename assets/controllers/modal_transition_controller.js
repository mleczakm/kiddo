import { Controller } from '@hotwired/stimulus';

/**
 * Plays a fade/zoom exit animation before a modal overlay+panel disappears,
 * instead of having Symfony UX Live Component's DOM morph yank it away
 * instantly as soon as the server responds.
 *
 * Setting `data-live-ignore` tells Live Component's morph algorithm to
 * leave an element alone (see Idiomorph's beforeNodeRemoved/beforeNodeMorphed
 * hooks in @symfony/ux-live-component), which is what buys us the time to
 * play the CSS "animate-out" utilities (see app.css) before finishing the
 * close ourselves.
 *
 * Two modes, matched to how each modal template renders:
 *  - "remove" (default): the overlay/panel only exist in the DOM while the
 *    modal is open ({% if modalOpened %}...{% endif %}). We ignore+animate
 *    the wrapper element, then remove it once the animation ends.
 *  - "toggle": the overlay/panel are always in the DOM and just toggle a
 *    `hidden` class / the dialog's `open` attribute. We ignore+animate the
 *    targets directly, then apply the closed state ourselves.
 */
export default class extends Controller {
    static targets = ['overlay', 'panel'];
    static values = {
        duration: { type: Number, default: 200 },
        mode: { type: String, default: 'remove' },
    };

    close() {
        const elements = [...this.overlayTargets, ...this.panelTargets];

        if (this.modeValue === 'toggle') {
            if (elements.some((el) => el.hasAttribute('data-live-ignore'))) {
                return;
            }

            elements.forEach((el) => {
                el.setAttribute('data-live-ignore', '');
                el.setAttribute('data-state', 'closed');
            });

            window.setTimeout(() => {
                elements.forEach((el) => {
                    el.removeAttribute('data-live-ignore');
                    el.classList.add('hidden');
                    if (el.tagName === 'DIALOG') {
                        el.removeAttribute('open');
                    }
                });
            }, this.durationValue);

            return;
        }

        if (this.element.hasAttribute('data-live-ignore')) {
            return;
        }

        this.element.setAttribute('data-live-ignore', '');
        elements.forEach((el) => el.setAttribute('data-state', 'closed'));

        window.setTimeout(() => {
            this.element.remove();
        }, this.durationValue);
    }
}
