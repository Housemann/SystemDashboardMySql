<?php
    declare(strict_types=1);
    
    require_once __DIR__ . '/../libs/local.php';  // lokale Funktionen
    require_once __DIR__ . '/../libs/helper_variables.php';
    require_once __DIR__ . '/../libs/helper_hook.php';

    // Klassendefinition
    class SystemDashboardMySql extends IPSModule {
 
      use SDB_MySQLLocalLib;
      use SDB_HelperVariables;
      use SDB_HelperHook;

      // Überschreibt die interne IPS_Create($id) Funktion
      public function Create()
      {
        // Diese Zeile nicht löschen.
        parent::Create();

        // Propertys 
        $this->RegisterPropertyString('server', '');
        $this->RegisterPropertyInteger('port', '3306');
        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('database', '');
        $this->RegisterPropertyInteger('maxMessage', 1500);
        $this->RegisterPropertyString('UsernameWebHook', '');
        $this->RegisterPropertyString('PasswordWebHook', '');
        $this->RegisterPropertyInteger("UpdateIntervall",60);
        $this->RegisterPropertyString('getProfileassoziation', '');

        //Profile
        $this->RegisterProfileIntegerEx('SDB.Status'  , '', '', '', Array(
          Array(0 , $this->translate('Unread')       , '', '0xFFBE80'),
          Array(1 , $this->translate('Read')         , '', '0')
        ));          
        $this->RegisterProfileIntegerEx('SDB.MessageType'  , '', '', '', Array(
          Array(0 , $this->translate('All')           , '', '0'),
          Array(1 , $this->translate('Information')   , '', '0x00FF00'),
          Array(2 , $this->translate('Alert')         , '', '0xFF0000'),
          Array(3 , $this->translate('Warning')       , '', '0xFFFF00'),
          Array(4 , $this->translate('ToDo')          , '', '0x0000FF')
        ));      
        $this->RegisterProfileIntegerEx('SDB.Filter'  , '', '', '', Array(
          Array(0 , $this->translate('All')         , '', '0')
        ));
        $this->RegisterProfileIntegerEx('SDB.StatusChange'  , '', '', '', Array(
          Array(0 , $this->translate('Set status')    , '', '0x80BEFF')
        ));  
        $this->RegisterProfileIntegerEx('SDB.DeleteMessages'  , '', '', '', Array(
          Array(0 , $this->translate('Inactive')    , '', '0x000000'),
          Array(1 , $this->translate('Active')      , '', '0xFF0000')
        ));  

        // Variablen
        $this->RegisterVariableInteger("status", $this->translate("Status"), "SDB.Status", 1);
        $this->RegisterVariableInteger("messageType", $this->translate("Messagetype"), "SDB.MessageType", 2);
        $this->RegisterVariableInteger("filter", $this->translate("Filter"), "SDB.Filter", 3);
        $this->RegisterVariableInteger("statusChange", $this->translate("Status change"), "SDB.StatusChange", 4);
        $this->RegisterVariableInteger("delete", $this->translate("Delete"), "SDB.DeleteMessages", 5);
        $this->RegisterVariableInteger("numberAllMessages", $this->translate("Number of all messages"), "", 6);
        $this->RegisterVariableInteger("numberFilteredMessages", $this->translate("Number of filtred messages"), "", 7);
        $this->RegisterVariableInteger("numberUnreadMessages", $this->translate("Number of unread messages"), "", 8);
        $this->RegisterVariableInteger("numberMessagesRead", $this->translate("Number of messages read"), "", 9);
        $this->RegisterVariableString("messages", $this->translate("Messages"), "~HTMLBox", 10);

        // Enable Action
        $this->EnableAction("status");
        $this->EnableAction("messageType");
        $this->EnableAction("filter");
        $this->EnableAction("statusChange");
        $this->EnableAction("delete");

        // WebHook generieren
        $this->RegisterHook('/hook/'.$this->hook);

        // Timer
        $this->RegisterTimer ("Update", 0, 'SDB_TimerExpiredUpdate($_IPS[\'TARGET\']);');

      }
 
      public function Destroy() 
      {
          // Remove variable profiles from this module if there is no instance left
          $InstancesAR = IPS_GetInstanceListByModuleID('{B408EDD5-667E-C748-336D-F2911EE09BA2}');
          if ((@array_key_exists('0', $InstancesAR) === false) || (@array_key_exists('0', $InstancesAR) === NULL)) {
              $VarProfileAR = array('SDB.Status','SDB.MessageType','SDB.Filter','SDB.StatusChange','SDB.DeleteMessages');
              foreach ($VarProfileAR as $VarProfileName) {
                  @IPS_DeleteVariableProfile($VarProfileName);
              }
          }
          parent::Destroy();
      }        

      // Überschreibt die intere IPS_ApplyChanges($id) Funktion
      public function ApplyChanges() 
      {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

        $server = $this->ReadPropertyString('server');
        $port = $this->ReadPropertyInteger('port');
        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $database = $this->ReadPropertyString('database');
    
        if ($server != '' && $port > 0) {
          $ok = true;
          if ($server == '') {
              echo 'no value for property "server"';
              $ok = false;
          }
          if ($user == '') {
              echo 'no value for property "user"';
              $ok = false;
          }
          if ($password == '') {
              echo 'no value for property "password"';
              $ok = false;
          }
          if ($database == '') {
              echo 'no value for property "database"';
              $ok = false;
          }
          $this->SetStatus($ok ? IS_ACTIVE : self::$IS_INVALIDCONFIG);
          $this->CreateTable();
        } else {
          $this->SetStatus(IS_INACTIVE);
        }

        // Timer setzten
        $this->SetTimerInterval("Update", $this->ReadPropertyInteger("UpdateIntervall") * 60 * 1000);

        // VarProfil aktualisieren
        $this->FillVariableProfileFilter();

      }
      
      public function RequestAction($Ident, $Value) 
      {
        switch($Ident) {
          case "status":
            SetValue($this->GetIDForIdent($Ident), $Value);
            $this->SelectSqlStatements();
            break;
          case "messageType":
            SetValue($this->GetIDForIdent($Ident), $Value);
            $this->SelectSqlStatements();
            break;
          case "filter":
            SetValue($this->GetIDForIdent($Ident), $Value);
            $this->SelectSqlStatements();
            break;
          case "statusChange":
            SetValue($this->GetIDForIdent($Ident), $Value);
            $this->UpdateSqlStatements();
            $this->SelectSqlStatements();
            break;
          case "delete":
            SetValue($this->GetIDForIdent($Ident), $Value);
            $this->SelectSqlStatements();
            break;                                                                                      
          default:
            throw new Exception("Invalid Ident");
        }
      }

      // WebHook Daten verarbeiten
      protected function ProcessHookData()
      {
        $this->SendDebug('Data', print_r($_GET, true), 0);
        
        if ((IPS_GetProperty($this->InstanceID, 'UsernameWebHook') != '') || (IPS_GetProperty($this->InstanceID, 'PasswordWebHook') != '')) {
          if (!isset($_SERVER['PHP_AUTH_USER'])) {
              $_SERVER['PHP_AUTH_USER'] = '';
          }
          if (!isset($_SERVER['PHP_AUTH_PW'])) {
              $_SERVER['PHP_AUTH_PW'] = '';
          }

          if (($_SERVER['PHP_AUTH_USER'] != IPS_GetProperty($this->InstanceID, 'UsernameWebHook')) || ($_SERVER['PHP_AUTH_PW'] != IPS_GetProperty($this->InstanceID, 'PasswordWebHook'))) {
              header('WWW-Authenticate: Basic Realm="NotificationBoard WebHook"');
              header('HTTP/1.0 401 Unauthorized');
              echo 'Authorization required';
              $this->SendDebug('Unauthorized', print_r($_GET, true), 0);
              return;
          }
          
          $id = $_GET['id'];
          if (isset($id)) {
            switch ($_GET['action']) {
              case 'toggle':
                $ValueStatus = $this->GetValue("status");
                if($ValueStatus==0) {
                  $query = "update ips_MessageBoard set status=1 WHERE status=0 and id=".$id;
                } else {
                  $query = "update ips_MessageBoard set status=0 WHERE status=1 and id=".$id;
                }
                $this->SqlExecute($query);
                $this->SelectSqlStatements();
                break;
              case 'delete':
                $query = "delete from ips_MessageBoard where id=".$id;
                $this->SqlExecute($query);
                $this->SelectSqlStatements();
                break;
            }	  
         }
        }
      }

      // Abfragen anhand der Filterung in DashBoard
      private function SelectSqlStatements() 
      {
        $status = $this->GetValue("status");
        $messageType = $this->GetValue("messageType");
        $filter = GetValueFormatted($this->GetIDForIdent("filter"));
        $LimitSQL = $this->ReadPropertyInteger("maxMessage");         

        $query = "select id, date, message, status, type, icon, craftname, expirationDate 
                  from ips_MessageBoard where status=".$status;

        $queryCnt = "select count(*) cnt from ips_MessageBoard where status=".$status;            

        $queryMessageStatus = "select status, count(*) cnt from ips_MessageBoard group by status order by status";
                  
        if($messageType!==0) {
          $addWhere1 = " and type=".$messageType;
          $query = $query . $addWhere1;
          $queryCnt = $queryCnt . $addWhere1;
        }
        
        if($filter!==$this->translate("All")) {
          $addWhere2 = " and craftname='".$filter."'";
          $query = $query . $addWhere2;
          $queryCnt = $queryCnt . $addWhere2;
        }

        $query = $query . " order by date desc limit ".$LimitSQL;

        $sqlRows = @$this->SqlExecute($query);
        $sqlRowsCnt = @$this->SqlExecute($queryCnt);
        $sqlRowsCntStatus = @$this->SqlExecute($queryMessageStatus);
        
        // Falls Offset 0 ist prüfung und auf 0 setzten
        $sqlRowsAllStatus0 = isset($sqlRowsCntStatus[0]['cnt']) ? $sqlRowsCntStatus[0]['cnt'] : 0; 
        $sqlRowsAllStatus1 = isset($sqlRowsCntStatus[1]['cnt']) ? $sqlRowsCntStatus[1]['cnt'] : 0;

        $this->renderData($sqlRows);
        $this->SetValue("numberFilteredMessages", $sqlRowsCnt[0]['cnt']);
        $this->SetValue("numberUnreadMessages", isset($sqlRowsCntStatus[0]['cnt']) ? $sqlRowsCntStatus[0]['cnt'] : 0);
        $this->SetValue("numberMessagesRead", isset($sqlRowsCntStatus[1]['cnt']) ? $sqlRowsCntStatus[1]['cnt'] : 0);
        $this->SetValue("numberAllMessages", $sqlRowsAllStatus0 + $sqlRowsAllStatus1);

      }

      // Update der Daten anhand des Filters
      private function UpdateSqlStatements()
      {
        $status = $this->GetValue("status");
        $messageType = $this->GetValue("messageType");
        $filter = GetValueFormatted($this->GetIDForIdent("filter"));
        $LimitSQL = $this->ReadPropertyInteger("maxMessage");

        $query = "update ips_MessageBoard 
                  set  status=##setStatus## 
                  WHERE status=".$status;

        if($messageType!==0) {
          $addWhere1 = " and type=".$messageType;
          $query = $query . $addWhere1;
        }

        if($filter!==$this->translate("All")) {
          $addWhere2 = " and craftname='".$filter."'";
          $query = $query . $addWhere2;
        }

        if($status==0) {
          $query = str_replace("##setStatus##","1",$query);
        } else {
          $query = str_replace("##setStatus##","0",$query);
        }
        
        @$this->SqlExecute($query);
      }

      // Timer für Updateintervall
      public function TimerExpiredUpdate() 
      {
        $query = "update ips_MessageBoard set status=1 where status = 0 and expirationDate < NOW()";
        @$this->SqlExecute($query);
        $this->SelectSqlStatements();
      }

      // Bestimmtes Profil auslesen und in Filter schreiben
      public function FillVariableProfileFilter() 
      {
        $profilename = 'SDB.Filter';

        #Profil wo Daten her geholt werden
        
        $ProfileForData = $this->ReadPropertyString("getProfileassoziation");
        if(empty($ProfileForData))
          $ProfileForData = 'STNB.NotificationInstanzen';

        if (IPS_VariableProfileExists($profilename) === false) {
          IPS_CreateVariableProfile($profilename, 1);
        }

        #Assoziationen immer vorher leeren
        $GetVarProfile = IPS_GetVariableProfile ( $profilename );
        foreach($GetVarProfile['Associations'] as $assi ) {
          @IPS_SetVariableProfileAssociation ($profilename, $assi['Value'], "", "", 0 );
        }

        $Profile = IPS_GetVariableProfile($ProfileForData); // NotificationInstanzen
        $ArrayInstanzIDs = array();
        foreach($Profile['Associations'] as $profAssi) {
          $ArrayInstanzIDs[] = array('id'=>$profAssi['Value'],'name'=>$profAssi['Name']);
        }

        IPS_SetVariableProfileAssociation($profilename, 0, $this->translate("All"), "", 0 );
        asort($ArrayInstanzIDs);
        foreach($ArrayInstanzIDs as $key) {
          IPS_SetVariableProfileAssociation($profilename, $key['id'], $key['name'], "", 0 );
        }
      }

      // Funktion zum senden einer Nachricht ins DashBoard
      public function SendSqlMessage(string $type, string $icon, string $craftname, string $msg, int $expirationTime) 
      {
        if (is_numeric($type)==false) { 
          switch(strtolower($type)) {
              case "information":
                  $type=1;
                  break;
              case "alarm":
                  $type=2;
                  break;
              case "warnung":
                  $type=3;
                  break;
              case "aufgabe":
                  $type=4;
                  break;
              case "homematic":
                  $type=5;
                  break;
          }
        } elseif (is_numeric($type)==true) {
          $type = $type;
        }
        
        if(empty($expirationTime)) {
          $expirationDate = "NULL";
        } else {
          $expirationDate = "DATE_ADD(NOW(), INTERVAL ".$expirationTime." SECOND)";
        }
  
        $query = "insert into ips_MessageBoard (date,message,status,type,icon,craftname,expirationDate) VALUES (NOW(),'".addslashes($msg)."',0,".$type.",'".$icon."','".$craftname."',".$expirationDate.")";
        @$this->SqlExecute($query);
        $this->SelectSqlStatements();
      
      }

      // HtmlBox fuellen
      private function renderData(array $sqlRows) {
        $ValueDeleteStatusID = $this->GetValue("delete");
        $Username = $this->ReadPropertyString("UsernameWebHook");
        $Password = $this->ReadPropertyString("PasswordWebHook");

        // Etwas CSS und HTML
        $style = "";
        $style = $style.'<style type="text/css">';
        $style = $style.'table.msg { width:100%; border-collapse: collapse; }';
        $style = $style.'td.fst { width: 36px; padding: 2px; border-left: 1px solid rgba(255, 255, 255, 0.2); border-top: 1px solid rgba(255, 255, 255, 0.1); }';
        $style = $style.'td.mid { padding: 2px;  border-top: 1px solid rgba(255, 255, 255, 0.1); }';
        $style = $style.'td.lst { width: 42px; text-align:center; padding: 2px;  border-right: 1px solid rgba(255, 255, 255, 0.2); border-top: 1px solid rgba(255, 255, 255, 0.1); }';
        $style = $style.'tr:last-child { border-bottom: 1px solid rgba(255, 255, 255, 0.2); }';
        $style = $style.'.blue { padding: 5px; color: rgb(255, 255, 255); background-color: rgb(0, 0, 255); background-icon: linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-icon: -o-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -moz-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -webkit-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -ms-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); }';
        $style = $style.'.red { padding: 5px; color: rgb(255, 255, 255); background-color: rgb(255, 0, 0); background-icon: linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-icon: -o-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -moz-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -webkit-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -ms-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); }';
        $style = $style.'.green { padding: 5px; color: rgb(255, 255, 255); background-color: rgb(0, 255, 0); background-icon: linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-icon: -o-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -moz-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -webkit-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -ms-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); }';
        $style = $style.'.yellow { padding: 5px; color: rgb(255, 255, 255); background-color: rgb(255, 255, 0); background-icon: linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-icon: -o-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -moz-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -webkit-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -ms-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); }';
        $style = $style.'.orange { padding: 5px; color: rgb(255, 255, 255); background-color: rgb(255, 160, 0); background-icon: linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-icon: -o-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -moz-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -webkit-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -ms-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); }';
        $style = $style.'.time {  vertical-align: text-top; float:left; font-size: 9px; padding-left:3px; padding-top: 0px; padding-right: 2px; margin-right:4px; }';
        $style = $style.'.img { vertical-align: text-top; padding-left: 8px; padding-right: 1px; padding-bottom:0px; width:25px; height: 25px;}';
        $style = $style.'.bild { margin-left:4px; margin-right:3px; padding: 1px; padding-top:4px; padding-right:0px; width:30px; height: 30px;}';
        
        $style = $style.'.pagebutton {float:left; padding: 5px; margin-right: 1px; color: rgb(255, 255, 255); background-color: rgb(255, 160, 0); background-icon: linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-icon: -o-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -moz-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -webkit-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); background-image: -ms-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 50%,rgba(0,0,0,0.3) 100%); }';
          
        $style = $style.'</style>';
        
        $content = $style;
        $content = $content.'<table class="msg">';
        
            $count = 0;
            if(is_countable($sqlRows)) {
                $count = count($sqlRows);
            }
    
            if ($count == 0) {
                $content = $content.'<tr>';
                $content = $content.'<td class="fst"><img src=\'img/icons/Ok.svg\'></img></td>';
                $content = $content.'<td class="mid">Keine Meldungen vorhanden!</td>';
                $content = $content.'<td class=\'mid\'>&nbsp;</td>';
                $content = $content.'<td class=\'lst\'>&nbsp;</td>';
                $content = $content.'</tr>';
        } else {
          foreach ($sqlRows as $rows) {
            if ($rows['type']) {
              switch ($rows['type']) {
                #case 5:
                #	$type = 'orange';
                #break;
                case 4:
                  $type = 'blue';
                break;
                case 3:
                  $type = 'yellow';
                break;
                case 2:
                  $type = 'red';
                break;
                case 1:
                  $type = 'green';
                break;
                default:
                  $type = '';
                break;
              }
            } else {
              $type = 'green';
            }
            if ($rows['icon']) {
              $icon = '<img src=\'img/icons/'.$rows['icon'].'.svg\' class="img"></img>';
            } else {
              $icon = '<img src=\'img/icons/Ok.svg\' class="img"></img>';
            }
            
            $phpdate = strtotime($rows['date']);
            $bild = "";
          
            $content .= '<tr>';
            $content = $content.'<td class="fst">'.$icon.'<div class="time">'. date("d.m.Y H:i:s", $phpdate) . '</div></td>';
            #$content = $content.'<td class="mid">' . utf8_decode($rows['message']).'</td>';
            $content = $content.'<td class="mid">' . $rows['message'].'</td>';
          
            $content = $content . "<td class='mid'>";
            if($bild != "") { 
              #$linkbild = '<div><img class=\'bild\' src=\'' . $bild . ' \' onclick="window.xhrGet=function xhrGet(o) {var HTTP = new XMLHttpRequest();HTTP.open(\'GET\',o.url,true);HTTP.send();};window.xhrGet({ url: \'hook/msg?ts=\' + (new Date()).getTime() + \'&action=showimage&image='.$bild.'&number='.$rows['id'].'&text='.utf8_encode($rows['message']).'\' });" ></div>'; 
            } else { 
              $linkbild = ""; 
            }
            $content = $content . "</td>";
          
            ################################################################################################################################################################################################################################################################################################################################################				
            if($ValueDeleteStatusID == 0) {
              $content = $content.'<td class=\'lst\'><div class=\''.$type.'\' onclick="window.xhrGet=function xhrGet(o) {var HTTP = new XMLHttpRequest();HTTP.open(\'GET\',o.url,true,\''.$Username.'\',\''.$Password.'\');HTTP.send();};window.xhrGet({ url: \'hook/SystemDashboard?ts=\' + (new Date()).getTime() + \'&action=toggle&id='.$rows['id'].'\' });">OK</div></td>';
            } elseif ($ValueDeleteStatusID == 1) {
              $content = $content.'<td class=\'lst\'><div class=\''.$type.'\' onclick="window.xhrGet=function xhrGet(o) {var HTTP = new XMLHttpRequest();HTTP.open(\'GET\',o.url,true,\''.$Username.'\',\''.$Password.'\');HTTP.send();};window.xhrGet({ url: \'hook/SystemDashboard?ts=\' + (new Date()).getTime() + \'&action=delete&id='.$rows['id'].'\' });">OK</div></td>';
            }
            ################################################################################################################################################################################################################################################################################################################################################
            $content .= '</tr>';
          }
        }
        $content = $content. '</table>';
        $this->SetValue("messages", $content);
      }


      ###############################################################################################################################
      ###############################   SQL Bereich für die Verbindung und Konfiguration und Execute  ###############################
      ###############################################################################################################################
      // Test Connection
      public function TestConnection()
      {
          $rows = $this->ExecuteSimple('select now() as now');
          if ($rows && isset($rows[0]->now)) {
              $now = strtotime($rows[0]->now);
              $tstamp = date('d.m.Y H:i:s', $now);
              $n = $now - time();
              if (abs($n) > 1) {
                  $msg = ', differ from localtime ' . $n . 'sec';
              } else {
                  $msg = '';
              }
              echo 'current timestamp on database-server is ' . $tstamp . $msg;
              $this->SetStatus(IS_ACTIVE);
          } else {
              $this->SetStatus(self::$IS_INVALIDCONFIG);
          }
      }

      // Tabelle erstellen
      public function CreateTable()
      {
        # SQL TABELLE ANLEGEN (date not NULL geht nicht für MariaDB weil immer mit CurrentTimestamp belegt wird)
        $query = "
        CREATE TABLE IF NOT EXISTS `ips_MessageBoard` (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          date timestamp NULL,
          message NVARCHAR(1000) NOT NULL,
          status int NOT NULL,
          type int NOT NULL,
          icon nvarchar(20) NOT NULL,
          craftname NVARCHAR(250) NOT NULL,
          expirationDate datetime NULL
        )";

        #Create Indexe anlegen
        $createuniqueindex = "create unique index IF NOT EXISTS ui_id on ips_MessageBoard (id)";
        $createqueryindex = "create index IF NOT EXISTS i_stat_type_craft on ips_MessageBoard (status,type,craftname)";
        
        $this->SqlExecute($query);
        $this->SqlExecute($createuniqueindex);
        $this->SqlExecute($createqueryindex);
      }

      // Funktion für saemtliche aufrufe im Modul
      public function SqlExecute(string $query) 
      {
        $data = $this->ExecuteSimple($query);
        return json_decode(json_encode($data),true);
      }
      
      // Funktion zum aufruf der query
      private function ExecuteSimple(string $statement)
      {
          $server = $this->ReadPropertyString('server');
          $database = $this->ReadPropertyString('database');
  
          $dbHandle = $this->Open();
  
          if ($dbHandle == false) {
              return $dbHandle;
          }
  
          $ret = $this->Query($dbHandle, $statement);
  
          return $ret;
      }

      // Open MySQL verbindung
      private function Open()
      {
          $server = $this->ReadPropertyString('server');
          $port = $this->ReadPropertyInteger('port');
          $user = $this->ReadPropertyString('user');
          $password = $this->ReadPropertyString('password');
          $database = $this->ReadPropertyString('database');
  
          $this->SendDebug(__FUNCTION__, 'open database ' . $database . '@' . $server . ':' . $port . '(user=' . $user . ')', 0);
  
          $dbHandle = new mysqli($server, $user, $password, $database, $port);
          if ($dbHandle->connect_errno) {
              $this->SendDebug(__FUNCTION__, " => can't open database", 0);
              echo "can't open database " . $database . '@' . $server . ': ' . $dbHandle->connect_error . "\n";
  
              return false;
          }
  
          $this->SendDebug(__FUNCTION__, ' => dbHandle=' . print_r($dbHandle, true), 0);
  
          return $dbHandle;
      }
  
      // Close MySQL Connection
      private function Close(object $dbHandle)
      {
          if ($dbHandle && $dbHandle->close() == false) {
              echo "unable to close database\n";
  
              return false;
          }
  
          return true;
      }

      // Query verarbeiten
      private function Query(object $dbHandle, string $statement)
      {
          $server = $this->ReadPropertyString('server');
          $database = $this->ReadPropertyString('database');
  
          if ($dbHandle == false) {
              echo 'unable to execute statement "' . $statement . "\": invalid database-handle\n";
  
              return $dbHandle;
          }
  
          $this->SendDebug(__FUNCTION__, 'query "' . $statement . '" on ' . $database . '@' . $server, 0);
          $res = $dbHandle->query($statement);
          if ($res == false) {
              $this->SendDebug(__FUNCTION__, ' => unable to query', 0);
              echo 'unable to execute statement "' . $statement . '": ' . $dbHandle->error . "\n";
  
              return $res;
          }
  
          if (!isset($res->num_rows)) {
              return $res;
          }
  
          $this->SendDebug(__FUNCTION__, ' => got ' . $res->num_rows . ' rows', 0);
  
          $rows = [];
          while ($row = $res->fetch_object()) {
              $rows[] = $row;
          }
          $res->close();
  
          return $rows;
      }
      ###############################################################################################################################
      ###############################################################################################################################


















    }