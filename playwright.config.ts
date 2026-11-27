import { defineConfig, devices } from '@playwright/test';
import { buildIsolatedLaravelEnv, getE2EConfig } from './tests/e2e/support/env';

const e2eConfig = getE2EConfig();
const isolatedEnv = buildIsolatedLaravelEnv(e2eConfig);

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 60_000,
    expect: {
        timeout: 15_000,
    },
    // Serialisasi worker saat memakai SQLite lokal untuk mencegah lock contention pada operasi CRUD paralel.
    fullyParallel: false,
    workers: process.env.CI ? 1 : 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ],
    globalSetup: './tests/e2e/global-setup.ts',
    use: {
        baseURL: e2eConfig.baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        actionTimeout: 15_000,
        navigationTimeout: 45_000,
    },
    webServer: e2eConfig.isExternalTarget
        ? undefined
        : {
              command: `${e2eConfig.phpBin} -d max_execution_time=120 -S ${e2eConfig.host}:${e2eConfig.port} -t public tests/e2e/support/server-router.php`,
              url: `${e2eConfig.baseURL}/up`,
              reuseExistingServer: false,
              timeout: 60_000,
              env: isolatedEnv,
          },
    projects: [
        {
            name: 'setup',
            testMatch: /auth\.setup\.ts/,
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
            },
        },
        {
            name: 'desktop-chromium',
            testMatch: /specs\/.*\.spec\.ts/,
            dependencies: ['setup'],
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
            },
        },
        {
            name: 'tablet-chromium',
            testMatch: /specs\/03-theme-and-accessibility\.spec\.ts/,
            dependencies: ['setup'],
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 768, height: 1024 },
                hasTouch: true,
            },
        },
    ],
});
