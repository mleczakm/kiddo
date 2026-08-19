import { Controller } from '@hotwired/stimulus';

/**
 * Mirrors AsciiSlugger's Polish transliteration + slugify rules client-side,
 * so the slug preview under the title updates live as the admin types.
 * The server (PostEditor::updateEditorial) remains the source of truth: it
 * only regenerates the slug when it still matches the previous auto-slug,
 * so this preview is informational, not a field that gets submitted.
 */
const POLISH_TRANSLITERATION = {
    ą: 'a', ć: 'c', ę: 'e', ł: 'l', ń: 'n', ó: 'o', ś: 's', ź: 'z', ż: 'z',
    Ą: 'a', Ć: 'c', Ę: 'e', Ł: 'l', Ń: 'n', Ó: 'o', Ś: 's', Ź: 'z', Ż: 'z',
};

function slugify(text) {
    const transliterated = text
        .split('')
        .map((char) => POLISH_TRANSLITERATION[char] ?? char)
        .join('');

    return transliterated
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default class extends Controller {
    static targets = ['title', 'slugPreview'];

    connect() {
        this.updateSlugPreview();
    }

    updateSlugPreview() {
        if (!this.hasTitleTarget || !this.hasSlugPreviewTarget) return;

        const slug = slugify(this.titleTarget.value);
        this.slugPreviewTarget.textContent = slug === '' ? '/blog/…' : `/blog/${slug}`;
    }
}
