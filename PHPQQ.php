<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2013 Toknot.com
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @link       https://github.com/chopins/PHPQQ
 */
class PHPQQ {

    public $errno;
    public $errstr;
    public $timeout = 600;
    public $loginUIHost = 'ui.ptlogin2.qq.com';
    public $aid = '1003903';
    private $imgResposeHeader;
    private $safeKey;
    private $loginUIBody;
    private $loginUIResposeHeader;
    private $checkSignSafeKeyHeader;
    private $checkSignSafeKeyBody;
    private $UIN;
    private $verifyCode;
    private $begTime;
    private $loginQQAccountHeader;
    private $loginResponseData;
    private $dWebProxyUrl = 'd.web2.qq.com/proxy.html?v=20110331002&callback=1&id=3';
    private $sWebProxyUrl = 's.web2.qq.com/proxy.html?v=20110412001&callback=1&id=1';
    private $clientId;
    private $safeCookieFile = '/data/safeCookie';
    private $cwd;
    private $userFriendList;
    private $PSID;
    private $userOlineFriendList;
    private $pipMaxWindow = 5;
    private $mainWindow;
    private $mainWindowLine;
    private $mainWindowColumns;
    private $messageBox;
    private $friendsBox;

    public function __construct() {
        $this->initUI();
        $this->setTimeZone();
        $this->begTime = microtime(true);
        $this->checkCWD();
        $this->callLoginUIPage();
        $this->verCache();
        $this->checkSignSafeKey();
        $this->loginQQAccount();
        $this->loginWebQQ();
        //$this->poll();
    }

    public function __destruct() {
        ncurses_delwin($this->mainWindow);
        ncurses_clear();
        ncurese_end();
    }

    public function initUI() {
        setlocale(LC_ALL, "");
        ncurses_init();
        ncurses_start_color();
        ncurses_init_pair(1, NCURSES_COLOR_YELLOW, NCURSES_COLOR_BLACK);
        ncurses_color_set(1);
        $this->mainWindow = ncurses_newwin(0, 0, 0, 0);
        ncurses_getmaxyx(&$this->mainWindow, $this->mainWindowLine, $this->mainWindowColumns);
        ncurses_border(0, 0, 0, 0, 0, 0, 0, 0);
        ncurses_attron(NCURSES_A_REVERSE);
        ncurses_mvaddstr(0, 1, "PHPQQ 0.1");
        ncurses_attroff(NCURSES_A_REVERSE);
        ncurses_wrefresh($this->mainWindow); 
        $this->createFriendsBox();
    }

    public function setTimeZone() {
        date_default_timezone_set('Asia/Chongqing');
    }

    public function checkCWD() {

        $cwd = getcwd();
        if (!is_writable($cwd)) {
            $cwd = $_ENV['HOME'] . "/.PHPQQ";
            chdir($cwd);
            if (!file_exists($cwd)) {
                mkdir($cwd);
            }
        }
        $this->cwd = $cwd;
        $this->safeCookieFile = $this->cwd . $this->safeCookieFile;
        if (!file_exists("$cwd/data")) {
            mkdir("$cwd/data");
        }
        if (!file_exists("$cwd/data/receiveFile")) {
            mkdir("$cwd/data/receiveFile");
        }
        if (!file_exists("$cwd/data/messageData")) {
            mkdir("$cwd/data/messageData");
        }
        if (!function_exists('json_decode')) {
            die('你必须安装JSON扩展');
        }
        if (!function_exists('pcntl_fork')) {
            die('你必须安装PCNTL扩展');
        }
        if (!function_exists('ncurses_init')) {
            die('你必须安装Ncurses扩展最新版本,注意在编译扩展时，你必须激活--enable-ncursesw选项');
        }
    }

    public function getQQNumber() {
        //$qqNumber = '2498360247';
        $qqNumber = '371276747';
        return $qqNumber;
    }

    public function getQQPass() {
        $qqPassword = 'qq123456';
        $qqPassword = 'vways169';
        return $qqPassword;
    }

