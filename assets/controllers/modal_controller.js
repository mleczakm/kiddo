import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets = ['dialog'];
    open() {
        this.dialogTarget.show();
        document.body.classList.add('overflow-hidden', 'blur-sm');

        const previousDiv = this.dialogTarget.previousElementSibling;
        if (previousDiv) {
            previousDiv.dataset.state = 'open';
        }
    }

    close() {
        if (!this.hasDialogTarget) {
            return;
        }

        this.dialogTarget.close();
        document.body.classList.remove('overflow-hidden', 'blur-sm');

        //change previous div data-state to closed
        const previousDiv = this.dialogTarget.previousElementSibling;
        if (previousDiv) {
            previousDiv.dataset.state = 'closed';
        }
    }

    clickOutside() {
        this.close();
    }
}