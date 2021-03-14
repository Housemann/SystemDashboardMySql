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
WICHTIG --> Ihr müsst zuvor ein Schema (eine Datenbank) erstellt haben. 

Überischt SystemDashboard
![ModulKonf1](img/ModulKonf1.png?raw=true)


