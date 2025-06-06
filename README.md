
# EnergieMaintenancePrix

Projet de stage de fin de L3 - Traitement de prix négatifs de la bourse de l'électricité


## Cahier des charges

Ce qu'il reste à faire:
- React - Rendre le css plus joli
- React - Créer un bouton d'envoi des événements au serveur Flask
- PHP - Modifier le modèle de calcul (calcArretDemarrage()) de sorte à être plus précis
- PHP - Eventuellement tout automatiser via tâches cron
- PHP - Ajouter une fonction d'envoi des événements au serveur Flask
- Python - Définir les fonctions métier en utilisant [snap7](https://www.solisplc.com/tutorials/introduction-to-snap7-integration-into-siemens-tia-portal#memory-access-instructions) pour les automates Siemens ou [pymodbus](https://stackoverflow.com/questions/31912493/how-to-write-to-plc-input-registers-using-pymodbus) pour les automates Schneider

### Exemple de fonction métier pour Snap7

La fonction suivante inverse le 1er bit (index 0) du tableau mémoire:
```py
plc = snap7.client.Client()
plc.connect("192.168.51.104",0,2)

def flip():
    tab = plc.db_read(108,0,26) # Il y a 26 octets en tout
    arrReducDemain = ((tab[0] & 0x01)!=0)
    print(str(arrReducDemain))

    snap7.util.set_bool(tab, 0, 0, not(arrReducDemain))
    plc.db_write(108, 0, tab)
    return 0;
```