    public function message($str) {
        if (is_resource($this->messageBox)) {
            ncurses_delwin($this->messageBox);
        }
        $this->messageBox = ncurses_newwin(3, $this->mainWindowColumns - 4, $this->mainWindowLine - 4, 1);
        ncurses_wborder($this->messageBox, 0, 0, 0, 0, 0, 0, 0, 0);
        ncurses_mvwaddstr($this->messageBox, 1, 5, "$str\n");
        ncurses_wrefresh($this->messageBox);
    }

    public function getStandardRequestHeader() {
        $standardRequestHeader = "User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:22.0) Gecko/20130324 Firefox/22.0\r\n" .
                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" .
                "Accept-Language: zh-cn,zh;q=0.8,en-us;q=0.5,en;q=0.3\r\n" .
                "Accept-Encoding: deflate\r\n" .
                "DNT: 1\r\n" .
                "Connection: keep-alive\r\n" .
                "\r\n";
        return $standardRequestHeader;
    }

    public function callLoginUIPage() {
        $this->message('获取webQQ UI');
        $referer = 'web.qq.com';
        $fp = $this->openHTTP($this->loginUIHost, $this->getLoginUIQueryString(), 80, $referer);
        list($this->loginUIResposeHeader, $this->loginUIBody) = $this->splitResponse($fp);
        $this->setSafeKey();
    }

    public function verCache() {
        $verUrl = '/cgi-bin/ver';
        $loginUIQueryString = $this->getLoginUIQueryString();
        $referer = $this->loginUIHost . $loginUIQueryString;
        $this->message("检查webQQ 本地缓存");
        $verfp = $this->openHTTP($this->loginUIHost, $verUrl, 80, $referer);
        //list($ver_header, $ver_body) = responseParse($verfp);
    }

    public function checkSignSafeKey() {
        $loginUIQueryString = $this->getLoginUIQueryString();
        $referer = $this->loginUIHost . $loginUIQueryString;
        $checkHost = 'check.ptlogin2.qq.com';
        $checkQueryString = $this->getCheckLoginKeyQueryString();
        $this->message('检查账户登录KEY');
        $requestCookie = $this->loginUIResposeHeader['cookie'];
        if (file_exists($this->safeCookieFile)) {
            $existCookie = file_get_contents($this->safeCookieFile);
            $oldCookie = unserialize($existCookie);
            if (isset($oldCookie['ptvfsession']))
                $requestCookie['ptvfsession'] = $oldCookie['ptvfsession'];
            if (isset($oldCookie['verifysession']))
                $requestCookie['verifysession'] = $oldCookie['verifysession'];
        }
        $requestCookieStr = $this->cookieArr2Str($requestCookie);
        $chkfp = $this->openHTTP($checkHost, $checkQueryString, 80, $referer, $requestCookieStr);
        list($this->checkSignSafeKeyHeader, $this->checkSignSafeKeyBody) = $this->splitResponse($chkfp);
        if (isset($this->imgResposeHeader['cookie']['verifysession'])) {
            $this->checkSignSafeKeyHeader['cookie']['verifysession'] = $this->imgResposeHeader['cookie']['verifysession'];
        }
        $cookieStr = serialize($this->checkSignSafeKeyHeader['cookie']);
        file_put_contents($this->safeCookieFile, $cookieStr);
        $this->checkVC();
    }

    public function getLoginQQAccountQueryString() {
        $qqPass = $this->getQQPass();
        $qqNumber = $this->getQQNumber();
        $binPass = md5($qqPass, true);
        $np = strtoupper(md5($binPass . $this->UIN));
        $password = strtoupper(md5($np . strtoupper($this->verifyCode)));
        $actionTime = floor((microtime(true) - $this->begTime) * 10E5);
        $rn1 = mt_rand(1, 8);
        $rn2 = mt_rand(1, 8);
        $loginUrl = "/login?u=$qqNumber&p=$password&verifycode={$this->verifyCode}" .
                "&webqq_type=10&remember_uin=1&login2qq=1&aid=1003903" .
                "&u1=http%3A%2F%2Fweb.qq.com%2Floginproxy.html%3Flogin2qq%3D1%26webqq_type%3D10" .
                "&h=1&ptredirect=0&ptlang=2052&from_ui=1&pttype=1&dumy=&fp=loginerroralert" .
                "&action={$rn1}-{$rn2}-$actionTime&mibao_css=m_webqq&t=1&g=1&js_type=0&js_ver=10024" .
                "&login_sig={$this->safeKey}";
        return $loginUrl;
    }

