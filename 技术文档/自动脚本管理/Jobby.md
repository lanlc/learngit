##### Jobby是一个PHP计划任务管理器，只需要安装好Jobby，便能管理你的离线计划任务，无需编辑系统的crontab，便能增加新的计划任务，Jobby能够记录、锁定、错误信息发送至邮件等，That is cool！
##### 作者推荐使用composer去安装该源码，因为很方便
##### composer安装步骤
- cd /path/to/my/project  该目录只是一个路径问题，指明文件下载到哪里
- curl -sS https://getcomposer.org/installer | php
- 笔者安装到 /usr/local/bin下， 为了方便， ```cp composer.phar composer```，这样可以只需要使用composer命令就可以使用Composer而不需要输入php composer.phar

##### 源码安装
- composer require hellogerard/jobby 或者 composer.json中添加下面的依赖：'hellogerard/jobby': 'dev-master'，在require中
- 如果没有自动install,需要 composer install
- 系统crontab添加 ```* * * * * cd /path/to/project && php jobby.php 1>> /dev/null 2>&1```
- 可以到 ```vendor/hellogerard/jobby/resources/jobby.php```看实例代码，运行该文件，即可看到对应的效果，是不是很方便
- TP目录下面就安装了可用的源码，直接下载就可以使用