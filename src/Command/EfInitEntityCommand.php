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
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
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
            ->addOption('--listPerpertyGroup', null, InputOption::VALUE_NONE, '列出所有的属性分组');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');

        if ($arg1) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }

        /**
         * 便利src/Entity目录的模型文件，并初始化到数据库
         */
        if ($input->getOption('init')) {
            $finder = new Finder();
            $finder->files()->in('src/Entity');

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

                foreach ($finder as $file) {
                    $absolutFilePath = $file->getRealPath();
                    $fileName = $file->getRelativePathname();

                    $filePath = $absolutFilePath;
                    $file = PhpFile::fromCode(file_get_contents($filePath));
                    $key = array_key_first($file->getClasses());
                    $class = $file->getClasses()[$key];

                    $isBusinessEntity = Str::isBusinessEntity($class->getComment());

                    if ($class->isClass() && $isBusinessEntity) {
                        $entity = new Entity();
                        $entityToken = sha1(random_bytes(10));
                        $entityClass = $class->getNamespace()->getName() . '\\' . $class->getName();
                        $metaData = $this->em->getClassMetadata($entityClass);
                        $tableName = $metaData->getTableName();
                        $entity->setName($class->getName() . '.php')
                            ->setToken($entityToken)
                            ->setFqn($metaData->name)
                            ->setIsCustomized(false)
                            ->setClassName($class->getName())
                            ->setDataTableName($tableName);

                        $dynamicClass = new $entityClass();
                        $reflectionClass = new ReflectionClass($dynamicClass::class);

                        // $properties = $metaData->getReflectionProperties();
                        $fields = $metaData->fieldMappings;
                        unset($fields['deletedAt']);
                        unset($fields['createdAt']);
                        unset($fields['updatedAt']);
                        unset($fields['createdBy']);
                        unset($fields['updatedBy']);

                        // 初始化entity类型的属性分组
                        $entityGroup = new EntityPropertyGroup();
                        $entityGroup->setName($class->getName())
                            ->setLabel($class->getName())
                            ->setType('entity')
                            ->setToken($entityToken)
                            ->setFqn($metaData->name)
                            ->setParent($root);

                        $this->em->persist($entityGroup);
                        $this->em->flush();

                        foreach ($fields as $field) {
                            $fieldName = $field['fieldName'];
                            $annotationField = $reflectionClass->getProperty($fieldName);
                            $reader = new AnnotationReader();
                            $anno = $reader->getPropertyAnnotation(
                                $annotationField,
                                Ef::class
                            );

                            $group = null;
                            $isBusinessField = false;
                            if ($anno !== null) {
                                $group = $anno->getValue()['group'];
                                $isBusinessField = $anno->getValue()['bf'];
                            }

                            if ($isBusinessField) {
                                $property = new EntityProperty();
                                $propertyToken = sha1(random_bytes(10));

                                $comment = Str::getComment($class->properties[$fieldName]->getComment());
                                $property->setToken($propertyToken)
                                    ->setIsCustomized(false)
                                    ->setPropertyName($fieldName)
                                    ->setComment($comment)
                                    ->setType($field['type'])
                                    ->setFieldName($field['columnName'])
                                    ->setUniqueable($field['unique'])
                                    ->setNullable($field['nullable']);

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
                                           ->setParent($entityGroup);
                                        $this->em->persist($propertyGroup);
                                        $this->em->flush();

                                        $propertyGroup = $repo->findOneBy(['name' => $group]);
                                    }

                                    // Group 下的 Property
                                    $groupProperty = new EntityPropertyGroup();
                                    $groupProperty->setName($fieldName)
                                        ->setLabel($fieldName)
                                        ->setType('property')
                                        ->setToken($propertyToken)
                                        ->setParent($propertyGroup);

                                    $this->em->persist($groupProperty);
                                    $this->em->flush();
                                }

                                $entity->addProperty($property);
                                $this->em->persist($property);
                                $this->em->flush();
                            }
                        }

                        // $dynamicClass = new $entityClass();
                        // $reflectionClass = new ReflectionClass($dynamicClass::class);
                        // $annotationReader = new AnnotationReader();
                        // $classAnnotations = $annotationReader->getClassAnnotation($reflectionClass, 'Doctrine\ORM\Mapping\Table');
                        // dump($classAnnotations->name);

                        // $repoClass = 'App\Repository\Organization\CorporationRepository';
                        // $repoInstance = new $repoClass();

                        $this->em->persist($entity);
                        $this->em->flush();
                    }
                }
            }
        }

        if ($input->getOption('listPerpertyGroup')) {
            $repo = $this->em->getRepository(EntityPropertyGroup::class);
            $tree = $repo->childrenHierarchy();
            dump($tree);
        }

        $io->success('已成功初始化所有Entity文件到数据库');

        return Command::SUCCESS;
    }
}
