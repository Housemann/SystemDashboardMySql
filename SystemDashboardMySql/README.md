# IPSymcon System Dashboard (MySql)

[![PHPModule](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![IP-Symcon is awesome!](https://img.shields.io/badge/IP--Symcon-5.5-blue.svg)](https://www.symcon.de)

Modul zum Versenden und Anzeigen einzelner Nachrichten, die in einer MySql / MariaDB Datenbank abgespeichert werden.

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Installation](#3-installation)  
4. [Funktionsreferenz](#4-funktionsreferenz)  
5. [Beispiele](#5-beispiele)  

## 1. Funktionsumfang

Mit dem Modul lassen sich Nachrichten Versenden und Anzeigen, die in einer MySql / MariaDB Datenbank abgelegt werden. Das Modul änelt dem aus dem Forum, mit der Meldungsanzeige nur das hier die Nachrichten in der Datenbank bleiben.

Über verschiedene Filter können Nachrichten nach dem Meldungstyp (Information, Warnung, Alarm und Aufgabe) gefiltert werden. Über ein eigenens definiertes Variablenprofil, kann man eine Integer Variable zum Filtern der Nachrichten nutzen. Weiter kann man über einen Filter, alle gerade ungelesenen Nachrichten als gelesen markieren, oder man macht dieses einzeln. Das löschen einer Nachricht aus der Datenbank ist ebenfalls möglich.

Zum Ändern oder Löschen einzelner Nachrichten wird ein Webhook angelegt, der den Befehl an IPS sendet. 

Überischt SystemDashboard

![Uebersicht1](img/Uebersicht1.png?raw=true)

## 2. Voraussetzungen

 - IPS 5.5
 - MySql oder MariaDB Datenbank mit angelegtem Schema (z.B. ipsymcon) -- (getestet unter MariaDB)
 - Verschachtelung im WebFront Editor sollte aktiviert sein, damit man alles sieht

## 3. Installation

### a. Modul hinzufügen

Über das Module Control folgende URL hinzufügen: `https://github.com/Housemann/SystemDashboardMySql`
Danach eine neue Instanz hinzufügen und nach System Dashboard suchen und dieses installieren. Es werden zu dem Modul fünf Variablenprofile angelegt. 

Name                | Beschreibung
------------------- | --------------------------------------------------------------------------------------------
SDB.DeleteMessages  | Umschalten ob über Webhook nachrichten aus der DB gelöscht werden sollen
SDB.Filter          | Hier werden aus dem hinterlegtem Integer-Profil die Werte automatisch hinterlegt
SDB.MessageType     | Zum Filtern des Nachrichtentyps (Information, Warnung, Alarm und Aufgabe)
SDB.Status          | Filter gelesen / ungelesen
SDB.StatusChange    | Zum umändern aller ungelesenen Nachrichten als gelesen und umgekehrt

### b. Modul konfigurieren

Nach der Installation öffnet sich das Konfigurationsformular, wo man die Zugangsdaten seiner Datenbank interlegt. 
WICHTIG --> Ihr müsst zuvor ein Schema (eine Datenbank) erstellt haben. Es wird automatisch eine Tabelle "ips_MessageBoard" in der Datenbank mit Indexen erstellt.

![ModulKonf1](img/ModulKonf1.png?raw=true)

Tabellenfelder      | Datentyp                  | Beschreibung
------------------- | ------------------------- | ------------------------------------------------------------------
id                  | bigint NOT NULL           | Eindeutiger Schlüssel, wird automatisch hinterlegt
date                | timestamp NULL            | Erstelldatum der Nachricht, wird automatisch hinterlegt
message             | nvarchar(1000) NOT NULL   | Nachrichten Inhalt 
status              | int NOT NULL              | 0 = ungelesen / 1 = gelesen
type                | int NOT NULL              | 0 = Alle / 1 = Information / 2 = Warnung / 3 = Alarm / 4 = Aufgabe
icon                | nvarchar(20) NOT NULL     | Iconname aus IPS Symcon
craftname           | nvarchar(250) NOT NULL    | Name welcher in Filtervariable steht
expirationDate      | datetime NULL             | Abkaufdatum der Nachricht wann als gelesen Markiert werden soll
MediaID             | nvarchar(6) NULL          | MediaId aus IPS
AttachmentPath      | nvarchar(400) NULL        | Bild oder Dateipfad

Danach muss man ein Profil hinterlegen, wo Integerwerte hinterlegt sind. Ansonsten wird das Profil STNB.NotificationInstanzen angelegt, welches ich in einem anderen Modul nutze. 

![ProfilHinterlegen](img/ProfilHinterlegen.png?raw=true)

Zur demonstration habe ich mir das Profil "IntegerTestProfil" angelegt, welches ich im Modul hinterlege.

![TestProfil](img/TestProfil.png?raw=true)

Das Nachrichtenlimit habe ich bei mir auf 1500 eingegrenzt, da es bei zu vielen Nachrichten einen Überlauf der String-Variable gibt. 
Das Zeitinterval setzt anhand des Ablaufdatums was in einer Nachticht hinterlegt wird, diese auf gelesen. 
Im unteren Bereich muss für die HTML Box ein WebHook konfiguriert werden, damit das Ändern der entsprechenden Nachricht klappt.
Dann noch die Einstellungen mit "Änderungen Übernehmen" abschließen. 

![ModulKonf2](img/ModulKonf2.png?raw=true)

Nach der Anlage des Moduls sollte nun alles so aussehen...

![ObjektBaum1](img/ObjektBaum1.png?raw=true)

Das Variablen-Profil SDB.Filter sollte nun auch Werte aus eurem hinterlegtem Profil übernommen haben.

![Filter1](img/Filter1.png?raw=true)

Das Dashbord kann nun im Webfront angezeigt werden und schaut wie foglt aus...

![FirstLook](img/FirstLook.png?raw=true)


## 4. Funktionsreferenz

### b. Erste Nachricht senden

Zeilenumbrüche sind mit ```html <br>``` zu erstellen in der Nachricht.
Der Inhalt in der Var $NotificationType wird im Modul auf einen Integer-Wert geändert Information = 1, Warnung = 2, Alöarm = 3, Aufgabe = 4

```php
$InstanceId = 59723;

$NotificationSubject  = "";
$NotifyType           = "information";
$NotifyIcon           = "IPS";
$Message              = "Das ist meine erste Nachricht<br>im System Dashboard!";
$ExpirationTime       = 3600;
$MediaID              = "";
$AttachmentPath       = "";

SDB_SendSqlMessage($InstanceId, $NotifyType, $NotifyIcon, $NotificationSubject, $Message, $ExpirationTime, $MediaID, $AttachmentPath);
```

Danach sehr ihr diese Nachricht im Dashboard. 

![ErsteNachricht](img/ErsteNachricht.png?raw=true)

Wenn ihr ein Programm wie z.B. MySql Workbench oder PHP MyAdmin habt, könnt ihr die Nachricht auch dort sehen.

```sql
SELECT * FROM DATENBANKNAME.ips_MessageBoard order by date desc
```

![MYSQL](img/MYSQL.png?raw=true)
