<div x-show="confirmation.length > 0" x-cloak class="mt-2 text-xs">
    <!-- Saat Cocok Persis -->
    <div x-show="confirmation === password && password.length > 0"
         class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-1.5 font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/30 dark:text-emerald-400 dark:ring-emerald-500/30">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
             style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;"
             class="text-emerald-600 dark:text-emerald-400">
            <circle cx="8" cy="8" r="7" fill="currentColor" fill-opacity="0.15"/>
            <path d="M5 8.2L7 10.2L11.2 5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>Password cocok</span>
    </div>

    <!-- Saat Belum Cocok -->
    <div x-show="confirmation !== password"
         class="inline-flex items-center gap-2 rounded-lg bg-rose-50 px-3 py-1.5 font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-950/30 dark:text-rose-400 dark:ring-rose-500/30">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
             style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;"
             class="text-rose-600 dark:text-rose-400">
            <circle cx="8" cy="8" r="7" fill="currentColor" fill-opacity="0.15"/>
            <path d="M5.5 5.5L10.5 10.5M10.5 5.5L5.5 10.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        <span>Password belum cocok</span>
    </div>
</div>
