<main data-code-page="{{ $page->id }}" lang="{{ $currentLocale }}">{{ $translation->title }} — Blade content</main>
@foreach($templateData as $content)
    <div>{{ $content }}</div>
@endforeach
