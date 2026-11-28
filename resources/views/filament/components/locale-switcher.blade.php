@php
    $currentLocale = app()->getLocale();
@endphp

<form
    method="POST"
    action="{{ route('filament.admin.locale.switch') }}"
    data-rse-locale-switcher="topbar"
    class="rse-locale-switcher rse-locale-switcher--topbar"
    role="group"
    aria-label="{{ __('Pilih bahasa antarmuka') }}"
>
    @csrf
    <button
        type="submit"
        name="locale"
        value="id"
        data-locale-option="id"
        aria-pressed="{{ $currentLocale === 'id' ? 'true' : 'false' }}"
        aria-label="{{ __('Bahasa Indonesia') }}"
        title="{{ __('Bahasa Indonesia') }}"
        class="rse-locale-btn {{ $currentLocale === 'id' ? 'is-active' : '' }}"
    >
        ID
    </button>
    <button
        type="submit"
        name="locale"
        value="en"
        data-locale-option="en"
        aria-pressed="{{ $currentLocale === 'en' ? 'true' : 'false' }}"
        aria-label="{{ __('Bahasa Inggris') }}"
        title="{{ __('Bahasa Inggris') }}"
        class="rse-locale-btn {{ $currentLocale === 'en' ? 'is-active' : '' }}"
    >
        EN
    </button>
</form>
