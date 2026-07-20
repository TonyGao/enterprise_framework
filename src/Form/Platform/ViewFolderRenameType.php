<?php

namespace App\Form\Platform;

use App\Entity\Platform\View;
use App\Form\BaseFormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ViewFolderRenameType extends BaseFormType
{
  public function buildForm(FormBuilderInterface $builder, array $options): void
  {
      $builder->add('name', TextType::class, ['label' => '文件夹名']);
  }

  public function configureOptions(OptionsResolver $resolver): void
  {
      $resolver->setDefaults([
          'data_class' => View::class,
          'attr' => [
              'style' => 'width: 450px;',
          ],
      ]);
  }
}
