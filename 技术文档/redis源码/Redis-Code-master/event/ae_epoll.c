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


static int aeApiCreate(aeEventLoop *eventLoop) {
    aeApiState *state = zmalloc(sizeof(aeApiState));

    if (!state) return -1;
    state->events = zmalloc(sizeof(struct epoll_event)*eventLoop->setsize);
    if (!state->events) {
        zfree(state);
        return -1;
    }
    //����һ�� epoll ר�õ��ļ�����������ʵ������һ���ں˿ռ䣬������������ע�� socket fd ���Ƿ����Լ�������ʲô�¼���
    //size ����������� epoll fd ���ܹ�ע����� socket fd ������С�Զ���ֻҪ�ڴ��㹻��
    //1024���ں˱�֤�ܹ���ȷ��������������������������ʱ�ں˿ɲ���֤Ч����
    //Linux�ں˻ᴴ��һ��eventpoll�ṹ��
    state->epfd = epoll_create(1024); /* 1024 is just a hint for the kernel */
    if (state->epfd == -1) {
        zfree(state->events);
        zfree(state);
        return -1;
    }
    //���state�����ݸ�ֵ��eventLoop��API data��
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
//����¼�����
static int aeApiAddEvent(aeEventLoop *eventLoop, int fd, int mask) {
    aeApiState *state = eventLoop->apidata;
    struct epoll_event ee;
    /* If the fd was already monitored for some event, we need a MOD
     * operation. Otherwise we need an ADD operation. */
     //���fd�Ѿ�������һЩ�¼�����Ҫһ��MOD��������֮��ADD����
    int op = eventLoop->events[fd].mask == AE_NONE ?
            EPOLL_CTL_ADD : EPOLL_CTL_MOD;

    ee.events = 0;
    mask |= eventLoop->events[fd].mask; /* Merge old events */
    if (mask & AE_READABLE) ee.events |= EPOLLIN;
    if (mask & AE_WRITABLE) ee.events |= EPOLLOUT;
    ee.data.u64 = 0; /* avoid valgrind warning */
    ee.data.fd = fd;
    //����ĳ�� epoll �ļ��������ϵ��¼�
    //epfd �� epoll_create() ���� epoll ר�õ��ļ�������������� select ģ���е� FD_SET �� FD_CLR ��;
    //op������Ҫ�ѵ�ǰ����׽ӿ�fd������õ�epfd�ϱ�ȥ��һ����epoll�ṩ��������ָ����EPOLL_CTL_ADD��EPOLL_CTL_DEL��EPOLL_CTL_MOD��
    //fd: ���¼�����ʱ������Ŀ���׽ӿڡ�
    //eventָ�������Ҫ������׽ӿ�fd��ʲô�¼���
    //��Ҫ�ǵ���epoll_ctl(state->epfd,op,fd,&ee)����ǰfd���õ����������ĵ��¼�ע�ᵽepoll_create ���ص�epoll�ľ���
    //������������ע�����AE_READABLE�¼������Ե����fd(��redis�����˿ڵ��׽���)�����ݿɶ�ʱ(�����������ǿͻ������ӵ���)��
    //�ͻᴥ����Ӧ���¼���������������¼�����������acceptTcpHandler����������������
    //���ս�����socket���뵽epoll�������أ����߰� epoll���ڼ�ص�ĳ��socket����Ƴ�epoll�����ټ�����ȵȡ�
    //ÿ�ε���epoll_ctlֻ�������ں˵����ݽṹ�������µ�socket���
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
//�����˿����ӵĵ������Ѵ������׽��ַŵ�eventLoop.fired������
//����������óɹ������ض�ӦI/O����׼���õ��ļ���������Ŀ
static int aeApiPoll(aeEventLoop *eventLoop, struct timeval *tvp) {
    //����״̬
    aeApiState *state = eventLoop->apidata;
    int retval, numevents = 0;
    //����IO���Ѿ�׼���õ��ļ���������Ŀ������0��ʾ��ʱ
    //�ȴ� I/O �¼��ķ���
    //epfd: �� epoll_create() ���ɵ� epoll ר�õ��ļ�������,ϵͳĬ�ϼ�����������1024��
    //epoll_event: ���ڻش������¼������飬����events�Ƿ���õ�epoll_event�ṹ������
    //maxevents: ���ص�����¼�����maxevents��֪�ں����events�ж����� maxevents��ֵ���ܴ��ڴ���epoll_create()ʱ��size 1024
    //timeout: �ȴ� I/O �¼������ĳ�ʱֵ�����룩��
    //�ڵ���ʱ���ڸ�����timeoutʱ���ڣ����ڼ�ص����о�������¼�����ʱ���ͷ����û�̬�Ľ��̡�
    retval = epoll_wait(state->epfd,state->events,eventLoop->setsize, tvp ? (tvp->tv_sec*1000 + tvp->tv_usec/1000) : -1);
    if (retval > 0) {
        int j;

        numevents = retval;
        for (j = 0; j < numevents; j++) {
            int mask = 0;
            struct epoll_event *e = state->events+j;
            //EPOLLIN ����ʾ��Ӧ���ļ����������Զ��������Զ�SOCKET�����رգ���
            //EPOLLOUT����ʾ��Ӧ���ļ�����������д��
            //EPOLLPRI����ʾ��Ӧ���ļ��������н��������ݿɶ�������Ӧ�ñ�ʾ�д������ݵ�������
             //EPOLLERR����ʾ��Ӧ���ļ���������������
            //EPOLLHUP����ʾ��Ӧ���ļ����������Ҷϣ�
            //EPOLLET�� ��EPOLL��Ϊ��Ե����(Edge Triggered)ģʽ�����������ˮƽ����(Level Triggered)��˵�ġ�
            //EPOLLONESHOT��ֻ����һ���¼���������������¼�֮���������Ҫ�����������socket�Ļ�����Ҫ�ٴΰ����socket���뵽EPOLL������
            if (e->events & EPOLLIN) mask |= AE_READABLE;
            if (e->events & EPOLLOUT) mask |= AE_WRITABLE;
            if (e->events & EPOLLERR) mask |= AE_WRITABLE;
            if (e->events & EPOLLHUP) mask |= AE_WRITABLE;
            eventLoop->fired[j].fd = e->data.fd;
            eventLoop->fired[j].mask = mask;
        }
    }
    //epoll_wait���ش������¼�����
    return numevents;
}

static char *aeApiName(void) {
    return "epoll";
}
