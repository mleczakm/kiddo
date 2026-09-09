<?php

declare(strict_types=1);

namespace App\Entity;

enum ConsentType: string
{
    case APP_TERMS = 'app_terms';
    case PRIVACY = 'privacy';
    case CLASSES_TERMS = 'classes_terms';
    case WITHDRAWAL_INFO_ACK = 'withdrawal_info_ack';
    case MARKETING_EMAIL = 'marketing_email';
    case AI_USAGE = 'ai_usage';
    case CHILD_DATA_GUARDIAN = 'child_data_guardian';
    case CONTENT_LICENSE = 'content_license';
    case ACCOUNT_DELETION = 'account_deletion';

    public function documentType(): ?LegalDocumentType
    {
        return match ($this) {
            self::APP_TERMS => LegalDocumentType::APP_TERMS,
            self::PRIVACY => LegalDocumentType::PRIVACY,
            self::CLASSES_TERMS => LegalDocumentType::CLASSES_TERMS_GENERAL,
            default => null,
        };
    }
}
