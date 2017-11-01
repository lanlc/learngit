#### 关于php-resque，网上有很多的文档，这里只是描述一下原理以及过程
#### php-resque是一个基于redis的轻量级队列，作为类似线性表的一种结构，队列是FIFO；其原理是前台把数据压入队列中（其实是放到redis缓存中，使用的类型是列表与集合）
#### 然后在队列的另一端，有一个worker角色不断的从列表中拿出元素进行消费；
--------------------

- 前台代码基本分析
    ```Resque::setBackend``` 用来连接指定的后台数据库
    ```Resque::enqueue``` 用来入队列的，有四个参数，第一个是队列的名称，这个名称就是redis成员的key，第二个是类名，也就是要执行文件的类名，第三个是执行文件所需的参数
    在该函数中，先生成工作Id，利用了基于时间生成唯一ID的函数```uniqid```
    ```Resque_Job::create```创建一个队列任务，将队列名称压入集合中，把任务压入列表中
   
- 后台代码基本分析
    ```new Resque_Worker($queues)``` 实例化消费者，并把要执行的队列作为参数传入
    ```$worker->work($interval, $BLOCKING)``` 开始消费，参数一是多长时间检查新的任务
    在该函数中```Resque::fork()```生成一个子进程进行任务消费
    ```$this->reserve($blocking, $interval)```储备队列名称，```$this->queues()``` 获取集合成员,  循环遍历获取的成员进行处理```Resque_Job::reserve($queue)```，根据成员名称，lpop一个列表成员进行处理，返回一个```Resque_Job($queue, $payload)```类给 $job ，参数是任务类名称，任务参数
    ```Resque::fork()```在fork一个子进程执行该次消费任务```$this->perform($job)```在该函数里执行任务，```$job->perform()```,首先调用```$this->getInstance()```获取对应任务类的实例，利用工厂方法进行实例化，参数分别是任务类名称，任务参数，队列名称
    然后调用 ```$instance->perform()```执行任务，其中参数通过```$this->args```获取
    至此，一个队列名称的一个成员的一次任务消费完毕。
    
- 后台代码细节分析
    ```$this->startup()```函数调用 ```$this->registerSigHandlers()```为指定的信号安装一个新的信号处理器，调用 ```pruneDeadWorkers()```从服务中剔除停止工作的worker; 调用```registerWorker()```把worker放到redis中
    