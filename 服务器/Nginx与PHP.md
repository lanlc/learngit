一个正常的请求，到返回结果，这个流程是怎么样的呢？
-------------------------
- 客户端访问服务器的80端口
- Nginx监听80端口的数据，master进程将数据交给一个worker进程去处理
- 一个worker通过```fastcgi_pass```指定socket通信的方式(文件或IP)与FastCGI取得连接
- wrapper接收到请求Fork一个新的线程，用来调用解释器或者外部程序处理脚本并返回数据
- wrapper将返回的数据通过FastCGI接口，沿着socket回传给Nginx，然后Nginx再发送给客户端

而PHP-FPM是FastCGI的一个管理器```php-fpm.conf```的```listen_address```与Nginx配置文件```fastcgi_pass```要一致，这个就是Nginx与PHP交互的通道，也是FastCGI监听的地方
所有.php的文件都交由给通道处理

- PHP-FPM启动，生成```start_servers```个CGI子进程，子进程等待web server请求
- 发生请求，Nginx通过location指令，分配请求数据到对应的端口
- PHP-FPM选择并连接到一个子进程CIG解释器，将CGI环境变量和标准输入发送到FastCGI子进程
- 子进程处理结束后返回，关闭连接
- 等待下一个