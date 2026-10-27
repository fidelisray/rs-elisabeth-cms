<div x-show="password.length > 0" x-cloak class="rse-pw-strength-card">
    <div class="rse-pw-strength-head">
        <span class="rse-pw-strength-title">Kekuatan Password:</span>
        <div class="rse-pw-strength-meta">
            <span class="rse-pw-strength-ratio" x-text="score + '/4 kriteria'"></span>
            <span
                class="rse-pw-strength-badge"
                :class="{
                    'rse-pw-badge-weak': score <= 1,
                    'rse-pw-badge-medium': score === 2,
                    'rse-pw-badge-strong': score === 3,
                    'rse-pw-badge-very-strong': score === 4,
                }"
                x-text="label"
            ></span>
        </div>
    </div>

    <div
        class="rse-pw-bar-track"
        role="progressbar"
        aria-label="Tingkat kekuatan password"
        aria-valuemin="0"
        aria-valuemax="4"
        :aria-valuenow="score"
    >
        <div
            class="rse-pw-bar-fill"
            :class="{
                'rse-pw-fill-weak': score <= 1,
                'rse-pw-fill-medium': score === 2,
                'rse-pw-fill-strong': score === 3,
                'rse-pw-fill-very-strong': score === 4,
            }"
            :style="'width: ' + (score === 0 ? 10 : score * 25) + '%;'"
        ></div>
    </div>

    <div class="rse-pw-criteria-grid">
        <div class="rse-pw-criterion" :class="lengthValid ? 'rse-pw-criterion-valid' : ''">
            <template x-if="lengthValid">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;">
                    <circle cx="8" cy="8" r="7" fill="currentColor" fill-opacity="0.18"/>
                    <path d="M5 8.2L7 10.2L11.2 5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </template>
            <template x-if="!lengthValid">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;">
                    <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.5 2.5"/>
                </svg>
            </template>
            <span>Minimal 8 karakter</span>
        </div>

        <div class="rse-pw-criterion" :class="hasUpperAndLower ? 'rse-pw-criterion-valid' : ''">
            <template x-if="hasUpperAndLower">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;">
                    <circle cx="8" cy="8" r="7" fill="currentColor" fill-opacity="0.18"/>
                    <path d="M5 8.2L7 10.2L11.2 5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </template>
            <template x-if="!hasUpperAndLower">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;">
                    <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.5 2.5"/>
                </svg>
            </template>
            <span>Huruf besar & huruf kecil</span>
        </div>

        <div class="rse-pw-criterion" :class="hasNumber ? 'rse-pw-criterion-valid' : ''">
            <template x-if="hasNumber">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;">
                    <circle cx="8" cy="8" r="7" fill="currentColor" fill-opacity="0.18"/>
                    <path d="M5 8.2L7 10.2L11.2 5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </template>
            <template x-if="!hasNumber">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;">
                    <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.5 2.5"/>
                </svg>
            </template>
            <span>Mengandung angka (0-9)</span>
        </div>

        <div class="rse-pw-criterion" :class="hasSpecial ? 'rse-pw-criterion-valid' : ''">
            <template x-if="hasSpecial">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;">
                    <circle cx="8" cy="8" r="7" fill="currentColor" fill-opacity="0.18"/>
                    <path d="M5 8.2L7 10.2L11.2 5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </template>
            <template x-if="!hasSpecial">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                     style="width: 16px; height: 16px; min-width: 16px; max-width: 16px; flex-shrink: 0;">
                    <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.5 2.5"/>
                </svg>
            </template>
            <span>Mengandung simbol (!@#$...)</span>
        </div>
    </div>
</div>
