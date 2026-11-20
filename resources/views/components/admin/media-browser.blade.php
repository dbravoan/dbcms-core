<div class="space-y-4" x-id="['media-search', 'media-type', 'media-per-page', 'media-upload', 'media-import', 'media-title', 'media-alt', 'media-caption']">
    <form class="admin-media-toolbar" @submit.prevent="applyFilters()" role="search" :aria-label="@js(__('admin.filemanager.search_label'))">
        <div class="admin-field">
            <label class="admin-field-label" :for="$id('media-search')">{{ __('admin.filemanager.search_label') }}</label>
            <input type="search" name="search" class="admin-control" :id="$id('media-search')" x-model="search"
                   @input.debounce.300ms="applyFilters()" maxlength="2000" placeholder="{{ __('admin.filemanager.search_placeholder') }}" :disabled="busy">
        </div>
        <div class="admin-field">
            <label class="admin-field-label" :for="$id('media-type')">{{ __('admin.filemanager.type_label') }}</label>
            <select name="type" class="admin-control" :id="$id('media-type')" x-model="type" @change="applyFilters()" :disabled="busy || kind !== 'all'">
                <option value="all">{{ __('admin.filemanager.tab_all') }}</option>
                <option value="image">{{ __('admin.filemanager.tab_images') }}</option>
                <option value="video">{{ __('admin.filemanager.tab_videos') }}</option>
                <option value="audio">{{ __('admin.filemanager.tab_audio') }}</option>
                <option value="animation">{{ __('admin.filemanager.tab_animations') }}</option>
                <option value="doc">{{ __('admin.filemanager.tab_docs') }}</option>
            </select>
        </div>
        <div class="admin-field">
            <label class="admin-field-label" :for="$id('media-per-page')">{{ __('admin.table.show') }}</label>
            <select name="per_page" class="admin-control" :id="$id('media-per-page')" x-model.number="perPage" @change="applyFilters()" :disabled="busy">
                @foreach(config('cms.options.per_page', [10, 15, 25, 50, 100]) as $size)
                    <option value="{{ $size }}">{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <x-admin.button variant="icon" icon="fa-solid fa-rotate" :title="__('admin.filemanager.reload')" aria-label="{{ __('admin.filemanager.reload') }}"
                        @click="fetchFiles()" x-bind:disabled="busy || loading" />
    </form>

    <div class="admin-media-toolbar admin-media-actions">
        <input type="file" class="hidden" x-ref="upload" :id="$id('media-upload')" :accept="uploadAccept" @change="uploadFile($event)" :disabled="busy" tabindex="-1" aria-hidden="true">
        <x-admin.button variant="primary" icon="fa-solid fa-upload" @click="$refs.upload.click()" x-bind:disabled="busy">
            <span x-text="uploading ? @js(__('admin.filemanager.uploading')) : @js(__('admin.filemanager.upload'))"></span>
        </x-admin.button>
        <form class="admin-media-import" @submit.prevent="importImage()" x-show="kind === 'all' || kind === 'image'">
            <div class="admin-field">
                <label class="admin-field-label" :for="$id('media-import')">{{ __('admin.filemanager.import_label') }}</label>
                <input type="url" class="admin-control" :id="$id('media-import')" x-model="importUrl" placeholder="https://example.com/image.webp" :disabled="busy" required>
            </div>
            <x-admin.button type="submit" icon="fa-solid fa-link" x-bind:disabled="busy || !importUrl.trim()">{{ __('admin.filemanager.import') }}</x-admin.button>
        </form>
        <x-admin.button icon="fa-solid fa-wand-magic-sparkles" x-show="aiAvailable && targetField && (kind === 'all' || kind === 'image')"
                        @click="window.CmsAssistant.open({ mode: 'image', mediaTarget: targetField }); close(false)" x-bind:disabled="busy">
            {{ __('admin.ai.media_generate') }}
        </x-admin.button>
    </div>

    <p class="admin-field-error" role="alert" x-show="error" x-cloak x-text="error"></p>
    <p class="admin-media-status" role="status" x-show="notice" x-cloak x-text="notice"></p>
    <p class="admin-media-status" role="status" x-show="loading">{{ __('admin.filemanager.loading') }}</p>

    <div class="admin-media-layout" :aria-busy="loading">
        <div class="min-w-0">
            <p class="admin-media-status" x-show="!loading && !error && files.length === 0" x-cloak>{{ __('admin.filemanager.empty') }}</p>
            <div class="admin-media-grid" role="group" aria-label="{{ __('admin.filemanager.library_title') }}">
                <template x-for="file in files" :key="file.filename">
                    <button type="button" class="admin-media-tile" :aria-pressed="isSelected(file)" :disabled="busy || loading"
                            :aria-label="file.title || file.original_name || file.filename" @click="clickFile(file)"
                            @dblclick="selectFile(file)">
                        <span class="admin-media-preview">
                            <template x-if="file.is_image"><img :src="file.url" alt="" loading="lazy"></template>
                            <template x-if="!file.is_image">
                                <i class="fa-solid" :class="file.is_audio ? 'fa-music' : (file.is_animation ? 'fa-film' : (file.is_video ? 'fa-video' : 'fa-file-lines'))" aria-hidden="true"></i>
                            </template>
                        </span>
                        <span class="block truncate" x-text="file.title || file.original_name || file.filename"></span>
                        <span class="admin-field-hint" x-text="file.size_formatted"></span>
                    </button>
                </template>
            </div>
        </div>
        <aside class="admin-media-details" aria-label="{{ __('admin.filemanager.detail_seo') }}">
            <template x-if="!selectedFile">
                <div class="space-y-2">
                    <h2 class="admin-card-title">{{ __('admin.filemanager.no_file') }}</h2>
                    <p class="admin-field-hint">{{ __('admin.filemanager.no_file_hint') }}</p>
                </div>
            </template>
            <template x-if="selectedFile">
                <form class="space-y-4" @submit.prevent="saveMetadata()">
                    <h2 class="admin-card-title break-words" x-text="selectedFile.original_name || selectedFile.filename"></h2>
                    <template x-if="selectedFile.is_image"><img class="admin-media-preview" :src="selectedFile.url" :alt="selectedFile.alt_text || ''"></template>
                    <template x-if="selectedFile.is_video && !selectedFile.is_audio"><video class="w-full" :src="selectedFile.url" controls preload="metadata"></video></template>
                    <template x-if="selectedFile.is_audio"><audio class="w-full" :src="selectedFile.url" controls preload="metadata"></audio></template>
                    <p class="admin-field-hint"><span>{{ __('admin.filemanager.size') }} </span><span x-text="selectedFile.size_formatted"></span></p>
                    <div class="admin-field">
                        <label class="admin-field-label" :for="$id('media-title')">{{ __('admin.filemanager.title_label') }}</label>
                        <input class="admin-control" :id="$id('media-title')" type="text" x-model="selectedFile.title" maxlength="255" :disabled="busy">
                    </div>
                    <div class="admin-field">
                        <label class="admin-field-label" :for="$id('media-alt')">{{ __('admin.filemanager.alt_label') }}</label>
                        <input class="admin-control" :id="$id('media-alt')" type="text" x-model="selectedFile.alt_text" maxlength="255" :disabled="busy">
                        <p class="admin-field-hint">{{ __('admin.filemanager.alt_hint') }}</p>
                    </div>
                    <div class="admin-field">
                        <label class="admin-field-label" :for="$id('media-caption')">{{ __('admin.filemanager.caption_label') }}</label>
                        <textarea class="admin-control" :id="$id('media-caption')" x-model="selectedFile.caption" maxlength="500" rows="3" :disabled="busy"></textarea>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-admin.button type="submit" icon="fa-solid fa-floppy-disk" x-bind:disabled="busy">
                            <span x-text="savingMetadata ? @js(__('admin.filemanager.saving')) : @js(__('admin.actions.save'))"></span>
                        </x-admin.button>
                        <x-admin.button variant="danger" icon="fa-solid fa-trash" @click="askDelete()" x-bind:disabled="busy">{{ __('admin.actions.delete') }}</x-admin.button>
                        <x-admin.button variant="primary" icon="fa-solid fa-check" @click="selectFile()" x-show="picker" x-bind:disabled="busy || !allowsKind(selectedFile)">{{ __('admin.filemanager.select_for_block') }}</x-admin.button>
                    </div>
                </form>
            </template>
        </aside>
    </div>

    <nav class="admin-media-pagination" aria-label="{{ __('admin.filemanager.pagination_label') }}">
        <p role="status" class="admin-field-hint" x-text="(pagination.from || 0) + '–' + (pagination.to || 0) + ' / ' + pagination.total"></p>
        <div class="flex items-center gap-2">
            <x-admin.button variant="icon" icon="fa-solid fa-chevron-left" :title="__('admin.filemanager.previous')" aria-label="{{ __('admin.filemanager.previous') }}"
                            @click="goToPage(page - 1)" x-bind:disabled="busy || loading || page <= 1" />
            <span class="admin-field-hint" x-text="page + ' / ' + pagination.last_page"></span>
            <x-admin.button variant="icon" icon="fa-solid fa-chevron-right" :title="__('admin.filemanager.next')" aria-label="{{ __('admin.filemanager.next') }}"
                            @click="goToPage(page + 1)" x-bind:disabled="busy || loading || page >= pagination.last_page" />
        </div>
    </nav>
</div>
