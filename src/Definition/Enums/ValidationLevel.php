<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * How much a transition has to be validated.
 *
 * A step of the flow can be a "save and continue later" action: it must accept
 * whatever the user typed so far, and only the checks the form itself performs on
 * the fields (types, formats). The transitions that move the record on, instead,
 * have to satisfy every rule of the state and of the transition.
 */
enum ValidationLevel: string
{
    /** No rule at all: the record is saved as it is (a draft). */
    case None = 'none';

    /** Only the rules of the fields themselves (type, format): no required, no workflow rules. */
    case Semantic = 'semantic';

    /** Everything: the rules of the state, of the transition and of the host fields. */
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::None => __('No validation'),
            self::Semantic => __('Field rules only'),
            self::Full => __('Every rule'),
        };
    }

    public function requiresWorkflowRules(): bool
    {
        return $this === self::Full;
    }
}
