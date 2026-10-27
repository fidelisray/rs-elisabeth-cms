<div x-show="confirmation.length > 0" x-cloak class="rse-pw-match-wrap" aria-live="polite">
    <div
        x-show="confirmation === password && password.length > 0"
        class="rse-pw-match-card rse-pw-match-ok"
    >
        <span class="rse-pw-match-icon" aria-hidden="true">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                 style="width: 14px; height: 14px; flex-shrink: 0;">
                <path d="M4.5 8.2L6.8 10.5L11.5 5.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
        <div>
            <p class="rse-pw-match-title">Password cocok</p>
            <p class="rse-pw-match-desc">Konfirmasi password sudah sesuai dengan password baru yang Anda masukkan.</p>
        </div>
    </div>

    <div
        x-show="confirmation !== password"
        class="rse-pw-match-card rse-pw-match-err"
    >
        <span class="rse-pw-match-icon" aria-hidden="true">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"
                 style="width: 14px; height: 14px; flex-shrink: 0;">
                <path d="M5.2 5.2L10.8 10.8M10.8 5.2L5.2 10.8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </span>
        <div>
            <p class="rse-pw-match-title">Password belum cocok</p>
            <p class="rse-pw-match-desc">Ketikan ulang belum sama dengan password baru di atas. Periksa kembali karakter yang dimasukkan.</p>
        </div>
    </div>
</div>
