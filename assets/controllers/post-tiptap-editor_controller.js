import { Controller } from '@hotwired/stimulus';
import { Editor } from '@tiptap/core';
import { StarterKit } from '@tiptap/starter-kit';
import { Image } from '@tiptap/extension-image';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableHeader } from '@tiptap/extension-table-header';
import { TableCell } from '@tiptap/extension-table-cell';
import { Typography } from '@tiptap/extension-typography';
import { optimizeImageToWebp } from '../utils/image_optimizer.js';
import { Callout } from '../tiptap/callout.js';
import { ArticleFigure } from '../tiptap/article-figure.js';

/**
 * Tiptap editor for article content: prose, headings (H2/H3), lists, tables,
 * images, links, undo/redo. Syncs JSON and sanitized HTML to hidden inputs.
 * Supports inline image uploads that create PostFile attachments, and a
 * toolbar that mirrors formatting state (active/disabled) as selection moves.
 */
export default class extends Controller {
    static targets = ['editor', 'toolbar', 'contentJson', 'contentHtml', 'uploadInput', 'uploadButton'];
    static values = { uploadUrl: String };

    connect() {
        this.editor = new Editor({
            element: this.editorTarget,
            extensions: [
                StarterKit.configure({
                    heading: { levels: [2, 3] },
                    document: true,
                    paragraph: true,
                    bold: true,
                    italic: true,
                    underline: true,
                    strike: true,
                    code: true,
                    codeBlock: true,
                    blockquote: true,
                    bulletList: true,
                    orderedList: true,
                    horizontalRule: true,
                    hardBreak: true,
                }),
                Image.configure({ HTMLAttributes: { class: 'prose-image' } }),
                Table.configure({ resizable: true }),
                TableRow,
                TableHeader,
                TableCell,
                Typography,
                Callout,
                ArticleFigure,
            ],
            content: this.getInitialContent(),
            onUpdate: () => this.syncContent(),
            onSelectionUpdate: () => this.updateToolbarState(),
            onTransaction: () => this.updateToolbarState(),
        });

        if (this.hasUploadButtonTarget) {
            this.uploadButtonTarget.addEventListener('click', (e) => {
                e.preventDefault();
                this.uploadInputTarget.click();
            });

            this.uploadInputTarget.addEventListener('change', (e) => this.handleImageUpload(e));
        }

        this.updateToolbarState();
    }

    disconnect() {
        if (this.editor) {
            this.editor.destroy();
        }
    }

    getInitialContent() {
        try {
            const json = JSON.parse(this.contentJsonTarget.value);
            return json.type === 'doc' ? json : { type: 'doc', content: [] };
        } catch {
            return { type: 'doc', content: [] };
        }
    }

    syncContent() {
        const json = this.editor.getJSON();
        const html = this.editor.getHTML();

        this.contentJsonTarget.value = JSON.stringify(json);
        this.contentHtmlTarget.value = html;
    }

    updateToolbarState() {
        if (!this.hasToolbarTarget || !this.editor) return;

        const checks = {
            bold: this.editor.isActive('bold'),
            italic: this.editor.isActive('italic'),
            underline: this.editor.isActive('underline'),
            strike: this.editor.isActive('strike'),
            heading2: this.editor.isActive('heading', { level: 2 }),
            heading3: this.editor.isActive('heading', { level: 3 }),
            bulletList: this.editor.isActive('bulletList'),
            orderedList: this.editor.isActive('orderedList'),
            blockquote: this.editor.isActive('blockquote'),
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

    toggleStrike() {
        this.editor.chain().focus().toggleStrike().run();
    }

    toggleHeading2() {
        this.editor.chain().focus().toggleHeading({ level: 2 }).run();
    }

    toggleHeading3() {
        this.editor.chain().focus().toggleHeading({ level: 3 }).run();
    }

    toggleBulletList() {
        this.editor.chain().focus().toggleBulletList().run();
    }

    toggleOrderedList() {
        this.editor.chain().focus().toggleOrderedList().run();
    }

    toggleBlockquote() {
        this.editor.chain().focus().toggleBlockquote().run();
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

    insertTable() {
        this.editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
    }

    insertCallout() {
        this.editor.chain().focus().insertContent({
            type: 'callout',
            content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Wpisz treść…' }] }],
        }).run();
    }

    undo() {
        this.editor.chain().focus().undo().run();
    }

    redo() {
        this.editor.chain().focus().redo().run();
    }

    async handleImageUpload(event) {
        const source = event.target.files?.[0];
        if (!source) return;

        let uploadFile = source;
        try {
            const { file } = await optimizeImageToWebp(source);
            uploadFile = file;
        } catch (error) {
            // Fall back to the original file — server-side MIME sniffing
            // and the upload policy remain the real validation boundary.
        }

        const formData = new FormData();
        formData.append('file', uploadFile);

        try {
            const response = await fetch(this.uploadUrlValue, { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Upload failed');

            const data = await response.json();
            if (data.url) {
                this.editor.chain()
                    .focus()
                    .setArticleFigure({ src: data.url, alt: data.alt || '' })
                    .run();
                this.syncContent();
            }
        } catch (error) {
            console.error('Image upload failed:', error);
            alert('Błąd przy wgrywaniu obrazu. Spróbuj ponownie.');
        } finally {
            event.target.value = '';
        }
    }
}
