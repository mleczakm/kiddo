<?php

declare(strict_types=1);

namespace App\UserInterface\Form;

use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Reusable "pick a user" field: id, name, email, or a child's name all
 * resolve to the owning User. Every "select a user" UI in the admin panel
 * (admins/hosts management, workshop instructors, manual bookings) should
 * search through App\Infrastructure\Doctrine\Repository\UserRepository::findForAutocomplete() —
 * either directly via this Form Type in a plain Symfony form, or via the
 * same repository method from a LiveComponent typeahead — so the matching
 * rules only need to change in one place.
 *
 * Usage:
 *     $builder->add('user', UserSelectType::class, [
 *         'query' => $searchTerm, // optional; empty options list without it
 *     ]);
 *
 * @extends AbstractType<User>
 */
final class UserSelectType extends AbstractType
{
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => User::class,
            'query' => null,
            'choice_label' => static fn(User $user): string => sprintf('%s (%s)', $user->getName(), $user->getEmail()),
            'query_builder' => static function (UserRepository $repository, array $options): QueryBuilder {
                $query = $options['query'];
                if (is_string($query) && trim($query) !== '') {
                    // findForAutocomplete() already returns User[]; wrap the
                    // matched ids back into a query_builder so EntityType's
                    // normal choice-loading/validation still applies.
                    $ids = array_map(
                        static fn(User $user): int => (int) $user->getId(),
                        $repository->findForAutocomplete($query),
                    );

                    return $repository
                        ->createQueryBuilder('u')
                        ->andWhere('u.id IN (:ids)')
                        ->setParameter('ids', $ids === [] ? [0] : $ids)
                        ->orderBy('u.name', 'ASC');
                }

                return $repository->createQueryBuilder('u')->orderBy('u.name', 'ASC');
            },
            'placeholder' => 'Wybierz użytkownika...',
            'translation_domain' => 'messages',
        ]);

        $resolver->setAllowedTypes('query', ['null', 'string']);
    }

    #[\Override]
    public function getParent(): string
    {
        return EntityType::class;
    }
}
