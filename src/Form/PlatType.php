<?php

namespace App\Form;

use App\Entity\Plat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class PlatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du plat',
                'attr' => ['placeholder' => 'Ex: Couscous', 'class' => 'form-control']
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
                'attr' => [
                    'rows' => 4, 
                    'placeholder' => 'Description du plat...',
                    'class' => 'form-control'
                ]
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix (DH)',
                'currency' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.01',
                    'min' => '0',
                    'placeholder' => 'Ex: 50.00',
                    'class' => 'form-control'
                ]
            ])
            ->add('disponible', CheckboxType::class, [
                'required' => false,
                'label' => 'Disponible à la vente',
                'label_attr' => ['class' => 'form-check-label'],
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('image', FileType::class, [
                'label' => 'Image du plat (JPG, PNG, WebP)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.jpg,.jpeg,.png,.webp'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Plat::class,
        ]);
    }
}