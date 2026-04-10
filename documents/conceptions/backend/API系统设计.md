# 此文档考虑的是设计一套易用、图形化、代码生成式的接口管理系统

## 概念

所有的设计都将围绕json api展开。

这将是一套参照postman, eolinker等接口测试工具，拥有请求、获取响应并提供给用户图形化配置工具的
系统，与此同时又可以借助输入的请求体json、获取响应的json对齐进行标注配置，以完成生成校验文件、生成
接口文档的功能。

引入opis, justinrainbow/json-schema 这类库，把json贴到图形界面，先从后台解析生成json twig模板，
标记变量，前端解析语法高亮，对json对象, key, value增加标记功能，再把标记存到数据库，并渲染到json twig
生成shema.json文件，路径存储到数据库，再需要用这个接口时，代码生成过程利用这个schema.json
