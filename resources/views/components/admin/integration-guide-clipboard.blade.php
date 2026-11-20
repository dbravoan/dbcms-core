@once
    @push('admin_scripts')
        <script nonce="{{ $cspNonce ?? '' }}">
            window.integrationGuideClipboard = function (messages) {
                return {
                    feedback: '',
                    async copy() {
                        const input = this.$refs.callback;
                        try {
                            const value = input.value;
                            const url = new URL(value);
                            if (!['http:', 'https:'].includes(url.protocol) || url.origin !== window.location.origin
                                || url.username || url.password || url.search || url.hash
                                || !/(?:^|\/)oauth\/[a-z][a-z0-9_-]*\/callback\/?$/.test(url.pathname)) {
                                throw new Error('Invalid public OAuth return URL');
                            }
                            await navigator.clipboard.writeText(value);
                            this.feedback = messages.copied;
                        } catch (_) {
                            input.focus();
                            input.select();
                            this.feedback = messages.failed;
                        }
                    },
                };
            };
        </script>
    @endpush
@endonce
