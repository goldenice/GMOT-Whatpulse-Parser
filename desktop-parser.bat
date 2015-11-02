:: GMOT WhatPulse Parser
:: Desktop editie - werkt alleen op Windows
:: Benodigd: php
:: Zorg ervoor dat je het script in dezelfde directory uitvoert als waar je de repo
:: hebt gecloned!
:: Dit script gooit de BBC in het bestand "stats.txt".
:: 2015-11-02 Initiele release

@echo off
echo GMOT WhatPulse Parser - Desktop editie

:: Even checken of we geen domme user hebben.
echo Controleren of PHP geïnstalleerd is...
php --version > NUL
if errorlevel 1 goto errorNoPHP

:: Controleren of het script wel aanwezig is.
SET script=bbcodegenerator.php
echo Script controleren...
if EXIST %script% (
	:: Nu gaan we het script uitvoeren.
	echo Script uitvoeren...
	php %script% > stats.txt
	echo Als het goed is staan de statistieken in stats.txt!
	goto:eof
) ELSE (
	goto errorNoScript
)

:errorNoPHP
echo.
echo Error^: Het lijkt erop dat PHP niet geïnstalleerd is.
goto:eof

:errorNoScript
echo.
echo Error^: Kan het script niet vinden!
echo Error^: Heb je dit script wellicht niet in de goede map gezet?
goto:eof

