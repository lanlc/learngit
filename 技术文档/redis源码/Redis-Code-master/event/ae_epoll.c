/* Linux epoll(2) based ae.c module
 *
 * Copyright (c) 2009-2012, Salvatore Sanfilippo <antirez at gmail dot com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in the
 *     documentation and/or other materials provided with the distribution.
 *   * Neither the name of Redis nor the names of its contributors may be used
 *     to endorse or promote products derived from this software without
 *     specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */


#include <sys/epoll.h>

typedef struct aeApiState {
    int epfd;
    struct epoll_event *events;
} aeApiState;

static int aeApiCreate(aeEventLoop *eventLoop)
static int aeApiResize(aeEventLoop *eventLoop, int setsize)
static void aeApiFree(aeEventLoop *eventLoop)
static int aeApiAddEvent(aeEventLoop *eventLoop, int fd, int mask)
static void aeApiDelEvent(aeEventLoop *eventLoop, int fd, int delmask)
static int aeApiPoll(aeEventLoop *eventLoop, struct timeval *tvp)
static char *aeApiName(void)

//调用IO多路复用
static int aeApiCreate(aeEventLoop *eventLoop) {
    aeApiState *state = zmalloc(sizeof(aeApiState));

    if (!state) return -1;
    state->events = zmalloc(sizeof(struct epoll_event)*eventLoop->setsize);
    if (!state->events) {
        zfree(state);
        return -1;
    }
    //生成一个 epoll 专用的文件描述符，其实是申请一个内核空间，用来存放你想关注的 socket fd 上是否发生以及发生了什么事件。
    //size 就是你在这个 epoll fd 上能关注的最大 socket fd 数，大小自定，只要内存足够。
    //1024是内核保证能够正确处理的最大句柄数，多于这个最大数时内核可不保证效果。
    //Linux内核会创建一个eventpoll结构体
    //占用一个fd值
    state->epfd = epoll_create(1024); /* 1024 is just a hint for the kernel */
    if (state->epfd == -1) {
        zfree(state->events);
        zfree(state);
        return -1;
    }
    //最后将state的数据赋值到eventLoop的API data中
    eventLoop->apidata = state;
    return 0;
}

static int aeApiResize(aeEventLoop *eventLoop, int setsize) {
    aeApiState *state = eventLoop->apidata;

    state->events = zrealloc(state->events, sizeof(struct epoll_event)*setsize);
    return 0;
}

static void aeApiFree(aeEventLoop *eventLoop) {
    aeApiState *state = eventLoop->apidata;

    close(state->epfd);
    zfree(state->events);
    zfree(state);
}
//添加事件机制
static int aeApiAddEvent(aeEventLoop *eventLoop, int fd, int mask) {
    aeApiState *state = eventLoop->apidata;
    struct epoll_event ee;
    /* If the fd was already monitored for some event, we need a MOD
     * operation. Otherwise we need an ADD operation. */
     //如果fd已经监视了一些事件，需要一个MOD操作，反之是ADD操作
    int op = eventLoop->events[fd].mask == AE_NONE ?
            EPOLL_CTL_ADD : EPOLL_CTL_MOD;

    ee.events = 0;
    mask |= eventLoop->events[fd].mask; /* Merge old events */
    if (mask & AE_READABLE) ee.events |= EPOLLIN;
    if (mask & AE_WRITABLE) ee.events |= EPOLLOUT;
    ee.data.u64 = 0; /* avoid valgrind warning */
    ee.data.fd = fd;
    //控制某个 epoll 文件描述符上的事件
    //epfd 是 epoll_create() 创建 epoll 专用的文件描述符。相对于 select 模型中的 FD_SET 和 FD_CLR 宏;
    //op就是你要把当前这个套接口fd如何设置到epfd上边去，一般由epoll提供的三个宏指定：EPOLL_CTL_ADD，EPOLL_CTL_DEL，EPOLL_CTL_MOD。
    //fd: 当事件发生时操作的目标套接口。
    //event指针就是你要给这个套接口fd绑定什么事件。
    //主要是调用epoll_ctl(state->epfd,op,fd,&ee)将当前fd设置到及其所关心的事件注册到epoll_create 返回的epoll的句柄里。
    //由于我们这里注册的是AE_READABLE事件，所以当这个fd(即redis监听端口的套接字)有数据可读时(这里我理解的是客户端连接到达)，
    //就会触发相应的事件处理函数，这里的事件处理函数便是acceptTcpHandler或者其他处理函数。
    //将刚建立的socket加入到epoll中让其监控，或者把 epoll正在监控的某个socket句柄移出epoll，不再监控它等等。
    //每次调用epoll_ctl只是在往内核的数据结构里塞入新的socket句柄
    if (epoll_ctl(state->epfd,op,fd,&ee) == -1) return -1;
    return 0;
}

