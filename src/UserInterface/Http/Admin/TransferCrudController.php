<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\Transfer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends  AbstractCrudController<Transfer>
 */
class TransferCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Transfer::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Transfer')
            ->setEntityLabelInPlural('Transfers')
            ->setSearchFields(['id', 'title', 'sender', 'accountNumber'])
            ->setDefaultSort([
                'transferredAt' => 'DESC',
            ])
            ->showEntityActionsInlined();
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('title'),
            TextField::new('accountNumber', 'Account Number'),
            TextField::new('sender'),
            TextField::new('amount'),
            DateTimeField::new('transferredAt', 'Transfer Date'),
            AssociationField::new('payment')
                ->setCrudController(PaymentCrudController::class)
                ->setRequired(false),
        ];
    }
}
