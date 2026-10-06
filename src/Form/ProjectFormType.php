<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\ProjectColor;
use App\Form\Data\ProjectData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ProjectData>
 */
final class ProjectFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'label' => 'project.name',
                'attr' => ['maxlength' => 100],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'project.description',
                'required' => false,
                'attr' => ['rows' => 4],
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
        $resolver->setDefaults([
            'data_class' => ProjectData::class,
        ]);
    }
}
