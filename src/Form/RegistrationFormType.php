<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formular zur Registrierung eines neuen Benutzers.
 *
 * @extends AbstractType<User>
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Vorname',
                'attr' => ['placeholder' => 'Max', 'class' => 'input', 'autocomplete' => 'given-name'],
                'constraints' => [
                    new NotBlank(message: 'Bitte einen Vornamen eingeben.'),
                    new Length(max: 100, maxMessage: 'Der Vorname darf maximal {{ limit }} Zeichen lang sein.'),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nachname',
                'attr' => ['placeholder' => 'Mustermann', 'class' => 'input', 'autocomplete' => 'family-name'],
                'constraints' => [
                    new NotBlank(message: 'Bitte einen Nachnamen eingeben.'),
                    new Length(max: 100, maxMessage: 'Der Nachname darf maximal {{ limit }} Zeichen lang sein.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-Mail-Adresse',
                'attr' => ['placeholder' => 'max@beispiel.de', 'class' => 'input', 'autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(message: 'Bitte eine E-Mail-Adresse eingeben.'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Passwort',
                    'attr' => ['placeholder' => 'Mindestens 8 Zeichen', 'class' => 'input', 'autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Passwort bestätigen',
                    'attr' => ['placeholder' => 'Passwort wiederholen', 'class' => 'input', 'autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'Die Passwörter stimmen nicht überein.',
                'constraints' => [
                    new NotBlank(message: 'Bitte ein Passwort eingeben.'),
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
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
