{{-- Startformulier landingspagina "Speed to lead elektriciens" (App\Livewire\Forms\SpeedToLeadStartForm). --}}
<div>
    @if ($sent)
        <div class="rounded-2xl border border-white/[0.08] bg-white/[0.04] p-8 text-center" role="status">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-cyan-400/20 bg-cyan-400/[0.10]">
                <svg class="h-7 w-7 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-white">Bedankt!</h3>
            <p class="mt-2 text-sm text-white/50">We bellen je binnen de werkdag om alles samen op te zetten.</p>
        </div>
    @else
        <form wire:submit="submit" class="space-y-4">
            {{-- Honeypot --}}
            <div class="hidden" aria-hidden="true">
                <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="stl-name" class="mb-1.5 block text-sm font-medium text-white/60">Naam <span class="text-red-400">*</span></label>
                <input id="stl-name" type="text" wire:model="name" autocomplete="name" placeholder="Jan Janssen" class="w-full rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-3 text-sm text-white placeholder-white/20 focus:border-cyan-400/40 focus:outline-none focus:ring-2 focus:ring-cyan-400/10">
                @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="stl-company" class="mb-1.5 block text-sm font-medium text-white/60">Bedrijf <span class="text-red-400">*</span></label>
                <input id="stl-company" type="text" wire:model="company" autocomplete="organization" placeholder="Elektro Janssen" class="w-full rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-3 text-sm text-white placeholder-white/20 focus:border-cyan-400/40 focus:outline-none focus:ring-2 focus:ring-cyan-400/10">
                @error('company') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="stl-email" class="mb-1.5 block text-sm font-medium text-white/60">E-mailadres <span class="text-red-400">*</span></label>
                <input id="stl-email" type="email" wire:model="email" autocomplete="email" placeholder="jan@bedrijf.be" class="w-full rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-3 text-sm text-white placeholder-white/20 focus:border-cyan-400/40 focus:outline-none focus:ring-2 focus:ring-cyan-400/10">
                @error('email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="stl-phone" class="mb-1.5 block text-sm font-medium text-white/60">Gsm <span class="text-red-400">*</span></label>
                <input id="stl-phone" type="tel" wire:model="phone" autocomplete="tel" placeholder="+32 478 00 00 00" class="w-full rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-3 text-sm text-white placeholder-white/20 focus:border-cyan-400/40 focus:outline-none focus:ring-2 focus:ring-cyan-400/10">
                @error('phone') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="w-full cursor-pointer rounded-full bg-white px-6 py-3.5 text-sm font-semibold text-[#050507] transition-all hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span wire:loading.remove>Ik wil starten</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Versturen...
                </span>
            </button>

            <p class="text-center text-xs text-white/40">€297 per maand · Maandelijks opzegbaar · Geen contract</p>
        </form>
    @endif
</div>
