<div>
    @if ($sent)
        <div class="rounded-2xl border border-white/[0.08] bg-white/[0.04] p-8 text-center" role="status">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-cyan-400/20 bg-cyan-400/[0.10]">
                <svg class="h-7 w-7 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-white">Bericht ontvangen!</h3>
            <p class="mt-2 text-sm text-white/50">We nemen zo snel mogelijk contact met je op.</p>
        </div>
    @else
        <form wire:submit="submit" class="space-y-4">
            {{-- Honeypot --}}
            <div class="hidden" aria-hidden="true">
                <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-white/60">Naam <span class="text-red-400">*</span></label>
                    <input
                        type="text"
                        wire:model="name"
                        placeholder="Jan Janssen"
                        class="w-full rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-3 text-sm text-white placeholder-white/20 focus:border-cyan-400/40 focus:outline-none focus:ring-2 focus:ring-cyan-400/10"
                    >
                    @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-white/60">E-mailadres <span class="text-red-400">*</span></label>
                    <input
                        type="email"
                        wire:model="email"
                        placeholder="jan@bedrijf.be"
                        class="w-full rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-3 text-sm text-white placeholder-white/20 focus:border-cyan-400/40 focus:outline-none focus:ring-2 focus:ring-cyan-400/10"
                    >
                    @error('email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-white/60">Telefoonnummer</label>
                <input
                    type="tel"
                    wire:model="phone"
                    @if ($phoneRequired) required autocomplete="tel" @endif
                    placeholder="+32 478 00 00 00"
                    class="w-full rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-3 text-sm text-white placeholder-white/20 focus:border-cyan-400/40 focus:outline-none focus:ring-2 focus:ring-cyan-400/10"
                >
                @error('phone') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-white/60">Bericht <span class="text-red-400">*</span></label>
                <textarea
                    wire:model="message"
                    rows="4"
                    placeholder="Vertel ons hoe we je kunnen helpen..."
                    class="w-full rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-3 text-sm text-white placeholder-white/20 focus:border-cyan-400/40 focus:outline-none focus:ring-2 focus:ring-cyan-400/10"
                ></textarea>
                @error('message') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="relative w-full cursor-pointer rounded-full bg-white px-6 py-3.5 text-sm font-semibold text-[#050507] transition-all hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <x-core::busy-spinner target="submit" />
                <span>Bericht versturen</span>
            </button>
        </form>
    @endif
</div>
