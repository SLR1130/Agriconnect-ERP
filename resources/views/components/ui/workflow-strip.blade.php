@props([
    'steps' => [],
    'label' => 'Operational workflow',
    'description' => null,
])

<section class="erp-workflow-strip" aria-label="{{ $label }}">
    <div class="erp-workflow-heading">
        <p class="erp-page-eyebrow">{{ $label }}</p>
        @if($description)<p class="erp-workflow-description">{{ $description }}</p>@endif
    </div>
    <ol class="erp-workflow-steps">
        @foreach($steps as $index => $step)
            <li class="erp-workflow-step">
                <span class="erp-workflow-number" aria-hidden="true">{{ $index + 1 }}</span>
                <span class="erp-workflow-label">{{ $step }}</span>
            </li>
        @endforeach
    </ol>
</section>
