import { Node, mergeAttributes } from '@tiptap/core';

/**
 * Inline image + caption as one editable unit, matching the sanitizer's
 * allow-listed `figure`/`figcaption` structure. The image itself is a node
 * attribute (uploaded once, not re-editable inline); the figcaption is the
 * node's actual editable content, so writing a caption is just typing.
 */
export const ArticleFigure = Node.create({
    name: 'articleFigure',
    group: 'block',
    content: 'inline*',
    defining: true,
    isolating: true,

    addAttributes() {
        // rendered: false — these back the manually-placed <img> inside
        // renderHTML(), not the <figure> wrapper itself; without it Tiptap's
        // default attribute-to-HTML mapping would also dump them onto
        // <figure> as stray src/alt/width/height attributes.
        return {
            src: { default: null, rendered: false },
            alt: { default: '', rendered: false },
            width: { default: null, rendered: false },
            height: { default: null, rendered: false },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'figure.article-figure',
                contentElement: 'figcaption',
                getAttrs: (dom) => {
                    const img = dom.querySelector('img');
                    if (!img) return false;

                    return {
                        src: img.getAttribute('src'),
                        alt: img.getAttribute('alt') || '',
                        width: img.getAttribute('width'),
                        height: img.getAttribute('height'),
                    };
                },
            },
        ];
    },

    renderHTML({ HTMLAttributes, node }) {
        const { src, alt, width, height } = node.attrs;
        const imgAttributes = { src, alt, class: 'prose-image' };
        if (width) imgAttributes.width = width;
        if (height) imgAttributes.height = height;

        return [
            'figure',
            mergeAttributes(HTMLAttributes, { class: 'article-figure' }),
            ['img', imgAttributes],
            ['figcaption', {}, 0],
        ];
    },

    addCommands() {
        return {
            setArticleFigure:
                (attrs) =>
                ({ commands }) =>
                    commands.insertContent({ type: this.name, attrs }),
        };
    },
});
