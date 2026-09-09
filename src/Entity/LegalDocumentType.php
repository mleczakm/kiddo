<?php

declare(strict_types=1);

namespace App\Entity;

enum LegalDocumentType: string
{
    case APP_TERMS = 'app_terms';
    case PRIVACY = 'privacy';
    case CLASSES_TERMS_GENERAL = 'classes_terms_general';

    public function label(): string
    {
        return match ($this) {
            self::APP_TERMS => 'Regulamin aplikacji',
            self::PRIVACY => 'Polityka prywatności',
            self::CLASSES_TERMS_GENERAL => 'Regulamin zajęć',
        };
    }

    public function heading(): string
    {
        return match ($this) {
            self::APP_TERMS => 'Regulamin aplikacji Warsztatownia Sensoryczna',
            self::PRIVACY => 'Polityka prywatności Warsztatowni Sensorycznej',
            self::CLASSES_TERMS_GENERAL => 'Regulamin zajęć Warsztatowni Sensorycznej (ogólny)',
        };
    }

    public function slug(): string
    {
        return match ($this) {
            self::APP_TERMS => 'regulamin',
            self::PRIVACY => 'polityka-prywatnosci',
            self::CLASSES_TERMS_GENERAL => 'regulamin-zajec',
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::APP_TERMS => 'legal_app_terms',
            self::PRIVACY => 'legal_privacy',
            self::CLASSES_TERMS_GENERAL => 'legal_classes_terms',
        };
    }
}
