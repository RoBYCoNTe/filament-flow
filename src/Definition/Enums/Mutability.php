<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

enum Mutability: string
{
    case Readonly = 'readonly';
    case Editable = 'editable';
    case Locked = 'locked';
}
