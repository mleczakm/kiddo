<?php

declare(strict_types=1);

namespace App\Entity;

enum ConsentSource: string
{
    case REGISTRATION = 'registration';
    case CHECKOUT = 'checkout';
    case BOOKING_MODAL = 'booking_modal';
    case PROFILE = 'profile';
    case NEWSLETTER_FORM = 'newsletter_form';
    case CHAT_ONBOARDING = 'chat_onboarding';
    case CHILD_FORM = 'child_form';
    case ADMIN_OFFLINE = 'admin_offline';
    case TERMS_REACCEPT = 'terms_reaccept';
    case ACCOUNT_SETTINGS = 'account_settings';
}
