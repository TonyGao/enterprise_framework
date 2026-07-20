<?php

namespace App\Form\Platform;

use App\Entity\Platform\View;
use App\Form\BaseFormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class ViewType extends BaseFormType
{
  public function buildForm(FormBuilderInterface $builder, array $options): void
  {
      $syncData = [
        "view_name" => [
          "callback" => [
            "translate" => [],
            "removePunctuation" => true,
            "tableize" => true
          ],
        ]
      ];
      $builder->add('label', TextType::class, [
          'label' => '视图中文名',
          'attr' => [
            'data-sync' => json_encode($syncData)
          ]
        ])
        ->add('name', TextType::class, ['label' => '视图英文名'])
        ->add('parent', EntityType::class, [
          'label' => '上级目录',
          'class' => View::class,
          'choice_label' => 'name',
          'placeholder' => '请选择上级目录',
          'required' => true,
          'query_builder' => function (EntityRepository $er) {
              return $er->createQueryBuilder('v')
                  ->where('v.type = :type')
                  ->setParameter('type', 'folder');
          },
          ])
        ->add('type', TextType::class, ['attr'=>[
          'readonly' => true,
        ],
        'data' => 'view',
        'label' => '类型'
      ]);

      if ($options['show_built_in']) {
          $builder->add('builtIn', CheckboxType::class, [
              'label' => '系统内置',
              'required' => false,
          ]);

          $builder->add('template', HiddenType::class, [
              'required' => false,
          ]);
      }
  }

  public function configureOptions(OptionsResolver $resolver): void
  {
      $resolver->setDefaults([
          'data_class' => View::class,
          'readonly' => false,
          'show_built_in' => false,
          'attr' => [
              'style' => 'width: 450px;',
          ],
      ]);
  }
}