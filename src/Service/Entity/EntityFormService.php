<?php

namespace App\Service\Entity;

use App\Lib\Str;
use Twig\Environment;
use App\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Platform\EntityProperty;
use App\Entity\Platform\EntityPropertyGroup;
use Symfony\Component\Form\FormFactoryInterface;
use App\Form\Common\SwitchType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class EntityFormService extends BaseService
{
  private $formFactory;
  private $twig;
  private $em;

  public function __construct(FormFactoryInterface $formFactory, Environment $twig, EntityManagerInterface $em)
  {
    $this->formFactory = $formFactory;
    $this->twig = $twig;
    $this->em = $em;
  }

  public function createFormBuilder($data = null, array $options = [])
  {
    // 使用注入的FormFactoryInterface服务创建表单构建器
    return $this->formFactory->createBuilder(FormType::class, $data, $options);
  }

  /**
   * 添加字段的表单字段，其中Group是通过查询EntityPropertyGroup动态获取的这个Entity的分组
   * 每个Entity都有各自的Group
   */
  public function getFieldView($epgToken, $choosedGroup = null, $init = true, $data = null)
  {
    $groupRepo = $this->em->getRepository(EntityPropertyGroup::class);
    $entity = $groupRepo->findOneBy(['token' => $epgToken]);
    $group = $groupRepo->getChildren($entity, true, 'lft', 'asc');
    $groupArr = [];
    $defaultValue = '';
    foreach ($group as $key => $g) {
      $id = (string) $g->getId();
      $groupArr[$g->getLabel()] = $id;

      /**
       * 如果分组为null，则采用默认分组，否则采用传入的分组id
       */
      if ($choosedGroup == null) {
        if ($g->getIsDefault()) {
          $defaultValue = $id;
        }
      }
    }

    if ($choosedGroup !== null) {
      $defaultValue = $choosedGroup;
    }

    $formBuilder = $this->createFormBuilder($data);
    $commentToken = Str::generateFieldToken();
    $fieldNameToken = Str::generateFieldToken();
    $fileTypeToken = Str::generateFieldToken();
    $fieldGroupToken = Str::generateFieldToken();

    $formBuilder
      ->add('fieldComment', TextType::class, [
        'attr' => [
          'class' => 'fieldComment',
          'id' => $commentToken,
          'name' => 'fieldComment'.$commentToken,
          'fieldName' => 'comment',
          'rounded' => true,
        ]
      ])
      ->add('fieldName', TextType::class, [
        'attr' => [
          'class' => 'fieldName',
          'id' => $fieldNameToken,
          'name' => 'fieldName'.$fieldNameToken,
          'fieldName' => 'name',
          'rounded' => true,
        ]
      ])
      ->add('fieldType', ChoiceType::class, [
        'choices' => [
          '文本' => 'string',
          '长文本' => 'text',
          '网页' => 'link',
          '选项' => 'options',
          '人员' => 'user'
        ],
        'attr' => [
          'id' => $fileTypeToken,
          'name' => 'fieldType'.$fileTypeToken,
          'data-field-type' => 'true',
          'fieldName' => 'type',
          'rounded' => true,
        ],
      ])
      ->add('fieldGroup', ChoiceType::class, ($data === null ? ['data' => $defaultValue] : []) + [
        'choices' => $groupArr,
        'attr' => [
          'id' => $fieldGroupToken,
          'name' => 'fieldGroup'.$fieldGroupToken,
          'fieldName' => 'group',
          'rounded' => true,
        ],
      ])
      ->add('fieldHeight', IntegerType::class, [
        'required' => false,
        'attr' => [
          'class' => 'fieldHeight',
          'placeholder' => '高度(px)',
          'fieldName' => 'height',
          'rounded' => true,
        ]
      ])
      ->add('fieldRounded', SwitchType::class, [
        'required' => false,
        'attr' => [
          'class' => 'fieldRounded',
          'fieldName' => 'rounded',
        ]
      ])
      ->add('fieldRows', IntegerType::class, [
        'required' => false,
        'attr' => [
          'class' => 'fieldRows',
          'placeholder' => '行数(Textarea)',
          'fieldName' => 'rows',
        ]
      ])
      ->add('fieldAutosize', CheckboxType::class, [
        'required' => false,
        'attr' => [
          'class' => 'fieldAutosize',
          'fieldName' => 'autosize',
        ]
      ]);

    $form = $formBuilder->getForm();
    $formView = $form->createView();

    // 返回的结果数组
    $result = [];
    $result['form'] = $formView;

    if ($init) {
      $additional = $this->getSingleFieldView('entityGroup', ChoiceType::class, ['choices' => $groupArr, 'data' => $defaultValue]);
      $result['additional'] = $additional;
    }
    
    return $result;
  }

  /**
   * 获取单个字段的表单html
   * 参数 字段类型，字段选项
   * 这个方法是为了获取单个字段的html，因此字段名称并不重要，所以把它固化为'field'。
   * 字段类型（string）是必须的，选项值（array）不是必须
   * 所以先判断参数数量，字符串类型的参数是字段类型，数组是选项
   */
  public function getSingleFieldView(...$args)
  {
    $name = 'field';
    $type = null;
    $options = [];

    // 解析参数
    if (!empty($args)) {
      foreach($args as $k => $v) {
        if(is_string($v)) {
          class_exists($v) ? $type = $v : $name = $v;
        }

        if (is_array($v)) {
          $options = $v;
        }
      }
    }

    if ($type === null) {
      throw new \InvalidArgumentException('$type 参数不能为空');
    }

    $formBuilder = $this->createFormBuilder();
    $formBuilder->add($name, $type, $options);
    $form = $formBuilder->getForm();
    $formView = $form->createView();
    return $this->twig->render('ui/form/singleField.html.twig', [
      'field' => $formView
    ]);
  }

  public function addField($epgToken, $group = null, $init = true)
  {
    $formView = $this->getFieldView($epgToken, $group, $init);
    $form = $this->twig->render('ui/drawer/addField.html.twig', [
      'formView' => $formView['form']
    ]);
    $result = [];
    $result['form'] = $form;
    if (isset($formView['additional'])) {
      $additional = $formView['additional'];
      $result['additional'] = $additional;
    }
    return $result;
  }

  public function getEditFieldForm($epgToken, $propertyToken)
  {
    $repo = $this->em->getRepository(EntityProperty::class);
    $property = $repo->findOneBy(['token' => $propertyToken]);

    $data = [
      'fieldComment' => $property->getComment(),
      'fieldName' => $property->getPropertyName(),
      'fieldType' => $property->getType(),
      'fieldGroup' => (string) $property->getGroup()->getId(),
      'fieldHeight' => $property->getHeight(),
      'fieldRounded' => $property->getRounded(),
      'fieldRows' => $property->getFormOptions()['rows'] ?? null,
      'fieldAutosize' => $property->getFormOptions()['autosize'] ?? false,
    ];

    $formView = $this->getFieldView($epgToken, null, true, $data);

    $form = $this->twig->render('ui/drawer/editField.html.twig', [
      'formView' => $formView['form'],
      'propertyToken' => $propertyToken,
      'type' => $property->getType(),
      'length' => $property->getLength() ?? 255,
      'nullable' => $property->getNullable(),
      'unique' => $property->getUniqueable(),
    ]);

    return $form;
  }
}