static void aeApiDelEvent(aeEventLoop *eventLoop, int fd, int delmask) {
    aeApiState *state = eventLoop->apidata;
    struct epoll_event ee;
    int mask = eventLoop->events[fd].mask & (~delmask);

    ee.events = 0;
    if (mask & AE_READABLE) ee.events |= EPOLLIN;
    if (mask & AE_WRITABLE) ee.events |= EPOLLOUT;
    ee.data.u64 = 0; /* avoid valgrind warning */
    ee.data.fd = fd;
    if (mask != AE_NONE) {
        epoll_ctl(state->epfd,EPOLL_CTL_MOD,fd,&ee);
    } else {
        /* Note, Kernel < 2.6.9 requires a non null event pointer even for
         * EPOLL_CTL_DEL. */
        epoll_ctl(state->epfd,EPOLL_CTL_DEL,fd,&ee);
    }
}
//监听端口连接的到来，把触发的套接字放到eventLoop.fired数组里
//如果函数调用成功，返回对应I/O上已准备好的文件描述符数目
static int aeApiPoll(aeEventLoop *eventLoop, struct timeval *tvp) {
    //监听状态
    aeApiState *state = eventLoop->apidata;
    int retval, numevents = 0;
    //返回IO上已经准备好的文件描述符数目，返回0表示超时
    //等待 I/O 事件的发生
    //epfd: 由 epoll_create() 生成的 epoll 专用的文件描述符,系统默认监听的数量是1024；
    //epoll_event: 用于回传处理事件的数组，参数events是分配好的epoll_event结构体数组
    //maxevents: 返回的最大事件数；maxevents告知内核这个events有多大，这个 maxevents的值不能大于创建epoll_create()时的size 1024
    //timeout: 等待 I/O 事件发生的超时值（毫秒）；
    //在调用时，在给定的timeout时间内，当在监控的所有句柄中有事件发生时，就返回用户态的进程。
    //epoll将会把发生的事件赋值到events数组中
    retval = epoll_wait(state->epfd,state->events,eventLoop->setsize, tvp ? (tvp->tv_sec*1000 + tvp->tv_usec/1000) : -1);
    if (retval > 0) {
        int j;

        numevents = retval;
        for (j = 0; j < numevents; j++) {
            int mask = 0;
            struct epoll_event *e = state->events+j;
            //EPOLLIN ：表示对应的文件描述符可以读（包括对端SOCKET正常关闭）；
            //EPOLLOUT：表示对应的文件描述符可以写；
            //EPOLLPRI：表示对应的文件描述符有紧急的数据可读（这里应该表示有带外数据到来）；
             //EPOLLERR：表示对应的文件描述符发生错误；
            //EPOLLHUP：表示对应的文件描述符被挂断；
            //EPOLLET： 将EPOLL设为边缘触发(Edge Triggered)模式，这是相对于水平触发(Level Triggered)来说的。
            //EPOLLONESHOT：只监听一次事件，当监听完这次事件之后，如果还需要继续监听这个socket的话，需要再次把这个socket加入到EPOLL队列里
            if (e->events & EPOLLIN) mask |= AE_READABLE;
            if (e->events & EPOLLOUT) mask |= AE_WRITABLE;
            if (e->events & EPOLLERR) mask |= AE_WRITABLE;
            if (e->events & EPOLLHUP) mask |= AE_WRITABLE;
            eventLoop->fired[j].fd = e->data.fd;
            eventLoop->fired[j].mask = mask;
        }
    }
    //epoll_wait返回触发的事件数。
    return numevents;
}

static char *aeApiName(void) {
    return "epoll";
}
