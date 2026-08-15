<?php

declare(strict_types=1);

namespace App\Form;

use App\Validator\SupportedRecipeSource;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

/**
 * Formular für den Rezept-Import: eine einzelne URL.
 *
 * @extends AbstractType<array{url: string|null}>
 */
class RecipeImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('url', UrlType::class, [
            'label' => 'Rezept-URL',
            'default_protocol' => 'https',
            'attr' => [
                'placeholder' => 'https://www.chefkoch.de/rezepte/…',
                'class' => 'input',
                'autofocus' => true,
            ],
            'constraints' => [
                new NotBlank(message: 'Bitte eine Rezept-URL eingeben.'),
                new Url(message: 'Bitte eine gültige URL eingeben.', requireTld: true),
                new SupportedRecipeSource(),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
