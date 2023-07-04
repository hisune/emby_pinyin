<?php

namespace App;

use GuzzleHttp\Client;
use Overtrue\Pinyin\Pinyin;

class EmbyPinyin
{

    protected $pinyin;
    protected $historyContentPath;
    protected $historyContent = [];
    protected $selected;
    protected $selectedByInput = false;
    protected $user;
    protected $items;
    protected $skipCount = 0;
    protected $processCount = 0;
    protected $pinyinType = 1; // 拼音方式：1：首字母，2：全拼，3：前置字母，4：默认
    protected $isJellyfin = false; // 是否是jellyfin服务器
    protected $options = [
        'server' => [
            'short' => 's',
            'description' => '服务器编号',
            'value' => null,
        ],
        'host' => [
            'short' => 'h',
            'description' => '指定服务器地址，配合key参数使用，例如：http://192.168.1.1:8096',
            'value' => null,
        ],
        'key' => [
            'short' => 'k',
            'description' => '指定服务器API密钥，配合host参数使用',
            'value' => null,
        ],
        'type' => [
            'short' => 't',
            'description' => '排序方式，1：首字母，2：全拼，3：前置字母，4：服务器默认',
            'value' => null,
        ],
        'originaltitle' => [
            'short' => 'o',
            'description' => 'OriginalTitle字段改写，1：首字母，2：全拼，3：前置字母，4：服务器默认',
            'value' => null,
        ],
        'all' => [
            'short' => 'a',
            'description' => '是否处理所有媒体库，y是，n否',
            'value' => null,
        ],
        'media' => [
            'short' => 'm',
            'description' => '媒体库编号',
            'value' => null,
        ],
        'help' => [
            'short' => 'H',
            'description' => '获取帮助',
            'value' => null,
        ],
    ];

    public function __construct()
    {
        date_default_timezone_set('Asia/Shanghai');
        if (isCliServer()) {
            //Jellyfin的webhook插件, Generic请求的content-type为json
            if ($_SERVER["CONTENT_TYPE"] ?? "" == "application/json") {
                $data = @json_decode(file_get_contents('php://input'), true);
                if($data){
                    $_POST['raw'] = $data;
                    $_POST['data'] = json_encode(['Item' => [
                        'Type' => $data['ItemType'] ?? "",
                        'Id' => $data['ItemId'] ?? "",
                    ]]);
                }
            }
            $defaultOptions = $_GET;
            logger("access_log\t" . json_encode([
                'uri' => $_SERVER['REQUEST_URI'],
                'get' => $_GET,
                'post' => $_POST,
            ]), false);
        }
        $this->historyContentPath = getcwd() . '/var/storage/history.data';
        $historyContentDir = dirname($this->historyContentPath);
        if (!file_exists($historyContentDir)) @mkdir($historyContentDir, 0777, true);
        if (!is_writable($historyContentDir)) {
            failure('错误：当前目录没有写入权限，请 更换目录 或 尝试以管理员模式运行：' . getcwd());
        }
        $this->pinyin = new Pinyin();
        $this->initOptions($defaultOptions ?? null);
    }

    public function run()
    {
        echo "                 __                        __               __        
.-----.--------.|  |--.--.--.      .-----.|__|.-----.--.--.|__|.-----.
|  -__|        ||  _  |  |  |      |  _  ||  ||     |  |  ||  ||     |
|_____|__|__|__||_____|___  |______|   __||__||__|__|___  ||__||__|__|
by: hisune.com        |_____|______|__|             |_____| 
----------------------------------------------------------------------\r\n";
        $this->selectServer();
        $this->saveHistory();
        logger(sprintf('地址: %s, API密钥: %s, 开始获取用户信息', $this->selected['host'], $this->getMaskKey($this->selected['key'])));
        $this->initUser();
        logger('当前服务器为：' . ($this->isJellyfin ? 'jellyfin' : 'emby') . '，开始获取媒体库信息');
        if (!isCliServer()) {
            $this->initItems();
            $this->toPinyin();
        } else {
            // 如果是序列剧集，使用SeriesId
            if ($_POST['data']['Item']['Type'] == 'Episode') {
                $_POST['data']['Item']['Id'] = $_POST['data']['Item']['SeriesId'];
                $_POST['data']['Item']['Type'] = 'Series';
            }
            $this->renderItems(['Items' => [$_POST['data']['Item']]]);
        }
    }

