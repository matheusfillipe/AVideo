<?php

global $global;
require_once $global['systemRootPath'] . 'plugin/Plugin.abstract.php';
require_once $global['systemRootPath'] . 'plugin/Plugin.abstract.php';
require_once $global['systemRootPath'] . 'plugin/YPTWallet/Objects/Wallet.php';
require_once $global['systemRootPath'] . 'plugin/YPTWallet/Objects/Wallet_log.php';
require_once $global['systemRootPath'] . 'objects/autoload.php';

class YPTWallet extends PluginAbstract
{
    const MANUAL_WITHDRAW = "Manual Withdraw Funds";
    const MANUAL_ADD = "Manual Add Funds";

    public function getTags()
    {
        return array(
            PluginTags::$MONETIZATION,
            PluginTags::$NETFLIX,
            PluginTags::$FREE,
        );
    }
    public function getDescription()
    {
        return "Wallet for AVideo";
    }

    public function getName()
    {
        return "YPTWallet";
    }

    public function getUUID()
    {
        return "2faf2eeb-88ac-48e1-a098-37e76ae3e9f3";
    }

    public function getPluginVersion()
    {
        return "4.0";
    }

    public function getEmptyDataObject()
    {
        $obj = new stdClass();
        $obj->decimalPrecision = 2;
        $obj->wallet_button_title = "My Wallet";
        $obj->add_funds_text = "<h1>Adding money instantly from credit/debit card</h1>Add funds on your Account Balance, to support our videos";
        $obj->add_funds_success_success = "<h1>Thank you,<br> Your funds has been added<h1>";
        $obj->add_funds_success_cancel = "<h1>Ops,<br> You have cancel it<h1>";
        $obj->add_funds_success_fail = "<h1>Sorry,<br> Your funds request has been fail<h1>";
        $obj->transfer_funds_text = "<h1>Transfer money for other users</h1>Transfer funds from your account to another user account";
        $obj->transfer_funds_success_success = "<h1>Thank you,<br> Your funds has been transfered<h1>";
        $obj->transfer_funds_success_fail = "<h1>Sorry,<br> Your funds transfer request has been fail<h1>";
        $obj->withdraw_funds_text = "<h1>Withdraw money from your</h1>Transfer funds from your account to your credit card or bank account";
        $obj->withdraw_funds_success_success = "<h1>Thank you,<br> Your request was submited<h1>";
        $obj->withdraw_funds_success_fail = "<h1>Sorry,<br> Your funds withdraw request has been fail<h1>";
        $obj->virtual_currency = "HEART";  // we will show this currency on the wallet but we will not make transactions on the payment gateway with it
        $obj->virtual_currency_symbol = "❤";  // we will show this currency on the wallet but we will not make transactions on the payment gateway with it
        $obj->virtual_currency_exchange_rate = "2"; // means 1 real currency will be 2 virtual currencies
        $obj->virtual_currency_decimalPrecision = 2.5; // the value 2.5 on it means if you purchase 2 real currency it will worth 5 virtual currencies
        $obj->virtual_currency_enable = false;
        $obj->currency = "USD";
        $obj->currency_symbol = "$";
        $obj->addFundsOptions = "[5,10,20,50]";
        $obj->showWalletOnlyToAdmin = false;
        $obj->CryptoWalletName = "Bitcoin Wallet Address";
        $obj->CryptoWalletEnabled = false;
        $obj->hideConfiguration = false;
        $obj->enableAutomaticAddFundsPage = true;
        // add funds
        $obj->enableManualAddFundsPage = false;
        $obj->manualAddFundsMenuTitle = "Add Funds/Deposit";
        $obj->manualAddFundsPageButton = "Notify Deposit Made";
        $obj->manualAddFundsNotifyEmail = "yourEmail@yourDomain.com";
        $obj->manualAddFundsTransferFromUserId = 1;
        // sell funds
        $obj->enableManualWithdrawFundsPage = true;
        $obj->enableAutoWithdrawFundsPagePaypal = false;
        $obj->withdrawFundsOptions = "[5,10,20,50,100,1000]";
        $obj->manualWithdrawFundsMenuTitle = "Withdraw Funds";
        $obj->manualWithdrawFundsPageButton = "Request Withdraw";
        $obj->manualWithdrawFundsNotifyEmail = "yourEmail@yourDomain.com";
        $obj->manualWithdrawFundsminimum = 1;
        $obj->manualWithdrawFundsmaximum = 100;
        $obj->manualWithdrawFundsTransferToUserId = 1;

        $plugins = self::getAvailablePlugins();
        foreach ($plugins as $value) {
            $eval = "\$obj->enablePlugin_{$value} = false;";
            eval($eval);
            $dataObj = self::getPluginDataObject($value);
            $obj = (object) array_merge((array) $obj, (array) $dataObj);
        }

        return $obj;
    }

