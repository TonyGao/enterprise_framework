<?php

namespace App\Service\Form;

use App\Lib\Arr;
use App\Lib\Str;
use App\Entity\Platform\View;
use App\Entity\Platform\ViewField;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Twig\Environment as TwigEnvironment;

class FormFieldRenderer
{
    private EntityManagerInterface $em;
    private FormFactoryInterface $formFactory;
    private TwigEnvironment $twig;

    public function __construct(
        EntityManagerInterface $em,
        FormFactoryInterface $formFactory,
        TwigEnvironment $twig
    ) {
        $this->em = $em;
        $this->formFactory = $formFactory;
        $this->twig = $twig;
    }

    /**
     * Build a Symfony Form from View + ViewField records (no HTML rendering).
     *
     * @param View   $view  The view with formEntity set
     * @param object $data  The underlying entity instance
     * @return array{formView: \Symfony\Component\Form\FormView, form: \Symfony\Component\Form\FormInterface, fields: array}
     */
    public function build(View $view, object $data, array $generalConfig = []): array
    {
        $fields = $this->em->getRepository(ViewField::class)
            ->findBy(['view' => $view], ['sortOrder' => 'ASC']);

        $builder = $this->formFactory->createBuilder(
            \Symfony\Component\Form\Extension\Core\Type\FormType::class,
            $data
        );

        foreach ($fields as $field) {
            $this->addFieldToBuilder($builder, $field, $generalConfig);
        }

        $form = $builder->getForm();
        $formView = $form->createView();

        return ['formView' => $formView, 'form' => $form, 'fields' => $fields];
    }

    /**
     * Build a Symfony Form from View + ViewField records and render to HTML.
     *
     * @param View   $view       The view with formEntity set
     * @param object $data       The underlying entity instance (e.g. Corporation)
     * @param bool   $editorMode Whether to render with editor wrappers (dashed borders, .ef-component)
     * @param array  $formAttr   HTML attributes for the <form> tag (e.g. ['style' => 'width: 450px;'])
     * @return array{formView: \Symfony\Component\Form\FormView, html: string}
     */
    public function render(View $view, object $data, bool $editorMode = false, array $formAttr = [], array $generalConfig = []): array
    {
        $result = $this->build($view, $data, $generalConfig);

        // 从 View.sectionConfig 推导 formAttr（仅非编辑器模式时生效）
        $sc = $view->getSectionConfig();
        if (!empty($sc['contentWidth'])) {
            if ($sc['contentWidth'] === 'boxed' && !empty($sc['width'])) {
                $unit = $sc['unit'] ?? 'px';
                $styleVal = 'width: ' . $sc['width'] . $unit;
            } else {
                $styleVal = 'width: 100%';
            }
            $existingStyle = $formAttr['style'] ?? '';
            $formAttr['style'] = $existingStyle ? $existingStyle . '; ' . $styleVal : $styleVal;
        }

        $html = $this->twig->render('admin/platform/form/_form_fields.html.twig', [
            'form' => $result['formView'],
            'fields' => $result['fields'],
            'editor_mode' => $editorMode,
            'form_attr' => $formAttr,
            'general_config' => $generalConfig,
            'section_config' => $sc,
        ]);

        return ['formView' => $result['formView'], 'html' => $html, 'form' => $result['form']];
    }

    private function addFieldToBuilder(FormBuilderInterface $builder, ViewField $field, array $generalConfig = []): void
    {
        $fieldName = $field->getFieldName();
        $config = $field->getConfig() ?? [];
        $fieldType = $field->getFieldType();

        $label = $config['label'] ?? $field->getFieldLabel();
        $required = $config['required'] ?? false;
        $placeholder = $config['placeholder'] ?? null;
        $validation = $config['validation'] ?? [];

        $attr = Arr::transValtoAttr($validation);
        // 移除 attr['required']，required HTML 属性应由 form 的 required 选项控制，
        // 而非 attr 数组。否则 `required="false"` 会渲染到 input 上，
        // 而在 HTML5 中布尔属性只要存在即视为 true。
        unset($attr['required']);
        if ($placeholder) {
            $attr['placeholder'] = $placeholder;
        }

        if (!empty($config['height'])) {
            $h = $config['height'];
            // textarea（fieldType='text'）：兼容旧像素值（>20），归一行数
            if ($fieldType === 'text' && $h > 20) {
                $h = max(1, (int) round($h / 14));
            }
            $attr['height'] = $h;
        }
        if (isset($config['rounded'])) {
            $attr['rounded'] = $config['rounded'];
        }
        if (!empty($config['formOptions'])) {
            if (isset($config['formOptions']['rows'])) {
                $attr['rows'] = $config['formOptions']['rows'];
            }
            if (isset($config['formOptions']['autosize'])) {
                $attr['autosize'] = $config['formOptions']['autosize'];
            }
        }

        if (!empty($config['regularBg']) && strtoupper($config['regularBg']) !== '#FFFFFF') {
            $attr['data-regular-bg'] = $config['regularBg'];
        }
        if (!empty($config['requiredBg'])) {
            $bg = $config['requiredBg'];
            if (strtoupper($bg) === '#FFF2E8' && !empty($generalConfig['requiredBg'])) {
                $bg = $generalConfig['requiredBg'];
            }
            $attr['data-required-bg'] = $bg;
        }

        $formType = $fieldType ? Str::convertFormType($fieldType) : TextType::class;

        $options = [
            'label' => $label,
            'attr' => $attr,
            'required' => $required,
        ];

        if ($fieldType === 'date') {
            $options['widget'] = 'single_text';
        }

        if ($fieldType === 'entity' && !empty($config['class'])) {
            $options['class'] = $config['class'];
            $options['choice_label'] = 'name';
        }

        if ($fieldType === 'select' && !empty($config['choices'])) {
            $choices = [];
            foreach ($config['choices'] as $choice) {
                $choices[$choice['label']] = $choice['value'];
            }
            $options['choices'] = $choices;
            $options['choice_translation_domain'] = 'messages';
        }

        $builder->add($fieldName, $formType, $options);
    }
}
