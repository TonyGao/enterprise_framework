<?php

namespace App\Form\Admin;

use App\Entity\Organization\Employee;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SystemAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('username', TextType::class, [
                'label' => '用户名',
                'disabled' => $isEdit,
                'required' => true,
            ])
            ->add('name', TextType::class, [
                'label' => '姓名',
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'label' => '邮箱',
                'required' => true,
            ])
            ->add('employeeNo', TextType::class, [
                'label' => '编号',
                'required' => false,
            ])
            ->add('mobile', TextType::class, [
                'label' => '手机号',
                'required' => false,
            ])
            ->add('roles', ChoiceType::class, [
                'label' => '角色',
                'choices' => [
                    '超级管理员' => 'ROLE_SYS_ADMIN',
                    '安全管理员' => 'ROLE_SEC_ADMIN',
                    '审计员' => 'ROLE_AUDITOR',
                    '管理员' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => true,
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => '状态',
                'choices' => [
                    '启用' => true,
                    '禁用' => false,
                ],
                'required' => true,
            ]);

        if (!$isEdit) {
            $builder->add('password', PasswordType::class, [
                'label' => '密码',
                'required' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Employee::class,
            'is_edit' => false,
        ]);
    }
}
