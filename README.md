# BarkHandler

## 更新日志
### Ver 0.4 (2019.7.30)
1. index.php 中移除调用 python 的部分，改为通过 PHP 实现；
2. 将 tool_func.php 文件移到 ToolFunc/ 目录下，便于管理；
3. 将日志相关的函数及配置剥离为独立的日志模块（logs.php 和 log.xml），一并移到 ToolFunc/ 目录下；
4. 新增 settings.php 即配置加载模块，支持加载 Ini 及 Xml 型配置文件；
5. 将 Bark 服务端地址及设备标识码转移到配置文件中；

Ver 0.3 (2019.7.29)
1. tool_func.php 中新增array_to_string()函数，支持将数组转换为易读的字符串，便于打印；
2. index.php 添加支持 config.ini 进行部分参数配置；

Ver 0.2 (2019.7.26)
1. 微调 tool_func.php 中部分函数的代码格式并加入简单注释；
2. index.php 加入验证码识别功能（支持4-8位纯数字验证码）；
3. index.php 完善部分注释；



## 待完成需求：
1. 扩展支持更多类型验证码；
2. ......
