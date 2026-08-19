import { Controller } from '@hotwired/stimulus';
import { Editor } from '@tiptap/core';
import { StarterKit } from '@tiptap/starter-kit';
import { Image } from '@tiptap/extension-image';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableHeader } from '@tiptap/extension-table-header';
import { TableCell } from '@tiptap/extension-table-cell';
import { Typography } from '@tiptap/extension-typography';

/**
 * Tiptap editor for article content: prose, headings (H2/H3), lists, tables,
 * images, links, undo/redo. Syncs JSON and sanitized HTML to hidden inputs.
 * Supports inline image uploads that create PostFile attachments.
 */
export default class extends Controller {
    static targets = ['editor', 'contentJson', 'contentHtml', 'uploadInput', 'uploadButton'];
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
            ],
            content: this.getInitialContent(),
            onUpdate: () => this.syncContent(),
            onSelectionUpdate: () => {},
        });

        if (this.hasUploadButtonTarget) {
            this.uploadButtonTarget.addEventListener('click', (e) => {
                e.preventDefault();
                this.uploadImageTarget.click();
            });

            this.uploadInputTarget.addEventListener('change', (e) => this.handleImageUpload(e));
        }
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

    async handleImageUpload(event) {
        const file = event.target.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(this.uploadUrlValue, { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Upload failed');

            const data = await response.json();
            if (data.url && data.alt) {
                this.editor.chain()
                    .focus()
                    .setImage({ src: data.url, alt: data.alt })
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
