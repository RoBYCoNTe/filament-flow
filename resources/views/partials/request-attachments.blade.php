@props(['documents' => []])

{{--
    The documents the requester attached to a request, one link each. The host decides where a link
    leads and who may follow it (`StoresRequestAttachments::documents()`); here they are only
    listed, so the banner of a page and the block of a form read them the same way.
--}}
@if ($documents !== [])
    <div data-request-attachments class="space-y-1">
        <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">
            {{ trans_choice('filament-flow::messages.open_requests_documents', count($documents), ['count' => count($documents)]) }}
        </p>

        <ul class="space-y-1">
            @foreach ($documents as $document)
                <li>
                    <a
                        href="{{ $document['url'] }}"
                        target="_blank"
                        rel="noopener"
                        data-request-attachment
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 underline hover:text-primary-500 dark:text-primary-400"
                    >
                        <x-filament::icon icon="heroicon-m-document-arrow-down" class="h-4 w-4 shrink-0" />
                        {{ $document['name'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
