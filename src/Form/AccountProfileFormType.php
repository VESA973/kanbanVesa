<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Data\AccountProfileData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AccountProfileData>
 */
final class AccountProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'user.first_name', 'empty_data' => '', 'attr' => ['autocomplete' => 'given-name', 'maxlength' => 100]])
            ->add('lastName', TextType::class, ['label' => 'user.last_name', 'empty_data' => '', 'attr' => ['autocomplete' => 'family-name', 'maxlength' => 100]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AccountProfileData::class]);
    }
}
