beantalk介绍
----------

### 核心概念
    1、job
        一个需要异步处理的任务，是Beanstalkd中的基本单元，需要放在一个tube中；
        有READY, RESERVED, DELAYED, BURIED四种状态
        put的时候处于READY，如果选择延迟就DELAYED,consumer获取后处于RESERVEDl；
        消费后，可以选择是delete 删除job还是bury休眠job
        
    2、tube
        一个有名的任务队列，用来存储统一类型的job，是producer和consumer操作的对象。
        
    3、producer
        Job的生产者，通过put命令来将一个job放到一个tube中。
     
    3、consumer
        ob的消费者，通过reserve/release/bury/delete命令来获取job或改变job的状态
        
### 特性
    1、优先级
        支持0到2**32的优先级，值越小，优先级越高，默认优先级为1024
        
    2、持久化
        可以通过binlog将job及其状态记录到文件里面，在Beanstalkd下次启动时可以通过读取binlog来恢复之前的job及状态。
        
    3、分布式容错
        分布式设计和Memcached类似，beanstalkd各个server之间并不知道彼此的存在，都是通过client来实现分布式以及根据tube名称去特定server获取job。
        
    4、超时控制
        为了防止某个consumer长时间占用任务但不能处理的情况，Beanstalkd为reserve操作设置了timeout时间，如果该consumer不能在指定时间内完成job，job将被迁移回READY状态，供其他consumer执行。
        
        
##### demo请看```test.php```