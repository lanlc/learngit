## SeasLog是什么
### SeasLog是一个C语言编写的PHP扩展，提供一组规范标准的功能函数，在PHP项目中方便、规范、高效地写日志，以及快速地读取和查询日志。

## 一.什么是日志系统

- 记录系统运行时的信息
- 系统日志、应用程序日志、安全日志
- 日志功能不应该影响到用户的正常使用

## 二.为什么选择SeasLog

- 高性能（C语言做的 先将日志写入内存达到一定量再写入文件比频繁写入文件好）。
- 无需配置
- 功能完善
- 使用简单

## 三.安装
- 下载安装包地址：```http://pecl.php.net/package/SeasLog```
- 解压、执行PHP安装目录的phpize、配置```./configure  --with-php-config=/your php-config path```、make&&make install
- 打开php.ini，添加extension=seaslog.so
- 紧接着配置基础信息
```
seaslog.default_basepath = 'D:/WWW/log' ;默认log根目录 记着 是目录
seaslog.default_logger = default ;默认日志目录 位于default_basepath目录下的小目录
seaslog.disting_type = 1 ;是否以type分文件 1是 0否(默认) 若是 则每种级别都会单独生成日志文件
seaslog.disting_by_hour = 0 ;是否每小时划分一个文件 1是 0否(默认) 若是 则每个小时都会生成新文件
seaslog.use_buffer = 1 ;是否启用buffer 1是 0否(默认) 默认关闭,当开启此项时，日志预存于内存，当请求结束时(或异常退出时)一次写入文件。
seaslog.buffer_size = 100 ;buffer中缓冲数量 默认0(不使用)
seaslog.level = 0 ;记录日志级别 默认0(所有日志)

```

- 重启php-fpm，执行命令php -m，或者phpinfo,检查有没有SeasLog扩展


### 官方文档：```https://github.com/Neeke/SeasLog```