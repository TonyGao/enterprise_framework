<?php

namespace App\Form\Platform;

use App\Entity\Platform\Menu;
use App\Form\BaseFormType;
use Symfony\Component\Form\AbstractType;
use App\Repository\Platform\MenuRepository;
use App\Service\Form\FormFieldBuilderService;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MenuType extends BaseFormType
{
  private $formFieldBuilder;

  public function __construct(FormFieldBuilderService $formFieldBuilder)
  {
    $this->formFieldBuilder = $formFieldBuilder;
  }

  public function buildForm(FormBuilderInterface $builder, array $options): void
  {
    $this->formFieldBuilder->buildFields($builder, Menu::class);

    $builder->add('enabled', CheckboxType::class, [
      'label' => '是否启用',
      'required' => false,
    ]);
  }

  public function configureOptions(OptionsResolver $resolver): void
  {
      $resolver->setDefaults([
          'data_class' => Menu::class,
          'attr' => [
            'style' => 'width: 450px;', // 设置表单宽度
           ],
      ]);
  }
}