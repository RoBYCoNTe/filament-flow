<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * What makes a notification leave: a transition, entering or leaving a state, an assignment, or
 * a change in a field.
 */
enum NotificationTrigger: string
{
    case OnTransition = 'on_transition';
    case OnStateEnter = 'on_state_enter';
    case OnStateExit = 'on_state_exit';
    case OnAssignment = 'on_assignment';
    case OnFieldChange = 'on_field_change';
}
