<style data-rse-dynamic-form-builder-styles>
    /* Pembedaan hierarki visual Bagian (Amber) vs Pertanyaan (Biru Sky) menggunakan direct-child selector agar tidak bocor ke repeater bersarang. */
    .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item {
        border: 1.5px solid #d97706 !important;
        background-color: #fffbeb !important;
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header {
        background-color: #fef3c7 !important;
        border-bottom: 1px solid #f59e0b;
        min-height: 44px;
    }

    .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label {
        color: #92400e !important;
        font-weight: 700;
    }

    .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item {
        border: 1.5px solid #0284c7 !important;
        background-color: #ffffff !important;
        border-radius: 0.625rem;
        overflow: hidden;
    }

    .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header {
        background-color: #e0f2fe !important;
        border-bottom: 1px solid #7dd3fc;
        min-height: 44px;
    }

    .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label {
        color: #075985 !important;
        font-weight: 600;
    }

    .dark .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item {
        border-color: rgba(245, 158, 11, 0.55) !important;
        background-color: rgba(120, 53, 15, 0.14) !important;
    }

    .dark .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header {
        background-color: #45280d !important;
        border-bottom-color: rgba(245, 158, 11, 0.4);
    }

    .dark .rse-df-sections-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label {
        color: #fde68a !important;
    }

    .dark .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item {
        border-color: rgba(56, 189, 248, 0.45) !important;
        background-color: rgba(24, 24, 27, 0.94) !important;
    }

    .dark .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header {
        background-color: #0c2d48 !important;
        border-bottom-color: rgba(56, 189, 248, 0.35);
    }

    .dark .rse-df-fields-repeater > .fi-fo-repeater-items > .fi-fo-repeater-item > .fi-fo-repeater-item-header .fi-fo-repeater-item-header-label {
        color: #bae6fd !important;
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
