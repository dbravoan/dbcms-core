<div class="p-3 border-b admin-divider">
    <x-admin.switch id="notification-email-preference" size="sm" :label="__('mail.module_notification.email_preference')"
        :hint="__('mail.module_notification.email_preference_hint')" x-model="emailEnabled"
        @change="saveEmailPreference()" x-bind:aria-checked="emailEnabled.toString()" />
    <p x-show="emailPreferenceError" x-cloak role="alert" class="mt-2 text-xs text-red-600"
        x-text="emailPreferenceError"></p>
</div>
