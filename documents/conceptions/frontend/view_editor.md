# 视图编辑器

视图编辑器是为twig, form等框架MVC中国的View层而设计的可视化工具，它能用来快速构建表单、页面等最终给用户呈现的界面。

## 视图编辑器的基本功能

左侧是组件库和数据源库，中间是画布，右侧是属性面板。

![视图编辑器](/documents/conceptions/assets/view_editor.jpg)

画布由不同的section构成，可以通过点击画布右下角的加号添加section。每个section中通过点击上方的加号添加grid布局组件，也可以通过左侧的表格进行拖拽形成表格用于布局。其中表格组件可以使用上方的表格格式化工具栏进行单元格合并、拆分、文字对齐、修改字体等等操作。

每个section可以通过右侧属性面板对当前激活状态的section进行属性设置，包括如下属性：

- Content Width: Full Width(全宽模式)，Boxed(盒子模式)。
  - 全宽模式：容器内部完全铺满（适合Banner, Hero布局等视觉冲击比较强的大图、大标题区域），CSS效果是 width: 100%，不设置 max-width。可以手动设置Padding（内边距）来控制内容不要贴边。
  - 盒子模式：section的宽度有限，可以设置最大宽度，比如max-width: 1140px，并且左右有自动的margin: auto，内容在容器中间显示，适合正文、文章区域、产品介绍去等，让阅读体验更集中、更舒服。width宽度的单位包括[px、%、vw](css基本概念.md)

在每个section的btn-add被点击，或者在布局组件被拖拽到section-content时就弹出一个modal弹窗，
弹窗内容是 SELECT YOUR STRUCTURE，两行包括

第一行
1， 一行一列 24
2，一行两列 12 12
3，一行三列 8 8 8
4，一行四列 6 6 6 6
5，一行两列 9 15
6，一行两列 15 9

第二行
7，一行三列 6 6 12
8，一行三列 12 6 6
9，一行三列 6 12 6
10，一行五列 x x x x x
11, 一行六列 4 4 4 4 4 4
12，一行三列 4 16 4

这些不以文字描述，而是以图形形式表现，我准备div css的形式来展现，就用grid分割这些图形，每个都是长方形，再以这些要求用灰色的块呈现出来。

## 视图保存的基本逻辑

视图编辑器将肩负两种功能，一种是普通的视图，它类似CMS的功能，将内容（静态或动态）的呈现给用户，另一种是表单视图，它将主体是表单的视图呈现给用户。

在视图编辑器点击保存图标（id="save-view-button"）时，前端将视图编辑器的URL中的id(/admin/platform/view/editor/f8914ab2-b300-488e-ab76-ce1e19518eb5 即f8914ab2-b300-488e-ab76-ce1e19518eb5的部分)作为参数1，和整个canvas的html代码作为参数2，通过ajax请求放到后端控制器，在控制器将传入的内容存成两份twig文件，第一份文件是设计器视图，第二份文件是可执行视图。下边我们将逐一解释这两个视图文件。

### 设计器视图文件

设计器视图文件包含了整个视图编辑器画布的所有内容，设计器视图文件的作用是为了方便开发者在后期进行二次编辑视图。控制器在收到请求后，先通过传入的视图id，通过[view模型](/src/Entity/Platform/View.php)查询出视图path（如 组织架构/post_management/1_0），那么最终视图文件的路径是这样存储的 `/templates/views/组织架构/post_management/1_0/post_management.design.twig` ，下边的可执行视图文件也在这个路径内，文件名是`post_management.html.twig`。通过 Symfony Filesystem Component 将传入的 canvas html 内容写入到 `/templates/views/组织架构/post_management/1_0/post_management.design.twig` 文件中。

### 可执行视图文件

可执行视图文件用来在生产环境中呈现视图。在与上边相同的请求控制中，将请求传入的 canvas html 通过基于Symfony DomCrawler Component封装的[DomManipulator](/src/Service/Utils/DomManipulator.php)将画布和有用的内容提取出来，去掉设计器的辅助dom，只保留可执行的视图内容，也是最终呈现给用户的视图。

是通过特殊的处理将类似添加section按钮这种设计器的部分dom去掉，以及将特殊标记的dom作为变量来替代为twig变量和代码，最终将处理好的html代码写入到 `/templates/views/组织架构/post_management/1_0/post_management.html.twig` 文件中。

首先，去掉特定的dom，包括：

- .add-section-button
- .section-header

然后，遍历dom，去掉特定的class, 包括：

- ui-droppable
- class="section active" 里的 active
- ui-droppable
- ef-component-labels

与此同时，遍历中去掉特定的dom属性，包括：

- data-table-keys
- contenteditable
- data-cell-active
- key-press
- key-event
- key-scope

与此同时，遍历中如果是`<td>`dom，那么当它有border: 1px dashed rgb(213, 216, 220);这个特定的css样式时，将它的border属性去掉。

