<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Enums;

/**
 * Where a notification goes: into the database, where the panel shows it, or by mail.
 */
enum NotificationChannel: string
{
    case Database = 'database';
    case Mail = 'mail';
}
