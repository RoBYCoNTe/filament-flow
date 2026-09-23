<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * Whether a state shows a field at all: `hidden` drops it from the form and from the read-only
 * view, and its value is not touched.
 */
enum Visibility: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
}
