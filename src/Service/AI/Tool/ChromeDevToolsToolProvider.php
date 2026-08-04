<?php

namespace App\Service\AI\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(name: 'cdp_inspect', description: '通过 Chrome DevTools 检查页面元素信息（标签、ID、class、文本内容、内联样式），需 Chrome 已开启远程调试', method: 'inspect')]
#[AsTool(name: 'cdp_setStyle', description: '通过 Chrome DevTools 修改页面元素的 CSS 内联样式，支持单个或多个属性，需 Chrome 已开启远程调试', method: 'setStyle')]
#[AsTool(name: 'cdp_getComputedStyle', description: '通过 Chrome DevTools 获取页面元素的计算样式值，需 Chrome 已开启远程调试', method: 'getComputedStyle')]
#[AsTool(name: 'cdp_injectCSS', description: '通过 Chrome DevTools 向页面注入全局 CSS 样式表，需 Chrome 已开启远程调试', method: 'injectCSS')]
#[AsTool(name: 'cdp_getPageHTML', description: '通过 Chrome DevTools 获取页面完整 HTML 或指定子树的 HTML 源码，需 Chrome 已开启远程调试', method: 'getPageHTML')]
#[AsTool(name: 'cdp_getCurrentUrl', description: '获取当前 Chrome 页面的 URL 地址', method: 'getCurrentUrl')]
#[AsTool(name: 'cdp_navigate', description: '导航 Chrome 到指定 URL', method: 'navigate')]
#[AsTool(name: 'cdp_setStyleByText', description: '根据文本内容查找元素并直接修改其 CSS 内联样式', method: 'setStyleByText')]
#[AsTool(name: 'cdp_addRow', description: '在视图编辑器画布的最后一行后面新增一行布局。columns 只能传 {"24": "..."}（单列全宽），禁止传多个 key 创建多列', method: 'addRow')]
#[AsTool(name: 'cdp_click', description: '通过 CDP 点击页面上的元素（按钮、菜单项等），只需 CSS 选择器', method: 'click')]
#[AsTool(name: 'cdp_setText', description: '设置元素的文本内容（替换所有子元素为纯文本）', method: 'setText')]
#[AsTool(name: 'cdp_addClass', description: '给元素添加 CSS 类名（支持 Tailwind、自定义框架类）', method: 'addClass')]
#[AsTool(name: 'cdp_removeClass', description: '移除元素的 CSS 类名', method: 'removeClass')]
#[AsTool(name: 'cdp_injectHTML', description: '在指定元素周围插入 HTML（position: beforebegin/afterbegin/beforeend/afterend）', method: 'injectHTML')]
#[AsTool(name: 'cdp_removeElement', description: '删除页面中匹配选择器的元素', method: 'removeElement')]
#[AsTool(name: 'cdp_setContent', description: '一次性整体替换 .section-content 的完整内容为新 HTML（整页重写）', method: 'setContent')]
#[AsTool(name: 'cdp_save', description: '点击视图编辑器的保存按钮来持久化所有 CDP 修改', method: 'save')]
#[AsTool(name: 'cdp_setAttr', description: '设置元素的 HTML 属性', method: 'setAttr')]
#[AsTool(name: 'cdp_removeAttr', description: '移除元素的 HTML 属性', method: 'removeAttr')]
#[AsTool(name: 'cdp_screenshot', description: '对当前页面或指定元素截图。selector 可选（截取该元素），fullPage 可选（整页长截图），保存到本地文件或返回 base64', method: 'screenshot')]
class ChromeDevToolsToolProvider
{
    private const PROXY_HOST = '127.0.0.1';
    private const PROXY_PORT = 9223;

    private function sendCDP(string $method, array $params = []): array
    {
        $payload = json_encode([
            'method' => $method,
            'params' => $params,
            'timeout' => 30000,
        ])."\n";

        $fp = @stream_socket_client(
            sprintf('tcp://%s:%d', self::PROXY_HOST, self::PROXY_PORT),
            $errno,
            $errstr,
            5
        );

        if (!$fp) {
            throw new \RuntimeException('无法连接 CDP 代理 (127.0.0.1:'.self::PROXY_PORT.')，请先运行 php bin/console ef:chrome:open 启动 Chrome 调试');
        }

        stream_set_timeout($fp, 30);
        fwrite($fp, $payload);
        $response = '';
        while (!feof($fp)) {
            $chunk = fgets($fp);
            if (false === $chunk) {
                break;
            }
            $response .= $chunk;
            if (str_ends_with(trim($chunk), '}')) {
                break;
            }
        }
        fclose($fp);

        $result = json_decode($response, true);
        if (!is_array($result)) {
            throw new \RuntimeException('CDP 代理返回数据异常');
        }

        if (isset($result['error'])) {
            throw new \RuntimeException('CDP 错误: '.$result['error']);
        }

        return $result['result'] ?? [];
    }

