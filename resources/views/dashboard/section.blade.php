<x-layouts.dashboard :title="$title" :heading="$title">
    <x-section-scaffold :title="$title" :part="$part ?? 'the next release'">
        {{ $body ?? '' }}
    </x-section-scaffold>
</x-layouts.dashboard>
