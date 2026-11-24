<?php

use App\Support\Applications\IdDocumentTypes;

test('labels name the type, and others shows what the player wrote', function () {
    expect(IdDocumentTypes::label('student_id'))->toBe('Student ID')
        ->and(IdDocumentTypes::label('drivers_license'))->toBe("Driver's License")
        ->and(IdDocumentTypes::label('others', 'Postal ID'))->toBe('Others: Postal ID')
        ->and(IdDocumentTypes::label('others'))->toBe('Others')
        ->and(IdDocumentTypes::label(null))->toBe('Valid ID document');
});
