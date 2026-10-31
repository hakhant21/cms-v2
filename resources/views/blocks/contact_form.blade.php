@php
    $title = filled($data['title'] ?? null) ? (string) $data['title'] : 'Send a Message';
@endphp

<section class="scroll-mt-24 bg-green-50 py-14 sm:py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16">
            <div data-reveal="left">
                <p class="content-eyebrow">Contact</p>
                <h2 class="mt-4 text-3xl font-black tracking-tight text-stone-900 sm:text-4xl">{{ $title }}</h2>

                <p class="mt-4 max-w-md text-base leading-8 text-stone-600">
                    Tell us about your farm, the crops you grow and the results you are looking for.
                    Our agronomy team reads every message.
                </p>

                <ul class="mt-8 space-y-4 text-sm text-stone-700">
                    @if (setting('company.phone'))
                        <li class="flex items-center gap-3">
                            <span class="contact-tile__icon">
                                <x-site.icon name="phone" class="h-4 w-4" />
                            </span>
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) setting('company.phone')) }}" class="font-semibold transition hover:text-green-700">
                                {{ setting('company.phone') }}
                            </a>
                        </li>
                    @endif
                    @if (setting('company.email'))
                        <li class="flex items-center gap-3">
                            <span class="contact-tile__icon">
                                <x-site.icon name="mail" class="h-4 w-4" />
                            </span>
                            <a href="mailto:{{ setting('company.email') }}" class="font-semibold transition hover:text-green-700">
                                {{ setting('company.email') }}
                            </a>
                        </li>
                    @endif
                    @if (setting('company.office_hours'))
                        <li class="flex items-center gap-3">
                            <span class="contact-tile__icon">
                                <x-site.icon name="clock" class="h-4 w-4" />
                            </span>
                            <span class="font-semibold">{{ setting('company.office_hours') }}</span>
                        </li>
                    @endif
                </ul>
            </div>

            <div data-reveal="right">
                <livewire:contact-form />
            </div>
        </div>
    </div>
</section>
