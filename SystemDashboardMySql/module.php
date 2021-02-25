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

        //Profile
        $this->RegisterProfileIntegerEx('SDB.Status'  , '', '', '', Array(
          Array(0 , $this->translate('Unread')       , '', '0xFFBE80'),
          Array(1 , $this->translate('read')         , '', '0')
        ));          
        $this->RegisterProfileIntegerEx('SDB.MessageType'  , '', '', '', Array(
          Array(0 , $this->translate('All')           , '', '0'),
          Array(1 , $this->translate('Information')   , '', '0x00FF00'),
          Array(2 , $this->translate('Alert')         , '', '0xFF0000'),
          Array(3 , $this->translate('Warning')       , '', '0xFFFF00'),
          Array(4 , $this->translate('ToDo')          , '', '0x0000FF')
        ));      
        $this->RegisterProfileIntegerEx('SDB.Filter'  , '', '', '', Array(
          Array(0 , $this->translate('empty')         , '', '0')
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
      }
 
      
      public function RequestAction($Ident, $Value) 
      {
        switch($Ident) {
          case "status":
            SetValue($this->GetIDForIdent($Ident), $Value);
            break;
          case "messageType":
            SetValue($this->GetIDForIdent($Ident), $Value);
            break;
          case "filter":
            SetValue($this->GetIDForIdent($Ident), $Value);
            break;
          case "statusChange":
            SetValue($this->GetIDForIdent($Ident), $Value);
            break;
          case "delete":
            SetValue($this->GetIDForIdent($Ident), $Value);
            break;                                                                                      
          default:
            throw new Exception("Invalid Ident");
        }
      }
















      public function FillVariableProfileFilter() 
      {
        $profilename = 'SDB.Filter';

        #Profil wo Daten her geholt werden
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

        IPS_SetVariableProfileAssociation($profilename, 0, "Alle", "", 0 );
        asort($ArrayInstanzIDs);
        foreach($ArrayInstanzIDs as $key) {
          IPS_SetVariableProfileAssociation($profilename, $key['id'], $key['name'], "", 0 );
        }
      }

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





      ##################################################################################################
      ###############################   SQL Bereich für die Verbindung   ###############################
      ##################################################################################################
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
      ##################################################################################################
      ##################################################################################################


















    }