## Emby Pinyin

此工具能使emby支持电影和电视剧的拼音首字母排序，如果觉得此工具帮到你，可以点个Star⭐️！

#### 特性
- 自动保存历史服务器配置，方便下次执行
- 一键处理所有媒体库或自定义媒体库处理
- 速度快

### 使用方法

#### windows系统使用方法

为方便没有windows php环境的用户，直接打包了exe程序执行：

1. 下载：[release](https://github.com/hisune/emby_pinyin/releases)
2. 解压后打开文件夹里面的EmbyPinyin.exe
3. 输入你的emby服务器地址、API密钥（首次输入后，下次可不用输入，直接选择）
4. 选择排序方式及要处理的媒体库（如不确定此工具对你的媒体库产生的影响，可自行选择一个媒体库处理，确认没问题后再处理所有媒体库，而不是一开始就处理所有媒体库）
5. 等待处理完成

#### linux及mac系统使用方法

> 需要PHP7.2及以上版本

首次执行：

```sh
composer create-project hisune/emby_pinyin:dev-master
cd emby_pinyin
composer pre-install
composer start
```

二次执行：
```sh
composer start
```

执行完毕以上命令后的操作步骤和windows版本一致

#### 拼音排序方式
以“测试”俩字为例，不同排序方式的最终结果如下：
1. 首字母：cs 
2. 全拼：ceshi
3. 前置字母：c测试
4. emby默认：测试

运行截图：

![](https://raw.githubusercontent.com/hisune/images/master/emby_pinyin_2.jpg)


### 使用效果

![](https://raw.githubusercontent.com/hisune/images/master/emby_pinyin_1.jpg)
