<style data-rse-dynamic-form-builder-styles>
    /* Pembedaan hierarki visual Bagian (Medical Navy) vs Pertanyaan (Fresh Emerald) menggunakan direct-child selector agar tidak bocor ke repeater bersarang. */
    .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item {
        border: 1.5px solid var(--rse-brand-600, #095988) !important;
        background-color: var(--rse-brand-50, #f0f8fc) !important;
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header {
        background-color: var(--rse-brand-100, #dceff9) !important;
        border-bottom: 1px solid var(--rse-brand-300, #89c9ea);
        min-height: 44px;
    }

    .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label {
        color: var(--rse-brand-700, #07476e) !important;
        font-weight: 700;
    }

    .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item {
        border: 1.5px solid var(--rse-secondary-500, #159e74) !important;
        background-color: var(--rse-slate-surface, #ffffff) !important;
        border-radius: 0.625rem;
        overflow: hidden;
    }

    .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header {
        background-color: var(--rse-secondary-100, #d1fae5) !important;
        border-bottom: 1px solid var(--rse-secondary-300, #6ee7b7);
        min-height: 44px;
    }

    .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label {
        color: var(--rse-secondary-800, #094d38) !important;
        font-weight: 600;
    }

    .dark .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item {
        border-color: rgba(25, 151, 230, 0.55) !important;
        background-color: rgba(9, 89, 136, 0.16) !important;
    }

    .dark .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header {
        background-color: var(--rse-brand-900, #052d47) !important;
        border-bottom-color: rgba(25, 151, 230, 0.4);
    }

    .dark .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label {
        color: var(--rse-brand-200, #bae0f4) !important;
    }

    .dark .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item {
        border-color: rgba(52, 211, 153, 0.45) !important;
        background-color: rgba(15, 23, 42, 0.94) !important;
    }

    .dark .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header {
        background-color: var(--rse-secondary-950, #052e21) !important;
        border-bottom-color: rgba(52, 211, 153, 0.35);
    }

    .dark .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label {
        color: var(--rse-secondary-200, #a7f3d0) !important;
    }

    .rse-df-sections-repeater > .fi-fo-repeater-add .fi-btn,
    .rse-df-fields-repeater > .fi-fo-repeater-add .fi-btn {
        min-height: 44px;
    }

    .rse-df-sections-repeater .fi-fo-rich-editor-toolbar {
        flex-wrap: wrap;
        max-width: 100%;
    }

    @media (max-width: 639px) {
        .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-content,
        .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-content {
            padding: 0.75rem;
        }
    }
</style>
