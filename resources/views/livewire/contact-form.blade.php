<div class="contact-form">
    @if ($sent)
        <div class="flex flex-col items-start gap-4 rounded-3xl border border-green-200 bg-white p-8 shadow-sm" role="status" data-reveal="zoom">
            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-green-50 text-green-700">
                <x-site.icon name="check-circle" class="h-7 w-7" />
            </span>

            <div>
                <h3 class="text-2xl font-black tracking-tight text-stone-900">Message sent</h3>
                <p class="mt-2 max-w-xl text-sm leading-7 text-stone-600">{{ $successMessage }}</p>
            </div>

            <button
                type="button"
                wire:click="$set('sent', false)"
                class="inline-flex items-center gap-2 rounded-full border border-green-700 px-5 py-2.5 text-sm font-semibold text-green-700 transition hover:bg-green-700 hover:text-white"
            >
                Send another message
            </button>
        </div>
    @else
        <form
            wire:submit="submit"
            class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8"
            novalidate
        >
            @error('form')
                <p class="mb-5 flex items-start gap-2 rounded-2xl bg-red-50 p-4 text-sm text-red-700">
                    <x-site.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
                    {{ $message }}
                </p>
            @enderror

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="contact-name">Your name</label>
                    <input
                        id="contact-name"
                        type="text"
                        class="form-input"
                        wire:model.blur="name"
                        autocomplete="name"
                        placeholder="Daw Aye Aye"
                        {{ $enabled ? '' : 'disabled' }}
                    >
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label" for="contact-email">Email</label>
                    <input
                        id="contact-email"
                        type="email"
                        class="form-input"
                        wire:model.blur="email"
                        autocomplete="email"
                        placeholder="you@example.com"
                        {{ $enabled ? '' : 'disabled' }}
                    >
                    @error('email') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label" for="contact-phone">Phone <span class="font-normal text-stone-400">(optional)</span></label>
                    <input
                        id="contact-phone"
                        type="tel"
                        class="form-input"
                        wire:model.blur="phone"
                        autocomplete="tel"
                        placeholder="+95 9 000 000 000"
                        {{ $enabled ? '' : 'disabled' }}
                    >
                    @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label" for="contact-subject">Subject</label>
                    <input
                        id="contact-subject"
                        type="text"
                        class="form-input"
                        wire:model.blur="subject"
                        placeholder="Product enquiry"
                        {{ $enabled ? '' : 'disabled' }}
                    >
                    @error('subject') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="form-label" for="contact-message">Message</label>
                    <textarea
                        id="contact-message"
                        rows="6"
                        class="form-input resize-y"
                        wire:model.blur="message"
                        placeholder="Tell us about your farm, the crops you grow and what you need."
                        {{ $enabled ? '' : 'disabled' }}
                    ></textarea>
                    @error('message') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Honeypot: hidden from people, irresistible to bots. --}}
            <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label for="contact-website">Website</label>
                <input id="contact-website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-4">
                <button
                    type="submit"
                    class="btn-glow inline-flex items-center gap-2 rounded-full bg-green-700 px-7 py-3.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                    {{ $enabled ? '' : 'disabled' }}
                >
                    <span wire:loading.remove wire:target="submit" class="inline-flex items-center gap-2">
                        {{ __('ui.common.send_message') }}
                        <x-site.icon name="send" class="h-4 w-4" />
                    </span>
                    <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                        Sending…
                    </span>
                </button>

                @if (! $enabled)
                    <p class="text-sm text-stone-500">The form is closed at the moment. Please email us instead.</p>
                @else
                    <p class="text-xs text-stone-500">We reply within one business day.</p>
                @endif
            </div>
        </form>
    @endif
</div>
