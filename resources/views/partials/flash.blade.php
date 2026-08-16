@if (session('status') || session('success') || session('error'))
    <div class="mx-auto max-w-7xl px-3 px-sm-4 px-lg-4 pt-3">
        @if (session('success'))<x-alert type="success">{{ session('success') }}</x-alert>@endif
        @if (session('status'))<x-alert type="info">{{ session('status') }}</x-alert>@endif
        @if (session('error'))<x-alert type="error">{{ session('error') }}</x-alert>@endif
    </div>
@endif
