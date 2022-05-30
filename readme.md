## Emby Pinyin

此工具能使emby支持电影和电视剧的拼音首字母排序，如果觉得此工具帮到你，可以点个Star！

### 使用方法
#### windows系统环境

1. 下载：[release](https://github.com/hisune/emby_pinyin/releases)
2. 解压后打开文件夹里面的emby.exe
3. 输入你的emby服务器地址、API密钥（首次输入后，下次可不用输入，直接选择）
4. 选择要处理的媒体库（如不确定此工具对你的媒体库产生的影响，可自行选择一个媒体库处理，确认没问题后再处理所有媒体库，而不是一开始就处理所有媒体库）
5. 等待处理完成

#### linux及mac使用方式
> 需要PHP7.2以上版本
```sh
cd src && composer install
cd ..
php pack.php
php run.php
```
执行完毕以上命令后的操作步骤和windows版本一致

![](https://raw.githubusercontent.com/hisune/images/master/emby_pinyin_2.jpg)


### 使用效果
![](https://raw.githubusercontent.com/hisune/images/master/emby_pinyin_1.jpg)
