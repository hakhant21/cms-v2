@php
    use App\Support\SectionData;
    use Illuminate\Support\Facades\View;

    $section = SectionData::make($data);
    $layout = $section->layout();
    $view = 'blocks.sections.'.$layout;
@endphp

@if (View::exists($view))
    @include($view, ['section' => $section])
@else
    @include('blocks.sections.plain', ['section' => $section])
@endif
