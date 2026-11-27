import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { buildIsolatedLaravelEnv, getE2EConfig } from './support/env';

export default async function globalSetup(): Promise<void> {
    const config = getE2EConfig();
    fs.mkdirSync(config.authDir, { recursive: true });

    if (config.isExternalTarget) {
        console.log(`[Playwright] External target mode active (${config.baseURL}); skipping isolated DB bootstrap.`);
        return;
    }

    const env = buildIsolatedLaravelEnv(config);

    // Bersihkan cache khusus E2E jika tersisa dari run sebelumnya.
    for (const cacheKey of ['APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE'] as const) {
        const filePath = env[cacheKey];
        if (filePath && fs.existsSync(filePath)) {
            fs.rmSync(filePath, { force: true });
        }
    }

    const bootstrapScript = path.resolve(process.cwd(), 'tests/e2e/support/bootstrap-e2e.php');
    console.log(`[Playwright] Bootstrapping isolated E2E SQLite database at ${config.e2eDbPath}...`);

    execFileSync(config.phpBin, [bootstrapScript], {
        cwd: process.cwd(),
        env,
        stdio: 'inherit',
    });
}
