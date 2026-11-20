const mediaKinds = ['image', 'video', 'audio', 'animation', 'doc'];

/** The library page and every picker share queries, mutations and event routing. */
export function createMediaManager(options = {}) {
    return {
        picker: !!options.picker,
        open: false,
        files: [],
        loading: false,
        uploading: false,
        savingMetadata: false,
        deleting: false,
        error: '',
        notice: '',
        search: '',
        type: 'all',
        kind: 'all',
        targetField: null,
        selectedFile: null,
        importUrl: '',
        deleteOpen: false,
        pendingDelete: null,
        aiAvailable: typeof window !== 'undefined' && !!window.CmsAssistant,
        page: 1,
        perPage: Number(options.perPage || 15),
        pagination: { current_page: 1, last_page: 1, total: 0, from: null, to: null },
        requestId: 0,
        session: 0,
        openListener: null,

        init() {
            if (this.picker) {
                this.openListener = event => this.openPicker(event.detail || {});
                window.addEventListener('open-filemanager', this.openListener);
            } else {
                const query = new URLSearchParams(window.location.search);
                this.search = query.get('search') || '';
                this.type = mediaKinds.includes(query.get('type')) ? query.get('type') : 'all';
                this.page = Math.max(1, Number(query.get('page')) || 1);
                const requestedSize = Number(query.get('per_page'));
                if ((options.perPageOptions || [10, 15, 25, 50, 100]).includes(requestedSize)) this.perPage = requestedSize;
                this.fetchFiles();
            }
        },

        destroy() {
            if (this.openListener) window.removeEventListener('open-filemanager', this.openListener);
            this.requestId++;
            this.session++;
        },

        message(key) { return options.messages?.[key] || key; },

        openPicker(detail) {
            this.session++;
            this.targetField = detail.target || null;
            this.kind = mediaKinds.includes(detail.kind) ? detail.kind : 'all';
            this.type = this.kind;
            this.search = '';
            this.page = 1;
            this.selectedFile = null;
            this.deleteOpen = false;
            this.pendingDelete = null;
            this.error = '';
            this.notice = '';
            this.open = true;
            this.fetchFiles();
        },

        close(notify = true) {
            if (!this.open) return;
            const detail = { target: this.targetField, kind: this.kind };
            this.open = false;
            this.deleteOpen = false;
            this.session++;
            this.requestId++;
            this.loading = false;
            if (notify) window.dispatchEvent(new CustomEvent('filemanager-closed', { detail }));
        },

        allowsKind(file) { return !!file && (this.kind === 'all' || !!file[`is_${this.kind}`]); },
        isSelected(file) { return !!this.selectedFile && this.selectedFile.filename === file.filename; },
        get busy() { return this.uploading || this.savingMetadata || this.deleting; },
        get uploadAccept() {
            return { image: 'image/*', video: 'video/*', audio: 'audio/*', animation: '.json,application/json', doc: '.pdf,.doc,.docx,.xls,.xlsx,.txt,.vtt' }[this.kind] || '.jpg,.jpeg,.png,.webp,.gif,.svg,.mp4,.webm,.mov,.mp3,.wav,.ogg,.m4a,.json,.pdf,.doc,.docx,.xls,.xlsx,.txt,.vtt';
        },

        async request(url, init = {}) {
            let response;
            try {
                response = await fetch(url, {
                    ...init,
                    headers: { Accept: 'application/json', ...(init.method ? { 'X-CSRF-TOKEN': options.csrfToken } : {}), ...init.headers },
                });
            } catch {
                throw new Error(this.message('network_error'));
            }
            let data;
            try { data = await response.json(); } catch {
                throw new Error(`${this.message('response_error')} (HTTP ${response.status}).`);
            }
            if (!data || typeof data !== 'object' || Array.isArray(data)) {
                throw new Error(`${this.message('response_error')} (HTTP ${response.status}).`);
            }
            if (!response.ok || data.success === false) {
                const validation = Object.values(data.errors || {}).flat().join(' ');
                const explanation = validation || data.message || this.message('request_error');
                throw new Error(`${explanation}${response.ok ? '' : ` (HTTP ${response.status})`}`);
            }
            return data;
        },

        async fetchFiles() {
            const requestId = ++this.requestId;
            this.loading = true;
            this.error = '';
            const query = new URLSearchParams({ search: this.search, type: this.kind === 'all' ? this.type : this.kind, page: this.page, per_page: this.perPage });
            try {
                const data = await this.request(`${options.filesUrl}?${query}`);
                if (requestId !== this.requestId) return;
                if (!Array.isArray(data.files) || !data.pagination) throw new Error(this.message('response_error'));
                this.files = data.files;
                this.pagination = data.pagination;
                this.page = data.pagination.current_page;
                if (!this.picker) {
                    query.set('page', this.page);
                    window.history.replaceState(null, '', `${window.location.pathname}?${query}`);
                }
            } catch (error) {
                if (requestId === this.requestId) {
                    this.files = [];
                    this.error = error.message;
                }
            } finally {
                if (requestId === this.requestId) this.loading = false;
            }
        },

        applyFilters() {
            this.page = 1;
            this.selectedFile = null;
            this.notice = '';
            return this.fetchFiles();
        },

        goToPage(page) {
            if (this.loading || page < 1 || page > this.pagination.last_page) return;
            this.page = page;
            this.selectedFile = null;
            return this.fetchFiles();
        },

        clickFile(file) {
            if (this.busy) return;
            this.selectedFile = { ...file };
            this.notice = '';
        },

        selectFile(file = this.selectedFile) {
            if (!this.picker || !this.targetField || this.busy || !file) return;
            if (!this.allowsKind(file)) { this.error = this.message('kind_error'); return; }
            window.dispatchEvent(new CustomEvent('file-selected', { detail: { target: this.targetField, kind: this.kind, file: { ...file } } }));
            this.close();
        },

        async uploadFile(event) {
            const input = event.target;
            const file = input.files?.[0];
            if (!file || this.busy) return;
            const form = new FormData();
            form.append('file', file);
            try { await this.addFile(options.uploadUrl, { method: 'POST', body: form }); }
            finally { input.value = ''; }
        },

        async importImage() {
            if (!this.importUrl.trim() || this.busy) return;
            const session = this.session;
            const success = await this.addFile(options.importUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ url: this.importUrl.trim() }) });
            if (session === this.session && success) this.importUrl = '';
        },

        async addFile(url, init) {
            this.uploading = true;
            this.error = '';
            this.notice = '';
            const session = this.session;
            try {
                const data = await this.request(url, init);
                if (session !== this.session) return false;
                if (data.success !== true || !data.file) throw new Error(this.message('response_error'));
                this.selectedFile = { ...data.file };
                this.page = 1;
                await this.fetchFiles();
                if (session !== this.session) return false;
                if (!this.allowsKind(data.file)) this.error = this.message('kind_error');
                else this.notice = this.message('upload_success');
                return true;
            } catch (error) {
                if (session === this.session) this.error = error.message;
                return false;
            } finally { this.uploading = false; }
        },

        async saveMetadata() {
            if (!this.selectedFile || this.busy) return;
            const file = { ...this.selectedFile };
            const session = this.session;
            this.savingMetadata = true;
            this.error = '';
            this.notice = '';
            try {
                const data = await this.request(options.updateUrl.replace('__ID__', encodeURIComponent(file.id)), {
                    method: 'PUT', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ title: file.title, alt_text: file.alt_text, caption: file.caption }),
                });
                if (session !== this.session) return;
                if (data.success !== true || !data.file) throw new Error(this.message('response_error'));
                // Metadata responses are model attributes; preserve media capabilities and resolved URL.
                this.selectedFile = { ...file, ...Object.fromEntries(['title', 'alt_text', 'caption'].filter(key => key in data.file).map(key => [key, data.file[key]])) };
                await this.fetchFiles();
                if (session !== this.session) return;
                this.notice = this.message('metadata_success');
            } catch (error) {
                if (session === this.session) this.error = error.message;
            } finally { this.savingMetadata = false; }
        },

        askDelete(file = this.selectedFile) {
            if (!file || this.busy) return;
            this.pendingDelete = { ...file };
            this.deleteOpen = true;
        },

        cancelDelete() {
            if (this.deleting) return;
            this.deleteOpen = false;
            this.pendingDelete = null;
        },

        async deleteFile() {
            if (!this.deleteOpen || !this.pendingDelete || this.busy) return;
            const file = this.pendingDelete;
            const session = this.session;
            this.deleting = true;
            this.error = '';
            this.notice = '';
            try {
                const data = await this.request(options.deleteUrl.replace('__FILE__', encodeURIComponent(file.filename)), { method: 'DELETE' });
                if (session !== this.session) return;
                if (data.success !== true) throw new Error(this.message('response_error'));
                this.deleteOpen = false;
                this.pendingDelete = null;
                if (this.isSelected(file)) this.selectedFile = null;
                await this.fetchFiles();
                if (session !== this.session) return;
                this.notice = this.message('delete_success');
            } catch (error) {
                if (session === this.session) {
                    this.deleteOpen = false;
                    this.error = error.message;
                }
            } finally { this.deleting = false; }
        },
    };
}
