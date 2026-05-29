<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Editor = 'editor';
    case Journalist = 'journalist';
    case SeoManager = 'seo_manager';
    case AiReviewer = 'ai_reviewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Editor => 'Editor',
            self::Journalist => 'Journalist',
            self::SeoManager => 'SEO Manager',
            self::AiReviewer => 'AI Reviewer',
        };
    }

    public static function adminRoles(): array
    {
        return [
            self::SuperAdmin->value,
            self::Editor->value,
            self::Journalist->value,
            self::SeoManager->value,
            self::AiReviewer->value,
        ];
    }
}
