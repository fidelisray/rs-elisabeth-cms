<div class="space-y-4 text-sm">
    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 p-3">
        <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Waktu') }}</dt>
            <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">
                {{ optional($record->created_at)->format('d M Y, H:i:s') ?? '-' }}
            </dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Aksi') }}</dt>
            <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">{{ $record->action }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Aktor') }}</dt>
            <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">
                {{ filled($record->actor_email) ? $record->actor_email : __('Sistem / Konsol') }}
                <span class="font-normal text-slate-500 dark:text-slate-400">({{ $actorTypeLabel }})</span>
            </dd>
        </div>
        <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Subjek') }}</dt>
            <dd class="mt-0.5 font-semibold text-slate-900 dark:text-slate-100">
                {{ $subjectLabel }}
                @if (filled($record->subject_email))
                    <span class="block font-normal text-xs text-slate-500 dark:text-slate-400">{{ $record->subject_email }}</span>
                @endif
            </dd>
        </div>
    </dl>

    <div>
        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {{ __('Metadata Tersanitasi (JSON)') }}
        </p>
        <pre class="max-h-80 overflow-auto rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-900 p-3 text-xs leading-relaxed text-slate-100">{{ $prettyMetadata }}</pre>
    </div>
</div>
