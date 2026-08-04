<?php

namespace App\Service\Utils;

use DOMDocument;
use DOMElement;
use DOMNode;
use Symfony\Component\DomCrawler\Crawler;

class DomManipulator
{
    private DOMDocument $dom;
    private Crawler $crawler;

    public function load(string $html): self
    {
        $this->dom = new DOMDocument();
        libxml_use_internal_errors(true);
        // 用 UTF-8 声明前缀加载，避免 mb_convert_encoding(HTML-ENTITIES) 丢弃补充平面字符（如 🏮 灯笼等 emoji）
        $this->dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $this->crawler = new Crawler($this->dom);
        return $this;
    }

    public function removeClass(string $selector, string $class): self
    {
        $this->crawler->filter($selector)->each(function (Crawler $node) use ($class) {
            $el = $node->getNode(0);
            if ($el instanceof DOMElement && $el->hasAttribute('class')) {
                $classes = explode(' ', $el->getAttribute('class'));
                $classes = array_filter($classes, fn($c) => $c !== $class);
                $el->setAttribute('class', implode(' ', $classes));
            }
        });
        return $this;
    }

    public function addClass(string $selector, string $class): self
    {
        $this->crawler->filter($selector)->each(function (Crawler $node) use ($class) {
            $el = $node->getNode(0);
            if ($el instanceof DOMElement) {
                $classes = explode(' ', $el->getAttribute('class') ?? '');
                if (!in_array($class, $classes)) {
                    $classes[] = $class;
                    $el->setAttribute('class', implode(' ', $classes));
                }
            }
        });
        return $this;
    }

    public function remove(string $selector): self
    {
        $this->crawler->filter($selector)->each(function (Crawler $node) {
            $el = $node->getNode(0);
            if ($el instanceof DOMNode && $el->parentNode) {
                $el->parentNode->removeChild($el);
            }
        });
        return $this;
    }

    public function append(string $selector, string $html): self
    {
        $this->crawler->filter($selector)->each(function (Crawler $node) use ($html) {
            $el = $node->getNode(0);
            if ($el instanceof DOMElement) {
                $fragment = $this->dom->createDocumentFragment();
                $fragment->appendXML($html);
                $el->appendChild($fragment);
            }
        });
        return $this;
    }

    public function prepend(string $selector, string $html): self
    {
        $this->crawler->filter($selector)->each(function (Crawler $node) use ($html) {
            $el = $node->getNode(0);
            if ($el instanceof DOMElement) {
                $fragment = $this->dom->createDocumentFragment();
                $fragment->appendXML($html);
                $el->insertBefore($fragment, $el->firstChild);
            }
        });
        return $this;
    }

    public function setAttribute(string $selector, string $attr, string $value): self
    {
        $this->crawler->filter($selector)->each(function (Crawler $node) use ($attr, $value) {
            $el = $node->getNode(0);
            if ($el instanceof DOMElement) {
                $el->setAttribute($attr, $value);
            }
        });
        return $this;
    }

    public function html(): string
    {
        $full = $this->dom->saveHTML();
        $bodyStart = strpos($full, '<body>');
        $bodyEnd = strpos($full, '</body>');
        return ($bodyStart !== false && $bodyEnd !== false)
            ? substr($full, $bodyStart + 6, $bodyEnd - $bodyStart - 6)
            : $full;
    }
    
    /**
     * 获取处理后的HTML
     */
    public function getHtml(): string
    {
        return $this->html();
    }
    
    /**
     * 处理表格单元格，移除特定的边框样式和属性
     */
    public function processTableCells(): self
    {
        $this->crawler->filter('td, th')->each(function (Crawler $node) {
            $el = $node->getNode(0);
            if ($el instanceof DOMElement) {
                // 移除特定的边框样式
                if ($el->hasAttribute('style')) {
                    $style = $el->getAttribute('style');
                    if (strpos($style, 'border: 1px dashed rgb(213, 216, 220)') !== false) {
                        $style = str_replace('border: 1px dashed rgb(213, 216, 220)', '', $style);
                        $el->setAttribute('style', $style);
                    }
                }
                
                // 移除特定的属性
                $attributesToRemove = ['data-table-keys', 'contenteditable', 'data-cell-active', 'key-press', 'key-event', 'key-scope'];
                foreach ($attributesToRemove as $attr) {
                    if ($el->hasAttribute($attr)) {
                        $el->removeAttribute($attr);
                    }
                }
            }
        });
        return $this;
    }
    
    /**
     * 处理动态字段，将特定标记的DOM转换为Twig变量
     */
    public function processDynamicFields(): self
    {
        // 处理简单的动态字段 data-dynamic
        $this->crawler->filter('[data-dynamic]')->each(function (Crawler $node) {
            $el = $node->getNode(0);
            if ($el instanceof DOMElement) {
                $fieldName = $el->getAttribute('data-dynamic');
                $el->removeAttribute('data-dynamic');
                
                // 创建新的文本节点，包含Twig变量
                $twigVar = $this->dom->createTextNode("{{ {$fieldName} }}");
                
                // 清空当前节点内容并添加Twig变量
                while ($el->firstChild) {
                    $el->removeChild($el->firstChild);
                }
                $el->appendChild($twigVar);
            }
        });
        
        // 处理循环字段 data-repeat
        $this->crawler->filter('[data-repeat]')->each(function (Crawler $node) {
            $el = $node->getNode(0);
            if ($el instanceof DOMElement) {
                $collectionName = $el->getAttribute('data-repeat');
                $el->removeAttribute('data-repeat');
                
                // 保存原始内容
                $innerHtml = '';
                foreach ($el->childNodes as $child) {
                    $innerHtml .= $this->dom->saveHTML($child);
                }
                
                // 清空当前节点内容
                while ($el->firstChild) {
                    $el->removeChild($el->firstChild);
                }
                
                // 添加Twig循环开始标记
                $startLoop = $this->dom->createTextNode("{% for item in {$collectionName} %}");
                $el->appendChild($startLoop);
                
                // 添加原始内容的文档片段
                $fragment = $this->dom->createDocumentFragment();
                $fragment->appendXML($innerHtml);
                $el->appendChild($fragment);
                
                // 添加Twig循环结束标记
                $endLoop = $this->dom->createTextNode("{% endfor %}");
                $el->appendChild($endLoop);
            }
        });
        
        return $this;
    }
}
