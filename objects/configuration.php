<?php
global $global, $config;
if (!isset($global['systemRootPath'])) {
    require_once '../videos/configuration.php';
}
require_once $global['systemRootPath'] . 'objects/user.php';
require_once $global['systemRootPath'] . 'objects/functions.php';

class Configuration
{
    private $id;
    private $video_resolution;
    private $webSiteTitle;
    private $language;
    private $contactEmail;
    private $users_id;
    private $version;
    private $authCanUploadVideos;
    private $authCanViewChart;
    private $authCanComment;
    private $head;
    private $logo;
    private $logo_small;
    private $adsense;
    private $mode;
    // version 2.7
    private $disable_analytics;
    private $disable_youtubeupload;
    private $allow_download;
    private $session_timeout;
    private $autoplay;
    // version 3.1
    private $theme;
    //version 3.3
    private $smtp;
    private $smtpAuth;
    private $smtpSecure;
    private $smtpHost;
    private $smtpUsername;
    private $smtpPassword;
    private $smtpPort;
    // version 4
    private $encoderURL;

    public function __construct($video_resolution = "")
    {
        $this->load();
        if (!empty($video_resolution)) {
            $this->video_resolution = $video_resolution;
        }
    }

    public function load()
    {
        global $global;
        $sql = "SELECT * FROM configurations WHERE id = 1 LIMIT 1";
        //echo $sql;exit;
        // add true because I was not getting the SMTP configuration on function setSiteSendMessage(&$mail)
        $res = sqlDAL::readSql($sql, "", [], true);
        $result = sqlDAL::fetchAssoc($res);
        sqlDAL::close($res);
        if ($res && !empty($result)) {
            $config = $result;
            //var_dump($config);exit;
            foreach ($config as $key => $value) {
                $this->$key = $value;
            }
        } else {
            return false;
        }
    }

    public function save()
    {
        global $global;
        if (!User::isAdmin()) {
            header('Content-Type: application/json');
            die('{"error":"' . __("Permission denied") . '"}');
        }
        $this->users_id = User::getId();

        ObjectYPT::deleteCache("getEncoderURL");

        $sql = "UPDATE configurations SET "
                . "video_resolution = '{$this->video_resolution}',"
                . "webSiteTitle = '{$this->webSiteTitle}',"
                . "language = '{$this->language}',"
                . "contactEmail = '{$this->contactEmail}',"
                . "users_id = '{$this->users_id}',  "
                . "authCanUploadVideos = '{$this->authCanUploadVideos}',"
                . "authCanViewChart = '{$this->authCanViewChart}',"
                . "authCanComment = '{$this->authCanComment}',"
                . "encoderURL = '{$global['mysqli']->real_escape_string($this->_getEncoderURL())}',"
                . "head = '{$global['mysqli']->real_escape_string($this->getHead())}',"
                . "adsense = '{$global['mysqli']->real_escape_string($this->getAdsense())}',"
                . "mode = '{$this->getMode()}',"
                . "logo = '{$global['mysqli']->real_escape_string($this->getLogo())}',"
                . "logo_small = '{$global['mysqli']->real_escape_string($this->getLogo_small())}',"
                . "disable_analytics = '{$this->getDisable_analytics()}',"
                . "disable_youtubeupload = '{$this->getDisable_youtubeupload()}',"
                . "allow_download = '{$this->getAllow_download()}',"
                . "session_timeout = '{$this->getSession_timeout()}',"
                . "autoplay = '{$this->getAutoplay()}',"
                . "theme = '{$global['mysqli']->real_escape_string($this->getTheme())}',"
                . "smtp = '{$this->getSmtp()}',"
                . "smtpAuth = '{$this->getSmtpAuth()}',"
                . "smtpSecure = '{$global['mysqli']->real_escape_string($this->getSmtpSecure())}',"
                . "smtpHost = '{$global['mysqli']->real_escape_string($this->getSmtpHost())}',"
                . "smtpUsername = '{$global['mysqli']->real_escape_string($this->getSmtpUsername())}',"
                . "smtpPort = '{$global['mysqli']->real_escape_string($this->getSmtpPort())}',"
                . "smtpPassword = '{$global['mysqli']->real_escape_string($this->getSmtpPassword())}'"
                . " WHERE id = 1";


        return sqlDAL::writeSql($sql);
    }

    public function getVideo_resolution()
    {
        return $this->video_resolution;
    }

    public function getUsers_id()
    {
        return $this->users_id;
    }

    public function getVersion()
    {
        if (empty($this->version)) {
            return " 0.1";
        }
        return $this->version;
    }

    public function getWebSiteTitle()
    {
        return $this->webSiteTitle;
    }

