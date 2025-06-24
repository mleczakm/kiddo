<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\Lesson;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
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
            ->setSearchFields(['title', 'description'])
            ->setDefaultSort([
                'metadata.schedule' => 'DESC',
            ])
            ->showEntityActionsInlined();
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('title'),
            TextEditorField::new('description')->hideOnIndex(),
            DateTimeField::new('metadata.schedule'),
            IntegerField::new('duration')->setLabel('Duration (minutes)'),
            IntegerField::new('maxParticipants'),
            BooleanField::new('isActive'),
            AssociationField::new('series')
                ->setFormTypeOption('choice_label', 'name')
                ->setRequired(true),
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('updatedAt')->hideOnForm(),
        ];
    }
}
