请求缓存
==============

为了减少请求次数，将一些不经常修改的响应缓存到indexedDB中，数据结构如下

系统采用[LocalForage](https://localforage.docschina.org/)进行前端缓存的第三方库间接调用indexedDB

```json
{
  "session":"nmkk97m4a17ense1ej1u3cvncv",
  "org": {
    "singleDepartmentHtml": "xxxx"
  }
}
```
