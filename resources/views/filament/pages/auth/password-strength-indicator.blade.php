<div x-show="password.length > 0" x-cloak class="mt-2 space-y-3 rounded-lg border border-gray-200 bg-gray-50/50 p-3.5 dark:border-white/10 dark:bg-white/5">
    <!-- Header: Label Kekuatan Password -->
    <div class="flex items-center justify-between text-xs">
        <span class="font-medium text-gray-600 dark:text-gray-300">Tingkat Kekuatan Password:</span>
        <span class="font-bold uppercase tracking-wider text-xs"
              :class="{
                  'text-red-600 dark:text-red-400': score <= 1,
                  'text-amber-600 dark:text-amber-400': score === 2,
                  'text-blue-600 dark:text-blue-400': score === 3,
                  'text-emerald-600 dark:text-emerald-400': score === 4,
              }"
              x-text="label"></span>
    </div>

    <!-- 4 Bar Segmen Progres Kekuatan -->
    <div class="grid grid-cols-4 gap-1.5">
        <div class="h-1.5 rounded-full transition-all duration-300"
             :class="score >= 1 ? (score === 1 ? 'bg-red-500' : (score === 2 ? 'bg-amber-500' : (score === 3 ? 'bg-blue-500' : 'bg-emerald-500'))) : 'bg-gray-200 dark:bg-gray-700'"></div>
        <div class="h-1.5 rounded-full transition-all duration-300"
             :class="score >= 2 ? (score === 2 ? 'bg-amber-500' : (score === 3 ? 'bg-blue-500' : 'bg-emerald-500')) : 'bg-gray-200 dark:bg-gray-700'"></div>
        <div class="h-1.5 rounded-full transition-all duration-300"
             :class="score >= 3 ? (score === 3 ? 'bg-blue-500' : 'bg-emerald-500') : 'bg-gray-200 dark:bg-gray-700'"></div>
        <div class="h-1.5 rounded-full transition-all duration-300"
             :class="score >= 4 ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-700'"></div>
    </div>

    <!-- Checklist Kriteria Interaktif -->
    <div class="grid grid-cols-1 gap-1.5 pt-1 text-xs sm:grid-cols-2">
        <!-- 1. Minimal 8 Karakter -->
        <div class="flex items-center gap-1.5"
             :class="lengthValid ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400'">
            <template x-if="lengthValid">
                <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </template>
            <template x-if="!lengthValid">
                <svg class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="12" cy="12" r="9" stroke-width="2"/>
                </svg>
            </template>
            <span>Minimal 8 karakter</span>
        </div>

        <!-- 2. Kombinasi Huruf Besar & Huruf Kecil -->
        <div class="flex items-center gap-1.5"
             :class="hasUpperAndLower ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400'">
            <template x-if="hasUpperAndLower">
                <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </template>
            <template x-if="!hasUpperAndLower">
                <svg class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="12" cy="12" r="9" stroke-width="2"/>
                </svg>
            </template>
            <span>Huruf besar & huruf kecil</span>
        </div>

        <!-- 3. Karakter Angka -->
        <div class="flex items-center gap-1.5"
             :class="hasNumber ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400'">
            <template x-if="hasNumber">
                <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </template>
            <template x-if="!hasNumber">
                <svg class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="12" cy="12" r="9" stroke-width="2"/>
                </svg>
            </template>
            <span>Mengandung angka (0-9)</span>
        </div>

        <!-- 4. Simbol Khusus -->
        <div class="flex items-center gap-1.5"
             :class="hasSpecial ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400'">
            <template x-if="hasSpecial">
                <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </template>
            <template x-if="!hasSpecial">
                <svg class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="12" cy="12" r="9" stroke-width="2"/>
                </svg>
            </template>
            <span>Mengandung simbol (!@#$...)</span>
        </div>
    </div>
</div>
