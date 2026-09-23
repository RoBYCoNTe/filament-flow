<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * How the access rules of a state are combined: `or`, where any rule that holds is enough, or
 * `and`, where all of them have to.
 */
enum AccessOperator: string
{
    case Or = 'or';
    case And = 'and';
}
