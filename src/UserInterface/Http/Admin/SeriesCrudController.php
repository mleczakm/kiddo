<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\Series;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

/**
 * @extends AbstractCrudController<Series>
 */
class SeriesCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Series::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Series')
            ->setEntityLabelInPlural('Series')
            ->setSearchFields([]) // No searchable fields in the base entity
            ->setDefaultSort([
                'id' => 'DESC',
            ])
            ->showEntityActionsInlined()
            ->setEntityPermission('ROLE_ADMIN');
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            // Basic info
            IdField::new('id')
                ->hideOnForm()
                ->hideOnIndex(),

            //            // Workshop type
            //            TextField::new('type', 'Workshop Type')
            //                ->formatValue(fn ($value) => $value ? $value->value : '')
            //                ->hideWhenCreating()
            //                ->hideWhenUpdating(),

            // Ticket options (read-only)
            TextareaField::new('ticketOptions', 'Ticket Options')
                ->formatValue(function ($value) {
                    if (empty($value)) {
                        return 'No ticket options';
                    }
                    return implode("\n", array_map(
                        fn($opt) => sprintf(
                            '%s: %s %s',
                            $opt->type->value,
                            $opt->price->getAmount(),
                            $opt->price->getCurrency()
                                ->getCurrencyCode()
                        ),
                        $value
                    ));
                })
                ->hideOnIndex()
                ->setFormTypeOption('disabled', 'disabled'),

            // Lessons count (read-only)
            IntegerField::new('lessons', 'Number of Lessons')
                ->formatValue(fn($value, $entity) => count($entity->lessons))
                ->hideWhenCreating()
                ->hideWhenUpdating(),

            // Lessons association (for reference)
            AssociationField::new('lessons')
                ->setTemplatePath('admin/fields/lessons.html.twig')
                ->hideOnForm(),
        ];
    }
}
