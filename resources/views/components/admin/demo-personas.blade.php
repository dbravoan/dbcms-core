@props(['personas' => []])

@if($personas !== [])
    <section class="admin-panel mt-6 p-4 space-y-3" data-demo-personas>
        <h2 class="admin-field-label">{{ __('admin.auth.quick_access_title') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach($personas as $role => $email)
                <a href="{{ route('admin.quick-login', $role) }}" class="admin-btn admin-btn--neutral flex-col items-start min-w-0">
                    <span>{{ ucfirst($role) }}</span>
                    <span class="admin-text-muted truncate max-w-full">{{ $email }}</span>
                    <span class="admin-text-muted">{{ __('admin.auth.role_scope.'.$role) }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
