import { Controller } from '@hotwired/stimulus';
import { Editor } from '@tiptap/core';
import { StarterKit } from '@tiptap/starter-kit';
import { Underline } from '@tiptap/extension-underline';
import { Link } from '@tiptap/extension-link';

/**
 * Minimal tiptap editor for the workshop description field: bold, italic,
 * underline, lists, links. Syncs sanitized-on-the-server HTML to a hidden
 * textarea bound via data-model="description", dispatching an "input" event
 * so the LiveComponent picks up the change (setting .value alone isn't
 * observed by its model-sync mechanism, which listens for input/change).
 *
 * The editor target carries data-live-ignore so unrelated live re-renders
 * (e.g. typing in another field) don't tear down and rebuild the editor,
 * which would otherwise lose cursor position/selection/undo history.
 */
export default class extends Controller {
    static targets = ['editor', 'toolbar', 'output'];

    connect() {
        this.editor = new Editor({
            element: this.editorTarget,
            extensions: [
                StarterKit.configure({
                    heading: false,
                    horizontalRule: false,
                    codeBlock: false,
                    code: false,
                }),
                Underline,
                Link.configure({ openOnClick: false }),
            ],
            content: this.outputTarget.value,
            onUpdate: () => this.syncContent(),
            onSelectionUpdate: () => this.updateToolbarState(),
            onTransaction: () => this.updateToolbarState(),
        });

        this.updateToolbarState();
    }

    disconnect() {
        if (this.editor) {
            this.editor.destroy();
        }
    }

    syncContent() {
        this.outputTarget.value = this.editor.getHTML();
        this.outputTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }

    updateToolbarState() {
        if (!this.hasToolbarTarget || !this.editor) return;

        const checks = {
            bold: this.editor.isActive('bold'),
            italic: this.editor.isActive('italic'),
            underline: this.editor.isActive('underline'),
            bulletList: this.editor.isActive('bulletList'),
            orderedList: this.editor.isActive('orderedList'),
            link: this.editor.isActive('link'),
        };

        this.toolbarTarget.querySelectorAll('[data-format]').forEach((button) => {
            const format = button.dataset.format;
            const isActive = checks[format] ?? false;
            button.classList.toggle('bg-indigo-100', isActive);
            button.classList.toggle('text-indigo-700', isActive);
        });
    }

    toggleBold() {
        this.editor.chain().focus().toggleBold().run();
    }

    toggleItalic() {
        this.editor.chain().focus().toggleItalic().run();
    }

    toggleUnderline() {
        this.editor.chain().focus().toggleUnderline().run();
    }

    toggleBulletList() {
        this.editor.chain().focus().toggleBulletList().run();
    }

    toggleOrderedList() {
        this.editor.chain().focus().toggleOrderedList().run();
    }

    setLink() {
        const previousUrl = this.editor.getAttributes('link').href;
        const url = window.prompt('Adres URL linku:', previousUrl || 'https://');

        if (url === null) return;

        if (url === '') {
            this.editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }

        this.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }
}
