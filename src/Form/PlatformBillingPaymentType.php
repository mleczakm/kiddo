<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * @template TData of array
 * @template-extends AbstractType<TData>
 */
class PlatformBillingPaymentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', TextType::class, [
                'label' => 'platform_billing.payment_amount',
                'required' => true,
                'attr' => [
                    'class' => 'flex h-10 w-full rounded-md border border-input bg-background pl-3 pr-9 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                    'inputmode' => 'decimal',
                    'placeholder' => '0,00',
                    'data-controller' => 'money-input',
                    'data-action' => 'input->money-input#sanitize',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Regex(pattern: '/^\d+([.,]\d{1,2})?$/', message: 'Podaj poprawną kwotę, np. 150,00'),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'platform_billing.submit_payment',
                'attr' => [
                    'class' => 'bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition shadow-sm',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
        ]);
    }
}
