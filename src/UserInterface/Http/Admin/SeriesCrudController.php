<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\Series;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

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
            ->setSearchFields(['name', 'description'])
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->showEntityActionsInlined();
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            SlugField::new('slug')
                ->setTargetFieldName('name')
                ->hideOnIndex(),
            TextareaField::new('description')
                ->hideOnIndex(),
            ImageField::new('image')
                ->setBasePath('uploads/series')
                ->setUploadDir('public/uploads/series')
                ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
                ->hideOnIndex(),
            IntegerField::new('sortOrder', 'Sort Order')
                ->setHelp('Lower numbers appear first'),
            BooleanField::new('isActive'),
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('updatedAt')->hideOnForm(),
        ];
    }
}