    private function checkedDefaultOptions($defaultOptions)
    {
        if (!isset($defaultOptions['server']) && (!isset($defaultOptions['host']) && !isset($defaultOptions['key']))) {
            failure('请指定server参数或同时指定host、key参数');
        }
        if (!isset($defaultOptions['type'])) {
            $defaultOptions['type'] = 1;
        }

        $this->pinyinType = $defaultOptions['type'];
        $defaultOptions['all'] = 'y';

        if (!isset($_POST['data'])) {
            failure('Webhooks Server服务运行正常');
        }
        $_POST['data'] = json_decode($_POST['data'], true);
        if (!isset($_POST['data']['Item'])) {
            failure('错误的webhook回调内容');
        }
        return $defaultOptions;
    }

    private function initOptions($defaultOptions = null)
    {
        $shortOptions = '';
        $longOptions = [];
        $optionsMap = [];
        foreach ($this->options as $name => $option) {
            $shortOptions .= $option['short'] . ($name == 'help' ? '' : ':');
            $longOptions[] = $name . ($name == 'help' ? '' : ':');
            $optionsMap[$option['short']] = $name;
        }
        if (!is_null($defaultOptions)) {
            $options = $this->checkedDefaultOptions($defaultOptions);
        } else {
            $options = getopt($shortOptions, $longOptions);
        }
        foreach ($options as $name => $option) {
            if (isset($this->options[$name])) {
                $this->options[$name]['value'] = $option;
            } elseif (isset($this->options[$optionsMap[$name]])) {
                $this->options[$optionsMap[$name]]['value'] = $option;
            }
        }
        if ($this->options['help']['value'] !== null) {
            echo "\r\n使用方法：emby [参数]\r\n";
            foreach ($this->options as $name => $option) {
                echo "-{$option['short']}, --{$name}\t\t" . "{$option['description']}\r\n";
            }
            failure('');
        }
    }

    private function getOption($name)
    {
        return $this->options[$name]['value'];
    }

    private function getMaskKey($key): string
    {
        $keyLength = min(strlen($key), 24);
        return substr($key, 0, -$keyLength) . str_repeat('*', $keyLength);
    }

    protected function selectServer()
    {
        $host = $this->getOption('host');
        $key = $this->getOption('key');
        if ($host && $key) {
            logger('使用自定义host和key参数');
            $this->parseHost($host);
            $this->selected['key'] = $key;
            return true;
        }

        if (!file_exists($this->historyContentPath)) {
            logger("未找到history.data文件", false);
            $this->selectByInput();
            return;
        }

        logger('加载historyContent: ' . $this->historyContentPath, false);
        $historyContent = file_get_contents($this->historyContentPath);
        logger($historyContent, false);
        $historyContent = json_decode($historyContent, true);
        if (!$historyContent) {
            logger("history.data文件格式不正确");
            copy($this->historyContentPath, $this->historyContentPath . '.' . time()); // 删除文件
            unlink($this->historyContentPath);
            $this->selectServer();
            return;
        }
        $this->historyContent = $historyContent;
        $count = count($this->historyContent);
        if ($this->getOption('server') === null && !$this->getOption('host')) {
            echo "\r\n";
            foreach ($this->historyContent as $key => $data) {
                echo ($key + 1) . ") 地址：{$data['host']}\tAPI密钥：" . $this->getMaskKey($data['key']) . "\r\n";
            }
            echo "0) 输入新的服务器地址和API密钥\r\n\r\n";
            $ask = ask("找到 $count 个历史服务器，输入编号直接选取(默认为1)，或编号前加减号-删除该配置项，例如：-1");
            if (trim($ask) === '') $ask = 1;
            if ($ask == '0') {
                $this->selectByInput();
            } else {
                $this->selectByHistory($ask);
            }
        } else {
            logger('使用server参数：' . $this->getOption('server'));
            $this->selectByHistory($this->getOption('server'));
        }
    }

    private function selectByInput()
    {
        $this->selectHostByInput();
        $this->selectKeyByInput();
    }

    private function selectHostByInput()
    {
        $ask = ask('请输入你的服务器地址，例如：http://192.168.1.1:8096，也可省略http://或端口（默认为http和8096）');
        if (!$ask) $this->selectHostByInput();
        $this->parseHost($ask);
    }

    private function parseHost($host)
    {
        $parseUrl = parse_url($host);
        if (!isset($parseUrl['scheme'])) $parseUrl['scheme'] = 'http';
        if (!isset($parseUrl['port'])) $parseUrl['port'] = '8096';
        $this->selected['host'] = $parseUrl['scheme'] . '://' . ($parseUrl['host'] ?? $parseUrl['path']) . ':' . $parseUrl['port'];
    }

    private function selectKeyByInput()
    {
        $ask = ask('请输入你的API密钥，密钥需要使用【管理员账号】在管理后台的[高级]->[API密钥]进行创建和获取：');
        if (!$ask) $this->selectKeyByInput();
        $this->selected['key'] = $ask;
        $this->selectedByInput = true;
    }

