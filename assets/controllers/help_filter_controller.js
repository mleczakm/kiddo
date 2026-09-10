import { Controller } from '@hotwired/stimulus';

/**
 * Client-side filter for the help & documentation index.
 *
 * Typing in the `input` target hides every `article` target whose
 * `data-help-search` text does not contain the query, then hides any `group`
 * section left with no visible article and shows the `empty` placeholder when
 * nothing matches.
 */
export default class extends Controller {
    static targets = ['input', 'article', 'group', 'empty'];

    apply() {
        const query = this.inputTarget.value.trim().toLowerCase();
        let anyVisible = false;

        this.articleTargets.forEach((el) => {
            const match = query === '' || (el.dataset.helpSearch || '').includes(query);
            el.hidden = !match;
            if (match) {
                anyVisible = true;
            }
        });

        this.groupTargets.forEach((group) => {
            const hasVisible = group.querySelectorAll('[data-help-filter-target="article"]:not([hidden])').length > 0;
            group.hidden = !hasVisible;
        });

        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = anyVisible;
        }
    }
}
