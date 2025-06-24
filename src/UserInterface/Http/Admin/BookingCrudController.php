<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\Booking;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

/**
 * @extends AbstractCrudController<Booking>
 */
class BookingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Booking::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Booking')
            ->setEntityLabelInPlural('Bookings')
            ->setSearchFields(['id', 'user.email', 'lessons.title'])
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->showEntityActionsInlined();
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            AssociationField::new('user')
                ->setCrudController(UserCrudController::class),
            AssociationField::new('lessons')
                ->setCrudController(LessonCrudController::class),
            AssociationField::new('payment')
                ->setCrudController(PaymentCrudController::class)
                ->setRequired(false),
            ChoiceField::new('status')
                ->setChoices([
                    'Pending' => 'pending',
                    'Confirmed' => 'confirmed',
                    'Cancelled' => 'cancelled',
                    'Completed' => 'completed',
                ]),
            TextareaField::new('notes')
                ->hideOnIndex(),
            DateTimeField::new('createdAt')
                ->hideOnForm(),
            DateTimeField::new('updatedAt')
                ->hideOnForm(),
        ];
    }
}
