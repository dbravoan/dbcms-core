/** Selection belongs only to the private composition frame, never to its payload. */
export function installBlockPreview(frame = window) {
    const document = frame.document;
    if (!document.documentElement.hasAttribute('data-cms-preview') || frame.parent === frame) return () => {};
    const region = document.querySelector('[data-cms-content-region]');
    if (!region) return () => {};
    const origin = new URL(document.baseURI).origin;
    const rootId = document.body.dataset.previewRoot ?? '';
    let selected;
    const select = ({ node_id, column_index }, scroll = false) => {
        const targets = node_id !== undefined
            ? [...region.querySelectorAll('[data-cms-node]')].filter(node => node.dataset.cmsNode === String(node_id))
            : [...region.querySelectorAll('[data-cms-column-index]')].filter(node => (node.dataset.cmsColumnOwner ?? '') === rootId && Number(node.dataset.cmsColumnIndex) === Number(column_index));
        const next = targets[0];
        if (!next) return;
        selected?.removeAttribute('data-cms-preview-selected');
        selected = next;
        selected.setAttribute('data-cms-preview-selected', '');
        if (scroll) selected.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    };
    const click = event => {
        const node = event.target.closest?.('[data-cms-node], [data-cms-column-index]');
        if (!node || !region.contains(node)) return;
        const detail = node.dataset.cmsNode
            ? { node_id: node.dataset.cmsNode }
            : node.hasAttribute('data-cms-column-index') ? ((node.dataset.cmsColumnOwner ?? '') === rootId
                ? { column_index: Number(node.dataset.cmsColumnIndex) }
                : node.dataset.cmsColumnOwner ? { node_id: node.dataset.cmsColumnOwner } : null) : null;
        if (!detail) return;
        select(detail);
        frame.parent.postMessage({ source: 'cms-block-preview', type: 'select', ...detail }, origin);
    };
    const message = event => {
        if (event.source !== frame.parent || event.origin !== origin || event.data?.source !== 'cms-block-preview' || event.data.type !== 'selection') return;
        if (typeof event.data.node_id === 'string') select({ node_id: event.data.node_id }, true);
        else if (Number.isInteger(event.data.column_index) && event.data.column_index >= 0) select({ column_index: event.data.column_index }, true);
    };
    region.addEventListener('click', click);
    frame.addEventListener('message', message);
    const initial = document.body.dataset;
    if (initial.previewColumn !== undefined) select({ column_index: Number(initial.previewColumn) });
    else if (initial.previewNode) select({ node_id: initial.previewNode });
    const cleanup = () => {
        region.removeEventListener('click', click);
        frame.removeEventListener('message', message);
        frame.removeEventListener('pagehide', cleanup);
    };
    frame.addEventListener('pagehide', cleanup, { once: true });
    frame.parent.postMessage({ source: 'cms-block-preview', type: 'ready' }, origin);
    return cleanup;
}
