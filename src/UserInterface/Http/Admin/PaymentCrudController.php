<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\Payment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Payment>
 */
class PaymentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Payment')
            ->setEntityLabelInPlural('Payments')
            ->setSearchFields(['id', 'status', 'user.email'])
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->showEntityActionsInlined();
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->hideOnForm(),
            AssociationField::new('user')
                ->setCrudController(UserCrudController::class),
            TextField::new('amount'),
            ChoiceField::new('status')
                ->setChoices([
                    'Pending' => 'pending',
                    'Paid' => 'paid',
                    'Failed' => 'failed',
                    'Refunded' => 'refunded',
                    'Expired' => 'expired',
                ]),
            AssociationField::new('bookings')
                ->hideOnForm(),
            AssociationField::new('transfers')
                ->hideOnForm(),
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('paidAt', 'Paid At'),
        ];
    }
}
