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
    protected $pinyinType = 1; // 拼音方式：1：首字母，2：全拼，3：前置字母，4：emby默认

    public function __construct()
    {
        $this->historyContentPath = getcwd() . '/var/storage/history.data';
        $historyContentDir = dirname($this->historyContentPath);
        if(!file_exists($historyContentDir)) @mkdir($historyContentDir, 0777, true);
        if(!is_writable($historyContentDir)){
            failure('错误：当前目录没有写入权限，请 更换目录 或 尝试以管理员模式运行：' . getcwd());
        }
        $this->pinyin = new Pinyin();
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
        logger('开始获取用户信息');
        $this->initUser();
        logger('开始获取媒体库信息');
        $this->initItems();
        $this->toPinyin();
    }

    protected function selectServer()
    {
        if(!file_exists($this->historyContentPath)) {
            logger("未找到history.data文件", false);
            $this->selectByInput();
            return;
        }

        logger('加载historyContent: ' . $this->historyContentPath, false);
        $historyContent = file_get_contents($this->historyContentPath);
        logger($historyContent, false);
        $historyContent = json_decode($historyContent, true);
        if(!$historyContent){
            logger("history.data文件格式不正确");
            copy($this->historyContentPath, $this->historyContentPath . '.' . time()); // 删除文件
            unlink($this->historyContentPath);
            $this->selectServer();
            return;
        }
        $this->historyContent = $historyContent;
        $count = count($this->historyContent);
        echo "\r\n";
        foreach($this->historyContent as $key => $data){
            echo ($key + 1) . ") 地址：{$data['host']}\tAPI密钥：{$data['key']}\r\n";
        }
        echo "0) 输入新的服务器地址和API密钥\r\n\r\n";
        $ask = ask("找到 $count 个历史emby服务器，输入编号直接选取，或编号前加减号-删除该配置项，例如：-1");
        if($ask == '0'){
            $this->selectByInput();
        }else{
            $this->selectByHistory($ask);
        }
    }

    private function selectByInput()
    {
        $this->selectHostByInput();
        $this->selectKeyByInput();
    }

    private function selectHostByInput()
    {
        $ask = ask('请输入你的emby服务器地址，例如：http://192.168.1.1:8096，也可省略http://或端口（默认为http和8096）');
        if(!$ask) $this->selectHostByInput();
        $parseUrl = parse_url($ask);
        if(!isset($parseUrl['scheme'])) $parseUrl['scheme'] = 'http';
        if(!isset($parseUrl['port'])) $parseUrl['port'] = '8096';
        $this->selected['host'] = $parseUrl['scheme'] . '://' . ($parseUrl['host'] ?? $parseUrl['path']) . ':' . $parseUrl['port'];
    }

    private function selectKeyByInput()
    {
        $ask = ask('请输入你的API密钥，密钥需要使用【管理员账号】在emby管理后台的[高级]->[API密钥]进行创建和获取：');
        if(!$ask) $this->selectKeyByInput();
        $this->selected['key'] = $ask;
        $this->selectedByInput = true;
    }

    private function selectByHistory($answer)
    {
        $num = abs($answer);
        if(!isset($this->historyContent[$num - 1])){
            logger("\r\n编号：{$num} 无效，请重新选取");
            $this->selectServer();
            return false;
        }
        if($answer < 0){ // 删除
            unset($this->historyContent[$num - 1]);
            $this->historyContent = array_values($this->historyContent); // 重新排序
            if(!$this->historyContent){
                unlink($this->historyContentPath);
            }else{
                $this->writeHistory();
            }
            logger("已删除编号：{$num} 的配置项");
            $this->selectServer();
        }else{ // 选取
            $this->selected = $this->historyContent[$num - 1];
        }
        return true;
    }

    private function writeHistory()
    {
        return file_put_contents($this->historyContentPath, json_encode($this->historyContent));
    }

    protected function saveHistory()
    {
        if($this->selectedByInput){
            $this->historyContent[] = $this->selected;
            $this->writeHistory();
        }
    }

    protected function initUser()
    {
        $users = $this->sendRequest('Users');
        logger(json_encode($users), false);
        foreach($users as $user){
            if($user['Policy']['IsAdministrator']){
                $this->user = $user;
            }
        }
        if(!$this->user){
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
        echo "\r\n1) 首字母\r\n2) 全拼\r\n3) 前置字母\r\n4) emby默认\r\n";
        $this->pinyinType = intval(ask("请选择拼音排序方式(默认为1)："));
        if(!in_array($this->pinyinType, [1,2,3,4])){
            $this->pinyinType = 1;
        }
        echo "\r\n";
        $auto = ask("是否自动处理所有媒体库？选是将自动处理所有媒体库，选否需要你自行选择处理哪些媒体库。(y/n)");
        if($auto == 'y') { // 自动处理所有媒体库
            foreach($this->items['Items'] as $item){
                if(!$item['IsFolder']) {
                    logger('跳过非目录：' . $item['Name'], false);
                    continue;
                }
                $this->processedItem($item);
            }
        }else{
            $processed = [];
            while(true){
                foreach($this->items['Items'] as $key => $item){
                    if(!$item['IsFolder']) {
                        logger('跳过非目录：' . $item['Name'], false);
                        continue;
                    }
                    $isProcessed = isset($processed[$key]) ? "\t(本次已处理)" : '';
                    echo "{$key}) {$item['name']}$isProcessed\r\n";
                }
                $ask = ask("请选择要处理的媒体库");
                if(!isset($this->items['Items'][$ask])){
                    logger("无效的选项：{$ask}");
                }else{
                    $this->processedItem($this->items['Items'][$ask]);
                    $processed[$ask] = true;
                }
            }
        }
    }

    private function processedItem($item)
    {
        $this->initCount();
        logger("开始处理 【{$item['Name']}】，选取文字后暂停，回车继续");
        $this->renderFolder($item['Id']);
        logger("已跳过：{$this->skipCount}，已处理：{$this->processCount}");
    }

    private function initCount()
    {
        $this->processCount = 0;
        $this->skipCount = 0;
    }

    private function renderFolder($id)
    {
        $items = $this->sendRequest('Items', ['ParentId' => $id]);
//        logger(json_encode($items), false);
        foreach($items['Items'] as $item){
            if(in_array($item['Type'], ['Folder', 'CollectionFolder'])){
                $this->renderFolder($item['Id']);
            }else if(in_array($item['Type'], ['Series', 'Movie', 'BoxSet'])){
                // 获取item详情
                $itemDetail = $this->sendRequest("Users/{$this->user['Id']}/Items/{$item['Id']}");
                switch ($this->pinyinType){
                    case 2: // 全拼
                        $sortName = $this->pinyin->permalink($itemDetail['Name'], '');
                        break;
                    case 3: // 前置字母
                        $pinyinAbbr = $this->pinyin->abbr($itemDetail['Name'], PINYIN_KEEP_NUMBER|PINYIN_KEEP_ENGLISH);
                        $sortName = substr($pinyinAbbr, 0, 1) . $itemDetail['Name'];
                        break;
                    case 4: // emby默认
                        $sortName = $itemDetail['Name'];
                        break;
                    default: // 首字母
                        $sortName = $this->pinyin->abbr($itemDetail['Name'], PINYIN_KEEP_NUMBER|PINYIN_KEEP_ENGLISH);
                }
                if($itemDetail['SortName'] == $sortName){
                    logger('跳过：' . $itemDetail['Name'], false);
                    $this->skipCount++;
                }else{
                    $itemDetail['SortName'] = $sortName;
                    $itemDetail['ForcedSortName'] = $sortName;
                    $itemDetail['LockedFields'] = ['SortName'];
                    // 修改
                    $this->sendRequest("/Items/{$item['Id']}", [], $itemDetail);
                    $this->processCount++;
                }
                echo "已跳过：{$this->skipCount}，已处理：{$this->processCount}\r";
            }
        }
    }

    private function sendRequest($uri, $params = [], $postData = [])
    {
        try{
            if($params){
                $paramsString = '&' . http_build_query($params);
            }else{
                $paramsString = '';
            }
            $client = new Client();
            $fullUrl = "{$this->selected['host']}/{$uri}?api_key={$this->selected['key']}{$paramsString}";
            logger("url: {$fullUrl}, data: " . json_encode($postData), false);
            if(!$postData){
                $response = $client->get($fullUrl);
            }else{
                $response = $client->post($fullUrl, [
                    'json' => $postData,
                ]);
            }
            $content = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();
            if($statusCode != 200 && $statusCode != 204){
                failure('响应错误，检查您的参数：' . $statusCode . ' with ' . $content);
            }else{
                // logger($content, false);
                return json_decode($content, true);
            }
        }catch (\Exception $e){
            failure('响应错误，检查您的服务器地址配置：' . $e->getMessage());
        }
    }
}