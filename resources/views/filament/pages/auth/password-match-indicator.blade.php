<div x-show="confirmation.length > 0" x-cloak class="mt-1.5 text-xs">
    <!-- Saat Cocok Persis -->
    <div x-show="confirmation === password && password.length > 0" class="flex items-center gap-1.5 font-medium text-emerald-600 dark:text-emerald-400">
        <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
        <span>Password cocok</span>
    </div>

    <!-- Saat Belum Cocok -->
    <div x-show="confirmation !== password" class="flex items-center gap-1.5 font-medium text-red-600 dark:text-red-400">
        <svg class="h-4 w-4 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        <span>Password tidak cocok</span>
    </div>
</div>