    private function selectByHistory($answer): bool
    {
        $num = abs($answer);
        if (!isset($this->historyContent[$num - 1])) {
            logger("\r\n编号：{$num} 无效，请重新选取");
            if (isCliServer()) exit;
            sleep(1);
            $this->selectServer();
            return false;
        }
        if ($answer < 0) { // 删除
            unset($this->historyContent[$num - 1]);
            $this->historyContent = array_values($this->historyContent); // 重新排序
            if (!$this->historyContent) {
                unlink($this->historyContentPath);
            } else {
                $this->writeHistory();
            }
            logger("已删除编号：{$num} 的配置项");
            $this->selectServer();
        } else { // 选取
            $this->selected = $this->historyContent[$num - 1];
        }
        return true;
    }

    private function writeHistory(): void
    {
        file_put_contents($this->historyContentPath, json_encode($this->historyContent));
    }

    protected function saveHistory()
    {
        if ($this->selectedByInput) {
            $this->historyContent[] = $this->selected;
            $this->writeHistory();
        }
    }

    protected function initUser()
    {
        $users = $this->sendRequest('Users');
        logger(json_encode($users), false);
        foreach ($users as $user) {
            if ($user['Policy']['IsAdministrator']) {
                $this->user = $user;
                if (isset($user['Policy']['AuthenticationProviderId']) && strpos($user['Policy']['AuthenticationProviderId'], 'Jellyfin') === 0) {
                    $this->isJellyfin = true;
                }
            }
        }
        if (!$this->user) {
            failure('未找到管理员账户，请检查你的API KEY参数');
        }
    }

    protected function initItems()
    {
        $items = $this->sendRequest("Users/{$this->user['Id']}/Views");
        logger(json_encode($items), false);
        logger("获取到 {$items['TotalRecordCount']} 个媒体库");
        $this->items = $items;
    }

    protected function toPinyin()
    {
        if ($this->getOption('type') === null) {
            echo "\r\n-----------------------\r\n";
            echo "1) 首字母\r\n2) 全拼\r\n3) 前置字母\r\n4) 默认\r\n";
            echo "-----------------------\r\n";
            $this->pinyinType = intval(ask("请选择拼音排序方式(默认为1)："));
            echo "\r\n";
        } else {
            $this->pinyinType = intval($this->getOption('type'));
            logger('使用type参数：' . $this->pinyinType);
        }
        if (!in_array($this->pinyinType, [1, 2, 3, 4])) {
            logger("无效的选项，将使用默认排序");
            $this->pinyinType = 1;
        }
        if ($this->getOption('all') === null) {
            $auto = ask("是否自动处理所有媒体库？选是将自动处理所有媒体库，选否需要你自行选择处理哪些媒体库。(y/n,默认为n)");
        } else {
            $auto = $this->getOption('all');
            logger('使用all参数：' . $auto);
        }
        if ($auto == 'y') { // 自动处理所有媒体库
            foreach ($this->items['Items'] as $item) {
                if (!$item['IsFolder']) {
                    logger('跳过非目录：' . $item['Name'], false);
                    continue;
                }
                $this->processedItem($item);
            }
        } else {
            if ($this->getOption('media') === null) {
                $processed = [];
                while (true) {
                    echo "\r\n-----------------------\r\n";
                    foreach ($this->items['Items'] as $key => $item) {
                        if (!$item['IsFolder']) {
                            logger('跳过非目录：' . $item['Name'], false);
                            continue;
                        }
                        $humanKey = $key + 1;
                        $isProcessed = isset($processed[$humanKey]) ? "\t(本次已处理)" : '';
                        echo "{$humanKey}) {$item['Name']}$isProcessed\r\n";
                    }
                    echo "-----------------------\r\n";
                    $ask = intval(ask("请选择要处理的媒体库"));
                    $key = $ask - 1;
                    if (!isset($this->items['Items'][$key])) {
                        logger("无效的选项：{$ask}");
                    } else {
                        $this->processedItem($this->items['Items'][$key]);
                        $processed[$ask] = true;
                    }
                }
            } else {
                logger('使用media参数：' . $this->getOption('media'));
                $key = $this->getOption('media') - 1;
                if (isset($this->items['Items'][$key])) {
                    $this->processedItem($this->items['Items'][$key]);
                } else {
                    logger("无效的选项：" . $this->getOption('media'));
                }
            }
        }
    }

    private function processedItem($item)
    {
        $this->initCount();
        $tips = PHP_OS_FAMILY === 'Windows' ? '，选取文字后暂停，回车继续' : '';
        logger("开始处理 【{$item['Name']}】{$tips}");
        $this->renderFolder($item['Id'], $item['CollectionType'] ?? null);
        logger("已跳过：{$this->skipCount}，已处理：{$this->processCount}");
    }

