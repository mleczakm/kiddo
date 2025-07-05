<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\Lesson;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Lesson>
 */
class LessonCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Lesson::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Lesson')
            ->setEntityLabelInPlural('Lessons')
            ->setSearchFields(['metadata.title', 'metadata.description'])
            ->setDefaultSort([
                'metadata.schedule' => 'DESC',
            ])
            ->showEntityActionsInlined();
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm()->hideOnIndex(),
            TextField::new('metadata.title', 'Title'),
            TextEditorField::new('metadata.description', 'Description')->hideOnIndex(),
            TextField::new('metadata.lead', 'Lead')->hideOnIndex(),
            TextField::new('metadata.visualTheme', 'Visual Theme')->hideOnIndex(),
            DateTimeField::new('metadata.schedule', 'Schedule'),
            IntegerField::new('metadata.duration', 'Duration (minutes)'),
            IntegerField::new('metadata.capacity', 'Capacity'),
            TextField::new('metadata.category', 'Category'),

            // Age range fields
            IntegerField::new('metadata.ageRange.min', 'Min Age')
                ->setFormTypeOption('attr', [
                    'min' => 0,
                ])
                ->hideOnIndex(),
            IntegerField::new('metadata.ageRange.max', 'Max Age')
                ->setFormTypeOption('attr', [
                    'min' => 0,
                ])
                ->hideOnIndex(),

            // Series relationship
            AssociationField::new('series')
                ->setFormTypeOption('choice_label', 'lessons[0].metadata.title')
                ->setRequired(false),

            // Timestamps
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('updatedAt')->hideOnForm(),

            // Status
            TextField::new('status'),
        ];
    }
}
