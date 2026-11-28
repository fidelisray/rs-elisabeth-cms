<style data-rse-brand-tokens>
    :root {
        /* Brand Primary: RS Elisabeth Medical Navy (#095988) */
        --rse-brand-50: #f0f8fc;
        --rse-brand-100: #dceff9;
        --rse-brand-200: #bae0f4;
        --rse-brand-300: #89c9ea;
        --rse-brand-400: #1997e6;
        --rse-brand-500: #0d74b0;
        --rse-brand-600: #095988;
        --rse-brand-700: #07476e;
        --rse-brand-800: #063a5a;
        --rse-brand-900: #052d47;
        --rse-brand-950: #031d2f;

        /* Brand Secondary: Fresh Emerald (#159E74) */
        --rse-secondary-50: #ecfdf5;
        --rse-secondary-100: #d1fae5;
        --rse-secondary-200: #a7f3d0;
        --rse-secondary-300: #6ee7b7;
        --rse-secondary-400: #34d399;
        --rse-secondary-500: #159e74;
        --rse-secondary-600: #0f7a59;
        --rse-secondary-800: #094d38;
        --rse-secondary-950: #052e21;

        /* Semantic Interactive Brand Tokens (Light) */
        --rse-brand-accent: #095988;
        --rse-brand-accent-hover: #07476e;
        --rse-brand-accent-text: #ffffff;
        --rse-brand-link: #095988;
        --rse-brand-focus: #095988;
        --rse-brand-soft-bg: #f0f8fc;
        --rse-brand-badge-bg: #dceff9;
        --rse-brand-badge-text: #07476e;
        --rse-brand-secondary: #159e74;

        /* Slate Neutrals (Light — DESIGN_SYSTEM.md) */
        --rse-slate-surface: #ffffff;
        --rse-slate-muted: #f8fafc;
        --rse-slate-border: #cbd5e1;
        --rse-slate-border-strong: #64748b;
        --rse-slate-divider: #e2e8f0;
        --rse-slate-text: #0f172a;
        --rse-slate-text-secondary: #475569;
        --rse-slate-track: #e2e8f0;
    }

    .dark {
        /* Semantic Interactive Brand Tokens (Dark — hsl(204 80% 50%) = #1997E6) */
        --rse-brand-accent: #1997e6;
        --rse-brand-accent-hover: #89c9ea;
        --rse-brand-accent-text: #0b0f19;
        --rse-brand-link: #38bdf8;
        --rse-brand-focus: #1997e6;
        --rse-brand-soft-bg: rgba(9, 89, 136, 0.22);
        --rse-brand-badge-bg: #052d47;
        --rse-brand-badge-text: #bae0f4;
        --rse-brand-secondary: #34d399;

        /* Slate Neutrals (Dark — DESIGN_SYSTEM.md) */
        --rse-slate-surface: #0f172a;
        --rse-slate-muted: #1e293b;
        --rse-slate-border: #334155;
        --rse-slate-border-strong: #64748b;
        --rse-slate-divider: #233044;
        --rse-slate-text: #f8fafc;
        --rse-slate-text-secondary: #cbd5e1;
        --rse-slate-track: #1e293b;
    }

    /* Brand Logo Lockup (Sidebar, Topbar, Auth Pages) */
    .rse-brand-lockup {
        display: inline-flex;
        align-items: center;
        gap: 0.625rem;
        max-width: 100%;
    }

    .rse-brand-icon {
        width: 2rem;
        height: 2rem;
        flex-shrink: 0;
        object-fit: contain;
        border-radius: 0.375rem;
    }

    .rse-brand-text {
        display: flex;
        flex-direction: column;
        line-height: 1.15;
        text-align: left;
    }

    .rse-brand-title {
        font-size: 0.9375rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        color: var(--rse-brand-600);
        white-space: nowrap;
    }

    .dark .rse-brand-title {
        color: #f8fafc;
    }

    .rse-brand-subtitle {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--rse-slate-text-secondary);
        white-space: nowrap;
    }

    /* Segmented Pill Language Switcher (ID | EN) */
    .rse-locale-switcher {
        display: inline-flex;
        align-items: center;
        gap: 0.125rem;
        padding: 0.1875rem;
        border-radius: 9999px;
        background-color: var(--rse-slate-muted);
        border: 1px solid var(--rse-slate-border);
        flex-shrink: 0;
    }

    .rse-locale-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.125rem;
        height: 1.625rem;
        padding: 0 0.5rem;
        border: 0;
        border-radius: 9999px;
        background: transparent;
        color: var(--rse-slate-text-secondary);
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        line-height: 1;
        cursor: pointer;
        transition: background-color 150ms ease, color 150ms ease;
    }

    .rse-locale-btn:hover:not(.is-active) {
        background-color: var(--rse-brand-soft-bg);
        color: var(--rse-slate-text);
    }

    .rse-locale-btn:focus-visible {
        outline: 2px solid var(--rse-brand-focus);
        outline-offset: 2px;
    }

    .rse-locale-btn.is-active {
        background-color: var(--rse-brand-accent);
        color: var(--rse-brand-accent-text);
    }

    .rse-login-help-note {
        margin-top: 1.25rem;
        text-align: center;
        font-size: 0.8125rem;
        line-height: 1.4;
        color: var(--rse-slate-text-secondary);
    }
</style>
