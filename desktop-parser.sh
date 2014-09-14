#!/bin/bash
# GMOT WhatPulse Parser
# Desktop editie - werkt alleen op Linux & Bash
# Benodigd: php5-cli
# Zorg ervoor dat je het script in dezelfde directory uitvoert als waar je de repo
# hebt gecloned!
# Dit script gooit de BBC in het bestand "stats.txt".
# 2014-09-14 Initiele release

echo "GMOT WhatPulse Parser - Desktop editie"  > /dev/stderr

# Even checken of we geen domme user hebben.
PHP=$(which php);
echo "Controleren of PHP geïnstalleerd is..." > /dev/stderr
if [[ ! -f "$PHP" ]]; then
    echo "Het lijkt erop dat PHP niet geïnstalleerd is." > /dev/stderr
    echo "Meestal helpt het als je het pakket php5-cli installeert." > /dev/stderr
    exit 1
fi

# Nu gaan we het script uitvoeren.
SCRIPT="./bbcodegenerator.php"
echo "Script controleren..." > /dev/stderr
if [[ ! -f "$SCRIPT" ]]; then
    echo "Kan het script niet vinden!" > /dev/stderr
    echo "Heb je dit script wellicht niet in de goede map gezet?" > /dev/stder
    exit 1
fi
echo "Script uitvoeren..." > /dev/stderr
echo > /dev/stderr
php $SCRIPT > stats.txt
echo "Als het goed is staan de statistieken in stats.txt!" > /dev/stderr
