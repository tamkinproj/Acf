<?php

namespace App\Modules\Aytam\Registration;

final class FieldTypes
{
    public const LABELS = [
        'short_text' => 'Short text', 'long_text' => 'Long text', 'number' => 'Number', 'date' => 'Date', 'dropdown' => 'Dropdown',
        'multiple_choice' => 'Multiple choice (pick one)', 'checkbox' => 'Checkboxes (pick any)', 'yes_no' => 'Yes / No',
        'file_upload' => 'File upload', 'photo' => 'Photo', 'address' => 'Address', 'phone' => 'Phone', 'email' => 'Email',
    ];

    public const CHOICE = ['dropdown', 'multiple_choice', 'checkbox'];
    public const FILE = ['file_upload', 'photo'];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function needsOptions(string $type): bool
    {
        return in_array($type, self::CHOICE, true);
    }

    public static function isFile(string $type): bool
    {
        return in_array($type, self::FILE, true);
    }
}
