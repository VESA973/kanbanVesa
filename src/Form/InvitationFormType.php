<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\ProjectRole;
use App\Form\Data\InvitationData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<InvitationData>
 */
final class InvitationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'invitation.email',
                'empty_data' => '',
            ])
            ->add('role', EnumType::class, [
                'label' => 'invitation.role',
                'class' => ProjectRole::class,
                'choices' => [ProjectRole::EDITOR, ProjectRole::VIEWER],
                'choice_label' => static fn (ProjectRole $role): string => $role->translationKey(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InvitationData::class]);
    }
}
