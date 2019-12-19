# Trident工具方法

Trident提供了一些工具方法, 可以更加方便的构建业务。如群发、私聊等。因为我们项目业务还不是很复杂, 所以没有用到特别多的函数和方法。如果需要添加较通用的方法, 请与我联系, 我们一同完善。

#### $_SERVER
我们把一些信息放在了$_SERVER中, 例如用户的唯一ID(在分布式中仍然是唯一的)。

#### $_SESSION
习惯了Web的开发, 很多需要暂时记录的东西MeepoPS仍然支持写入到$_SESSION。在Trident中, 不需要session_start(), 直接使用$_SESSION即可。

#### 群发消息: \MeepoPS\Core\Trident\AppBusiness::sendToAll($message)
- 名称: \MeepoPS\Core\Trident\AppBusiness::sendToAll();
- 参数: string 参数$message是消息内容。
- 返回: bool
- 描述: 给当前在线的所有用户发送消息。

###### 示例:
这是我们自行编写的代码: demo.php
```php
<?php
\MeepoPS\Core\Trident\AppBusiness::sendToAll('hello world');
```


#### 私聊消息: \MeepoPS\Core\Trident\AppBusiness::sendToOne($message, $clientId);
- 名称: \MeepoPS\Core\Trident\AppBusiness::sendToOne();
- 参数1: string 参数$message是消息内容。
- 参数2: string 参数$clientId是接收方的用户ID。
- 返回: bool
- 描述: 给指定的某个用户发送消息。

###### 示例:
这是我们自行编写的代码: demo.php
```php
<?php
\MeepoPS\Core\Trident\AppBusiness::sendToOne('你好小明', '1a2lj1ljadn13zchjajkl');
```


#### 给自己发消息: \MeepoPS\Core\Trident\AppBusiness::sendToMe($message);
- 名称: \MeepoPS\Core\Trident\AppBusiness::sendToMe();
- 参数: string 参数$message是消息内容。
- 返回: bool
- 描述: 给自己发送消息。

###### 示例:
这是我们自行编写的代码: demo.php
```php
<?php
\MeepoPS\Core\Trident\AppBusiness::sendToMe('系统给您回复消息了');
```