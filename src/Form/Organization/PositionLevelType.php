<?php

namespace App\Form\Organization;

use App\Entity\Organization\PositionLevel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PositionLevelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $rounded = $options['rounded'] ?? true;
        $height = $options['height'] ?? 36;

        $builder
            ->add('name', TextType::class, [
                'label' => '级别名称', 'attr' => ['rounded' => $rounded, 'height' => $height],
                'required' => true,
            ])
            ->add('code', TextType::class, [
                'label' => '级别编码', 'attr' => ['rounded' => $rounded, 'height' => $height],
                'required' => false,
            ])
            ->add('levelOrder', IntegerType::class, [
                'label' => '级别序号', 'attr' => ['rounded' => $rounded, 'height' => $height],
                'required' => true,
                'help' => '数字越小级别越高',
            ])
            ->add('salaryMin', NumberType::class, [
                'label' => '薪资范围下限', 'attr' => ['rounded' => $rounded, 'height' => $height],
                'required' => false,
                'scale' => 2,
            ])
            ->add('salaryMax', NumberType::class, [
                'label' => '薪资范围上限', 'attr' => ['rounded' => $rounded, 'height' => $height],
                'required' => false,
                'scale' => 2,
            ])
            ->add('description', TextareaType::class, [
                'label' => '级别描述', 'attr' => ['rounded' => $rounded, 'rows' => 10],
                'required' => false,
            ])
            ->add('state', CheckboxType::class, [
                'label' => '启用', 'attr' => ['rounded' => $rounded, 'height' => $height],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PositionLevel::class,
            'rounded' => true,
            'height' => 36,
        ]);
    }
}
