<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * Who receives a notification: a role, a person, whoever triggered it, the people assigned, the
 * owner of the record, the actors of a state, everyone involved — or whoever the host answers
 * with, through a field, a query or a class of its own.
 */
enum RecipientType: string
{
    case Role = 'role';
    case User = 'user';
    case TriggerUser = 'trigger_user';
    case AssignedUsers = 'assigned_users';
    case RecordOwner = 'record_owner';
    case StateActors = 'state_actors';
    case AllInvolved = 'all_involved';
    case InvolvementType = 'involvement_type';
    case CustomField = 'custom_field';
    case CustomQuery = 'custom_query';
    case CustomClass = 'custom_class';
}
