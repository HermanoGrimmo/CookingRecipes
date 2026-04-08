<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Recipe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class RecipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titel',
                'attr' => ['placeholder' => 'z. B. Cashew Hähnchen-Curry', 'class' => 'input'],
                'constraints' => [new NotBlank(message: 'Bitte einen Titel eingeben.')],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Beschreibung',
                'required' => false,
                'attr' => ['placeholder' => 'Kurze Beschreibung des Rezepts…', 'rows' => 3, 'class' => 'input'],
            ])
            ->add('author', TextType::class, [
                'label' => 'Autor',
                'attr' => ['placeholder' => 'Dein Name', 'class' => 'input'],
                'constraints' => [new NotBlank(message: 'Bitte einen Autorennamen eingeben.')],
            ])
            ->add('imagePath', TextType::class, [
                'label' => 'Bild-URL',
                'required' => false,
                'attr' => ['placeholder' => 'https://…', 'class' => 'input'],
            ])
            ->add('servings', IntegerType::class, [
                'label' => 'Portionen',
                'attr' => ['min' => 1, 'max' => 100, 'class' => 'input'],
                'constraints' => [new Range(min: 1, max: 100)],
            ])
            ->add('prepTime', IntegerType::class, [
                'label' => 'Zubereitungszeit (Min.)',
                'attr' => ['min' => 0, 'class' => 'input'],
                'constraints' => [new Range(min: 0)],
            ])
            ->add('cookTime', IntegerType::class, [
                'label' => 'Kochzeit (Min.)',
                'attr' => ['min' => 0, 'class' => 'input'],
                'constraints' => [new Range(min: 0)],
            ])
            ->add('difficulty', ChoiceType::class, [
                'label' => 'Schwierigkeitsgrad',
                'choices' => [
                    'Einfach' => 'einfach',
                    'Mittel'  => 'mittel',
                    'Schwer'  => 'schwer',
                ],
                'attr' => ['class' => 'input'],
            ])
            ->add('ingredients', CollectionType::class, [
                'label' => false,
                'entry_type' => IngredientType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__ingredient_index__',
            ])
            ->add('steps', CollectionType::class, [
                'label' => false,
                'entry_type' => StepType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__step_index__',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Recipe::class]);
    }
}
