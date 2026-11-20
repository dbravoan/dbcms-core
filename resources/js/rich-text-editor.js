import { newNodeId } from './block-editor-tree.js';
import { t } from './editor-labels.js';

function mediaUrl(source, origins) {
    if (!source || /[\s\\]/.test(source)) return false;
    try {
        const url = new URL(source, location.origin);
        return !url.username && !url.password && [location.origin, ...origins].includes(url.origin);
    } catch {
        return false;
    }
}

function extensionsFor({ Extension, Node }, StarterKit, typography, origins) {
    const paragraphStyle = Extension.create({
        name: 'editorialTypography',
        addGlobalAttributes() {
            return [{
                types: ['paragraph', 'heading'],
                attributes: {
                    editorialStyle: {
                        default: {},
                        parseHTML: element => Object.fromEntries(Object.entries(typography)
                            .map(([key, options]) => [key, element.style.getPropertyValue(key).trim()])
                            .filter(([key, value]) => value && Object.hasOwn(typography[key], value))),
                        renderHTML: ({ editorialStyle }) => {
                            const style = Object.keys(typography).filter(key => editorialStyle[key])
                                .map(key => key + ': ' + editorialStyle[key]).join('; ');
                            return style ? {style} : {};
                        },
                    },
                },
            }];
        },
    });
    const media = ['img', 'video', 'audio'].map(tag => Node.create({
        name: tag,
        group: tag === 'img' ? 'inline' : 'block',
        inline: tag === 'img',
        atom: true,
        addAttributes() {
            return Object.fromEntries((tag === 'img'
                ? ['src', 'alt', 'title', 'width', 'height', 'loading']
                : ['src', 'aria-label']).map(name => [name, {default: null}]));
        },
        parseHTML() {
            return [{tag: tag + '[src]', getAttrs: element => mediaUrl(element.getAttribute('src'), origins) ? null : false}];
        },
        renderHTML({ HTMLAttributes }) {
            return [tag, tag === 'img' ? HTMLAttributes : {
                ...HTMLAttributes, controls: '', preload: 'none', ...(tag === 'video' && {playsinline: ''}),
            }];
        },
    }));
    const starter = StarterKit.extend({
        addExtensions() {
            return this.parent().map(extension => extension.name === 'bold'
                // Paragraph weight belongs to the paragraph, independently of inline bold.
                ? extension.extend({parseHTML() { return this.parent().filter(rule => !rule.style); }})
                : extension);
        },
    }).configure({link: {openOnClick: false, HTMLAttributes: {target: null, rel: null, class: null}}});
    return [starter, paragraphStyle, ...media];
}

function signature(html) {
    const template = document.createElement('template');
    template.innerHTML = html;
    const nodeSignature = node => {
        if (node.nodeType !== 1) return [node.nodeType, node.nodeValue];
        const name = { b: 'strong', i: 'em', del: 's' }[node.localName] ?? node.localName;
        const attributes = [...node.attributes].map(({name, value}) => [
            name,
            name === 'style' ? value.replace(/;\s*$/, '') : value,
        ]).sort(([a], [b]) => a.localeCompare(b));
        return [name, attributes, [...node.childNodes].map(nodeSignature)];
    };
    const nodes = [...template.content.childNodes].filter(node => node.nodeType !== 3 || node.nodeValue.trim() !== '');
    return JSON.stringify(nodes.map(nodeSignature));
}

