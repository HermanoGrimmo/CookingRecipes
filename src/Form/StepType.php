<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Step;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Step> */
class StepType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Schrittbezeichnung (optional)', 'class' => 'input'],
            ])
            ->add('description', TextareaType::class, [
                'label' => false,
                'attr' => ['placeholder' => 'Zubereitungsanleitung…', 'rows' => 4, 'class' => 'input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Step::class]);
    }
}