    public function getLanguage()
    {
        if ($this->language == "en") {
            return "us";
        }
        return $this->language;
    }

    public function getContactEmail()
    {
        return $this->contactEmail;
    }

    public function setVideo_resolution($video_resolution)
    {
        $this->video_resolution = $video_resolution;
    }

    public function setWebSiteTitle($webSiteTitle)
    {
        $this->webSiteTitle = $webSiteTitle;
    }

    public function setLanguage($language)
    {
        $this->language = $language;
    }

    public function setContactEmail($contactEmail)
    {
        $this->contactEmail = $contactEmail;
    }

    public function currentVersionLowerThen($version)
    {
        return version_compare($version, $this->getVersion()) > 0;
    }

    public function currentVersionGreaterThen($version)
    {
        return version_compare($version, $this->getVersion()) < 0;
    }

    public function currentVersionEqual($version)
    {
        return version_compare($version, $this->getVersion()) == 0;
    }

    public function getAuthCanUploadVideos()
    {
        return $this->authCanUploadVideos;
    }

    public function getAuthCanViewChart()
    {
        return $this->authCanViewChart;
    }

    public function getAuthCanComment()
    {
        return $this->authCanComment;
    }

    public function setAuthCanUploadVideos($authCanUploadVideos)
    {
        $this->authCanUploadVideos = intval($authCanUploadVideos);
    }

    public function setAuthCanViewChart($authCanViewChart)
    {
        $this->authCanViewChart = $authCanViewChart;
    }

    public function setAuthCanComment($authCanComment)
    {
        $this->authCanComment = $authCanComment;
    }

    public function getHead()
    {
        return $this->head;
    }

    public function getLogo($timestamp = false)
    {
        global $global;
        if (empty($this->logo)) {
            return "view/img/logo.png";
        }
        $get = '';
        $file = str_replace("?", "", $global['systemRootPath'] . $this->logo);
        if ($timestamp && file_exists($file)) {
            $get .= "?" . filemtime($file);
        }
        return $this->logo . $get;
    }

    public static function _getFavicon($getPNG = false)
    {
        global $global;
        $file = false;
        $url = false;
        if (!$getPNG) {
            $file = $global['systemRootPath'] . "videos/favicon.ico";
            $url = getCDN()."videos/favicon.ico";
            if (!file_exists($file)) {
                $file = $global['systemRootPath'] . "view/img/favicon.ico";
                $url = getCDN()."view/img/favicon.ico";
            }
        }
        if (empty($url) || !file_exists($file)) {
            $file = $global['systemRootPath'] . "videos/favicon.png";
            $url = getCDN()."videos/favicon.png";
            if (!file_exists($file)) {
                $file = $global['systemRootPath'] . "view/img/favicon.png";
                $url = getCDN()."view/img/favicon.png";
            }
        }
        return ['file' => $file, 'url' => $url];
    }

    public function getFavicon($getPNG = false, $getTime = true)
    {
        $return = self::_getFavicon($getPNG);
        if ($getTime) {
            return $return['url'] . "?" . filemtime($return['file']);
        } else {
            return $return['url'];
        }
    }

    public static function getOGImage()
    {
        global $global;
        $destination = Video::getStoragePath()."cache/og_200X200.jpg";
        $return = self::_getFavicon(true);
        if (file_exists($return['file'])) {
            convertImageToOG($return['file'], $destination);
        }
        return getCDN() . "videos/cache/og_200X200.jpg";
    }

    public function setHead($head)
    {
        $this->head = $head;
    }

    public function setLogo($logo)
    {
        $this->logo = $logo;
    }

    public function getLogo_small()
    {
        if (empty($this->logo_small)) {
            return "view/img/logo32.png";
        }
        return $this->logo_small;
    }

    public function setLogo_small($logo_small)
    {
        $this->logo_small = $logo_small;
    }

    public function getAdsense()
    {
        return $this->adsense;
    }

    public function setAdsense($adsense)
    {
        $this->adsense = $adsense;
    }

