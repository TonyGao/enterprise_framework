<?php

namespace App\Lib;

use App\Form\Common\DepartmentType;
use App\Form\Common\SwitchType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Doctrine\Inflector\InflectorFactory;

class Str
{
    public static function getComment($text)
    {
        $comment = '';
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $text) as $key => $line) {
            if ($key === 0 && (substr($line, 0, 1) !== '@')) {
                $comment .= $line;
            }
        }

        return $comment;
    }

    /**
     * 通过判断有没有以isBusinessEntity开始的注释判断是不是业务实体
     */
    public static function isBusinessEntity($text)
    {
        $result = false;
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $text) as $line) {
            if (substr($line, 0, 16) === 'isBusinessEntity') {
                $result = true;
            }
        }

        return $result;
    }

    public static function convertFormType($type)
    {
        switch ($type) {
            case 'boolean':
                $class = SwitchType::class;
                break;
            case 'string':
                $class = TextType::class;
                break;
            case 'text':
                $class = TextareaType::class;
                break;
            case 'integer':
                $class = IntegerType::class;
                break;
            case 'department':
                $class = DepartmentType::class;
                break;
            case 'user':
                $class = TextType::class;
                break;
            case 'entity':
                $class = EntityType::class;
                break;
            case 'select':
                $class = ChoiceType::class;
                break;
            case 'email':
                $class = EmailType::class;
                break;
            case 'date':
                $class = DateType::class;
                break;
            case 'decimal':
                $class = NumberType::class;
                break;
            default:
                $class = TextType::class;
                break;
        }

        return $class;
    }

    /**
     * 将 Entity 类型的实体属性从targetEntity字符串转化为特定的formType
     * targetEntity字符串类似 "App\Entity\Organization\Department"
     */
    public static function convertFormTypeFromTargetEntity($targetEntity)
    {
        switch ($targetEntity) {
            case 'App\Entity\Organization\Department':
                $formType = DepartmentType::class;
                break;
            default:
                $formType = EntityType::class;
                break;
        }

        return $formType;
    }

    /**
     * 生成 Entity 字段 token 的通用方法
     */
    public static function generateFieldToken()
    {
        return sha1(random_bytes(10));
    }

    /**
     * 将驼峰名称转变为蛇形命名
     * fieldName ==> field_name
     */
    public static function tableize($name)
    {
        $inflector = InflectorFactory::create()->build();
        return $inflector->tableize($name);
    }

    /**
     * Remove last word from class namespace
     *
     * @param string $fullNamespace
     * @return string
     */
    public static function removeLastWord(string $fullNamespace)
    {
        $parts = explode('\\', $fullNamespace);
        array_pop($parts);
        $result = implode('\\', $parts);
        return $result;
    }

    /**
     * 删除文件名中的扩展名
     *
     * @param string $filename
     * @return string
     */
    public static function removeExtension(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME);
    }

    // public static function getGroup($text) {
    //     $group = '';
    //     foreach(preg_split("/((\r?\n)|(\r\n?))/", $text) as $line){
    //         if (substr($line, 0, 1) === '@EfGroup') {
    //             $group .= $line;
    //         }
    //     }

    //     return $comment;
    // }
}