    private function initCount()
    {
        $this->processCount = 0;
        $this->skipCount = 0;
    }

    private function getSortName($itemDetail, $type = null)
    {
        $type = is_null($type) ? $this->pinyinType : intval($type);
        switch ($type) {
            case 2: // 全拼
                $sortName = $this->pinyin->permalink($itemDetail->Name, '');
                break;
            case 3: // 前置字母
                $pinyinAbbr = $this->pinyin->abbr($itemDetail->Name, PINYIN_KEEP_NUMBER | PINYIN_KEEP_ENGLISH);
                $sortName = substr($pinyinAbbr, 0, 1) . $itemDetail->Name;
                break;
            case 4: // 默认
                $sortName = $itemDetail->Name;
                break;
            default: // 首字母
                $sortName = $this->pinyin->abbr($itemDetail->Name, PINYIN_KEEP_NUMBER | PINYIN_KEEP_ENGLISH);
        }
        return $sortName;
    }

    private function renderItems($items)
    {
        foreach ($items['Items'] as $item) {
            if (in_array($item['Type'], ['Folder', 'CollectionFolder'])) {
                $this->renderFolder($item['Id'], $item['CollectionType'] ?? null);
            } else if (in_array($item['Type'], ['Series', 'Movie', 'BoxSet', 'Audio', 'MusicAlbum', 'MusicArtist', 'Video', 'Photo'])) {
                // 获取item详情
                $itemDetail = $this->sendRequest("Users/{$this->user['Id']}/Items/{$item['Id']}", [], [], false);
                $sortName = $this->getSortName($itemDetail);
                $originalTitle = $this->getSortName($itemDetail, $this->getOption('originaltitle'));
                $skip = $itemDetail->SortName == $sortName;
                if ($this->getOption('originaltitle') && isset($itemDetail->OriginalTitle) && $itemDetail->OriginalTitle != $originalTitle) {
                    $itemDetail->OriginalTitle = $originalTitle;
                    $skip = false;
                }
                if ($skip) {
                    logger('跳过，已处理：' . $itemDetail->Name, false);
                    $this->skipCount++;
                } else {
                    if ($this->isJellyfin) {
                        unset($itemDetail->SortName);
                    } else {
                        $itemDetail->SortName = $sortName;
                        $itemDetail->LockedFields = ['SortName'];
                    }
                    $itemDetail->ForcedSortName = $sortName;
                    // 修改
                    $this->sendRequest("Items/{$item['Id']}", [], $itemDetail);
                    logger("完成修改\tid: {$item['Id']}, content: " . json_encode($itemDetail), false);
                    $this->processCount++;
                }
            } else {
                logger('跳过，未知类型：' . json_encode($item), false);
                $this->skipCount++;
            }
            echo "已跳过：{$this->skipCount}，已处理：{$this->processCount}\r";
        }
    }

    private function renderFolder($id, $collectionType = null)
    {
        if ($collectionType == 'music') {
            // 专辑
            $items = $this->sendRequest("Users/{$this->user['Id']}/Items", [
                'IncludeItemTypes' => 'MusicAlbum',
                'Recursive' => 'true',
                'ParentId' => $id,
            ]);
            $this->renderItems($items);
            // 艺术家
            $items = $this->sendRequest("Artists", [
                'ArtistType' => 'Artist,AlbumArtist',
                'Recursive' => 'true',
                'ParentId' => $id,
                'userId' => $this->user['Id'],
            ]);
            $this->renderItems($items);
        }
        $items = $this->sendRequest("Users/{$this->user['Id']}/Items", ['ParentId' => $id]);
        $this->renderItems($items);
    }

    private function sendRequest($uri, $params = [], $postData = [], $assoc = true)
    {
        try {
            if ($params) {
                $paramsString = '&' . http_build_query($params);
            } else {
                $paramsString = '';
            }
            $client = new Client(['timeout' => 5]);
            $fullUrl = "{$this->selected['host']}/{$uri}?api_key={$this->selected['key']}{$paramsString}";
            logger("sendRequest request\turl: {$fullUrl}, data: " . json_encode($postData), false);
            if (!$postData) {
                $response = $client->get($fullUrl);
            } else {
                $response = $client->post($fullUrl, [
                    'json' => $postData,
                ]);
            }
            $content = $response->getBody()->getContents();
            logger("sendRequest response\turl: {$fullUrl}, content: " . $content, false);
            $statusCode = $response->getStatusCode();
            if ($statusCode != 200 && $statusCode != 204) {
                failure('响应错误，检查您的参数：' . $statusCode . ' with ' . $content);
            } else {
                // logger($content, false);
                return json_decode($content, $assoc);
            }
        } catch (\Exception $e) {
            failure('响应错误，检查您的服务器地址配置：' . $e->getMessage());
        }
    }
}
