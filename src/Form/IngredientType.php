<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Ingredient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IngredientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Menge', 'class' => 'input input-amount'],
            ])
            ->add('unit', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Einheit', 'class' => 'input input-unit'],
            ])
            ->add('name', TextType::class, [
                'label' => false,
                'attr' => ['placeholder' => 'Zutat', 'class' => 'input input-name'],
            ])
            ->add('groupName', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Gruppe (optional)', 'class' => 'input input-group'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Ingredient::class]);
    }
}
