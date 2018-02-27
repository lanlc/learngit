# Supervisor是一个进程管理器，方便管理linux进程

### 安装
- 运行命令```yum install python-setuptools```、```easy_install supervisor```

- 配置Supervisor
    1. ```mkdir /etc/supervisor```
    2. ```echo_supervisord_conf > /etc/supervisor/supervisord.conf```

- 配置好include文件，每次都要reload才会生效

### 子进程管理(supervisorctl)
- 查看所有子进程的状态： supervisorctl status
- 关闭、开启指定的子进程：supervisorctl stop DocName supervisorctl start DocName
- 关闭、开启所有的子进程：supervisorctl stop all    supervisorctl start all