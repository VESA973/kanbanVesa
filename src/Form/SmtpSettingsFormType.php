<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\SmtpEncryption;
use App\Form\Data\SmtpSettingsData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<SmtpSettingsData>
 */
final class SmtpSettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('host', TextType::class, ['label' => 'admin.smtp.host', 'empty_data' => '', 'attr' => ['placeholder' => 'smtp.example.com']])
            ->add('port', IntegerType::class, ['label' => 'admin.smtp.port'])
            ->add('encryption', EnumType::class, [
                'label' => 'admin.smtp.encryption.label',
                'class' => SmtpEncryption::class,
                'choice_label' => static fn (SmtpEncryption $encryption): string => $encryption->translationKey(),
            ])
            ->add('username', TextType::class, ['label' => 'admin.smtp.username', 'required' => false, 'attr' => ['autocomplete' => 'off']])
            ->add('password', PasswordType::class, [
                'label' => 'admin.smtp.password',
                'required' => false,
                'help' => $options['has_password'] ? 'admin.smtp.password_keep' : null,
                'attr' => ['autocomplete' => 'new-password'],
            ])
            ->add('fromAddress', EmailType::class, ['label' => 'admin.smtp.from_address', 'help' => 'admin.smtp.from_address_help', 'empty_data' => ''])
            ->add('fromName', TextType::class, ['label' => 'admin.smtp.from_name', 'required' => false]);

        if ($options['has_password']) {
            $builder->add('removePassword', CheckboxType::class, ['label' => 'admin.smtp.remove_password', 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SmtpSettingsData::class, 'has_password' => false]);
        $resolver->setAllowedTypes('has_password', 'bool');
    }
}
