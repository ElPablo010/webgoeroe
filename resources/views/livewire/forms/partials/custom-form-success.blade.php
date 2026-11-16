<div class="rounded-2xl border border-white/[0.08] bg-white/[0.04] p-8 text-center" role="status">
    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-cyan-400/20 bg-cyan-400/[0.10]">
        <svg class="h-7 w-7 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
    </div>
    <h3 class="text-lg font-bold text-white">Aanvraag ontvangen!</h3>
    <p class="mt-2 text-sm text-white/50">{{ $sentMessage ?: 'We nemen zo snel mogelijk contact met je op.' }}</p>
</div>
