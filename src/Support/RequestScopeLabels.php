<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\HasFieldLabels;
use RoBYCoNTe\FilamentFlow\Contracts\HasFieldPresentation;
use RoBYCoNTe\FilamentFlow\Presentation\DefaultFieldPresenter;
use RoBYCoNTe\FilamentFlow\Presentation\FieldPresentation;

/**
 * The fields a request opened, called by the words a person reads: the host that knows its own
 * fields is asked first, and the generic reading covers what remains. A field the host keeps out
 * of every reading (a hidden presentation) is left out.
 */
final class RequestScopeLabels
{
    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    public static function for(Model $record, array $paths): array
    {
        $labels = [];

        foreach ($paths as $path) {
            $presentation = $record instanceof HasFieldPresentation ? $record->fieldPresentation($path, null) : null;

            if (! $presentation instanceof FieldPresentation) {
                $label = $record instanceof HasFieldLabels ? $record->fieldLabel($path) : null;
                $presentation = app(DefaultFieldPresenter::class)->present($path, null, is_string($label) ? $label : null);
            }

            if ($presentation->visible) {
                $labels[] = $presentation->label;
            }
        }

        return $labels;
    }
}