    public function updateScript() {
        global $global;
        if (AVideoPlugin::compareVersion($this->getName(), "4.0") < 0) {
            $sqls = file_get_contents($global['systemRootPath'] . 'plugin/YPTWallet/install/updateV4.0.sql');
            $sqlParts = explode(";", $sqls);
            foreach ($sqlParts as $value) {
                sqlDal::writeSql(trim($value));
            }
        }
        return true;
    }
    
    public function getBalance($users_id)
    {
        $wallet = self::getWallet($users_id);
        return $wallet->getBalance();
    }

    public function getBalanceText($users_id)
    {
        $balance = $this->getBalanceFormated($users_id);
        return self::formatCurrency($balance);
    }

    public function getBalanceFormated($users_id)
    {
        $balance = $this->getBalance($users_id);
        $obj = $this->getDataObject();
        return number_format($balance, $obj->decimalPrecision);
    }

    public static function formatCurrency($value, $addHTML=false, $doNotUseVirtualCurrency = false, $currency=false)
    {
        $value = floatval($value);
        $obj = AVideoPlugin::getObjectData('YPTWallet');
        $currency_symbol = $obj->currency_symbol;
        $decimalPrecision = $obj->decimalPrecision;
        if($currency===false){
            $currency = $obj->currency;
        }
        if (empty($doNotUseVirtualCurrency) && $obj->virtual_currency_enable) {
            $currency_symbol = $obj->virtual_currency_symbol;
            $decimalPrecision = $obj->virtual_currency_decimalPrecision;
            $currency = $obj->virtual_currency;
        }
        $value = number_format($value, $decimalPrecision);

        if ($addHTML) {
            return "{$currency_symbol} <span class=\"walletBalance\">{$value}</span> {$currency}";
        } else {
            return "{$currency_symbol} {$value} {$currency}";
        }
    }

    public static function getStep($doNotUseVirtualCurrency = false)
    {
        $obj = AVideoPlugin::getObjectData('YPTWallet');
        $decimalPrecision = $obj->decimalPrecision;
        if ($obj->virtual_currency_enable) {
            $decimalPrecision = $obj->virtual_currency_decimalPrecision;
        }
        if (empty($decimalPrecision)) {
            return 1;
        }
        return "0.".str_repeat("0", $decimalPrecision-1)."1";
    }

    public static function formatFloat($value)
    {
        $value = floatval($value);
        $obj = AVideoPlugin::getObjectData('YPTWallet');
        return number_format($value, $obj->decimalPrecision);
    }

    public static function getWallet($users_id)
    {
        $wallet = new Wallet(0);
        $wallet->setUsers_id($users_id);
        return $wallet;
    }

    public function getOrCreateWallet($users_id)
    {
        $wallet = new Wallet(0);
        $wallet->setUsers_id($users_id);
        if (empty($wallet->getId())) {
            $wallet_id = $wallet->save();
            $wallet = new Wallet($wallet_id);
        }
        return $wallet;
    }

