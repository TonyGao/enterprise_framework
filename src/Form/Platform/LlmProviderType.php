<?php

namespace App\Form\Platform;

use App\Entity\Platform\LlmProvider;
use App\Form\BaseFormType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LlmProviderType extends BaseFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => '名称',
            ])
            ->add('provider', ChoiceType::class, [
                'label' => '厂商',
                'choices' => [
                    '━━ 国产厂商 ────' => [
                        'DeepSeek（深度求索）' => 'deepseek',
                        '月之暗面（Moonshot）' => 'moonshot',
                        '阿里通义千问（Qwen）' => 'qwen',
                        '智谱AI（GLM）' => 'glm',
                        '百度文心（ERNIE）' => 'ernie',
                        '字节豆包（Doubao）' => 'doubao',
                        '百川智能（Baichuan）' => 'baichuan',
                        '零一万物（Yi）' => 'yi',
                        'SiliconFlow（硅基流动）' => 'siliconflow',
                    ],
                    '━━ 海外厂商 ────' => [
                        'OpenAI' => 'openai',
                        'Anthropic' => 'anthropic',
                        'Azure' => 'azure',
                    ],
                    '━━ 本地方案 ────' => [
                        'Ollama' => 'ollama',
                        'LM Studio' => 'lmstudio',
                    ],
                    '━━ 其他 ────' => [
                        '自定义' => 'custom',
                    ],
                ],
            ])
            ->add('model', TextType::class, [
                'label' => '模型名称',
            ])
            ->add('apiKey', TextType::class, [
                'label' => 'API Key',
                'mapped' => false,
                'required' => $options['is_new'],
                'help' => $options['is_new'] ? '' : '留空表示不修改',
            ])
            ->add('apiEndpoint', UrlType::class, [
                'label' => '接口地址（可选）',
                'required' => false,
            ])
            ->add('thinkingEnabled', CheckboxType::class, [
                'label' => '开启 Thinking 深度思考模式',
                'mapped' => false,
                'required' => false,
                'help' => 'DeepSeek V4 默认开启深度思考，关闭后响应更快',
            ])
            ->add('reasoningEffort', ChoiceType::class, [
                'label' => '推理强度',
                'mapped' => false,
                'required' => false,
                'placeholder' => 'medium（默认）',
                'choices' => [
                    '低（low）' => 'low',
                    '中（medium）' => 'medium',
                    '高（high）' => 'high',
                ],
                'help' => '思考模式下的推理强度',
            ])
            ->add('isEnabled', CheckboxType::class, [
                'label' => '启用',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LlmProvider::class,
            'is_new' => true,
        ]);
    }
}
