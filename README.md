# MeepoPS
###### MeepoPS是Meepo PHP Socket的缩写. 旨在提供高效稳定的由纯PHP开发的多进程SocketService.
###### MeepoPS可以轻松构建在线实时聊天, 即时游戏, 视频流媒体播放, RPC, 实时监控, 以及原本使用HTTP的接口/定时任务的场景中等.

#### 综述:
- 目前版本为V1.0.0 ( [查看改动日志](http://meepops.lanecn.com/doc/main/1-summary/7-update-log.html) ).
- PHP作为最好的语言, 不仅仅能依靠Nginx来开发Web应用, 同时,也可以构建高效稳定的即时通讯类Socket应用.
- MeepoPS的最低运行要求是安装了PHP的PCNTL库.
- MeepoPS的定位是一个插件. 不但可以独立运行, 也可以依附与ThinkPHP, CodeIgniter, YII等MVC框架中.

#### 传送门:
- 手册地址: http://meepops.lanecn.com
- Github: https://github.com/lixuancn/MeepoPS
- Bug提交: https://github.com/lixuancn/MeepoPS/issues
- 微博: http://weibo.com/lanephp

#### 声明:
- 绝大多数的PHP应用都部署在Linux服务器, 因此MeepoPS不支持非Unix操作系统(例如Windows). 你可以使用Apple Mac(OS X), CentOS, Ubuntu, Red Hat, Fedora, FreeBSD等类Unix操作系统来启动MeepoPS.
- Windows用户可以安装VirtualBox, Vmware等虚拟机软件来运行MeepoPS.
- MeepoPS需要PHP的POSIX库. POSIX是PHP默认安装的, 通常情况下你不需要手动安装. 如何安装: [PHP手册-POSIX安装](http://php.net/manual/zh/posix.installation.php)
- 多进程及信号处理需要依赖PHP的PCNTL库. MeepoPS深度依赖PCNTL, 因此PCNTL库是必须安装的, 即使只启动一个进程的MeepoPS, 仍然需要安装PCNTL. 如何安装: [PHP手册-PCNTL安装](http://php.net/manual/zh/pcntl.installation.php)
- 在大规模访问下, 我们建议安装PHP的PECL扩展Libevent, 但这不是必须的. 在高链接数的场景下, Libevent表现优异. 如何安装: [PHP手册-Libevent安装](http://php.net/manual/zh/libevent.installation.php). 截止2016-05-06, PHP官方的Libevent扩展不支持PHP7, PHP7下的Libevent安装方法: [PHP7的Libevent分支](https://github.com/expressif/pecl-event-libevent)
- 默认监听链接的方式为Select轮询机制. PHP的Select轮询机制最多只能监听1024个链接. 想要突破这个限制, 要么安装Libevent, 要么使用--enable-fd-setsize=2048重新编译安装PHP.

#### 快速入门:

##### 服务端使用方法:
基础功能和用法都写在demo-telnet.php, 基本您就可以直接用. 

###### 普通终端启动:
    1. 启动: 命令行输入"php demo-telnet.php start".
    2. 状态: 命令行输入"php demo-telnet.php status".
    3. 平滑结束: 启动后按下"ctrl + c"即可.
    4. 强行结束: 命令行输入"kill -INT `cat /var/run/meepops/meepops_master.pid`".

###### 守护进程模式启动:
    1. 启动: 命令行输入"php demo-telnet.php start -d".
    2. 状态: 命令行输入"php demo-telnet.php status".
    3. 平滑结束: 命令行输入"php demo-telnet.php stop".
    4. 强行结束: 命令行输入"php demo-telnet.php kill".
    5. 强行结束: 命令行输入"kill -INT `cat /var/run/meepops/meepops_master.pid`".

###### DEMO:
    1. 基于Telnet协议的服务端使用方法请参考demo-telnet.php.
    2. 如果服务端启动的是HOST是0.0.0.0, 那么客户端可以是外机,可以是本机.本机可以是127.0.0.1, 也可以是localhost.
    3. 如果服务端启动的是HOST是127.0.0.1/localhost, 那么客户端是不能外机,只能是本机.

###### Docker:
我们提供了Docker来快速部署。使用Docker可以快速部署MeepoPS, 我们的容器中已经安装好了MeepoPS、CentOS7、PHP5.6.24、Libevent2.0.20以及他们所需要的各项依赖。
```
docker pull xyzasd01/meepops-0.0.5-dev
```
MeepoPS部署在/usr/local/MeepoPS目录下

##### 客户端使用方法:

###### Telnet:
    客户端可使用telnet客户端.如: telnet 127.0.0.1 19910

###### 编写代码:
    客户端可借助编程语言的Socket来实现. 可参考Test/test_client.php

##### 惊鸿一瞥:
  1. MeepoPS/config.ini是MeepoPS的配置文件. 采用和php.ini同样的格式, ";"为注释.
  2. 必须引入MeepoPS/index.php文件. 使用MeepoPS都是从 require_once 'MeepoPS/index.php' 开始的.
  3. MeepoPS/Api/目录下的文件为暴露给用户的接口. 需要实例化接口类文件, MeepoPS的使用都是围绕实例化接口文件后的对象来操作的. 实例化的时候传入监听的HOST和端口即可.
  4. MeepoPS会以回调函数的方式来触发您设置的业务逻辑. 比如新链接加入时会回调您设置的"Hello world", 再比如某个链接发送了消息"PING"时, 会回调您设置的返回消息"PONG".
  5. MeepoPS可以启动多个实例, 每一次的new接口类文件都是一次实例化.
  7. MeepoPS不但可以实例化多个接口类文件, 也可以实例化同一个接口类文件多次. 比如启动了三个实例, 分别监听了19910, 19911, 19912端口.
  6. 实例化接口类文件并进行了相关设置后, 调用\MeepoPS\runMeepoPS()即可启动MeepoPS.
  7. \MeepoPS\runMeepoPS()之后的所有代码都将不会执行.

#### 示例:
Example目录下是示例案例, 每一个目录是一个独立的项目. 会不断添加.

#### 测试案例:
[请看文档-测试一栏](http://meepops.lanecn.com/doc/main/8-test/1-event-and-child-proccess.html)