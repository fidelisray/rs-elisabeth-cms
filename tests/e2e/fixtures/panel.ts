import AxeBuilder from '@axe-core/playwright';
import { expect, type Page } from '@playwright/test';

export interface ErrorTracker {
    consoleErrors: string[];
    pageErrors: string[];
    serverErrors: string[];
    assertZeroErrors: () => void;
}

/**
 * Pasang kolektor error runtime browser & HTTP 5xx agar setiap regresi JS/Livewire langsung terdeteksi.
 */
export function attachErrorTracker(page: Page): ErrorTracker {
    const consoleErrors: string[] = [];
    const pageErrors: string[] = [];
    const serverErrors: string[] = [];

    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            consoleErrors.push(msg.text());
        }
    });

    page.on('pageerror', (err) => {
        pageErrors.push(String(err));
    });

    page.on('response', (response) => {
        if (response.status() >= 500) {
            serverErrors.push(`${response.status()} ${response.url()}`);
        }
    });

    return {
        consoleErrors,
        pageErrors,
        serverErrors,
        assertZeroErrors: () => {
            expect(pageErrors, `Uncaught page errors:\n${pageErrors.join('\n')}`).toEqual([]);
            expect(serverErrors, `HTTP 5xx responses:\n${serverErrors.join('\n')}`).toEqual([]);
            expect(consoleErrors, `Browser console errors:\n${consoleErrors.join('\n')}`).toEqual([]);
        },
    };
}

/**
 * Login melalui formulir Filament /admin/login secara nyata.
 */
export async function loginViaFilament(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/admin/login', { waitUntil: 'networkidle' });

    const emailInput = page.locator('input[type="email"], input[id*="email"]').first();
    const passwordInput = page.locator('input[type="password"], input[id*="password"]').first();

    await emailInput.waitFor({ state: 'visible' });
    await emailInput.fill(email);
    await passwordInput.fill(password);

    const submitButton = page.locator('form button[type="submit"]').first();
    await Promise.all([
        page.waitForURL((url) => !url.pathname.endsWith('/admin/login'), { timeout: 45_000 }),
        submitButton.click(),
    ]);

    await page.waitForLoadState('networkidle');
}

/**
 * Audit aksesibilitas WCAG 2.0 / 2.1 Level AA berbasis @axe-core/playwright.
 * Melaporkan pelanggaran kritis & serius dengan detail elemen agar langsung actionable.
 */
export async function auditWcagAA(
    page: Page,
    contextLabel: string,
    options?: { exclude?: string[] },
): Promise<void> {
    let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']);

    for (const selector of options?.exclude ?? []) {
        builder = builder.exclude(selector);
    }

    const results = await builder.analyze();
    const criticalOrSerious = results.violations.filter(
        (v) => v.impact === 'critical' || v.impact === 'serious',
    );

    if (criticalOrSerious.length > 0) {
        const formatted = criticalOrSerious
            .map(
                (v) =>
                    `[${v.impact?.toUpperCase()}] ${v.id}: ${v.help} (${v.helpUrl})\n` +
                    v.nodes
                        .slice(0, 3)
                        .map((n) => `  - target: ${n.target.join(', ')} | ${n.failureSummary}`)
                        .join('\n'),
            )
            .join('\n\n');

        expect(
            criticalOrSerious,
            `WCAG AA critical/serious violations on ${contextLabel}:\n\n${formatted}`,
        ).toEqual([]);
    }
}
