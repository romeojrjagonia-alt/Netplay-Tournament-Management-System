<?php

namespace App\Support\Applications;

/**
 * The kinds of ID a player can upload with an application. "Others" needs
 * the player to say what the document is, stored in `id_document_other`.
 */
final class IdDocumentTypes
{
    public const OTHERS = 'others';

    /**
     * @var array<string, string>
     */
    public const TYPES = [
        'national_id' => 'Philippine National ID',
        'passport' => 'Passport',
        'birth_certificate' => 'Birth Certificate',
        'student_id' => 'Student ID',
        'drivers_license' => "Driver's License",
        self::OTHERS => 'Others',
    ];

    /**
     * The label shown to staff: the type's name, or what the player wrote
     * for "Others".
     */
    public static function label(?string $type, ?string $other = null): string
    {
        if ($type === self::OTHERS) {
            return filled($other) ? 'Others: '.$other : 'Others';
        }

        return self::TYPES[$type] ?? 'Valid ID document';
    }
}
