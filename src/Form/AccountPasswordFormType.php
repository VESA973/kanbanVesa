<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Data\AccountPasswordData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<AccountPasswordData>
 */
final class AccountPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'account.password.current',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'current-password'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'options' => ['empty_data' => ''],
                'type' => PasswordType::class,
                'invalid_message' => 'user.password.mismatch',
                'first_options' => ['label' => 'user.new_password', 'help' => 'user.password.help', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'user.password_confirmation', 'attr' => ['autocomplete' => 'new-password']],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AccountPasswordData::class]);
    }
}
