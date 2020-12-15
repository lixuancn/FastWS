# 配置文件
- MeepoPS的配置文件路径MeepoPS/config.php
- MeepoPS的配置文件采用php.ini的形式.
- 修改后需要重启MeepoPS.

```
; MeepoPS configuration file
; MeepoPS configuration file as in php.ini
; MeepoPS configuration file comments start with ';'

; ---------系统相关的设置------------------
[system]
; 是否开启Debug模式, 在Debug模式下, 错误信息就会直接打印.
debug = true

; 设置时区
date_default_timezone_set = 'PRC'

; 主进程在等待结束子进程时, 会等待多久, 单位秒
stop_multi_instance_time_interval = 5

; 是否立即刷送输出. 您若没有ob_系列函数则不需要修改此值
implicit_flush = true

; ---------文件相关的设置------------------
[file]
; 日志文件路径. Debug模式下不但会写日志, 而且会直接打印到标准输出(默认是显示器)
log_filename_prefix = '/var/log/meepops/meepops_'

; 标准输出文件路径. Debug模式下的标准输出就是显示器.
stdout_path_prefix = '/var/log/meepops/meepops_'

; 主进程的Pid文件存放路径
master_pid_path = '/var/run/meepops/meepops_master.pid'

; MeepoPS对子进程的统计信息收集存放的文件(该文件是一次性的)
statistics_path = '/var/run/meepops/meepops_statistics'

; ---------链接相关设置------------------
[connection]
; TCP协议下, 一个链接默认的最大缓冲区的大小, 单位字节.
; 默认是1M
tcp_send_max_buffer_size = 1048576

; TCP协议下, 一个链接默认可接收的单次请求中最大的数据量, 单位字节. 如果单次请求的数据量超过次值, 数据将直接被抛弃.
; 这种情况参考了PHP在接收到过大的POST请求时, 将直接抛弃的做法.
; 默认是10M
tcp_read_max_packet_size = 10485760

; 事件相关
[event]
; Select轮询机制超时时间, 默认100秒.
event_select_poll_timeout = 100000000

; Select轮询机制最大的监听资源数
; 这是PHP内核的限制. 默认是1024. 
; MeepoPS将限制1020. 因为我们在避免空轮训时加入了无意义的Socket资源, 因此这个值必须比FD_SETSIZE少4
; 如果我们想改变这个值, 必须重新编译PHP, 例如"--enable-fd-setsize=2048"
; PHP的FD_SETSIZE最大可以等于操作系统所限制的打开文件最大数
; 请注意: 比如您重新编译了FD_SETSIZE为2048, 但是MeepoPS并不能感知这个值, 所以需要手动设置.
event_select_max_size = 1020

; HTTP协议相关
[http]
; 域名和目录, 支持多组。 格式为: 域名1&路径1 | 域名2&路径2. 域名必须包含端口, 80端口除外
; 每组中域名和路径用"&"分割, 多组间用"|"分割。 自动忽略空格换行。
; 如果访问的域名并不在列表当中, 比如localhost, 就会选择列表当中的第一个。
http_domain_document_list = 'meepops.lanecn.com&/var/www/MeepoPS/Example/Web/ |
                             www.lanecn.com:19910&/var/www/blog/ |
                             lanewechat.lanecn.com:19910&/var/www/LaneWeChat'

; 默认页, 多个用英文半角逗号分割。 自动忽略空格换行
http_default_page = index.php, index.html

; Session name
http_session_name = MeepoPS-Session-Id

; 上传文件时, 是要生成临时文件, 还是只获取文件内的数据。 
; 值为true是同传统Web开发一样, 生成一个文件临时文件, 可使用$_FILE['表单字段']['tmp_name']使用。 但是在生成文件失败时, 将会返回文件内容。
; 值为false是仅获取文件的内容, 不生成临时文件。可使用$_FILE['表单字段']['file_content']使用。
; 因为TCP协议接收到的是文件内容和原文件名, 生成临时文件就是将内容写入到临时文件中, 你在移动为正式文件, 为何不只获取内容, 然后直接写到正式文件, 这些更高效。
; 但是, 为了兼容性, 默认仍然是true, 生成临时文件。
http_upload_file_generate_temp_file = true
```