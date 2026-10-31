@php
    use App\Support\SectionData;

    $anchor = $section->anchor();

    /* The heading component owns the opening copy, including the first
       paragraph. What is left over becomes a card, because a page of
       full-width paragraphs reads as a wall of text — two cards side by side
       break it up, and the reveal animation gives each one its own entrance as
       the visitor scrolls. */
    $prose = [];
    $leadTaken = false;

    foreach ($section->fields() as $field) {
        $type = (string) ($field['type'] ?? '');

        if (! in_array($type, ['paragraph', 'rich_text'], true)) {
            continue;
        }

        if (trim(strip_tags((string) ($field['value'] ?? ''))) === '') {
            continue;
        }

        // The first plain paragraph is the section's lead sentence.
        if ($type === 'paragraph' && ! $leadTaken) {
            $leadTaken = true;

            continue;
        }

        $prose[] = $field;
    }

    /* Everything that is not copy, a heading or one of the statement slots:
       images, galleries, videos, lists, buttons. */
    $body = array_values(array_filter(
        $section->fields(),
        fn (array $field): bool => ! in_array(
            $field['type'],
            ['eyebrow', 'title', 'subtitle', 'paragraph', 'rich_text', 'profile', 'mission', 'vision'],
            true,
        ),
    ));

    $statements = array_filter($section->statements());

    $hasHeading = $section->value('eyebrow')
        || $section->value('title')
        || $section->value('subtitle')
        || $section->value('paragraph');
@endphp

<section
    @if ($anchor !== '') id="{{ $anchor }}" @endif
    class="scroll-mt-24 py-12 sm:py-16"
>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl">
            @if ($hasHeading)
                <x-site.section-heading :section="$section" width="4xl" />
            @endif

            @if ($prose !== [])
                <div
                    @class([
                        'mt-9 grid gap-5',
                        'sm:grid-cols-2' => count($prose) > 1,
                    ])
                    data-reveal-group
                >
                    @foreach ($prose as $field)
                        <article class="content-card p-7 sm:p-8" data-reveal>
                            @if ($field['type'] === 'rich_text')
                                <div class="prose prose-stone max-w-none prose-p:leading-8">
                                    {!! rich_text(is_string($field['value'] ?? null) ? $field['value'] : null) !!}
                                </div>
                            @else
                                <p class="text-base leading-8 text-stone-700">{{ $field['value'] }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif

            @foreach ($body as $field)
                <div class="mt-8" data-reveal>
                    <x-site.section-field :field="$field" />
                </div>
            @endforeach

            @if ($statements !== [])
                <div class="mt-10 grid gap-4 sm:grid-cols-3" data-reveal-group>
                    @foreach ($statements as $label => $value)
                        <article class="content-card p-6" data-reveal>
                            <p class="content-card__index">{{ SectionData::STATEMENT_LABELS[$label] ?? ucfirst($label) }}</p>
                            <p class="mt-3 text-sm leading-7 text-stone-700">{{ $value }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
