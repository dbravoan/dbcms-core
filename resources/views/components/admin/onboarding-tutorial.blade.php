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
        @if(app()->environment(['local', 'testing']) && config('cms.quick_login', false))
        {
            title: @js(__('admin.onboarding.step2_title')),
            badge: @js(__('admin.onboarding.step2_badge')),
            desc: @js(__('admin.onboarding.step2_desc')),
            target: '#nav-persona-switcher'
        },
        @endif
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
        @if(!app()->isProduction() && config('cms.adminer_url'))
        {
            title: @js(__('admin.onboarding.step7_title')),
            badge: @js(__('admin.onboarding.step7_badge')),
            desc: @js(__('admin.onboarding.step7_desc')),
            target: '#nav-adminer'
        },
        @endif
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
        el.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center', inline: 'nearest' });

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
                this.popoverStyle = 'position: fixed; bottom: 16px; left: 16px; right: 16px; width: auto; max-width: calc(100vw - 32px);';
            } else {
                const cardWidth = Math.min(460, window.innerWidth - 48);
                const cardHeight = 340;

                // 1. Top bar persona switcher (top-right of screen)
                if (rect.top < 100 && rect.right > window.innerWidth / 2) {
                    const rightOffset = Math.max(24, Math.round(window.innerWidth - rect.right - 20));
                    this.popoverStyle = `position: fixed; top: ${Math.round(rect.bottom + 16)}px; right: ${rightOffset}px; width: ${cardWidth}px; `;
                }
                // 2. Sidebar elements (left edge)
                else if (rect.left < 340) {
                    const idealTop = Math.min(window.innerHeight - cardHeight - 24, Math.max(24, Math.round(rect.top - 20)));
                    const idealLeft = Math.max(24, Math.min(Math.round(rect.right + 24), window.innerWidth - cardWidth - 24));
                    this.popoverStyle = `position: fixed; top: ${idealTop}px; left: ${idealLeft}px; width: ${cardWidth}px; `;
                }
                // 3. Fallback: bottom-right
                else {
                    this.popoverStyle = `position: fixed; bottom: 24px; right: 24px; width: ${cardWidth}px; `;
                }
            }
        }, 100);
    }
}">

    <div x-show="open && spotlight.visible"
         x-cloak
         class="admin-tour-spotlight fixed pointer-events-none z-[80]"
         :style="'top: ' + (spotlight.top - 6) + 'px; left: ' + (spotlight.left - 6) + 'px; width: ' + (spotlight.width + 12) + 'px; height: ' + (spotlight.height + 12) + 'px;'"
         aria-hidden="true"></div>

    <x-admin.modal show="open"
                   max-width="lg"
                   z-index="z-[90]"
                   :maximizable="false"
                   on-close="finish()"
                   panel-style="spotlight.visible ? popoverStyle : ''"
                   backdrop-style="spotlight.visible ? 'background: transparent; backdrop-filter: none;' : ''"
                   @keydown.right.stop.prevent="nextStep()"
                   @keydown.left.stop.prevent="prevStep()">
        <x-slot:header>
            <h3 class="admin-dialog-title text-sm font-bold" x-text="steps[currentStep - 1]?.title"></h3>
            <p class="admin-text-muted" x-text="steps[currentStep - 1]?.badge"></p>
        </x-slot:header>

        <p class="admin-dialog-message" x-text="steps[currentStep - 1]?.desc"></p>
        <div x-show="!spotlight.visible" class="admin-alert space-y-1">
            <p class="font-bold">{{ __('admin.onboarding.interactive_nav') }}</p>
            <p>{{ __('admin.onboarding.interactive_nav_desc') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('admin.onboarding.interactive_tour') }}">
            <template x-for="(st, idx) in steps" :key="idx">
                <button type="button"
                        @click="goToStep(idx + 1)"
                        :title="st.title"
                        :aria-label="st.title"
                        :aria-current="currentStep === idx + 1 ? 'step' : null"
                        class="admin-tour-step"></button>
            </template>
        </div>
        <p class="admin-text-muted" aria-live="polite" x-text="currentStep + ' / ' + totalSteps"></p>

        <x-slot:footer>
            <x-admin.button variant="ghost" @click="finish()">
                <span x-text="currentStep === 1 ? @js(__('admin.onboarding.skip')) : @js(__('admin.onboarding.finish'))"></span>
            </x-admin.button>
            <x-admin.button x-show="currentStep > 1" @click="prevStep()">
                {{ __('admin.onboarding.previous') }}
            </x-admin.button>
            <x-admin.button variant="primary" @click="nextStep()" data-admin-dialog-initial-focus>
                <span x-text="currentStep === 1 ? @js(__('admin.onboarding.start')) : (currentStep === totalSteps ? @js(__('admin.onboarding.begin')) : @js(__('admin.onboarding.next')))"></span>
            </x-admin.button>
        </x-slot:footer>
    </x-admin.modal>
</div>
