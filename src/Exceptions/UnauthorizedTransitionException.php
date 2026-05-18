<?php

namespace RoBYCoNTe\FilamentFlow\Exceptions;

use Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * Exception thrown when a user attempts a state transition they are not authorized to perform.
 */
class UnauthorizedTransitionException extends Exception
{
    public function __construct(
        public readonly Model $record,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly ?Model $user = null,
        string $message = ''
    ) {
        if (empty($message)) {
            $message = $this->buildMessage();
        }

        parent::__construct($message);
    }

    /**
     * Build the default exception message.
     */
    protected function buildMessage(): string
    {
        $recordType = class_basename($this->record);
        $recordId = $this->record->getKey();
        $fromState = class_basename($this->fromState);
        $toState = class_basename($this->toState);

        if ($this->user) {
            $userId = $this->user->getKey();

            return "User #$userId is not authorized to transition $recordType #$recordId from '$fromState' to '$toState'.";
        }

        return "Unauthorized transition of $recordType #$recordId from '$fromState' to '$toState'. No authenticated user.";
    }

    /**
     * Get the record that was being transitioned.
     */
    public function getRecord(): Model
    {
        return $this->record;
    }

    /**
     * Get the source state.
     */
    public function getFromState(): string
    {
        return $this->fromState;
    }

    /**
     * Get the target state.
     */
    public function getToState(): string
    {
        return $this->toState;
    }

    /**
     * Get the user who attempted the transition.
     */
    public function getUser(): ?Model
    {
        return $this->user;
    }
}
