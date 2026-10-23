<div x-show="password.length > 0" x-cloak class="mt-2 space-y-3.5 rounded-xl border border-gray-200/90 bg-gray-50/70 p-4 shadow-xs dark:border-white/10 dark:bg-white/[0.03]">
    <!-- Header: Label Kekuatan Password & Status Badge -->
    <div class="flex items-center justify-between">
        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Kekuatan Password:</span>
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wide transition-colors duration-200"
              :class="{
                  'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-400 dark:ring-rose-500/30': score <= 1,
                  'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-400 dark:ring-amber-500/30': score === 2,
                  'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20 dark:bg-sky-950/40 dark:text-sky-400 dark:ring-sky-500/30': score === 3,
                  'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-500/30': score === 4,
              }"
              x-text="label"></span>
    </div>

    <!-- 4 Bar Segmen Progres Kekuatan -->
    <div class="grid grid-cols-4 gap-1.5">
        <div class="h-1.5 rounded-full transition-all duration-300 ease-out"
             :class="score >= 1 ? (score === 1 ? 'bg-rose-500' : (score === 2 ? 'bg-amber-500' : (score === 3 ? 'bg-sky-500' : 'bg-emerald-500'))) : 'bg-gray-200 dark:bg-white/10'"></div>
        <div class="h-1.5 rounded-full transition-all duration-300 ease-out"
             :class="score >= 2 ? (score === 2 ? 'bg-amber-500' : (score === 3 ? 'bg-sky-500' : 'bg-emerald-500')) : 'bg-gray-200 dark:bg-white/10'"></div>
        <div class="h-1.5 rounded-full transition-all duration-300 ease-out"
             :class="score >= 3 ? (score === 3 ? 'bg-sky-500' : 'bg-emerald-500') : 'bg-gray-200 dark:bg-white/10'"></div>
        <div class="h-1.5 rounded-full transition-all duration-300 ease-out"
             :class="score >= 4 ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-white/10'"></div>
    </div>

    <!-- Checklist Kriteria Interaktif (2 Kolom) -->
    <div class="grid grid-cols-1 gap-2 pt-2 border-t border-gray-200/60 dark:border-white/5 sm:grid-cols-2">
        <!-- 1. Minimal 8 Karakter -->
        <div class="flex items-center gap-2 text-xs transition-colors duration-200"
             :class="lengthValid ? 'text-emerald-700 dark:text-emerald-400 font-medium' : 'text-gray-500 dark:text-gray-400'">
            <template x-if="lengthValid">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;"
                     class="text-emerald-600 dark:text-emerald-400">
                    <circle cx="8" cy="8" r="7" fill="currentColor" fill-opacity="0.15"/>
                    <path d="M5 8.2L7 10.2L11.2 5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </template>
            <template x-if="!lengthValid">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;"
                     class="text-gray-400 dark:text-gray-500">
                    <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.5 2.5"/>
                </svg>
            </template>
            <span>Minimal 8 karakter</span>
        </div>

        <!-- 2. Kombinasi Huruf Besar & Huruf Kecil -->
        <div class="flex items-center gap-2 text-xs transition-colors duration-200"
             :class="hasUpperAndLower ? 'text-emerald-700 dark:text-emerald-400 font-medium' : 'text-gray-500 dark:text-gray-400'">
            <template x-if="hasUpperAndLower">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;"
                     class="text-emerald-600 dark:text-emerald-400">
                    <circle cx="8" cy="8" r="7" fill="currentColor" fill-opacity="0.15"/>
                    <path d="M5 8.2L7 10.2L11.2 5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </template>
            <template x-if="!hasUpperAndLower">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;"
                     class="text-gray-400 dark:text-gray-500">
                    <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.5 2.5"/>
                </svg>
            </template>
            <span>Huruf besar & huruf kecil</span>
        </div>

        <!-- 3. Karakter Angka -->
        <div class="flex items-center gap-2 text-xs transition-colors duration-200"
             :class="hasNumber ? 'text-emerald-700 dark:text-emerald-400 font-medium' : 'text-gray-500 dark:text-gray-400'">
            <template x-if="hasNumber">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;"
                     class="text-emerald-600 dark:text-emerald-400">
                    <circle cx="8" cy="8" r="7" fill="currentColor" fill-opacity="0.15"/>
                    <path d="M5 8.2L7 10.2L11.2 5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </template>
            <template x-if="!hasNumber">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;"
                     class="text-gray-400 dark:text-gray-500">
                    <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.5 2.5"/>
                </svg>
            </template>
            <span>Mengandung angka (0-9)</span>
        </div>

        <!-- 4. Simbol Khusus -->
        <div class="flex items-center gap-2 text-xs transition-colors duration-200"
             :class="hasSpecial ? 'text-emerald-700 dark:text-emerald-400 font-medium' : 'text-gray-500 dark:text-gray-400'">
            <template x-if="hasSpecial">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;"
                     class="text-emerald-600 dark:text-emerald-400">
                    <circle cx="8" cy="8" r="7" fill="currentColor" fill-opacity="0.15"/>
                    <path d="M5 8.2L7 10.2L11.2 5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </template>
            <template x-if="!hasSpecial">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;"
                     class="text-gray-400 dark:text-gray-500">
                    <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.5 2.5"/>
                </svg>
            </template>
            <span>Mengandung simbol (!@#$...)</span>
        </div>
    </div>
</div>