    public function getAllUsers($activeOnly = true)
    {
        global $global;
        $sql = "SELECT w.*, u.*, u.id as user_id, IFNULL(balance, 0) as balance FROM users u "
                . " LEFT JOIN wallet w ON u.id = w.users_id WHERE 1=1 ";

        if ($activeOnly) {
            $sql .= " AND status = 'a' ";
        }

        $sql .= BootGrid::getSqlFromPost(array('name', 'email', 'user'));

        $res = $global['mysqli']->query($sql);
        $user = array();

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $row = cleanUpRowFromDatabase($row);
                $row['name'] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $row['name']);
                $row['identification'] = User::getNameIdentificationById($row['user_id']);
                $row['identification'] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $row['identification']);
                unset($row['about']);
                $row['background'] = User::getBackground($row['user_id']);
                $row['photo'] = User::getPhoto($row['user_id']);
                $row['crypto_wallet_address'] = "";
                $user[] = $row;
            }
            //$user = $res->fetch_all(MYSQLI_ASSOC);
        } else {
            $user = false;
            die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        return $user;
    }

    public static function getTotalBalance()
    {
        global $global;
        $sql = "SELECT sum(balance) as total FROM wallet ";

        $res = $global['mysqli']->query($sql);
        $user = array();

        if ($res) {
            if ($row = $res->fetch_assoc()) {
                return $row['total'];
            }
        } else {
            die($sql . '\nError : (' . $global['mysqli']->errno . ') ' . $global['mysqli']->error);
        }
        return 0;
    }

    public static function getTotalBalanceText()
    {
        $value = self::getTotalBalance();
        return self::formatCurrency($value);
    }

    public function getHistory($user_id)
    {
        $wallet = self::getWallet($user_id);
        $log = new WalletLog(0);
        $rows = $log->getAllFromWallet($wallet->getId());
        return $rows;
    }

    public static function exchange($value)
    {
        $obj = AVideoPlugin::getObjectData('YPTWallet');
        $value = floatval($value);
        $virtual_currency_exchange_rate = floatval($obj->virtual_currency_exchange_rate);
        if (!empty($virtual_currency_exchange_rate)) {
            $value *= $virtual_currency_exchange_rate;
        }
        return $value;
    }

    /**
     *
     * @param type $users_id
     * @param type $value
     * @param type $description
     * @param type $json_data
     * @param type $mainWallet_user_id A user ID where the money comes from and where the money goes for
     */
    public function addBalance($users_id, $value, $description = "", $json_data = "{}", $mainWallet_user_id = 0, $noNotExchangeValue = false)
    {
        global $global;
        $obj = $this->getDataObject();
        if (empty($noNotExchangeValue) && !empty($obj->virtual_currency_enable)) {
            $originalValue = $value;
            $value = self::exchange($value);

            $originalValueFormated = self::formatCurrency($originalValue, false, true);
            $valueFormated = self::formatCurrency($value);
            $description .= " Rate Exchanged {$originalValueFormated} => {$valueFormated} ";
        }
        $wallet = $this->getOrCreateWallet($users_id);
        $balance = $wallet->getBalance();
        _error_log("YPTWallet::addBalance BEFORE (user_id={$users_id}) (balance={$balance})");
        $balance += $value;
        $wallet->setBalance($balance);
        $wallet_id = $wallet->save();

        WalletLog::addLog($wallet_id, $value, $description, $json_data, "success", "addBalance");

        if (!empty($mainWallet_user_id)) {
            $wallet = $this->getOrCreateWallet($mainWallet_user_id);
            $balance = $wallet->getBalance();
            $balance += ($value * -1);
            $wallet->setBalance($balance);
            $wallet_id = $wallet->save();
            $user = new User($users_id);
            WalletLog::addLog($wallet_id, ($value * -1), " From user ($users_id) " . $user->getUser() . " - " . $description, $json_data, "success", "addBalance to main wallet");
        }

        $wallet = $this->getOrCreateWallet($users_id);
        $balance = $wallet->getBalance();
        _error_log("YPTWallet::addBalance AFTER (user_id={$users_id}) (balance={$balance})");
        //_error_log("YPTWallet::addBalance $wallet_id, $value, $description, $json_data");
    }

    public function saveBalance($users_id, $value)
    {
        if (!User::isAdmin()) {
            return false;
        }
        $wallet = self::getWallet($users_id);
        $balance = $wallet->getBalance();
        $wallet->setBalance($value);
        $wallet_id = $wallet->save();
        $description = "Admin set your balance, from {$balance} to {$value}";
        WalletLog::addLog($wallet_id, $value, $description, "{}", "success", "saveBalance");
    }

    public static function transferBalanceToSiteOwner($users_id_from, $value, $description = "", $forceTransfer = false)
    {
        $obj = AVideoPlugin::getObjectData('YPTWallet');
        if (empty($obj->manualWithdrawFundsTransferToUserId)) {
            _error_log("YPTWallet::transferBalanceToSiteOwner site owner is not defined in the plugin, define it on the option manualWithdrawFundsTransferToUserId", AVideoLog::$ERROR);
        }
        return self::transferBalance($users_id_from, $obj->manualWithdrawFundsTransferToUserId, $value, $description, $forceTransfer);
    }

    public static function transferBalanceFromSiteOwner($users_id_from, $value, $description = "", $forceTransfer = false)
    {
        $obj = AVideoPlugin::getObjectData('YPTWallet');
        return self::transferBalance($obj->manualWithdrawFundsTransferToUserId, $users_id_from, $value, $description, $forceTransfer);
    }

    public static function transferBalanceFromMeToSiteOwner($value)
    {
        if (!User::isLogged()) {
            return false;
        }
        return self::transferBalanceToSiteOwner(User::getId(), $value);
    }

    public static function transferBalanceFromOwnerToMe($value)
    {
        if (!User::isLogged()) {
            return false;
        }
        return self::transferBalanceFromSiteOwner(User::getId(), $value);
    }

    public static function transferBalance($users_id_from, $users_id_to, $value, $forceDescription = "", $forceTransfer = false) {
        global $global;
        _error_log("transferBalance: $users_id_from, $users_id_to, $value, $forceDescription, $forceTransfer");
        if (!User::isAdmin()) {
            if ($users_id_from != User::getId() && !$forceTransfer) {
                _error_log("transferBalance: you are not admin, $users_id_from,$users_id_to, $value");
                return false;
            }
        }
        if (!User::idExists($users_id_from) || !User::idExists($users_id_to)) {
            _error_log("transferBalance: user does not exists, $users_id_from,$users_id_to, $value");
            return false;
        }
        $value = floatval($value);
        if ($value <= 0) {
            return false;
        }
        $wallet = self::getWallet($users_id_from);
        $balance = $wallet->getBalance();
        $newBalance = $balance - $value;
        if ($newBalance < 0) {
            _error_log("transferBalance: you dont have balance, $users_id_from,$users_id_to, $value (Balance: {$balance}) (New Balance: {$newBalance})");
            return false;
        }
        $identificationFrom = User::getNameIdentificationById($users_id_from);
        $identificationTo = User::getNameIdentificationById($users_id_to);

        $wallet->setBalance($newBalance);
        $wallet_id = $wallet->save();

        $description = "Transfer Balance {$value} from <strong>YOU</strong> to user <a href='{$global['webSiteRootURL']}channel/{$users_id_to}'>{$identificationTo}</a>";
        if (!empty($forceDescription)) {
            $description = $forceDescription;
        }
        
        $log_id_from = WalletLog::addLog($wallet_id, "-" . $value, $description, "{}", "success", "transferBalance to");


        $wallet = self::getWallet($users_id_to);
        $balance = $wallet->getBalance();
        $newBalance = $balance + $value;
        $wallet->setBalance($newBalance);
        $wallet_id = $wallet->save();
        $description = "Transfer Balance {$value} from user <a href='{$global['webSiteRootURL']}channel/{$users_id_from}'>{$identificationFrom}</a> to <strong>YOU</strong>";
        if (!empty($forceDescription)) {
            $description = $forceDescription;
        }
        ObjectYPT::clearSessionCache();
        
        $log_id_to = WalletLog::addLog($wallet_id, $value, $description, "{}", "success", "transferBalance from");
        return array('log_id_from'=>$log_id_from, 'log_id_to'=>$log_id_to);
    }
    
    public static function transferAndSplitBalanceWithSiteOwner($users_id_from, $users_id_to, $value, $siteowner_percentage, $forceDescription = "") {
        
        $response1 = self::transferBalance($users_id_from, $users_id_to, $value, $forceDescription, true);
        $response2 = true;
        if(!empty($siteowner_percentage)){
            $siteowner_value = ($value/100)*$siteowner_percentage;
            if($response1){
                $response2 = self::transferBalanceToSiteOwner($users_id_to, $siteowner_value, $forceDescription." {$siteowner_percentage}% fee",true);
            }
        }
        
        return $response1 && $response2;
    }

    public function getHTMLMenuRight()
    {
        global $global;
        if (!User::isLogged()) {
            return "";
        }
        $obj = $this->getDataObject();
        if ($obj->showWalletOnlyToAdmin && !User::isAdmin()) {
            return "";
        }
        include $global['systemRootPath'] . 'plugin/YPTWallet/view/menuRight.php';
    }

    public static function getAvailablePayments(){
        global $global;

        if (!User::isLogged()) {
            echo getButtonSignInAndUp();
            return false;
        }

        $dir = self::getPluginDir();
        $plugins = self::getEnabledPlugins();
        foreach ($plugins as $value) {
            $subdir = $dir . DIRECTORY_SEPARATOR . $value . DIRECTORY_SEPARATOR;
            $file = $subdir . "{$value}.php";
            if (is_dir($subdir) && file_exists($file)) {
                require_once $file;
                $eval = "\$obj = new {$value}();\$obj->getAprovalButton();";
                eval($eval);
            }
        }
        return true;
    }

    public static function getAvailableRecurrentPayments(){
        global $global;

        if (!User::isLogged()) {
            $redirectUri = getSelfURI();
            if (!empty($redirectUri)) {
                $redirectUri = "&redirectUri=" . urlencode($redirectUri);
            }
            echo getButtonSignUp(). getButtonSignIn();;
            return false;
        }

        $dir = self::getPluginDir();
        $plugins = self::getEnabledPlugins();
        foreach ($plugins as $value) {
            $subdir = $dir . DIRECTORY_SEPARATOR . $value . DIRECTORY_SEPARATOR;
            $file = $subdir . "{$value}.php";
            if (is_dir($subdir) && file_exists($file)) {
                require_once $file;
                $eval = "\$obj = new {$value}();\$obj->getRecurrentAprovalButton();";
                eval($eval);
            }
        }
    }    

    public static function getAvailableRecurrentPaymentsV2($total = '1.00', $currency = "USD", $frequency = "Month", $interval = 1, $name = '', $json = '', $addFunds_Success='', $trialDays = 0){
        global $global;

        if (!User::isLogged()) {
            $redirectUri = getSelfURI();
            if (!empty($redirectUri)) {
                $redirectUri = "&redirectUri=" . urlencode($redirectUri);
            }
            echo getButtonSignUp(). getButtonSignIn();;
            return false;
        }

        $dir = self::getPluginDir();
        $plugins = self::getEnabledPlugins();
        foreach ($plugins as $value) {
            $subdir = $dir . DIRECTORY_SEPARATOR . $value . DIRECTORY_SEPARATOR;
            $file = $subdir . "{$value}.php";
            if (is_dir($subdir) && file_exists($file)) {
                require_once $file;
                $eval = "\$obj = new {$value}();\$obj->getRecurrentAprovalButtonV2(\$total, \$currency, \$frequency, \$interval, \$name, \$json, \$addFunds_Success, \$trialDays);";
                eval($eval);
            }
        }
    }

    public static function getAvailablePlugins()
    {
        $dir = self::getPluginDir();
        $dirs = scandir($dir);
        $plugins = array();
        foreach ($dirs as $key => $value) {
            if (!in_array($value, array(".", ".."))) {
                $subdir = $dir . DIRECTORY_SEPARATOR . $value . DIRECTORY_SEPARATOR;
                $file = $subdir . "{$value}.php";
                if (is_dir($subdir) && file_exists($file)) {
                    $plugins[] = $value;
                }
            }
        }
        return $plugins;
    }

    public static function getEnabledPlugins()
    {
        global $global;
        $plugins = self::getAvailablePlugins();
        $wallet = new YPTWallet();
        $obj = $wallet->getDataObject();
        foreach ($plugins as $key => $value) {
            $eval = "\$val = \$obj->enablePlugin_{$value};";
            eval($eval);
            if (empty($val)) {
                unset($plugins[$key]);
            }
        }
        return $plugins;
    }

    public static function getPluginDataObject($pluginName)
    {
        $dir = self::getPluginDir();
        $file = $dir . "/{$pluginName}/{$pluginName}.php";
        if (file_exists($file)) {
            require_once $file;
            $eval = "\$obj = new {$pluginName}();";
            eval($eval);
            return $obj->getEmptyDataObject();
        }
        return array();
    }

    public static function getPluginDir()
    {
        global $global;
        $dir = $global['systemRootPath'] . "plugin/YPTWallet/plugins";
        return $dir;
    }

    public function sendEmails($emailsArray, $subject, $message)
    {
        global $global, $config;
        $siteTitle = $config->getWebSiteTitle();
        $footer = $config->getWebSiteTitle();
        $body = $this->replaceTemplateText($siteTitle, $footer, $message);
        return $this->send($emailsArray, $subject, $body);
    }

    private function replaceTemplateText($siteTitle, $footer, $message)
    {
        global $global, $config;
        $text = file_get_contents("{$global['systemRootPath']}plugin/YPTWallet/template.html");
        $words = array($siteTitle, $footer, $message);
        $replace = array('{siteTitle}', '{footer}', '{message}');

        return str_replace($replace, $words, $text);
    }

    private function send($emailsArray, $subject, $body)
    {
        if (empty($emailsArray)) {
            return false;
        }
        $emailsArray = array_unique($emailsArray);

        global $global, $config;

        //Create a new PHPMailer instance
        $mail = new \PHPMailer\PHPMailer\PHPMailer;
        setSiteSendMessage($mail);
        //Set who the message is to be sent from
        $mail->setFrom($config->getContactEmail(), $config->getWebSiteTitle());
        //Set who the message is to be sent to
        foreach ($emailsArray as $value) {
            if (empty($value)) {
                continue;
            }
            $mail->addBCC($value);
        }
        //Set the subject line
        $mail->Subject = $subject;
        $mail->msgHTML($body);

        //send the message, check for errors
        if (!$mail->send()) {
            _error_log("Wallet email FAIL [{$subject}] {$mail->ErrorInfo}");
            return false;
        } else {
            _error_log("Wallet email sent [{$subject}]");
            return true;
        }
    }

    /**
     *
     * @param type $wallet_log_id
     * @param type $new_status
     * return true if balance is enought
     */
    public function processStatus($wallet_log_id, $new_status)
    {
        $obj = $this->getDataObject();
        $walletLog = new WalletLog($wallet_log_id);
        $wallet = new Wallet($walletLog->getWallet_id());
        $oldStatus = $walletLog->getStatus();
        if ($walletLog->getType() == self::MANUAL_WITHDRAW) {
            if ($new_status != $oldStatus) {
                if ($oldStatus == "success" || $oldStatus == "pending") {
                    if ($new_status == "canceled") {
                        // return the value
                        return self::transferBalance($obj->manualWithdrawFundsTransferToUserId, $wallet->getUsers_id(), $walletLog->getValue());
                    } else {
                        // keep the value
                        return true;
                    }
                }
                // get the value again
                if ($oldStatus == "canceled") {
                    return self::transferBalance($wallet->getUsers_id(), $obj->manualWithdrawFundsTransferToUserId, $walletLog->getValue());
                }
            }
        } elseif ($walletLog->getType() == self::MANUAL_ADD) {
            if ($oldStatus == "pending") {
                if ($new_status == "canceled") {
                    // do nothing
                    return true;
                } elseif ($new_status == "success") {
                    // transfer the value
                    return self::transferBalance($obj->manualAddFundsTransferFromUserId, $wallet->getUsers_id(), $walletLog->getValue());
                }
            } elseif ($oldStatus == "success") {
                //get the money back
                return self::transferBalance($wallet->getUsers_id(), $obj->manualAddFundsTransferFromUserId, $walletLog->getValue());
            } elseif ($oldStatus == "canceled") {
                if ($new_status == "pending") {
                    // do nothing
                    return true;
                } elseif ($new_status == "success") {
                    // transfer the value
                    return self::transferBalance($obj->manualAddFundsTransferFromUserId, $wallet->getUsers_id(), $walletLog->getValue());
                }
            }
        }
        return true;
    }

    public static function getUserBalance($users_id=0)
    {
        if (empty($users_id)) {
            $users_id = User::getId();
        }
        if (empty($users_id)) {
            return 0;
        }
        $wallet = self::getWallet($users_id);
        return $wallet->getBalance();
    }

    public function getFooterCode()
    {
        global $global;
        $obj = $this->getDataObject();
        $js = "";
        $js .= "<script src=\"".getCDN()."plugin/YPTWallet/script.js\"></script>";

        return $js;
    }
    
    static function setAddFundsSuccessRedirectURL($url){
        _session_start();
        $_SESSION['addFunds_Success'] = $url;
    }   
    
    static function getAddFundsSuccessRedirectURL(){
        return @$_SESSION['addFunds_Success'];
    }
    
    static function setAddFundsSuccessRedirectToVideo($videos_id){
        self::setAddFundsSuccessRedirectURL(getRedirectToVideo($videos_id));
    }
    
    public function getWalletConfigurationHTML($users_id, $wallet, $walletDataObject) {
        global $global;
        if(empty($walletDataObject->CryptoWalletEnabled)){
            return '';
        }
        include_once $global['systemRootPath'].'plugin/YPTWallet/getWalletConfigurationHTML.php';
    }
    
    static function setLogInfo($wallet_log_id, $information){
        if(!is_array($wallet_log_id)){
            $wallet_log_id = array($wallet_log_id);
        }
        foreach ($wallet_log_id as $id) {
            $w = new WalletLog($id);
            $w->setInformation($information);
            $w->save();
        }
    }
    
    static function setLogDescription($wallet_log_id, $description){
        if(!is_array($wallet_log_id)){
            $wallet_log_id = array($wallet_log_id);
        }
        foreach ($wallet_log_id as $id) {
            $w = new WalletLog($id);
            $w->setDescription($description);
            $w->save();
        }
    }
}