async function renderSource(source, signal, targetFormat) {
    const root = document.getElementById('cms-rich-text-interface');
    const response = await fetch(root.dataset.renderUrl, {
        method: 'POST', credentials: 'same-origin', signal,
        headers: {
            'Content-Type': 'application/json', Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        },
        body: JSON.stringify({source, target_format: targetFormat}),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.errors?.target_format?.[0] ?? t('preview_failed'));
    return result;
}

function mediaMarkup(file, document) {
    const label = file.alt_text || file.title || file.name || file.filename;
    const kind = file.is_image ? 'img' : file.is_audio ? 'audio' : file.is_video ? 'video' : 'a';
    const element = document.createElement(kind);
    if (kind === 'a') {
        element.setAttribute('href', file.url);
        element.textContent = label;
    } else {
        element.setAttribute('src', file.url);
        if (kind === 'img') {
            element.setAttribute('alt', label);
            element.setAttribute('loading', 'lazy');
        } else {
            element.setAttribute('controls', '');
            element.setAttribute('preload', 'none');
            element.setAttribute('aria-label', label);
            if (kind === 'video') element.setAttribute('playsinline', '');
        }
    }
    return element.outerHTML;
}

export function createRichTextEditor(label) {
    let ui, current, mode, editor, request, timer, selectionTarget;

    function destroyEditor() {
        editor?.destroy();
        editor = undefined;
    }

    function clearRequest() {
        clearTimeout(timer);
        request?.abort();
        request = undefined;
    }

    function destroy() {
        clearRequest();
        destroyEditor();
        if (ui) {
            window.removeEventListener('file-selected', ui.selectMedia);
            window.removeEventListener('filemanager-closed', ui.closeMedia);
            ui = undefined;
        }
    }

    function commit(source, refresh = true) {
        current = source;
        ui.write(source);
        if (refresh) showMode();
        else updatePreview();
    }

    function updatePreview(delay = 200) {
        clearRequest();
        if (!ui || (!ui.previewing && (mode !== 'visual' || editor))) return;
        const source = current;
        timer = setTimeout(async () => {
            const controller = new AbortController();
            request = controller;
            try {
                const result = await renderSource(source, controller.signal);
                if (request !== controller || !ui.host.isConnected) return;
                ui.preview.innerHTML = result.html;
                if (mode === 'visual' && !editor) await mountVisual(result.html, controller);
            } catch (error) {
                if (request === controller && error.name !== 'AbortError') ui.status.textContent = error.message;
            }
        }, delay);
    }

    async function mountVisual(html, controller) {
        const [core, {default: StarterKit}] = await Promise.all([import('@tiptap/core'), import('@tiptap/starter-kit')]);
        if (request !== controller || mode !== 'visual' || !ui.host.isConnected) return;
        const extensions = extensionsFor(core, StarterKit, ui.typography, ui.origins);
        let content;
        try {
            content = core.createDocument(html, core.getSchema(extensions), {preserveWhitespace: 'full'}, {errorOnInvalidContent: true}).toJSON();
            if (html.trim() && signature(html) !== signature(core.generateHTML(content, extensions))) {
                throw new Error('Unrepresentable HTML');
            }
        } catch {
            ui.status.textContent = t('rich_unrepresentable');
            ui.status.dataset.richSourceFallback = '';
            showMode('html', false);
            return;
        }

        editor = new core.Editor({
            element: ui.canvas, extensions, content,
            parseOptions: {preserveWhitespace: 'full'},
            editorProps: { attributes: {role: 'textbox', 'aria-multiline': 'true', 'aria-label': label, class: 'cms-rich-text min-h-32 outline-none'} },
            onUpdate({editor: instance}) {
                const live = ui.read();
                if (live?.format !== current.format || live?.content !== current.content) return;
                commit({format: 'html', content: instance.isEmpty ? '' : instance.getHTML()}, false);
            },
            onTransaction: updateControls,
        });
        updateControls();
    }

    function paragraphKind() {
        return editor.isActive('heading') ? 'heading' : 'paragraph';
    }

    function currentList() {
        if (editor.isActive('bulletList')) return 'bulletList';
        if (editor.isActive('orderedList')) return 'orderedList';
        return 'none';
    }

    function applyTypography(property, value) {
        const kind = paragraphKind();
        const styles = {...editor.getAttributes(kind).editorialStyle, [property]: value};
        editor.chain().focus().updateAttributes(kind, {editorialStyle: styles}).run();
    }

    function chooseFormatting(choice, value) {
        if (!editor) return;
        if (choice === 'text-align') {
            applyTypography(choice, value);
            return;
        }
        const chain = editor.chain().focus();
        if (choice === 'heading') {
            (value === 'paragraph' ? chain.setParagraph() : chain.setHeading({level: Number(value)})).run();
            return;
        }
        const active = currentList();
        if (value !== active) chain.toggleList(value === 'none' ? active : value, 'listItem').run();
    }

    function updateControls() {
        if (!editor) return;
        const kind = paragraphKind();
        const attributes = editor.getAttributes(kind);
        const styles = attributes.editorialStyle ?? {};
        const choices = {
            heading: kind === 'heading' ? String(attributes.level) : 'paragraph',
            list: currentList(),
            'text-align': styles['text-align'] ?? '',
        };
        for (const menu of ui.menus) {
            for (const option of menu.querySelectorAll('[data-option]')) {
                option.setAttribute('aria-pressed', String(option.dataset.option === choices[menu.dataset.choice]));
            }
        }
        for (const select of ui.styles) select.value = styles[select.dataset.style] ?? '';
        for (const button of ui.formatButtons) button.setAttribute('aria-pressed', String(editor.isActive(button.dataset.activeMark)));
        for (const button of ui.historyButtons) button.disabled = !editor.can()[button.dataset.command]();
    }

    function connectMenu(menu) {
        const trigger = menu.querySelector('summary');
        trigger.addEventListener('click', () => {
            for (const other of ui.menus) if (other !== menu) other.open = false;
        });
        menu.addEventListener('focusout', event => {
            if (event.relatedTarget && !menu.contains(event.relatedTarget)) menu.open = false;
        });
        menu.addEventListener('keydown', event => {
            if (event.key === 'Escape' && menu.open) {
                event.stopPropagation();
                menu.open = false;
                trigger.focus();
            }
        });
        for (const option of menu.querySelectorAll('[data-option]')) {
            option.addEventListener('click', () => {
                menu.open = false;
                chooseFormatting(menu.dataset.choice, option.dataset.option);
            });
        }
    }

    function showMode(nextMode = mode, clear = true) {
        mode = nextMode;
        clearRequest();
        destroyEditor();
        if (clear) {
            ui.status.textContent = '';
            delete ui.status.dataset.richSourceFallback;
        }
        for (const menu of ui.menus) menu.open = false;
        ui.source.hidden = mode === 'visual';
        ui.canvas.hidden = mode !== 'visual';
        ui.visualTools.hidden = mode !== 'visual';
        ui.source.value = current.content;
        for (const button of ui.modeButtons) button.setAttribute('aria-pressed', String(button.dataset.mode === mode));
        updatePreview(0);
    }

    async function chooseMode(next) {
        const format = next === 'markdown' ? 'markdown' : 'html';
        if (format === current.format) return showMode(next);
        if (!current.content.trim()) {
            mode = next;
            return commit({format, content: ''});
        }
        if (format === 'html' && !window.confirm(t('convert_to_html'))) return;
        clearRequest();
        const controller = new AbortController();
        request = controller;
        try {
            const {source} = await renderSource(current, controller.signal, format);
            if (request !== controller) return;
            mode = next;
            commit(source);
        } catch (error) {
            if (request === controller) ui.status.textContent = error.message;
        }
    }

    function connect(host, read, write) {
        const template = document.getElementById('cms-rich-text-interface');
        host.append(template.content.cloneNode(true));
        const select = key => host.querySelector('[data-' + key + ']');
        ui = {
            host, read, write,
            source: select('source'), canvas: select('canvas'), preview: select('preview'), status: select('status'),
            visualTools: select('visual-tools'), menus: host.querySelectorAll('.cms-rich-menu'),
            styles: host.querySelectorAll('[data-style]'), modeButtons: host.querySelectorAll('[data-mode]'),
            formatButtons: host.querySelectorAll('[data-active-mark]'), historyButtons: host.querySelectorAll('[data-rich-history]'),
            typography: JSON.parse(template.dataset.typography), origins: JSON.parse(template.dataset.origins),
            previewing: false,
            closeMedia(event) {
                if (event.detail?.target === selectionTarget) selectionTarget = undefined;
            },
            selectMedia(event) {
                if (!selectionTarget || event.detail?.target !== selectionTarget) return;
                selectionTarget = undefined;
                const file = event.detail.file;
                if (!file || !mediaUrl(file.url, ui.origins)) return;
                const markup = mediaMarkup(file, document);
                if (mode === 'visual' && editor) {
                    editor.chain().focus().insertContent(markup).run();
                } else if (mode !== 'visual') {
                    const {selectionStart, selectionEnd} = ui.source;
                    const insertion = current.format === 'markdown' ? '\n\n' + markup + '\n\n' : markup;
                    ui.source.value = current.content.slice(0, selectionStart) + insertion + current.content.slice(selectionEnd);
                    commit({...current, content: ui.source.value}, false);
                    ui.source.focus();
                    ui.source.setSelectionRange(selectionStart + insertion.length, selectionStart + insertion.length);
                }
            },
        };
        for (const button of ui.modeButtons) button.onclick = () => { chooseMode(button.dataset.mode); };
        select('toggle-preview').onclick = event => {
            ui.previewing = !ui.previewing;
            event.currentTarget.setAttribute('aria-pressed', String(ui.previewing));
            ui.preview.hidden = !ui.previewing;
            select('editor-columns').classList.toggle('cms-rich-editor-split', ui.previewing);
            updatePreview(0);
        };
        select('media')?.addEventListener('click', () => {
            selectionTarget = 'rich-text-' + newNodeId();
            window.dispatchEvent(new CustomEvent('open-filemanager', {detail: {target: selectionTarget, kind: 'all'}}));
        });
        window.addEventListener('file-selected', ui.selectMedia);
        window.addEventListener('filemanager-closed', ui.closeMedia);
        ui.source.oninput = () => commit({...current, content: ui.source.value}, false);
        select('link').onclick = event => {
            if (!editor) return;
            const previous = editor.getAttributes('link').href ?? '';
            const href = window.prompt(event.currentTarget.getAttribute('aria-label'), previous)?.trim();
            if (href === undefined) return;
            const chain = editor.chain().focus().extendMarkRange('link');
            (href ? chain.setLink({href}) : chain.unsetLink()).run();
        };
        for (const button of host.querySelectorAll('[data-command]')) {
            button.onclick = () => editor?.chain().focus()[button.dataset.command]().run();
        }
        for (const menu of ui.menus) connectMenu(menu);
        for (const select of ui.styles) {
            select.onchange = () => { if (editor) applyTypography(select.dataset.style, select.value); };
        }
    }

    return {
        destroy,
        refresh(host, read, write) {
            const source = read();
            if (!source) return;
            if (!ui) {
                connect(host, read, write);
                host.classList.add('admin-rich-editor');
            }
            if (current?.format === source.format && current.content === source.content) return;
            const previous = current;
            current = source;
            showMode(previous?.format === current.format ? mode : (current.format === 'markdown' ? 'markdown' : 'visual'));
        },
    };
}
