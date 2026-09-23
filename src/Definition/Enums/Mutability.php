<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * What a state does to a field: it can be written (`editable`), read but not written
 * (`readonly`), or frozen (`locked` — drawn, carried along, not written).
 */
enum Mutability: string
{
    case Readonly = 'readonly';
    case Editable = 'editable';
    case Locked = 'locked';
}
