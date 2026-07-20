<?php

namespace App\Service\Form;

use App\Lib\Arr;
use App\Lib\Str;
use App\Service\BaseService;
use App\Entity\Platform\Entity;
use App\Entity\Platform\EntityProperty;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\DataTransformerInterface;

class FormFieldBuilderService extends BaseService implements DataTransformerInterface
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function buildFields(FormBuilderInterface $builder, string $entityClass, ?callable $customOptionsCallback = null): void
    {
        $reflectionClass = new \ReflectionClass($entityClass);
        $repo = $this->em->getRepository(Entity::class);
        $en = $repo->findOneBy([
            'fqn' => $reflectionClass->name,
        ]);

        $fields = $this->em->getRepository(EntityProperty::class)
            ->findBy(['entity' => $en], ['orderNum' => 'ASC']);

        foreach ($fields as $field) {
            $arr = [];
            if ($field->getValidation()) {
                $validation = $field->getValidation();
                $arr = Arr::transValtoAttr($validation);
            }

            $classType = Str::convertFormType($field->getType());
            $options = [
                'label' => $field->getComment(),
                'attr' => $arr,
                'required' => $arr['required'] ?? false,
            ];

            if ($field->getHeight() !== null) {
                $options['attr']['height'] = $field->getHeight();
            }

            if ($field->getRounded() !== null) {
                $options['attr']['rounded'] = $field->getRounded();
            }

            if ($field->getFormOptions()) {
                if (isset($field->getFormOptions()['rows'])) {
                    $options['attr']['rows'] = $field->getFormOptions()['rows'];
                }
                if (isset($field->getFormOptions()['autosize'])) {
                    $options['attr']['autosize'] = $field->getFormOptions()['autosize'];
                }
            }

            if ($field->getTargetEntity() !== null) {
                $options['class'] = $field->getTargetEntity();
            }

            // Use custom options callback if provided
            if ($customOptionsCallback) {
                $customOptionsCallback($field, $options);
            }

            $property = $field->getPropertyName();

            if ($field->getType() === 'user') {
                $options['attr']['data-user-field'] = 'true';
                $options['block_prefix'] = 'user';
                $builder->add($property, TextType::class, $options);
                $builder->get($property)->addViewTransformer($this);
            } else {
                $builder->add($property, $classType, $options);
            }
        }
    }

    public function transform($value): mixed
    {
        return is_array($value) ? json_encode($value) : ($value ?: '[]');
    }

    public function reverseTransform($value): mixed
    {
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    public function getEntityManager() {
        return $this->em;
    }
}
