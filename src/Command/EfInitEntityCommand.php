<?php

namespace App\Command;

use App\Annotation\Ef;
use App\Lib\Str;
use App\Entity\Platform\Entity;
use App\Entity\Platform\EntityProperty;
use App\Entity\Platform\EntityPropertyGroup;
use App\Repository\Platform\EntityRepository;
use App\Repository\Platform\EntityPropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Nette\PhpGenerator\PhpFile;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use ReflectionClass;

#[AsCommand(
    name: 'ef:entity',
    description: '将模型文件初始化到数据库',
)]
class EfInitEntityCommand extends Command
{

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('--init', null, InputOption::VALUE_NONE, '初始化模型文件到数据库')
            ->addOption('--listPerpertyGroup', null, InputOption::VALUE_NONE, '列出所有的属性分组')
            ->addOption('--entity', null, InputOption::VALUE_REQUIRED, '指定要初始化的实体类名称');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');
        $entityName = $input->getOption('entity'); // 获取 --entity 选项

        if ($arg1) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }

        /**
         * 遍历src/Entity目录的模型文件，并初始化到数据库
         */
        if ($input->getOption('init')) {
            $finder = new Finder();

            if ($entityName) {
              $finder->files()->in('src/Entity/')->name($entityName . '.php');
            } else {
              $finder->files()->in('src/Entity');
            }
            

            if ($finder->hasResults()) {
                $repo = $this->em->getRepository(EntityPropertyGroup::class);
                $root = $repo->findOneby(['type' => 'root']);

                if ($root === null) {
                    $rootET = new EntityPropertyGroup;
                    $rootET->setName('root')
                        ->setLabel('root')
                        ->setType('root');
                    $this->em->persist($rootET);
                    $this->em->flush();

                    $root = $repo->findOneby(['type' => 'root']);
                }

                $finder->files();

                $nameSpaceArr = [];
                $previousGroup = '';
                foreach ($finder as $file) {    
                    $absolutFilePath = $file->getRealPath();

                    $filePath = $absolutFilePath;
                    $file = PhpFile::fromCode(file_get_contents($filePath));
                    $key = array_key_first($file->getClasses());
                    $class = $file->getClasses()[$key];
                    $classNameTab = Str::tableize($class->getName()).'_base_info';

                    $isBusinessEntity = Str::isBusinessEntity($class->getComment());

                    if ($class->isClass() && $isBusinessEntity) {
                        $entity = new Entity();
                        $entityToken = Str::generateFieldToken();
                        $entityClass = $class->getNamespace()->getName() . '\\' . $class->getName();
                        $metaData = $this->em->getClassMetadata($entityClass);
                        $tableName = $metaData->getTableName();
                        $entity->setName($class->getName() . '.php')
                            ->setToken($entityToken)
                            ->setFqn($metaData->name)
                            ->setIsCustomized(false)
                            ->setClassName($class->getName())
                            ->setDataTableName($tableName);

                        // 判断命名空间中间的部分是否存在
                        $previousGroup = $this->isSameNameSpace($entityClass, $nameSpaceArr) ? $previousGroup : $root; // 初始父级设置为 root
                        $nameSpaceResult = $this->isNamespaceTypeEntityPropertyGroup($entityClass);
                        
                        if (is_array($nameSpaceResult) && !empty($nameSpaceResult) && !$this->isSameNameSpace($entityClass, $nameSpaceArr)) {
                            $nameSpaceArr[] = Str::removeLastWord($entityClass);
                            foreach ($nameSpaceResult as $index => $namespace) {
                                // 检查数据库中是否已存在该命名空间分组
                                $existingNamespace = $repo->findOneBy(['name' => $namespace, 'type' => 'namespace']);
                                
                                if ($existingNamespace) {
                                    // 如果存在，使用已存在的分组
                                    $previousGroup = $existingNamespace;
                                } else {
                                    // 如果不存在，创建新的分组
                                    $epg[$index] = new EntityPropertyGroup();
                                    $epg[$index]
                                        ->setName($namespace)
                                        ->setEntityToken($entityToken)
                                        ->setLabel($namespace)
                                        ->setType('namespace')
                                        ->setParent($previousGroup);

                                    // 持久化当前 group
                                    $this->em->persist($epg[$index]);
                                    
                                    // 更新 $previousGroup 为当前 group
                                    $previousGroup = $epg[$index];
                                }
                            }
                            // 在循环结束后统一调用 flush
                            $this->em->flush();
                        }

                        $dynamicClass = new $entityClass();
                        $reflectionClass = new ReflectionClass($dynamicClass::class);

                        // $properties = $metaData->getReflectionProperties();
                        $fields = $metaData->fieldMappings;

                        // 补充原fieldMappings不包含的Entity类型的字段属性
                        $associationMappings = $metaData->associationMappings;

                        foreach($associationMappings as $key=>$mappings) {
                            foreach($mappings as $mapping) {
                                $fields[$key]['fieldName'] = $mappings['fieldName'];
                                $fields[$key]['type'] = 'entity';
                                $fields[$key]['targetEntity'] = $mappings['targetEntity'];
                                switch ($mappings['type']) {
                                    case 1:
                                        $associationType = 'OneToOne';
                                        break;
                                    case 2:
                                        $associationType = 'ManyToOne';
                                        break;
                                    case 3:
                                        $associationType = "ManyToMany";
                                        break;
                                    case 4:
                                        $associationType = "OneToMany";
                                        break;
                                    default:
                                        $associationType = null;
                                        break;
                                }
                                $fields[$key]['associationType'] = $associationType;
                                $fields[$key]['scale'] = null;
                                $fields[$key]['length'] = null;
                                $fields[$key]['unique'] = null;
                                $fields[$key]['nullable'] = null;
                                $fields[$key]['precision'] = null;

                                if (array_key_exists('sourceToTargetKeyColumns', $mappings)) {
                                    $fields[$key]['fieldName'] = key($mappings['sourceToTargetKeyColumns']);
                                    $fields[$key]['targetId'] = $mappings['sourceToTargetKeyColumns'][key($mappings['sourceToTargetKeyColumns'])];
                                }
                            }
                        }

                        unset($fields['deletedAt']);
                        unset($fields['createdAt']);
                        unset($fields['updatedAt']);
                        unset($fields['createdBy']);
                        unset($fields['updatedBy']);

                        $propertyToken = Str::generateFieldToken();
                        // 初始化entity类型的属性分组
                        $entityGroup = new EntityPropertyGroup();
                        $entityGroup->setName($class->getName())
                            ->setLabel($class->getName())
                            ->setType('entity')
                            ->setToken($propertyToken)
                            ->setEntityToken($entityToken)
                            ->setFqn($metaData->name)
                            ->setParent($previousGroup);

                        $this->em->persist($entityGroup);
                        $this->em->flush();

                        /**
                         * 将Entity中的字段汇总到$fields数组中
                         * 默认包含的字段，包括：
                         * fieldName Entity字段名称
                         * type 包括 string, integer
                         * scale
                         * length 字符串长度
                         * unique 唯一性
                         * nullable 可为空
                         * precision 精确度
                         * columnName mysql 数据库中的字段名称
                         * ---------------------------------
                         * 关联查询的字段默认不在此变量，前边已经
                         * 处理加入此变量，其字段包括：
                         * fieldName Entity字段名称
                         * type 值固定为 "EntityType"
                         * targetEntity 如 "App\Entity\Organization\Company"
                         * associationType 原type重命名为此变量名
                         *     1 --> OneToOne
                         *     2 --> ManyToOne
                         *     3 --> ManyToMany(maybe)
                         *     4 --> OneToMany
                         */
                        // dump($fields);
                        foreach ($fields as $key => $field) {
                            $fieldName = $key;
                            $annotationField = $reflectionClass->getProperty($fieldName);
                            $attributes = $annotationField->getAttributes(Ef::class);

                            $group = null;
                            $isBusinessField = false;
                            if (!empty($attributes)) {
                                /** @var Ef $anno */
                                $anno = $attributes[0]->newInstance();
                                $group = $anno->group;
                                $isBusinessField = $anno->isBF;
                            }

                            if ($isBusinessField) {
                                $property = new EntityProperty();
                                $propertyToken = Str::generateFieldToken();

                                $comment = Str::getComment($class->getProperties()[$fieldName]->getComment());
                                // if (!array_key_exists('columnName', $field)) {
                                //     dump($field);
                                // }
                                $property->setToken($propertyToken)
                                    ->setIsCustomized(false)
                                    ->setPropertyName($fieldName)
                                    ->setComment($comment)
                                    ->setType($field['type'])
                                    ->setFieldName($field['fieldName'])
                                    ->setUniqueable($field['unique'])
                                    ->setNullable($field['nullable'])
                                    ->setBusinessField(true)
                                    ;

                                // 如果是entity类型就额外设置目标id和目标entity，以及formType
                                $targetIdExists = ['ManyToOne', 'OneToOne'];
                                if ($field['type'] == 'entity') {
                                    if (in_array($field['associationType'], $targetIdExists)) {
                                        $property->setTargetId($field['targetId']);
                                    }

                                    $property->setTargetEntity($field['targetEntity']);

                                    if ($field['targetEntity'] === 'App\Entity\Organization\Department') {
                                        $property->setType('department');
                                    }

                                    // 根据targetEntity获得formType
                                    $property->setFormType(Str::convertFormTypeFromTargetEntity($field['targetEntity']));
                                }

                                if ($field['precision'] !== null) {
                                    $property->setDecimalPrecision($field['precision']);
                                }

                                if ($field['scale'] !== null) {
                                    $property->setDecimalScale($field['scale']);
                                }

                                if ($field['length'] !== null) {
                                    $property->setLength($field['length']);
                                }

                                // Entity 下的 Property
                                if ($group === null) {
                                    $entityProperty = new EntityPropertyGroup();
                                    $entityProperty->setName($fieldName)
                                        ->setLabel($fieldName)
                                        ->setType("property")
                                        ->setToken($propertyToken)
                                        ->setParent($entityGroup);
                                    $this->em->persist($entityProperty);
                                }

                                // Entity 下的 Group
                                if ($group !== null) {
                                    $propertyGroup = $repo->findOneBy(['name' => $group]);
                                    if ($propertyGroup === null) {
                                        $propertyGroup = new EntityPropertyGroup();
                                        $propertyGroup->setName($group)
                                           ->setLabel($group)
                                           ->setType('group')
                                           ->setEntityToken($entityToken)
                                           ->setParent($entityGroup);
                                        if ($group === $classNameTab) {
                                            $propertyGroup->setIsDefault(true);
                                        }
                                        $this->em->persist($propertyGroup);
                                        $this->em->flush();

                                        $propertyGroup = $repo->findOneBy(['name' => $group]);
                                    }

                                    // Group 下的 Property
                                    $groupProperty = new EntityPropertyGroup();
                                    $groupProperty->setName($fieldName)
                                        ->setLabel($fieldName)
                                        ->setType('property')
                                        ->setEntityToken($entityToken)
                                        ->setToken($propertyToken)
                                        ->setParent($propertyGroup);

                                    $this->em->persist($groupProperty);
                                    $property->setGroup($propertyGroup);
                                    // $this->em->flush();
                                }

                                $entity->addProperty($property);
                                $this->em->persist($property);
                                // $this->em->flush();
                            }
                        }

                        $this->em->persist($entity);
                        $this->em->flush();
                    }
                }
            }
        }

        $io->success('已成功初始化所有Entity文件到数据库');

        return Command::SUCCESS;
    }

    /**
     * 判断targetEntity是否为命名空间类型的EntityPropertyGroup
     *
     * @param string $targetEntity 命名空间全名
     * @return array
     */
    private function isNamespaceTypeEntityPropertyGroup(string $targetEntity): array
    {
        // 去掉命名空间前缀
        $trimmedEntity = str_replace('App\Entity\\', '', $targetEntity);
        // 使用 \ 分割字符串
        $entityParts = explode('\\', $trimmedEntity);
        // 去掉最后一个单词
        array_pop($entityParts);

        // 只要 $entityParts 有值就返回 true
        return $entityParts;
    }

    /**
     * 判断类是否与存储的命名空间重复了
     *
     * @param string $class example 'App\Entity\Organization\Corporation'
     * @param array $nameSpaces ['App\Entity\Organization']
     * @return boolean
     */
    private function isSameNameSpace(string $class, array $nameSpaces)
    {
        // 首先去掉$class的最后一个单词
        $namespace = Str::removeLastWord($class);
        return in_array($namespace, $nameSpaces);
    }
}
