<?php

namespace App\Form\Platform;

use App\Entity\Platform\LlmProvider;
use App\Entity\Platform\LlmRole;
use App\Form\BaseFormType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LlmRoleType extends BaseFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('provider', EntityType::class, [
                'class' => LlmProvider::class,
                'label' => '绑定服务商',
                'placeholder' => '— 未配置 —',
                'required' => false,
            ])
            ->add('systemPrompt', TextareaType::class, [
                'label' => 'System Prompt',
                'required' => false,
                'attr' => ['rows' => 8],
            ])
            ->add('isEnabled', CheckboxType::class, [
                'label' => '启用',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LlmRole::class,
        ]);
    }
}