    public function getLoginQQAccountRequestCookie() {
        $qqNumber = $this->getQQNumber();
        $loginRequestCookie = "chkuin=$qqNumber;confirmuin=$qqNumber;ptisp=ctc;";
        if (isset($this->imgResposeHeader['cookie']['verifysession']))
            $loginRequestCookie .= "verifysession={$this->imgResposeHeader['cookie']['verifysession']};";
        if (isset($this->checkSignSafeKeyHeader['cookie']['ptvfsession']))
            $loginRequestCookie .= "ptvfsession={$this->checkSignSafeKeyHeader['cookie']['ptvfsession']};";
        if (isset($this->loginUIResposeHeader['cookie']['uikey']))
            $loginRequestCookie .= "uikey={$this->loginUIResposeHeader['cookie']['uikey']}";
        return $loginRequestCookie;
    }

    public function loginQQAccount() {
        $this->message('登录QQ账户');
        $loginRequestCookie = $this->getLoginQQAccountRequestCookie();
        $loginUIQueryString = $this->getLoginUIQueryString();
        $loginQQAccountHost = 'ptlogin2.qq.com';
        $referer = $this->loginUIHost . $loginUIQueryString;
        $loginQQAccountQueryString = $this->getLoginQQAccountQueryString();
        $lfp = $this->openHTTP($loginQQAccountHost, $loginQQAccountQueryString, 80, $referer, $loginRequestCookie);
        list($this->loginQQAccountHeader, $body) = $this->splitResponse($lfp);
        $reCode = $this->getBackJSFuncParam($body);
        if ($reCode[0] == 0) {
            $this->message('登录成功');
        } else {
            die("{$reCode[3]},QQ号码:{$reCode[4]}\n");
        }
    }

    public function getLoginWebQQPostData() {
        $ptwebqq = $this->loginQQAccountHeader['cookie']['ptwebqq'];
        $this->clientId = mt_rand(0, 99) . time() % 1E6;
        $postDataArray = array();
        $postDataArray['r'] = '{"status":"online","ptwebqq":"' . $ptwebqq . '",' .
                '"passwd_sig":"","clientid":"' . $this->clientId . '","psessionid":null}';
        $postDataArray['clientid'] = $this->clientId;
        $postDataArray['psessionid'] = 'null';
        return $this->data2URL($postDataArray);
    }

    public function getLoginWebQQCookie() {
        $cookie = $this->loginQQAccountHeader['cookie'];
        $cookie['ptisp'] = 'ctc';
        $cookie['ptui_loginuin'] = $this->getQQNumber();
        unset($cookie['ETK'], $cookie['ptuserinfo'], $cookie['ptcz'], $cookie['airkey']);
        return $this->cookieArr2Str($cookie);
    }

    public function mainProcess($sock, $pid) {
        while (true) {
            $data = $this->IPCR($sock);
            switch ($data['type']) {
                case 'kick':
                    $this->message($data['value']);
                    $this->reLogin();
                    break;
                case 'message':
                    $this->showMessage($data['value']);
                    break;
            }
            sleep(1);
        }
        pcntl_wait($status);
    }

    public function waitInputPoll() {
        while (true) {
            $input = fgets(STDIN);
        }
    }

