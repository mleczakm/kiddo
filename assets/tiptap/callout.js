import { Node, mergeAttributes } from '@tiptap/core';

const CALLOUT_TITLE = 'Warto wiedzieć';

/**
 * Fixed-title info callout ("Warto wiedzieć"), matching the sanitizer's
 * allow-listed `div.callout` / `span.callout-title` structure. Only the
 * body paragraphs are authored content — the title is rendered, not stored.
 */
export const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'paragraph+',
    defining: true,
    isolating: true,

    parseHTML() {
        return [
            {
                tag: 'div.callout',
                contentElement: (dom) => {
                    const wrapper = document.createElement('div');
                    dom.querySelectorAll('p').forEach((p) => wrapper.appendChild(p.cloneNode(true)));
                    return wrapper;
                },
            },
        ];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'div',
            mergeAttributes(HTMLAttributes, { class: 'callout' }),
            ['span', { class: 'callout-title' }, CALLOUT_TITLE],
            ['div', { class: 'callout-body' }, 0],
        ];
    },
});
