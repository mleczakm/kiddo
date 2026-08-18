<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Command\SendLoginNotification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class LoginUser extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    private bool $isSubmitted = false;

    private bool $isSuccessful = false;

    public function __construct(
        private MessageBusInterface $messageBus,
    ) {}

    /**
     * @return FormInterface<array{email: string}>
     */
    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        /** @var FormInterface<array{email: string}> $form */
        return $this
            ->createFormBuilder()
            ->add('email', EmailType::class, [
                'constraints' => [new Email(), new NotBlank()],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'form.login.submit',
            ])
            ->getForm();
    }

    #[LiveAction]
    public function save(): void
    {
        $this->submitForm();

        if ($this->getForm()->isValid()) {
            /** @var array{email: string} $data */
            $data = $this->getForm()->getData();

            $this->messageBus->dispatch(new SendLoginNotification($data['email']));

            $this->isSuccessful = true;
            $this->isSubmitted = true;
        } else {
            $this->isSubmitted = true;
            $this->isSuccessful = false;
        }
    }

    public function isSubmitted(): bool
    {
        return $this->isSubmitted;
    }

    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }
}