    public function poll() {
        $pPostDataArray = array();
        $pPostDataArray['r'] = '{"clientid":"' . $this->clientId . '","psessionid":"' . $this->PSID .
                '","key":0,"ids":[]}';
        $pPostDataArray['clientid'] = $this->clientId;
        $pPostDataArray['psessionid'] = $this->PSID;
        $pPostData = $this->data2URL($pPostDataArray);
        $loginRequestCookie = $this->getLoginWebQQCookie();
        $this->message('开始轮询');
        $kick = false;
        $ssp = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if (!$ssp)
            die('不能创建SOCKET');
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('无法创建POLL进程');
        } elseif ($pid > 0) {
            $this->mainProcess($ssp[0], $pid);
            return;
        }
        while (true) {
            $poll = $this->openHTTP('d.web2.qq.com', '/channel/poll2', 80, $this->dWebProxyUrl, $loginRequestCookie, 'POST', $pPostData);
            list($ht, $bPD) = $this->splitResponse($poll);
            $resArr = json_decode($bPD, true);
            if ($resArr['retcode'] == 0) {
                $pollType = $resArr['result'][0]['poll_type'];
                $pollValue = $resArr['result'][0]['value'];
                switch ($pollType) {
                    case 'message':
                        $this->IPCS($ssp[1], 'message', $pollValue);
                        break;
                    case 'kick_message':
                        $this->IPCS($ssp[1], 'kick', $pollValue['reason']);
                        break 2;
                }
            }
        }
        die;
    }

    public function IPCR($sock) {
        stream_set_blocking($sock, 0);
        $dataLength = (int) trim(fread($sock, $this->pipMaxWindow));
        if ($dataLength <= 0)
            return;
        $str = fread($sock, $dataLength);
        $data = unserialize($str);
        return $data;
    }

    public function IPCS($sock, $type, $value) {
        $pm = array('type' => $type, 'value' => $value);
        $pms = serialize($pm);
        $dataLength = strlen($pms);
        $cl = $this->pipMaxWindow - strlen($dataLength);
        if ($cl < 0) {
            $this->message('管道数据太大');
            return;
        }
        for ($i = 0; $i < $cl; $i++) {
            $dataLength .= "\n";
        }
        $pms = $dataLength . $pms;
        $len = strlen($pms);
        stream_set_blocking($sock, 0);
        fwrite($sock, $pms, $len);
    }

    public function reLogin() {
        echo '重新登录(yes/no)?:';
        for ($i = 0; $i < 5; $i++) {
            $enter = strtolower(trim(fgets(STDIN)));
            if ($enter == 'yes') {
                $this->loginWebQQ();
            } elseif ($enter == 'no') {
                die;
            } else {
                continue;
            }
        }
    }

    public function showMessage($message) {
        $nick = $this->userFriendList[$message['from_uin']]['nick'];
        $time = date('Y-m-d H:i:s', $message['time']);
        $content = $message['content'][1];
        $this->message("$nick $time\n$content");
    }

    public function sendMessage($msg, $to) {
        $msgDefStyle = '{\"name\":\"宋体\",\"size\":\"10\",\"style\":[0,0,0],\"color\":\"000000\"}';
        $referer = 'd.web2.qq.com/proxy.html?v=20110331002&callback=1&id=2';
        $loginRequestCookie = $this->getLoginWebQQCookie();
        $sendPostDataArr = array();
        $sendPostDataArr['r'] = '{"to":' . $to . ',"face":561,"content":"[\"' . $msg . '\",
                                    [\"font\",' . $msgDefStyle . ']]","msg_id":79050004,
                             "clientid":"' . $this->clientId . '","psessionid":"' . $this->PSID . '"}';
        $sendFp = $this->openHTTP('d.web2.qq.com', '/channel/send_buddy_msg2', 80, $referer, $loginRequestCookie, 'POST', $sendPostData);
    }

    public function loginWebQQ() {
        $postData = $this->getLoginWebQQPostData();
        $loginRequestCookie = $this->getLoginWebQQCookie();
        $clogin = $this->openHTTP('d.web2.qq.com', '/channel/login2', 80, $this->dWebProxyUrl, $loginRequestCookie, 'POST', $postData);
        list($ht, $bD) = $this->splitResponse($clogin);
        $this->loginResponseData = json_decode($bD, true);
        if ($this->loginResponseData['retcode'] == 0) {
            $this->PSID = $this->loginResponseData['result']['psessionid'];
            $this->message('WebQQ登录成功');
            $this->getFriendsList();
            $this->getOnlineFriendsList();
            $this->poll();
        }
    }

    public function getFriendsList() {
        $this->message('获取好友列表');
        $postDataArray = array('r' => '{"h":"hello","vfwebqq":"' . $this->loginResponseData['result']['vfwebqq'] . '"}');
        $postData = $this->data2URL($postDataArray);
        $loginRequestCookie = $this->getLoginWebQQCookie();
        $gFL = $this->openHTTP('s.web2.qq.com', '/api/get_user_friends2', 80, 
                $this->sWebProxyUrl, $loginRequestCookie, 'POST', $postData);
        list($ht, $bD) = $this->splitResponse($gFL);
        $responseData = json_decode($bD, true);
        if ($responseData['retcode'] == 0) {
            $this->userFriendList = array();
            $position = 1;
            foreach ($responseData['result']['info'] as $k => $friend) {
                $this->userFriendList[$friend['uin']] = $friend;
                ncurses_mvwaddstr($this->friendsBox, $position, 1, "{$friend['nick']}\n");
                $position++;
            }
            ncurses_wrefresh($this->friendsBox);
        }
    }

    public function createFriendsBox() {
        $this->friendsBox = ncurses_newwin($this->mainWindowLine-5, 20, 1, 2);
        ncurses_wborder($this->friendsBox, 0, 0, 0, 0, 0, 0, 0, 0);
        ncurses_wrefresh($this->friendsBox);
    }

    public function getOnlineFriendsList() {
        $this->message('获取在线好友列表');
        $time = time();
        $cookie = $this->getLoginWebQQCookie();
        $queryString = "/channel/get_online_buddies2?clientid={$this->clientId}&psessionid={$this->PSID}&t={$time}";
        $gOFL = $this->openHTTP('d.web2.qq.com', $queryString, 80, $this->dWebProxyUrl, $cookie);
        list($ht, $bD) = $this->splitResponse($gOFL);
        $responseData = json_decode($bD, true);
        if ($responseData['retcode'] == 0) {
            $this->userOlineFriendList = $responseData['result'];
        }
    }

    public function getLoginUIQueryString() {
        $loginUIQueryString = "/cgi-bin/login?target=self&style=5&mibao_css=m_webqq&appid={$this->aid}" .
                '&enable_qlogin=0&no_verifyimg=1&s_url=http%3A%2F%2Fweb.qq.com%2Floginproxy.html' .
                '&f_url=loginerroralert&strong_login=1&login_state=10&t=20130221001';
        return $loginUIQueryString;
    }

    public function setSafeKey() {
        preg_match('/g_login_sig="(.+)"/', $this->loginUIBody, $match);
        $this->safeKey = $match[1];
    }

    public function getCheckLoginKeyQueryString() {
        $qqNumber = $this->getQQNumber();
        $rand = microtime(true);
        $checkQueryString = "/check?uin={$qqNumber}&appid=1003903&js_ver=10024&js_type=0" .
                "&login_sig={$this->safeKey}" .
                "&u1=http%3A%2F%2Fweb.qq.com%2Floginproxy.html&r=$rand";
        return $checkQueryString;
    }

    public function getLoginUIResposeCookie() {
        return $this->loginUIResposeHeader['cookie'];
    }

    public function getCodeImg() {
        $loginUIQueryString = $this->getLoginUIQueryString();
        $qqNumber = $this->getQQNumber();
        $requestCookie = $this->getLoginUIResposeCookie();
        $r = '0.' . time();
        $fp = $this->openHTTP('captcha.qq.com', "/getimage?aid={$this->aid}&r=$r&uin=$qqNumber", 80, $this->loginUIHost . $loginUIQueryString, $requestCookie);

        echo "正在下载验证码图片";
        list($header, $body) = $this->splitResponse($fp);
        file_put_contents($this->cwd . '/data/code.jpg', $body);
        $this->imgResposeHeader = $header;
        $this->getEnterImgCode();
    }

    public function getEnterImgCode() {
        echo "\n请输入图片验证码:";
        $this->verifyCode = trim(fgets(STDIN));
    }

    public function checkVC() {
        $VC = $this->getBackJSFuncParam($this->checkSignSafeKeyBody);
        if ($VC[2] == '\x00\x00\x00\x00\x00\x00\x27\x10') {
            die('Error:');
        } else {
            $this->UIN = stripcslashes($VC[2]);
        }
        $this->verifyCode = $VC[1];
        if ($VC[0] == 1) {
            $this->getCodeImg();
        }
    }

    public function data2URL($data) {
        return http_build_query($data);
    }

    public function getBackJSFuncParam($str) {
        preg_match('/\(.*\)/', $str, $matches);
        $str = str_replace(array("('", "')"), '', $matches[0]);
        $p = explode("','", $str);
        return $p;
    }

    public function cookieArr2Str($cookieArr) {
        $str = '';
        foreach ($cookieArr as $cN => $cV) {
            $str .= "$cN=$cV;";
        }
        return $str;
    }

    public function splitResponse($fp, $debug = false) {
        if (!is_resource($fp))
            return;
        $response = '';
        while (!feof($fp)) {
            $response .= fread($fp, 1024);
        }
        if($debug) echo $response;
        list($header, $body) = explode("\r\n\r\n", $response, 2);
        $rowHeader = explode("\r\n", $header);
        $headerArr = array();
        $headerArr['cookie'] = array();
        foreach ($rowHeader as $row) {
            if (trim($row) == '')
                continue;;
            list($key, $var) = explode(':', trim($row));
            if (strtolower($key) == 'set-cookie') {
                $cookieArr = explode(';', $var);
                foreach ($cookieArr as $cookie) {
                    if (trim($cookie) == '')
                        continue;
                    list($cookieKey, $cookieValue) = explode('=', $cookie);
                    $cookieKey = trim($cookieKey);
                    switch (strtoupper($cookieKey)) {
                        case 'PATH':
                            break;
                        case 'DOMAIN':
                            break;
                        case 'EXPIRES':
                            break;
                        case 'SECURE':
                            break;
                        default :
                            $headerArr['cookie'][$cookieKey] = $cookieValue;
                            break;
                    }
                }
            } else {
                $key = trim($key);
                $headerArr[$key] = trim($var);
            }
        }
        if(isset($headerArr['Transfer-Encoding']) && $headerArr['Transfer-Encoding'] == 'chunked') {
            $bodyArray = explode("\r\n", $body);
            $contents = '';
            foreach ($bodyArray as $k => $dataStr) {
                if($k%2 != 0) {
                    $contents .= $dataStr;
                }
            }
            $body = $contents;
        }
        fclose($fp);
        return array($headerArr, $body);
    }

    public function openHTTP($host, $url, $port, $referer, $cookie = '', $m = 'GET', $data = NULL) {
        //echo "Open $host$url\n";
        if ($port == '443') {
            $host = 'ssl://' . $host;
        }
        $fp = fsockopen($host, $port, $this->errno, $this->errstr, $this->timeout);
        if (!$fp) {
            echo "ERROR:$errstr($errno)\n";
            die;
        }

        $requestHeader = "$m $url HTTP/1.1\r\n" .
                "Host: $host\r\n" .
                "Referer: http://$referer/\r\n" .
                "Cookie: $cookie\r\n";

        if ($data) {
            $len = strlen($data);
            $requestHeader .= "Content-Length: $len\r\n";
            $requestHeader .= "Content-Type: application/x-www-form-urlencoded\r\n";
        }
        $requestHeader .= $this->getStandardRequestHeader();
        fwrite($fp, $requestHeader, strlen($requestHeader));
        if ($data) {
            fwrite($fp, $data, strlen($data));
        }
        while (!feof($fp)) {
            $responseStatusLine = fgets($fp, 1024);
            if (trim($responseStatusLine) == '') {
                continue;
            }
            list($protocol, $statusCode, $statusMessage) = explode(' ', $responseStatusLine);
            if ($statusCode != 200) {
                echo "ERROR:$responseStatusLine";
                fclose($fp);
                die;
            } else {
                break;
            }
        }
        return $fp;
    }

}

return new PHPQQ();
