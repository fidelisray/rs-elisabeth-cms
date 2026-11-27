import fs from 'node:fs';
import path from 'node:path';

export interface RoleCredentials {
    email: string;
    password: string;
}

export interface E2EConfig {
    isExternalTarget: boolean;
    baseURL: string;
    host: string;
    port: number;
    phpBin: string;
    e2eDbPath: string;
    authDir: string;
    storageStatePaths: {
        superAdmin: string;
        marketing: string;
        staffIt: string;
    };
    credentials: {
        superAdmin: RoleCredentials;
        marketing: RoleCredentials;
        staffIt: RoleCredentials;
        forcedPasswordChange: RoleCredentials;
    };
}

let envLoaded = false;

/**
 * Muat override opsional dari .env.playwright tanpa menimpa env yang sudah disetel di shell/CI.
 */
export function loadPlaywrightEnvFile(): void {
    if (envLoaded) {
        return;
    }
    envLoaded = true;

    const envFile = path.resolve(process.cwd(), '.env.playwright');
    if (!fs.existsSync(envFile)) {
        return;
    }

    const lines = fs.readFileSync(envFile, 'utf8').split(/\r?\n/);
    for (const rawLine of lines) {
        const line = rawLine.trim();
        if (line === '' || line.startsWith('#')) {
            continue;
        }
        const eqIdx = line.indexOf('=');
        if (eqIdx <= 0) {
            continue;
        }
        const key = line.slice(0, eqIdx).trim();
        let value = line.slice(eqIdx + 1).trim();
        if (
            (value.startsWith('"') && value.endsWith('"')) ||
            (value.startsWith("'") && value.endsWith("'"))
        ) {
            value = value.slice(1, -1);
        }
        if (process.env[key] === undefined) {
            process.env[key] = value;
        }
    }
}

export function getE2EConfig(): E2EConfig {
    loadPlaywrightEnvFile();

    const externalBaseUrl = (process.env.PLAYWRIGHT_BASE_URL ?? '').trim();
    const isExternalTarget = externalBaseUrl !== '';
    const host = process.env.PLAYWRIGHT_SERVER_HOST ?? '127.0.0.1';
    const port = Number(process.env.PLAYWRIGHT_SERVER_PORT ?? '8010');
    const baseURL = isExternalTarget
        ? externalBaseUrl.replace(/\/+$/, '')
        : `http://${host}:${port}`;

    const authDir = path.resolve(process.cwd(), 'tests/e2e/.auth');

    return {
        isExternalTarget,
        baseURL,
        host,
        port,
        phpBin: process.env.PLAYWRIGHT_PHP_BIN ?? 'php',
        e2eDbPath: path.resolve(process.cwd(), 'database/e2e.sqlite'),
        authDir,
        storageStatePaths: {
            superAdmin: path.join(authDir, 'super-admin.json'),
            marketing: path.join(authDir, 'marketing.json'),
            staffIt: path.join(authDir, 'staff-it.json'),
        },
        credentials: {
            superAdmin: {
                email: process.env.PLAYWRIGHT_SUPER_ADMIN_EMAIL ?? 'super-admin@e2e.local',
                password: process.env.PLAYWRIGHT_SUPER_ADMIN_PASSWORD ?? 'E2eSuperAdmin!2026',
            },
            marketing: {
                email: process.env.PLAYWRIGHT_MARKETING_EMAIL ?? 'marketing@e2e.local',
                password: process.env.PLAYWRIGHT_MARKETING_PASSWORD ?? 'E2eMarketing!2026',
            },
            staffIt: {
                email: process.env.PLAYWRIGHT_STAFF_IT_EMAIL ?? 'it@e2e.local',
                password: process.env.PLAYWRIGHT_STAFF_IT_PASSWORD ?? 'E2eStaffIt!2026',
            },
            forcedPasswordChange: {
                email: process.env.PLAYWRIGHT_FORCED_EMAIL ?? 'forced@e2e.local',
                password: process.env.PLAYWRIGHT_FORCED_PASSWORD ?? 'E2eForced!2026',
            },
        },
    };
}

/**
 * Variabel environment terisolasi untuk bootstrap PHP & server uji E2E.
 * Menggunakan path cache Laravel tersendiri agar tidak berbenturan dengan Laravel Herd lokal.
 */
export function buildIsolatedLaravelEnv(config: E2EConfig): Record<string, string> {
    const baseEnv: Record<string, string> = {};
    for (const [k, v] of Object.entries(process.env)) {
        if (typeof v === 'string') {
            baseEnv[k] = v;
        }
    }

    return {
        ...baseEnv,
        APP_ENV: 'playwright',
        APP_DEBUG: 'true',
        APP_URL: config.baseURL,
        APP_TIMEZONE: 'Asia/Jakarta',
        APP_CONFIG_CACHE: path.resolve(process.cwd(), 'storage/framework/e2e-config.php'),
        APP_ROUTES_CACHE: path.resolve(process.cwd(), 'storage/framework/e2e-routes.php'),
        APP_EVENTS_CACHE: path.resolve(process.cwd(), 'storage/framework/e2e-events.php'),
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: config.e2eDbPath,
        DB_URL: '',
        CACHE_STORE: 'database',
        SESSION_DRIVER: 'database',
        QUEUE_CONNECTION: 'sync',
        FILESYSTEM_DISK: 'local',
        MEDIA_DISK: 'public',
        DYNAMIC_FORM_PRIVATE_DISK: 'local',
        RS_API_ENABLED: 'false',
        WEB_FRONTEND_WEBHOOK_URL: '',
        WEB_FRONTEND_PUBLIC_URL: 'http://127.0.0.1:2301',
        BOOTSTRAP_MINIO_SETUP: 'false',
        BCRYPT_ROUNDS: '4',
        MAIL_MAILER: 'array',
        BROADCAST_CONNECTION: 'null',
        PLAYWRIGHT_E2E_BOOTSTRAP: '1',
    };
}
