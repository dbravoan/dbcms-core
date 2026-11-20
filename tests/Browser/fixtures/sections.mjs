// Canonical section fixtures: layout belongs to the section, content to columns.
// Callers pass current block payloads explicitly; no retired shape is converted.
export const column = (id, blocks = [], style) => ({ id, blocks, ...(style ? { style } : {}) });

export const section = (id, blocks = [], presentation = {}) => ({
    id,
    type: 'text',
    columns_layout: '1',
    columns: [column(`${id}-column`, blocks)],
    ...presentation,
});

export const seedSections = async (page, sections, path = [0]) => page.evaluate(({ sections, path }) => {
    const editor = window.Alpine.$data(document.querySelector('.admin-block-manager'));
    editor.historyBoundary();
    editor.setBlocks(sections);
    editor.selectNode(path);
    editor.historyBoundary();
}, { sections, path });
