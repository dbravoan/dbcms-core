<div x-data="{
    open: false,
    currentStep: 1,
    totalSteps: 5,
    spotlight: {
        visible: false,
        top: 0,
        left: 0,
        width: 0,
        height: 0
    },
    popoverStyle: '',
    steps: [
        {
            title: @js(__('admin.onboarding.step1_title', ['app' => config('cms.name')])),
            badge: @js(__('admin.onboarding.step1_badge')),
            desc: @js(__('admin.onboarding.step1_desc')),
            target: null
        },
        {
            title: @js(__('admin.onboarding.step2_title')),
            badge: @js(__('admin.onboarding.step2_badge')),
            desc: @js(__('admin.onboarding.step2_desc')),
            target: '#nav-persona-switcher'
        },
        {
            title: @js(__('admin.onboarding.step3_title')),
            badge: @js(__('admin.onboarding.step3_badge')),
            desc: @js(__('admin.onboarding.step3_desc')),
            target: '#nav-modules'
        },
        {
            title: @js(__('admin.onboarding.step4_title')),
            badge: @js(__('admin.onboarding.step4_badge')),
            desc: @js(__('admin.onboarding.step4_desc')),
            target: '#nav-pages'
        },
        {
            title: @js(__('admin.onboarding.step7_title')),
            badge: @js(__('admin.onboarding.step7_badge')),
            desc: @js(__('admin.onboarding.step7_desc')),
            target: '#nav-adminer'
        }
    ],
    init() {
        if (!localStorage.getItem('dbcms_tour_completed')) {
            setTimeout(() => { this.startTour(); }, 600);
        }
        window.addEventListener('start-onboarding-tour', () => {
            this.startTour();
        });
        window.addEventListener('resize', () => {
            if (this.open) this.recalculateSpotlight();
        });
        window.addEventListener('scroll', () => {
            if (this.open) this.recalculateSpotlight();
        }, true);
        window.addEventListener('keydown', (e) => {
            if (!this.open) return;
            if (e.key === 'ArrowRight' || e.key === 'Enter') {
                this.nextStep();
            } else if (e.key === 'ArrowLeft') {
                this.prevStep();
            } else if (e.key === 'Escape') {
                this.finish();
            }
        });
    },
    startTour() {
        this.steps = this.steps.filter(s => !s.target || document.querySelector(s.target));
        this.totalSteps = this.steps.length;
        this.currentStep = 1;
        this.open = true;
        this.$nextTick(() => { this.recalculateSpotlight(); });
    },
    goToStep(stepNum) {
        this.currentStep = stepNum;
        this.$nextTick(() => { this.recalculateSpotlight(); });
    },
    nextStep() {
        if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.$nextTick(() => { this.recalculateSpotlight(); });
        } else {
            this.finish();
        }
    },
    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.$nextTick(() => { this.recalculateSpotlight(); });
        }
    },
    finish() {
        this.open = false;
        this.spotlight.visible = false;
        localStorage.setItem('dbcms_tour_completed', 'true');
    },
    recalculateSpotlight() {
        const step = this.steps[this.currentStep - 1];
        if (!step || !step.target) {
            this.spotlight.visible = false;
            this.popoverStyle = '';
            return;
        }

        const el = document.querySelector(step.target);
        if (!el) {
            this.spotlight.visible = false;
            this.popoverStyle = '';
            return;
        }

        // Smooth scroll target into view
        el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });

        // Update spotlight bounding rect and popover positioning
        setTimeout(() => {
            const rect = el.getBoundingClientRect();
            this.spotlight = {
                visible: true,
                top: Math.round(rect.top),
                left: Math.round(rect.left),
                width: Math.round(rect.width),
                height: Math.round(rect.height)
            };

            const isMobile = window.innerWidth < 768;
            if (isMobile) {
                this.popoverStyle = 'position: fixed; bottom: 16px; left: 16px; right: 16px; width: auto; max-width: calc(100vw - 32px); z-index: 60;';
            } else {
                const cardWidth = 460;
                const cardHeight = 280;

                // 1. Top bar persona switcher (top-right of screen)
                if (rect.top < 100 && rect.right > window.innerWidth / 2) {
                    const rightOffset = Math.max(24, Math.round(window.innerWidth - rect.right - 20));
                    this.popoverStyle = `position: fixed; top: ${Math.round(rect.bottom + 16)}px; right: ${rightOffset}px; width: ${cardWidth}px; z-index: 60;`;
                }
                // 2. Sidebar elements (left edge)
                else if (rect.left < 340) {
                    const idealTop = Math.min(window.innerHeight - cardHeight - 24, Math.max(24, Math.round(rect.top - 20)));
                    const idealLeft = Math.round(rect.right + 24);
                    this.popoverStyle = `position: fixed; top: ${idealTop}px; left: ${idealLeft}px; width: ${cardWidth}px; z-index: 60;`;
                }
                // 3. Fallback: bottom-right
                else {
                    this.popoverStyle = `position: fixed; bottom: 24px; right: 24px; width: ${cardWidth}px; z-index: 60;`;
                }
            }
        }, 100);
    }
}">

    <!-- 1. DYNAMIC SPOTLIGHT CUTOUT & TARGET HIGHLIGHT -->
    <div x-show="open && spotlight.visible"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed pointer-events-none z-50 transition-all duration-300 ease-out"
         :style="'top: ' + (spotlight.top - 6) + 'px; left: ' + (spotlight.left - 6) + 'px; width: ' + (spotlight.width + 12) + 'px; height: ' + (spotlight.height + 12) + 'px; border-radius: 10px; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.82), 0 0 35px rgba(0, 255, 153, 0.95); border: 2px solid #00FF99;'"
         style="display: none;">

        <!-- Radar ripple ping -->
        <span class="absolute -inset-1 rounded-xl border-2 border-[color:var(--admin-accent)] animate-ping opacity-60 pointer-events-none"></span>

        <!-- Floating Beacon Tag -->
        <div class="absolute -top-8 left-0 px-2.5 py-0.5 rounded bg-[color:var(--admin-accent)] text-black font-mono text-[10px] font-black uppercase tracking-wider flex items-center gap-1.5 shadow-[0_0_20px_rgba(0,255,153,0.9)] pointer-events-none whitespace-nowrap">
            <i class="fa-solid fa-bolt animate-pulse"></i>
            <span x-text="steps[currentStep - 1].badge"></span>
        </div>
    </div>

    <!-- 2. FULLSCREEN BACKDROP FOR WELCOME STEP (TARGET NULL) -->
    <div x-show="open && !spotlight.visible"
         @keydown.escape.window="finish()"
         @click.self="finish()"
         x-transition.opacity.duration.300ms
         class="fixed inset-0 z-50 bg-[color:var(--admin-surface)]/40 backdrop-blur-md flex items-center justify-center p-4"
         style="display: none;">
        
        <!-- Centered Welcome Card -->
        <div class="bg-[color:var(--admin-surface)] border-2 border-[color:var(--admin-accent)] max-w-lg w-full rounded-2xl shadow-[0_0_50px_rgba(0,255,153,0.35)] p-6 sm:p-8 font-mono relative overflow-hidden"
             @click.stop
             @click.outside="finish()">
            
            <div class="absolute -top-16 -right-16 w-36 h-36 bg-[color:var(--admin-accent)]/20 rounded-full blur-2xl pointer-events-none"></div>

            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-[color:var(--admin-border)] text-xs text-[color:var(--admin-muted)]">
                <span class="text-[color:var(--admin-accent)] font-bold">{!! __('admin.onboarding.interactive_tour') !!}</span>
                <span class="px-2 py-0.5 rounded bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[10px]" x-text="@js(__('admin.onboarding.step_of', ['current' => ':current', 'total' => ':total'])).replace(':current', currentStep).replace(':total', totalSteps)"></span>
            </div>

            <!-- Body -->
            <div class="py-6 space-y-3">
                <h3 class="text-xl font-bold text-[color:var(--admin-text)] tracking-tight flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&bull;</span>
                    <span x-text="steps[0].title"></span>
                </h3>
                <p class="text-sm text-[color:var(--admin-text)] leading-relaxed" x-text="steps[0].desc"></p>
                
                <div class="p-3 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl text-xs text-[color:var(--admin-muted)] space-y-1">
                    <div class="text-[color:var(--admin-accent)] font-bold flex items-center gap-1.5">
                        <i class="fa-solid fa-lightbulb text-[color:var(--admin-accent)]"></i>
                        <span>{{ __('admin.onboarding.interactive_nav') }}</span>
                    </div>
                    <p class="text-[11px] text-[color:var(--admin-muted)]">
                        {{ __('admin.onboarding.interactive_nav_desc') }}
                    </p>
                </div>
            </div>

            <!-- Step Dots -->
            <div class="flex items-center justify-center gap-1.5 pb-6">
                <template x-for="(st, idx) in steps" :key="idx">
                    <button type="button"
                            @click="goToStep(idx + 1)"
                            class="h-2 rounded-full transition-all duration-300"
                            :class="(currentStep === idx + 1) ? 'w-6 bg-[color:var(--admin-accent)] shadow-[0_0_10px_rgba(0,255,153,0.8)]' : 'w-2 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)]'"></button>
                </template>
            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center justify-between pt-2 border-t border-[color:var(--admin-border)]">
                <button type="button" @click="finish()" class="text-xs text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] underline">
                    {{ __('admin.onboarding.skip') }}
                </button>
                <x-admin.button variant="primary" type="button" @click="nextStep()" class="text-xs py-2 px-5 shadow-[0_0_20px_rgba(0,255,153,0.4)]">
                    {{ __('admin.onboarding.start') }} &rarr;
                </x-admin.button>
            </div>
        </div>
    </div>

    <!-- 3. FLOATING POPOVER CARD FOR HIGHLIGHTED TARGET STEPS -->
    <div x-show="open && spotlight.visible"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         :style="popoverStyle"
         class="bg-[color:var(--admin-surface)] border-2 border-[color:var(--admin-accent)] rounded-2xl shadow-[0_0_50px_rgba(0,255,153,0.35)] p-5 sm:p-6 font-mono text-xs overflow-hidden"
         style="display: none;">

        <div class="space-y-4">
            <!-- Header bar -->
            <div class="flex items-center justify-between pb-3 border-b border-[color:var(--admin-border)]">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-location-dot text-[color:var(--admin-accent)] text-sm"></i>
                    <span class="text-[color:var(--admin-accent)] font-bold text-[11px] uppercase tracking-wide" x-text="steps[currentStep - 1].badge"></span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-[color:var(--admin-muted)] text-[10px]">
                        <span class="text-[color:var(--admin-text)] font-bold" x-text="currentStep"></span> / <span x-text="totalSteps"></span>
                    </span>
                    <button type="button" @click="finish()" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] text-base font-bold leading-none">&times;</button>
                </div>
            </div>

            <!-- Title & Description -->
            <div class="space-y-2">
                <h4 class="text-base font-bold text-[color:var(--admin-text)] tracking-tight flex items-center gap-2">
                    <span class="text-[color:var(--admin-accent)]">&bull;</span>
                    <span x-text="steps[currentStep - 1].title"></span>
                </h4>
                <p class="text-[color:var(--admin-text)] text-xs leading-relaxed" x-text="steps[currentStep - 1].desc"></p>
            </div>

            <!-- Interactive Step Dots -->
            <div class="flex items-center gap-1.5 pt-1">
                <template x-for="(st, idx) in steps" :key="idx">
                    <button type="button"
                            @click="goToStep(idx + 1)"
                            :title="st.title"
                            class="h-1.5 rounded-full transition-all duration-300"
                            :class="(currentStep === idx + 1) ? 'w-5 bg-[color:var(--admin-accent)] shadow-[0_0_8px_rgba(0,255,153,0.8)]' : 'w-2 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)]'"></button>
                </template>
            </div>

            <!-- Footer Actions -->
            <div class="flex items-center justify-between pt-3 border-t border-[color:var(--admin-border)]">
                <button type="button"
                        @click="finish()"
                        class="text-[11px] text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] underline">
                    {{ __('admin.onboarding.finish') }}
                </button>

                <div class="flex items-center gap-2">
                    <button type="button"
                            x-show="currentStep > 1"
                            @click="prevStep()"
                            class="px-3 py-1.5 rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] hover:border-[color:var(--admin-border)] text-[color:var(--admin-text)] text-xs font-bold transition-colors">
                        {{ __('admin.onboarding.previous') }}
                    </button>

                    <x-admin.button variant="primary" type="button"
                            @click="nextStep()"
                            class="text-xs py-1.5 px-4 shadow-[0_0_15px_rgba(0,255,153,0.4)]">
                        <span x-text="currentStep === totalSteps ? @js(__('admin.onboarding.begin')) : @js(__('admin.onboarding.next'))"></span>
                    </x-admin.button>
                </div>
            </div>
        </div>
    </div>

</div>