把未来会用到的动态字段用 Twig 语法替换进去
比如：

```twig
<div>{{ dynamic_field }}</div>
```

```twig
<table>{% for row in table_data %}<tr><td>{{ row.value }}</td></tr>{% endfor %}</table>
```

这一步你可以设计一种前端约定，比如：

```html
<div data-dynamic="dynamic_field"></div>
```

后台发现 data-dynamic，就替换为

```twig
{{ dynamic_field }} 
```

甚至更复杂的，用 data-repeat="table_data"，后台替换成

```twig
{% for row in table_data %}...{% endfor %}
```

# 表格组件逻辑

## 关于选取单元格的逻辑

### 当有合并单元格的情况

<!-- 给我做一个markdown表格 -->
| td1 | td2 |
|-----|-----|
| td3 | td4 |

示例1

当td1和td3合并后:
td1的坐标是(0, 0), colspan=1, rowspan=2
td2的坐标(0, 1), colspan=1, rowspan=1
td3的坐标(1, 0), colspan=1, rowspan=1 display: none
td4的坐标(1, 1), colspan=1, rowspan=1

| td1 & td3  | td2 |
|-----------|-----|
| td1 & td3 | td4 |

当鼠标先点击td1，然后滑动到td2，实际上作用的是td1（因为td3已经隐藏），在td2释放鼠标，这时td1和td2具有data-cell-active="true"的属性了。实际上td3和td4性应该设置为data-cell-active="true"。基本逻辑是：当鼠标到td1时，判断colspan和rowspan，colspan=1 说明td1影响的横向单元格只有自己，rowspan=2 说明td1影响的纵向单元格有自己和下面的一个单元格，根据表格的坐标，推断出来td1，即(0,0)，下面的单元格是(1,0)，将这两个单元格的data-cell-active设置为true。另外，当td1存在这个rowspan=2，并且又是以这个td1为起点，那么鼠标在滑动到其他列时，那其他列应该参照这个起始的td1一样，(x, 0)，(x, 1) 都应该设置为data-cell-active="true"。

示例2

当td2和td4合并后:
td1的坐标是(0, 0), colspan=1, rowspan=1
td2的坐标(0, 1), colspan=1, rowspan=2
td3的坐标(1, 0), colspan=1, rowspan=1
td4的坐标(1, 1), colspan=1, rowspan=1 display: none

| td1  | td2 & td4 |
|------|-----------|
| td3  | td2 & td4 |

当鼠标先点击td1，然后滑动到td2，这时td1和td2都会设置data-cell-active="true"。鼠标滑动到td2时，判断出来它的rowspan=2，那么所有鼠标经过的被标记为data-cell-active="true"的坐标为(0,x)的单元格都应该将其对应的(1, x)的单元格设置为data-cell-active="true"。同时，td2坐标为(0,1)，rowspan=2, 那么它对应的(1,1)的单元格也应该设置为data-cell-active="true"。

示例3

| td1  | td2 | td3 |
|------|-----|-----|
| td4  | td5 | td6 |

合并td2和td5后

| td1  | td2 & td5 | td3 |
|------|-----------|-----|
| td4  | td2 & td5 | td6 |

当鼠标先点击td1，然后滑动到td2，再滑动到td3，这时td1, td2, td3都会设置data-cell-active="true"。参照示例2的逻辑，td5也会设置为data-cell-active="true"。当鼠标滑动到td3时，参照示例1的逻辑，td6也会设置为data-cell-active="true"。并且因为td2与td5的合并，影响了两行，所以td4也应该data-cell-active="true"。

从上边三个示例，可以得出以下结论，当鼠标选取滑动时经过存在纵向合并的单元格时，合并单元格的行和鼠标选取的列形成的矩形或方形内的所有<td>都应该设置为data-cell-active="true"。

## 数据源绑定

在“数据源”页签中，数据源目前是从单一模型读取的字段列表，可以点击字段列表的“标签”或“值”向画布容器内放置其名称与变量占位符，变量占位符是在Twig视图中的变量。

画布容器包括：

- 画布内的表格 <td> 里的 cell-content
- 画布内布局 ef-row 内的 item-block

当未绑定模型时上方有<div class="model-status text-primary mb-3">暂未绑定模型</div> 用来供用户点击，单击这个dom后弹出模态窗体用来供用户选择模型，选择模型后，该dom显示为选定模型的名称。

点击左侧“标签”的内容(datasource-content里的table第一组<td>内的内容)向画布容器插入字段标签，既有带入此字段标签名称文本的作用，也有后期批量修改的作用，比如通过修改标签的文本内容，就可以批量修改所有的字段标签。点击之后即存入文本，也存入标签变量名，后期可以通过变量名来实现前边说的批量修改的目的，也可以不影响不影响已存在的视图，让批量操作的影响可以得到可控性。

点击右侧“值”的内容(datasource-content里的table第二组<td>内的内容)向画布容器插入字段值，即视图的变量名占位的地方。
