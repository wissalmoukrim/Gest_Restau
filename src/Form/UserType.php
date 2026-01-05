<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('nom', TextType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('prenom', TextType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('telephone', TextType::class, [
                'required' => false,
            ])
            ->add('adresse', TextType::class, [
                'required' => false,
            ])
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Réceptionniste' => 'ROLE_RECEPTIONNISTE',
                    'Livreur' => 'ROLE_LIVREUR',
                    'Chef' => 'ROLE_CHEF',
                    'Responsable' => 'ROLE_RESPONSABLE',
                    'Admin' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('password', PasswordType::class, [
                'mapped' => true,
                'required' => !$isEdit,
                'constraints' => $isEdit ? [] : [new NotBlank()],
                'label' => $isEdit
                    ? 'Mot de passe (laisser vide si inchangé)'
                    : 'Mot de passe',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
            'csrf_protection' => true,
        ]);
    }
}
