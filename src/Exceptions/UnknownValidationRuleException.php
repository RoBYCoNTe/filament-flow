<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use RuntimeException;

/**
 * Thrown when a declaration names a validation rule nobody registered: a typo has to shout,
 * rather than pass as a rule that is always satisfied.
 */
class UnknownValidationRuleException extends RuntimeException {}