    public function getMode()
    {
        if (empty($this->mode)) {
            return 'Youtube';
        }
        return $this->mode;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    // version 2.7
    public function getDisable_analytics()
    {
        return $this->disable_analytics;
    }

    public function getDisable_youtubeupload()
    {
        return $this->disable_youtubeupload;
    }

    public function getAllow_download()
    {
        return $this->allow_download;
    }

    public function getSession_timeout()
    {
        return $this->session_timeout;
    }

    public function setDisable_analytics($disable_analytics)
    {
        $this->disable_analytics = ($disable_analytics == 'true' || $disable_analytics == '1') ? 1 : 0;
    }

    public function setDisable_youtubeupload($disable_youtubeupload)
    {
        $this->disable_youtubeupload = ($disable_youtubeupload == 'true' || $disable_youtubeupload == '1') ? 1 : 0;
    }

    public function setAllow_download($allow_download)
    {
        $this->allow_download = ($allow_download == 'true' || $allow_download == '1') ? 1 : 0;
    }

    public function setSession_timeout($session_timeout)
    {
        $this->session_timeout = $session_timeout;
    }

    public function getAutoplay()
    {
        return intval($this->autoplay);
    }

    public function setAutoplay($autoplay)
    {
        $this->autoplay = ($autoplay == 'true' || $autoplay == '1') ? 1 : 0;
    }

    // end version 2.7

    public static function rewriteConfigFile()
    {
        global $global, $mysqlHost, $mysqlUser, $mysqlPass, $mysqlDatabase;
        if (empty($global['salt'])) {
            $global['salt'] = uniqid();
        }
        if (empty($global['disableTimeFix'])) {
            $global['disableTimeFix'] = 0;
        }
        if (empty($global['logfile'])) {
            $global['logfile'] = $global['systemRootPath'] . 'videos/avideo.log';
        }
        $content = "<?php
\$global['configurationVersion'] = 3.1;
\$global['disableAdvancedConfigurations'] = {$global['disableAdvancedConfigurations']};
\$global['videoStorageLimitMinutes'] = {$global['videoStorageLimitMinutes']};
\$global['disableTimeFix'] = {$global['disableTimeFix']};
\$global['logfile'] = '{$global['logfile']}';
if(!empty(\$_SERVER['SERVER_NAME']) && \$_SERVER['SERVER_NAME']!=='localhost' && !filter_var(\$_SERVER['SERVER_NAME'], FILTER_VALIDATE_IP)) {
    // get the subdirectory, if exists
    \$file = str_replace(\"\\\\\", \"/\", __FILE__);
    \$subDir = str_replace(array(\$_SERVER[\"DOCUMENT_ROOT\"], 'videos/configuration.php'), array('',''), \$file);
    \$global['webSiteRootURL'] = \"http\".(!empty(\$_SERVER['HTTPS'])?\"s\":\"\").\"://\".\$_SERVER['SERVER_NAME'].\$subDir;
}else{
    \$global['webSiteRootURL'] = '{$global['webSiteRootURL']}';
}
\$global['systemRootPath'] = '{$global['systemRootPath']}';
\$global['salt'] = '{$global['salt']}';
\$global['enableDDOSprotection'] = {$global['enableDDOSprotection']};
\$global['ddosMaxConnections'] = {$global['ddosMaxConnections']};
\$global['ddosSecondTimeout'] = {$global['ddosSecondTimeout']};
\$global['strictDDOSprotection'] = {$global['strictDDOSprotection']};
\$global['noDebug'] = 0;
\$global['webSiteRootPath'] = '';
if(empty(\$global['webSiteRootPath'])){
    preg_match('/https?:\/\/[^\/]+(.*)/i', \$global['webSiteRootURL'], \$matches);
    if(!empty(\$matches[1])){
        \$global['webSiteRootPath'] = \$matches[1];
    }
}
if(empty(\$global['webSiteRootPath'])){
    die('Please configure your webSiteRootPath');
}

\$mysqlHost = '{$mysqlHost}';
\$mysqlUser = '{$mysqlUser}';
\$mysqlPass = '{$mysqlPass}';
\$mysqlDatabase = '{$mysqlDatabase}';

/**
 * Do NOT change from here
 */

require_once \$global['systemRootPath'].'objects/include_config.php';
";

        $fp = fopen($global['systemRootPath'] . "videos/configuration.php", "wb");
        fwrite($fp, $content);
        fclose($fp);
    }

    public function getTheme()
    {
        if (empty($this->theme)) {
            return "default";
        }
        return $this->theme;
    }

    public function setTheme($theme)
    {
        $this->theme = $theme;
    }

    public function getSmtp()
    {
        return intval($this->smtp);
    }

    public function getSmtpAuth()
    {
        return intval($this->smtpAuth);
    }

    public function getSmtpSecure()
    {
        return $this->smtpSecure;
    }

    public function getSmtpHost()
    {
        return $this->smtpHost;
    }

    public function getSmtpUsername()
    {
        return $this->smtpUsername;
    }

    public function getSmtpPassword()
    {
        return $this->smtpPassword;
    }

    public function setSmtp($smtp)
    {
        $this->smtp = ($smtp == 'true' || $smtp == '1') ? 1 : 0;
    }

    public function setSmtpAuth($smtpAuth)
    {
        $this->smtpAuth = ($smtpAuth == 'true' || $smtpAuth == '1') ? 1 : 0;
    }

    public function setSmtpSecure($smtpSecure)
    {
        $this->smtpSecure = $smtpSecure;
    }

    public function setSmtpHost($smtpHost)
    {
        $this->smtpHost = $smtpHost;
    }

    public function setSmtpUsername($smtpUsername)
    {
        $this->smtpUsername = $smtpUsername;
    }

    public function setSmtpPassword($smtpPassword)
    {
        $this->smtpPassword = $smtpPassword;
    }

    public function getSmtpPort()
    {
        return intval($this->smtpPort);
    }

    public function setSmtpPort($smtpPort)
    {
        $this->smtpPort = intval($smtpPort);
    }

    public function _getEncoderURL()
    {
        if (substr($this->encoderURL, -1) !== '/') {
            $this->encoderURL .= "/";
        }
        return $this->encoderURL;
    }

    public function shouldUseEncodernetwork()
    {
        global $advancedCustom, $global;
        if (empty($advancedCustom->useEncoderNetworkRecomendation) || empty($advancedCustom->encoderNetwork)) {
            return false;
        }
        if ($advancedCustom->encoderNetwork === 'https://network.wwbn.net/') {
            // check if you have your own encoder
            $encoderConfigFile = "{$global['systemRootPath']}Encoder/videos/configuration.php";
            if (file_exists($encoderConfigFile)) { // you have an encoder do not use the public one
                _error_log("Configuration:shouldUseEncodernetwork 1 You checked the Encoder Network but you have your own encoder, we will ignore this option");
                return false;
            }

            if (substr($this->encoderURL, -1) !== '/') {
                $this->encoderURL .= "/";
            }

            if (!preg_match('/encoder[1-9].avideo.com/i', $this->encoderURL)) {
                $creatingImages = "{$this->encoderURL}view/img/creatingImages.jpg";
                if (isURL200($creatingImages)) {
                    _error_log("Configuration:shouldUseEncodernetwork 2 You checked the Encoder Network but you have your own encoder, we will ignore this option");
                    return false;
                }
            }
        }
        return true;
    }

    public static function deleteEncoderURLCache()
    {
        _error_log_debug("Configuration::deleteEncoderURLCache");
        $name = "getEncoderURL" . DIRECTORY_SEPARATOR;
        $tmpDir = ObjectYPT::getCacheDir();
        $cacheDir = $tmpDir . $name;
        ObjectYPT::deleteCache($name);
        rrmdir($cacheDir);
    }

    public function getEncoderURL()
    {
        global $global, $getEncoderURL, $advancedCustom;
        if (!empty($global['forceEncoderURL'])) {
            return $global['forceEncoderURL'];
        }
        if (empty($getEncoderURL)) {
            $getEncoderURL = ObjectYPT::getCache("getEncoderURL". DIRECTORY_SEPARATOR, 60);
            if (empty($getEncoderURL)) {
                if ($this->shouldUseEncodernetwork()) {
                    if (substr($advancedCustom->encoderNetwork, -1) !== '/') {
                        $advancedCustom->encoderNetwork .= "/";
                    }
                    $bestEncoder = _json_decode(url_get_contents($advancedCustom->encoderNetwork . "view/getBestEncoder.php", "", 10, true));
                    if (!empty($bestEncoder->siteURL)) {
                        $this->encoderURL = $bestEncoder->siteURL;
                    } else {
                        error_log("Configuration::getEncoderURL ERROR your network ($advancedCustom->encoderNetwork) is not configured properly This slow down your site a lot, disable the option useEncoderNetworkRecomendation in your CustomizeAdvanced plugin");
                    }
                } else {
                    //error_log("Configuration::getEncoderURL shouldUseEncodernetwork said no");
                }

                if (empty($this->encoderURL)) {
                    $getEncoderURL = "https://encoder1.wwbn.net/";
                }
                addLastSlash($this->encoderURL);
                $getEncoderURL = $this->encoderURL;
                ObjectYPT::setCache("getEncoderURL", $getEncoderURL);
            } else {
                //error_log("Configuration::getEncoderURL got it from cache ". json_encode($getEncoderURL));
            }
        }
        return $getEncoderURL;
    }

    public function setEncoderURL($encoderURL)
    {
        $this->encoderURL = $encoderURL;
    }

    public function getPageTitleSeparator()
    {
        if (!defined('PAGE_TITLE_SEPARATOR')) {
            define("PAGE_TITLE_SEPARATOR", "&middot;"); // This is ready to be configurable, if needed
        }
        return " " . PAGE_TITLE_SEPARATOR . " ";
    }
}