    private function findNodeId(string $selector): int
    {
        $doc = $this->sendCDP('DOM.getDocument');
        $result = $this->sendCDP('DOM.querySelector', [
            'nodeId' => $doc['root']['nodeId'],
            'selector' => $selector,
        ]);
        $nodeId = $result['nodeId'] ?? 0;
        if (0 === $nodeId) {
            throw new \RuntimeException("未找到选择器匹配的元素: $selector");
        }

        return $nodeId;
    }

    private function resolveObjectId(int $nodeId): string
    {
        $result = $this->sendCDP('DOM.resolveNode', ['nodeId' => $nodeId]);

        return $result['object']['objectId'];
    }

    public function __destruct()
    {
    }

    #[AsTool(
        name: 'cdp.getCurrentUrl',
        description: '获取当前 Chrome 页面的 URL 地址，用于确认是否在正确的页面上',
    )]
    public function getCurrentUrl(): array
    {
        try {
            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => 'window.location.href',
                'returnByValue' => true,
            ]);

            return ['url' => $result['result']['value'] ?? ''];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'hint' => '请先运行 php bin/console ef:chrome:open 启动 Chrome 远程调试'];
        }
    }

    #[AsTool(
        name: 'cdp.navigate',
        description: '导航 Chrome 当前页到指定 URL。修改样式前如果发现页面不对，先用此工具导航到正确页面',
    )]
    public function navigate(string $url): array
    {
        try {
            $this->sendCDP('Page.navigate', ['url' => $url]);

            return ['result' => "已导航到: $url"];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'hint' => '请先运行 php bin/console ef:chrome:open 启动 Chrome 远程调试'];
        }
    }

    #[AsTool(
        name: 'cdp.inspect',
        description: '检查页面元素信息（标签、ID、class、文本内容、内联样式），通过 CSS 选择器定位元素',
    )]
    public function inspect(string $selector): array
    {
        try {
            $nodeId = $this->findNodeId($selector);
            $objId = $this->resolveObjectId($nodeId);

            $props = $this->sendCDP('Runtime.getProperties', [
                'objectId' => $objId,
                'ownProperties' => true,
                'accessorPropertiesOnly' => false,
            ]);

            $info = ['tagName' => null, 'id' => null, 'className' => null, 'innerText' => null, 'styles' => []];
            foreach ($props['result'] ?? [] as $prop) {
                $name = $prop['name'] ?? '';
                $value = $prop['value']['value'] ?? null;
                if ('tagName' === $name) {
                    $info['tagName'] = $value;
                }
                if ('id' === $name) {
                    $info['id'] = $value;
                }
                if ('className' === $name) {
                    $info['className'] = $value;
                }
                if ('innerText' === $name) {
                    $info['innerText'] = null !== $value ? mb_substr((string) $value, 0, 200) : null;
                }
                if ('style' === $name && isset($prop['value']['preview']['properties'])) {
                    foreach ($prop['value']['preview']['properties'] as $s) {
                        $info['styles'][$s['name']] = $s['value'];
                    }
                }
            }

            return $info;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'hint' => '请先运行 php bin/console ef:chrome:open 启动 Chrome 远程调试'];
        }
    }

    #[AsTool(
        name: 'cdp.setStyle',
        description: '修改页面元素的 CSS 内联样式。selector：CSS 选择器，cssText：CSS 属性字符串（如 "color: red; font-size: 16px"），replaceAll：是否替换所有内联样式（默认 false 合并）',
    )]
    public function setStyle(string $selector, string $cssText, ?bool $replaceAll = false): array
    {
        try {
            $nodeId = $this->findNodeId($selector);
            $objId = $this->resolveObjectId($nodeId);

            $cssTextJson = json_encode($cssText);
            if ($replaceAll) {
                $script = "this.style.cssText = $cssTextJson; this.style.cssText";
            } else {
                $script = <<<JS
                (() => {
                    const pairs = $cssTextJson.match(/(?:[^;:]+:[^;]+)/g) || [];
                    for (const p of pairs) {
                        const colon = p.indexOf(':');
                        if (colon > 0) {
                            const prop = p.substring(0, colon).trim();
                            const val = p.substring(colon + 1).trim();
                            if (prop && val) this.style.setProperty(prop, val);
                        }
                    }
                    return this.style.cssText;
                })()
                JS;
            }

            $result = $this->sendCDP('Runtime.callFunctionOn', [
                'objectId' => $objId,
                'functionDeclaration' => $script,
                'returnByValue' => true,
            ]);

            $newStyle = $result['result']['value'] ?? '';

            return ['result' => "样式已更新，当前内联样式: $newStyle"];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'hint' => '请先运行 php bin/console ef:chrome:open 启动 Chrome 远程调试'];
        }
    }

    #[AsTool(
        name: 'cdp.getComputedStyle',
        description: '获取页面元素的计算样式值。selector：CSS 选择器，properties：可选，指定要获取的属性名数组',
    )]
    public function getComputedStyle(string $selector, ?array $properties = null): array
    {
        try {
            $nodeId = $this->findNodeId($selector);
            $objId = $this->resolveObjectId($nodeId);

            $propsJson = json_encode($properties ?? []);
            $script = <<<JS
            (() => {
                const cs = getComputedStyle(this);
                const target = $propsJson;
                const result = {};
                if (target && target.length > 0) {
                    for (const p of target) result[p] = cs[p];
                } else {
                    for (let i = 0; i < cs.length; i++) {
                        const name = cs[i];
                        result[name] = cs[name];
                    }
                }
                return result;
            })()
            JS;

            $result = $this->sendCDP('Runtime.callFunctionOn', [
                'objectId' => $objId,
                'functionDeclaration' => $script,
                'returnByValue' => true,
            ]);

            return $result['result']['value'] ?? [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'hint' => '请先运行 php bin/console ef:chrome:open 启动 Chrome 远程调试'];
        }
    }

    #[AsTool(
        name: 'cdp.injectCSS',
        description: '向页面注入全局 CSS 样式表。cssText：完整的 CSS 规则文本。适合在 cdp_setStyle 修改 inline style 无效时使用，注入的样式会持续生效不受编辑器重绘影响',
    )]
    public function injectCSS(string $cssText): array
    {
        try {
            $cssJson = json_encode($cssText);
            $script = <<<JS
            (() => {
                const id = '_cdp_injected_' + Date.now();
                const existing = document.getElementById(id);
                if (existing) existing.remove();
                const style = document.createElement('style');
                style.id = id;
                style.textContent = $cssJson;
                document.head.appendChild(style);
                return 'CSS injected: ' + id;
            })()
            JS;

            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => $script,
                'returnByValue' => true,
            ]);

            return ['result' => $result['result']['value'] ?? 'CSS 已注入'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'hint' => '请先运行 php bin/console ef:chrome:open 启动 Chrome 远程调试'];
        }
    }

    #[AsTool(
        name: 'cdp.getPageHTML',
        description: '获取页面完整 HTML 或指定选择器子树的 HTML',
    )]
    public function getPageHTML(?string $selector = null): array
    {
        try {
            if ($selector) {
                $selJson = json_encode($selector);
                $script = "document.querySelector($selJson)?.outerHTML ?? 'Element not found'";
            } else {
                $script = 'document.documentElement.outerHTML';
            }

            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => $script,
                'returnByValue' => true,
            ]);

            $html = $result['result']['value'] ?? '';

            return ['html' => mb_substr($html, 0, 50000)];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'hint' => '请先运行 php bin/console ef:chrome:open 启动 Chrome 远程调试'];
        }
    }

    #[AsTool(
        name: 'cdp.setStyleByText',
        description: '根据文本内容查找元素并直接修改其 CSS 内联样式。不需要知道 CSS 选择器，只需知道元素的显示文本。一次性完成查找+修改，比 cdp_getPageHTML → cdp_setStyle 两步更快',
    )]
    public function setStyleByText(string $textContent, string $cssText, ?bool $replaceAll = false): array
    {
        try {
            $textJson = json_encode($textContent);
            $marker = '_cdp_'.bin2hex(random_bytes(8));
            $markerJson = json_encode($marker);
            $cssTextJson = json_encode($cssText);

            $findScript = <<<JS
            (() => {
                const walker = document.createTreeWalker(document, NodeFilter.SHOW_ELEMENT, null, false);
                let node;
                while (node = walker.nextNode()) {
                    if (node.textContent && node.textContent.trim() === $textJson) {
                        node.setAttribute($markerJson, '1');
                        return node.outerHTML.substring(0, 300);
                    }
                }
                return null;
            })()
            JS;

            $findResult = $this->sendCDP('Runtime.evaluate', [
                'expression' => $findScript,
                'returnByValue' => true,
            ]);

            $foundHtml = $findResult['result']['value'] ?? null;
            if (!$foundHtml) {
                return ['error' => "未找到包含文本「{$textContent}」的元素"];
            }

            $doc = $this->sendCDP('DOM.getDocument');
            $qsResult = $this->sendCDP('DOM.querySelector', [
                'nodeId' => $doc['root']['nodeId'],
                'selector' => "[{$marker}]",
            ]);

            $this->sendCDP('Runtime.evaluate', [
                'expression' => "document.querySelector('[{$marker}]')?.removeAttribute('{$marker}')",
                'returnByValue' => true,
            ]);

            $nodeId = $qsResult['nodeId'] ?? 0;
            if (0 === $nodeId) {
                return ['error' => '找到文本但无法获取元素引用'];
            }

            $objResult = $this->sendCDP('DOM.resolveNode', ['nodeId' => $nodeId]);
            $objId = $objResult['object']['objectId'] ?? '';
            if (!$objId) {
                return ['error' => '无法解析元素对象'];
            }

            if ($replaceAll) {
                $styleScript = "this.style.cssText = $cssTextJson; this.style.cssText";
            } else {
                $styleScript = <<<JS
                (() => {
                    const pairs = $cssTextJson.match(/(?:[^;:]+:[^;]+)/g) || [];
                    for (const p of pairs) {
                        const colon = p.indexOf(':');
                        if (colon > 0) {
                            const prop = p.substring(0, colon).trim();
                            const val = p.substring(colon + 1).trim();
                            if (prop && val) this.style.setProperty(prop, val);
                        }
                    }
                    return this.style.cssText;
                })()
                JS;
            }

            $result = $this->sendCDP('Runtime.callFunctionOn', [
                'objectId' => $objId,
                'functionDeclaration' => $styleScript,
                'returnByValue' => true,
            ]);

            $newStyle = $result['result']['value'] ?? '';

            return ['result' => "已找到文本「{$textContent}」的元素并应用样式，当前内联样式: {$newStyle}", 'elementHtml' => $foundHtml];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'hint' => '请先运行 php bin/console ef:chrome:open 启动 Chrome 远程调试'];
        }
    }

    #[AsTool(
        name: 'cdp.click',
        description: '点击页面上的元素，只需 CSS 选择器。可用于点击按钮、打开弹窗、切换标签等交互操作',
    )]
    public function click(string $selector): array
    {
        try {
            $selJson = json_encode($selector);
            $script = <<<JS
            (() => {
                const el = document.querySelector($selJson);
                if (!el) return '未找到元素: $selJson';
                el.click();
                return '已点击: $selJson';
            })()
            JS;
            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => $script,
                'returnByValue' => true,
            ]);

            return ['result' => $result['result']['value'] ?? '已点击'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'hint' => '请先运行 php bin/console ef:chrome:open 启动 Chrome 远程调试'];
        }
    }

    #[AsTool(
        name: 'cdp.addRow',
        description: '在视图编辑器画布的最后一行后面新增一行布局。columns 只能传 {"24": "..."}（单列全宽），禁止传多个 key。生成的容器无边框无内边距，适合放入自由 HTML 布局',
    )]
    public function addRow(string $sectionContentSelector = '.section-content', array $columns = ['24' => '新文本']): array
    {
        try {
            $colsJson = json_encode($columns);
            $random = '_cdp_r_'.bin2hex(random_bytes(6));
            $script = <<<JS
            (() => {
                const section = document.querySelector('$sectionContentSelector');
                if (!section) return '未找到 section-content';
                const cols = $colsJson;
                const id = '$random';
                let rowHtml = '';
                for (const [span, text] of Object.entries(cols)) {
                    const colClass = 'ef-col-24';
                    const compId = 'ef-text-comp-' + id + '_' + Math.random().toString(36).substr(2, 8);
                    rowHtml += '<div class="ef-row ef-row-align-start ef-row-justify-start" style="width: 100%;">';
                    rowHtml += '<div class="' + colClass + ' item-block ui-droppable" style="padding: 0; border: none; min-height: 0;">';
                    rowHtml += '<div class="font_2 ef-rich-text" contenteditable="true" data-placeholder="请输入文本">';
                    rowHtml += '<span>' + text + '</span></div></div></div>';
                }
                const lastRow = section.querySelector('.ef-row:last-child');
                if (lastRow) {
                    lastRow.insertAdjacentHTML('afterend', rowHtml);
                } else {
                    section.insertAdjacentHTML('beforeend', rowHtml);
                }
                return '已添加 ' + Object.keys(cols).length + ' 行';
            })()
            JS;

            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => $script,
                'returnByValue' => true,
            ]);

            return ['result' => $result['result']['value'] ?? '行已添加'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'hint' => '请先运行 php bin/console ef:chrome:open 启动 Chrome 远程调试'];
        }
    }

    #[AsTool(name: 'cdp.setText', description: '设置元素的文本内容（替换所有子元素为纯文本）')]
    public function setText(string $selector, string $text): array
    {
        try {
            $selJson = json_encode($selector);
            $textJson = json_encode($text);
            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => "document.querySelector($selJson).textContent = $textJson; document.querySelector($selJson).textContent.substring(0,100)",
                'returnByValue' => true,
            ]);

            return ['result' => '文本已设置为: '.($result['result']['value'] ?? '')];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    #[AsTool(name: 'cdp.addClass', description: '给元素添加 CSS 类名（支持 Tailwind、自定义框架类）')]
    public function addClass(string $selector, string $className): array
    {
        try {
            $selJson = json_encode($selector);
            $clsJson = json_encode($className);
            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => "document.querySelector($selJson)?.classList.add($clsJson); document.querySelector($selJson)?.className",
                'returnByValue' => true,
            ]);

            return ['result' => '类名已添加，当前 class: '.($result['result']['value'] ?? '')];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    #[AsTool(name: 'cdp.removeClass', description: '移除元素的 CSS 类名')]
    public function removeClass(string $selector, string $className): array
    {
        try {
            $selJson = json_encode($selector);
            $clsJson = json_encode($className);
            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => "document.querySelector($selJson)?.classList.remove($clsJson); document.querySelector($selJson)?.className",
                'returnByValue' => true,
            ]);

            return ['result' => '类名已移除，当前 class: '.($result['result']['value'] ?? '')];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    #[AsTool(name: 'cdp.injectHTML', description: '在指定元素周围插入 HTML（position: beforebegin/afterbegin/beforeend/afterend）')]
    public function injectHTML(string $selector, string $position, string $html): array
    {
        try {
            $selJson = json_encode($selector);
            $htmlJson = json_encode($html);
            $allowed = ['beforebegin', 'afterbegin', 'beforeend', 'afterend'];
            if (!in_array($position, $allowed)) {
                return ['error' => 'position 必须是: '.implode(', ', $allowed)];
            }
            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => "document.querySelector($selJson)?.insertAdjacentHTML('$position', $htmlJson); 'HTML 已插入'",
                'returnByValue' => true,
            ]);

            return ['result' => $result['result']['value'] ?? 'HTML 已插入'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    #[AsTool(name: 'cdp.removeElement', description: '删除页面中匹配选择器的所有元素（支持子选择器，如 .section-content > *）')]
    public function removeElement(string $selector): array
    {
        try {
            $selJson = json_encode($selector);
            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => "const els=document.querySelectorAll($selJson);const n=els.length;els.forEach(el=>el.remove());'已删除 '+n+' 个元素'",
                'returnByValue' => true,
            ]);

            return ['result' => $result['result']['value'] ?? '已删除'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 整体替换 .section-content 的完整内容：一次性写入整页（等价于文件直写的 writeDesign），
     * 避免增量编辑导致"描述但不执行"。
     */
    #[AsTool(name: 'cdp.setContent', description: '一次性整体替换 .section-content 的完整内容为新 HTML（整页重写，用于整体重构时直接给出完整新布局）')]
    public function setContent(string $html): array
    {
        try {
            $selJson = json_encode('.section-content');
            $htmlJson = json_encode($html);
            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => "(function(){ const c=document.querySelector($selJson); if(!c) return '未找到 .section-content'; c.innerHTML = $htmlJson; return '已整体替换 .section-content 内容，子元素数='+c.childElementCount; })()",
                'returnByValue' => true,
            ]);

            return ['result' => $result['result']['value'] ?? '已整体替换'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    #[AsTool(name: 'cdp.save', description: '点击视图编辑器的保存按钮来持久化所有 CDP 修改')]
    public function save(): array
    {
        try {
            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => <<<JS
                (() => {
                    const btn = document.querySelector('#save-view-button, #save-view-btn, .btn-save, [data-action="save"], button:has(.fa-save)');
                    if (btn) { btn.click(); return '已点击保存按钮'; }
                    return '未找到保存按钮';
                })()
                JS,
                'returnByValue' => true,
            ]);

            return ['result' => $result['result']['value'] ?? ''];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    #[AsTool(name: 'cdp.setAttr', description: '设置元素的 HTML 属性，如 data-*、class、style 等')]
    public function setAttr(string $selector, string $attr, string $value): array
    {
        try {
            $selJson = json_encode($selector);
            $attrJson = json_encode($attr);
            $valJson = json_encode($value);
            $this->sendCDP('Runtime.evaluate', [
                'expression' => "document.querySelector($selJson)?.setAttribute($attrJson, $valJson)",
                'returnByValue' => true,
            ]);

            return ['result' => "属性 {$attr} 已设置"];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    #[AsTool(name: 'cdp.removeAttr', description: '移除元素的 HTML 属性')]
    public function removeAttr(string $selector, string $attr): array
    {
        try {
            $selJson = json_encode($selector);
            $attrJson = json_encode($attr);
            $this->sendCDP('Runtime.evaluate', [
                'expression' => "document.querySelector($selJson)?.removeAttribute($attrJson)",
                'returnByValue' => true,
            ]);

            return ['result' => "属性 {$attr} 已移除"];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 对当前页面或指定元素截图。
     *
     * @param string|null $selector 可选，CSS 选择器，指定则只截取该元素
     * @param bool        $fullPage 可选，是否整页长截图（默认 false，只截当前视口）
     * @param string|null $savePath 可选，本地保存路径，传了则写入文件并返回路径，否则返回 base64
     * @param string      $format   可选，图片格式 png / jpeg
     * @param int         $quality  可选，jpeg 质量 0-100
     */
    #[AsTool(name: 'cdp.screenshot', description: '对当前页面或指定元素截图，返回 base64 或保存到本地文件')]
    public function screenshot(
        ?string $selector = null,
        bool $fullPage = false,
        ?string $savePath = null,
        string $format = 'png',
        int $quality = 90,
    ): array {
        try {
            $params = [
                'format' => 'jpeg' === $format ? 'jpeg' : 'png',
            ];
            if ('jpeg' === $format) {
                $params['quality'] = min(100, max(1, $quality));
            }

            if ($fullPage) {
                $metrics = $this->sendCDP('Page.getLayoutMetrics');
                $contentSize = $metrics['cssContentSize'] ?? null;
                if ($contentSize) {
                    $params['captureBeyondViewport'] = true;
                    $params['clip'] = [
                        'x' => 0,
                        'y' => 0,
                        'width' => $contentSize['width'] ?? 1280,
                        'height' => $contentSize['height'] ?? 800,
                        'scale' => 1,
                    ];
                }
            } elseif ($selector) {
                $nodeId = $this->findNodeId($selector);
                $objId = $this->resolveObjectId($nodeId);
                $box = $this->sendCDP('DOM.getBoxModel', ['nodeId' => $nodeId]);
                $quad = $box['model']['border'] ?? null;
                if (!$quad) {
                    return ['error' => "元素不可见或无边界框: $selector"];
                }
                $params['clip'] = [
                    'x' => $quad[0],
                    'y' => $quad[1],
                    'width' => max(1, $quad[2] - $quad[0]),
                    'height' => max(1, $quad[5] - $quad[1]),
                    'scale' => 1,
                ];
            }

            $result = $this->sendCDP('Page.captureScreenshot', $params);
            $base64 = $result['data'] ?? '';
            if ('' === $base64) {
                return ['error' => '截图返回为空'];
            }

            $imageData = base64_decode($base64);
            if ($savePath) {
                $dir = dirname($savePath);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                file_put_contents($savePath, $imageData);

                return [
                    'result' => "截图已保存: $savePath",
                    'path' => $savePath,
                    'bytes' => strlen($imageData),
                    'url' => $this->getPageUrlForLog(),
                ];
            }

            return [
                'result' => '截图成功',
                'base64' => $base64,
                'bytes' => strlen($imageData),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'hint' => '请先运行 php bin/console ef:chrome:open 启动 Chrome 远程调试'];
        }
    }

    private function getPageUrlForLog(): string
    {
        try {
            $result = $this->sendCDP('Runtime.evaluate', [
                'expression' => 'window.location.href',
                'returnByValue' => true,
            ]);

            return (string) ($result['result']['value'] ?? '');
        } catch (\Exception $e) {
            return '';
        }
    }
}
