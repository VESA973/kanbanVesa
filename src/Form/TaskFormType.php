<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\BoardColumn;
use App\Entity\Project;
use App\Form\Data\TaskData;
use App\Repository\BoardColumnRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TaskData>
 */
final class TaskFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Project $project */
        $project = $options['project'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'task.title',
                'empty_data' => '',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'task.description',
                'required' => false,
                'attr' => ['rows' => 6],
            ])
            // Keyboard-accessible alternative to drag & drop.
            ->add('column', EntityType::class, [
                'label' => 'task.column',
                'class' => BoardColumn::class,
                'choice_label' => 'name',
                'query_builder' => static fn (BoardColumnRepository $repository): QueryBuilder => $repository
                    ->createQueryBuilder('c')
                    ->andWhere('c.project = :project')
                    ->setParameter('project', $project)
                    ->orderBy('c.position', 'ASC'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TaskData::class]);
        $resolver->setRequired('project');
        $resolver->setAllowedTypes('project', Project::class);
    }
}
