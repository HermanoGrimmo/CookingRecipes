<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formular zur Anforderung eines Passwort-Reset-Links.
 *
 * @extends AbstractType<array<string, mixed>>
 */
class ResetPasswordRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'E-Mail-Adresse',
                'attr' => ['placeholder' => 'max@beispiel.de', 'class' => 'input', 'autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(message: 'Bitte eine E-Mail-Adresse eingeben.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
