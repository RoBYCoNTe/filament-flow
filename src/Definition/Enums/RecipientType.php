<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

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
