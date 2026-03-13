<?php

namespace Williamug\Audited\Enums;

enum AuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';
    case FailedLogin = 'failed_login';
    case PasswordChange = 'password_change';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Approve = 'approve';
    case Reject = 'reject';
    case Export = 'export';
    case ViewReport = 'view_report';

    /**
     * Human-readable label for display in UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Login => 'Login',
            self::Logout => 'Logout',
            self::FailedLogin => 'Failed Login',
            self::PasswordChange => 'Password Change',
            self::Create => 'Create',
            self::Update => 'Update',
            self::Delete => 'Delete',
            self::Approve => 'Approve',
            self::Reject => 'Reject',
            self::Export => 'Export',
            self::ViewReport => 'View Report',
        };
    }

    /**
     * Tailwind CSS badge classes for display in UI, including dark mode variants.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Login => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            self::Logout => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
            self::FailedLogin => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            self::PasswordChange => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            self::Create => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            self::Update => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
            self::Delete => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            self::Approve => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            self::Reject => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            self::Export => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
            self::ViewReport => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
        };
    }
}
