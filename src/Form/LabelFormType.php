<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\ProjectColor;
use App\Form\Data\LabelData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<LabelData>
 */
final class LabelFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'label.name.label',
                'empty_data' => '',
                'attr' => ['maxlength' => 30],
            ])
            ->add('color', EnumType::class, [
                'label' => 'project.color.label',
                'class' => ProjectColor::class,
                'expanded' => true,
                'choice_label' => static fn (ProjectColor $color): string => $color->translationKey(),
                'block_prefix' => 'project_color',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LabelData::class]);
    }
}
