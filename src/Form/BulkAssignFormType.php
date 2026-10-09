<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\BoardColumn;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Form\Data\BulkAssignData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<BulkAssignData>
 */
final class BulkAssignFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Project $project */
        $project = $options['project'];

        $builder
            ->add('assignees', EntityType::class, [
                'label' => 'bulk_assign.assignees.label',
                'class' => User::class,
                'choices' => $project->getMembers()->map(static fn (ProjectMember $member): User => $member->getUser())->getValues(),
                'choice_label' => 'fullName',
                'multiple' => true,
                'expanded' => true,
                'block_prefix' => 'chip_choices',
            ])
            ->add('column', EntityType::class, [
                'label' => 'bulk_assign.column',
                'class' => BoardColumn::class,
                'choices' => $project->getColumns()->getValues(),
                'choice_label' => 'name',
                'placeholder' => 'bulk_assign.all_columns',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => BulkAssignData::class]);
        $resolver->setRequired('project');
        $resolver->setAllowedTypes('project', Project::class);
    }
}
