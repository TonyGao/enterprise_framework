<?php

namespace App\Form\Organization;

use App\Entity\Organization\Company;
use App\Entity\Organization\Department;
use App\Entity\Organization\Employee;
use App\Entity\Organization\Position;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Form\Common\DepartmentType as CommonDepartmentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class EmployeeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $attr = ['rounded' => true, 'height' => 36];

        $builder
            ->add('name', TextType::class, [
                'label' => 'employee.field.name',
                'constraints' => [
                    new NotBlank(['message' => 'Name cannot be blank.'])
                ],
                'attr' => $attr,
            ])
            ->add('englishName', TextType::class, [
                'label' => 'employee.field.english_name',
                'required' => false,
                'attr' => $attr,
            ])
            ->add('employmentStatus', ChoiceType::class, [
                'label' => 'employee.field.employment_status',
                'choices' => [
                    'employee.employment_status.active' => 'active',
                    'employee.employment_status.probation' => 'probation',
                    'employee.employment_status.inactive' => 'inactive',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Status cannot be blank.'])
                ],
                'attr' => $attr,
            ])
            ->add('workStatus', ChoiceType::class, [
                'label' => 'employee.field.work_status',
                'choices' => [
                    'employee.work_status.working' => 'working',
                    'employee.work_status.vacation' => 'vacation',
                    'employee.work_status.business_trip' => 'business_trip',
                    'employee.work_status.out_of_office' => 'out_of_office',
                    'employee.work_status.in_meeting' => 'in_meeting',
                ],
                'attr' => $attr,
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => 'employee.field.account_status',
                'choices' => [
                    'employee.account_status.enabled' => true,
                    'employee.account_status.disabled' => false,
                ],
                'attr' => $attr,
            ])
            ->add('employeeNo', TextType::class, [
                'label' => 'employee.field.employee_no',
                'constraints' => [
                    new NotBlank(['message' => 'Employee number cannot be blank.'])
                ],
                'attr' => $attr,
            ])
            ->add('hireDate', DateType::class, [
                'label' => 'employee.field.entry_date',
                'widget' => 'single_text',
                'required' => false,
                'attr' => $attr,
            ])
            ->add('email', EmailType::class, [
                'label' => 'employee.field.email',
                'constraints' => [
                    new NotBlank(['message' => 'Email cannot be blank.'])
                ],
                'attr' => $attr,
            ])
            ->add('mobile', TextType::class, [
                'label' => 'employee.field.mobile',
                'required' => false,
                'attr' => $attr,
            ])
            ->add('company', EntityType::class, [
                'class' => Company::class,
                'choice_label' => 'name',
                'label' => 'employee.field.company',
                'required' => false,
                'attr' => $attr,
            ])
            ->add('department', EntityType::class, [
                'class' => Department::class,
                'choice_label' => 'name',
                'label' => 'employee.field.department',
                'required' => false,
                'attr' => $attr,
            ])
            ->add('position', EntityType::class, [
                'class' => Position::class,
                'choice_label' => 'name',
                'label' => 'employee.field.position',
                'required' => false,
                'attr' => $attr,
            ])
            ->add('manager', EntityType::class, [
                'class' => Employee::class,
                'choice_label' => 'name',
                'label' => 'employee.field.manager',
                'required' => false,
                'attr' => $attr,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Employee::class,
        ]);
    }
}
