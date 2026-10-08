<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Program;
use App\Entity\User;
use App\Enum\ProjectColor;
use App\Form\Data\ProgramData;
use App\Form\Data\ProjectData;
use App\Repository\ProgramRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Also used for programs (data_class ProgramData, name_label "program.name").
 *
 * @extends AbstractType<ProjectData|ProgramData>
 */
final class ProjectFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['program_choices_for'];
        if ($user instanceof User) {
            $builder->add('program', EntityType::class, [
                'label' => 'project.program',
                'help' => 'project.program_help',
                'class' => Program::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'project.program_default',
                'query_builder' => static fn (ProgramRepository $repository): QueryBuilder => $repository->queryWhereUserCreatesProjects($user),
            ]);
        }

        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'label' => $options['name_label'],
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
            'name_label' => 'project.name',
            // The user creating the project: adds the choice of its program.
            'program_choices_for' => null,
        ]);
        $resolver->setAllowedTypes('name_label', 'string');
        $resolver->setAllowedTypes('program_choices_for', ['null', User::class]);
    }
}
