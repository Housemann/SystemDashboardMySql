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

