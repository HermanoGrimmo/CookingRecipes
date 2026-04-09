<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formular zum Setzen eines neuen Passworts (nach dem Reset).
 *
 * @extends AbstractType<array<string, mixed>>
 */
class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Neues Passwort',
                    'attr' => ['placeholder' => 'Mindestens 8 Zeichen', 'class' => 'input', 'autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Neues Passwort bestätigen',
                    'attr' => ['placeholder' => 'Passwort wiederholen', 'class' => 'input', 'autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'Die Passwörter stimmen nicht überein.',
                'constraints' => [
                    new NotBlank(message: 'Bitte ein neues Passwort eingeben.'),
                    new Length(
                        min: 8,
                        minMessage: 'Das Passwort muss mindestens {{ limit }} Zeichen lang sein.',
                        max: 4096,
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
