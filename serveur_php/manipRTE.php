<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//////////////////////////////////////
/// Récupération des prix horaires ///
//////////////////////////////////////

$credentials = "NDc4ZDFlYWQtYzZjNS00MDBlLTliMjUtYTAxMmM0YTk4NTE4OjY2YjVjODk4LTQzNWYtNDFkYy1hMDkzLWM5MDVhZWUyNzcyMA==";

function getAccessToken(String $credentials) {
    $url = 'https://digital.iservices.rte-france.com/token/oauth/';
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Authorization: Basic $credentials\r\n" .
                         "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => ''
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    if ($response === false) {
        die("Erreur lors de la récupération du token.");
    }
    $json = json_decode($response, true);
    return $json['access_token'] ?? null;
}

function getPowerExchangeData($accessToken) {
    $url = "https://digital.iservices.rte-france.com/open_api/wholesale_market/v2/france_power_exchanges";
    $options = [
        'http' => [
            'method'  => 'GET',
            'header'  => "Authorization: Bearer $accessToken\r\n"
        ]
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return $result ? json_decode($result, true) : null;
}

function setPricesJson($dataPrix, $filePath) {
    $values = $dataPrix['france_power_exchanges'][0]['values'] ?? [];
    $prixJournalier = [];

    foreach ($values as $entry) {
        if (isset($entry['price'])) {
            $prixJournalier[] = floatval($entry['price']);
        }
    }

    // Lire les centrales existantes
    $listeCentrales = [];
    if (file_exists($filePath)) {
        $json = json_decode(file_get_contents($filePath), true);
        $listeCentrales = $json['listeCentrales'] ?? [];
    }

    $result = [
        'prixJournalier' => $prixJournalier,
        'listeCentrales' => $listeCentrales
    ];

    file_put_contents($filePath, json_encode($result, JSON_PRETTY_PRINT));
}

function updatePrices($credentials, $filePath){
    $accessToken = getAccessToken($credentials);
    if (!$accessToken) die("Impossible de récupérer le token.");

    $data = getPowerExchangeData($accessToken);
    if (!$data) die("Erreur lors de l'appel à l'API.");

    setPricesJson($data, $filePath);
}

function getPricesJson($filePath) {
    if (!file_exists($filePath)) return [];

    $json = json_decode(file_get_contents($filePath), true);
    return $json['prixJournalier'] ?? [];
}


///////////////////////
/// Classes système ///
///////////////////////

abstract class Turbine {
    public $id;
    public $pMini;
    public $pMaxi;
    public $tAMaxi;
    public $arretPossible;
    public $reductionPossible;

    function valeur($prix){
        if ($this instanceof TurbineML){ return $prix; }
        else if ($this instanceof TurbineOA){
            if (($this->negatif)&&($prix<0)){ return $prix; }
            else{
                if(estDansIntervalleSaisonnier(new DateTime(),new DateTime($this->dateEte),new DateTime($this->dateHiver))){return $this->palierOAEte;}
                else{return $this->palierOAHiver;}
            }
        }
    }


    function __construct($data) {
        $this->id = $data['nom'] ?? '';
        $this->pMini = $data['pMini'] ?? null;
        $this->pMaxi = $data['pMaxi'] ?? null;
        $this->tAMaxi = $data['tAMax'] ?? null;
        $this->arretPossible = $data['arretPossible'] ?? false;
        $this->reductionPossible = $data['reductionPossible'] ?? false;
    }
}

class TurbineML extends Turbine {
}

class TurbineOA extends Turbine {
    public $negatif = false;
    public $palierOAHiver;
    public $palierOAEte;
    public $dateHiver;
    public $dateEte;

    function __construct($data) {
        parent::__construct($data);
        $this->negatif = $data['modeChoisi'] === 'OA-';
        $this->palierOAEte = $data['palierOAe'] ?? null;
        $this->palierOAHiver = $data['palierOAh'] ?? null;
        $this->dateEte = $data['dateEte'] ?? null;
        $this->dateHiver = $data['dateHiver'] ?? null;
    }
}

class Centrale {
    public $nom;
    public $listeTurbines;
    public $vnf;
    public $seuil;

    // Calcul des priorités
    function calcPrio($tabPrix){
        $listeJour=array();
        for ($h=0; $h<24; $h++){
            $heure=array();
            $prio=array();
            $heure["heure"]=$h;
            foreach ($this->listeTurbines as $turbine){
                // Calculer sa valeur à l'heure
                // ML = $tabPrix[$h]
                // OA = Valeur OA
                // OA- = Valeur OA si positif, sinon ML
                $valeur=$turbine->valeur($tabPrix[$h]);
                $heure[$turbine->id]=$valeur;
                $prio[$turbine->id]=$valeur;
            }
            // Trier par valeur
            arsort($prio);
            $heure["prio"]=array_keys($prio);
            $listeJour[]=$heure;
        }
        return $listeJour;
    }

    // Calcul du démarrage/arrêt des ML/OA-
    function calcArretDemarrage($tabPrix) {
        $seuil = isset($this->seuil) ? $this->seuil : 4;
        $listeArrets = array();

        $duree = $this->sommeArrets();
        list($debut, $fin, $valeur) = $this->zoneNegative($tabPrix);

        // Surface totale
        $somme = array_sum($tabPrix);
        $listeArrets["valeurNormale"] = $somme;

        // Message utilisateur
        $listeArrets["message"] = "Il y a une surface négative de " . $valeur . "€. ";
        $listeArrets["tableau"] = array();

        // Calcul de base (aucun arrêt)
        $solutionSansAD = $this->calcJournee($tabPrix, 0, 0, 0);
        $solutionSansAD["commentaire"] = "Sans arrêt ni redémarrage";
        $meilleureSoluce = $solutionSansAD;
        $meilleurProfit = $solutionSansAD["profit"];

        // Cas sans arrêt recommandé
        if ($valeur >= -$seuil) {
            $solutionSansAD["meilleur"] = true;
            $listeArrets["message"] .= "Aucun arrêt n'est donc suggéré.";
            $listeArrets["tableau"][] = $solutionSansAD;
            $listeArrets["meilleurProfit"] = $meilleurProfit;
            return $listeArrets;
        }

        $listeArrets["message"] .= "Un arrêt peut être intéressant.";

        $listeTemp = array();
        $listeTemp[] = $solutionSansAD; // On stocke tout ici avant de marquer le meilleur

        for ($i = $debut - $duree; $i <= $debut; $i++) {
            for ($j = $fin - $duree; $j <= $fin; $j++) {
                if ($i < $j && $j - $i >= $duree) {
                    $soluce = $this->calcJournee($tabPrix, $i, $j, $duree);
                    if ($soluce["profit"] > $meilleurProfit) {
                        $meilleurProfit = $soluce["profit"];
                        $meilleureSoluce = $soluce;
                    }
                    $listeTemp[] = $soluce;
                }
            }
        }

        // Marquer la meilleure solution
        foreach ($listeTemp as &$soluce) {
            if ($soluce["profit"] === $meilleurProfit) {
                $soluce["meilleur"] = true;
                $listeArrets["message"] .= " Meilleur arrêt: ".$soluce["arret"]."h, meilleur redémarrage: ".$soluce["redemarrage"]."h.";
            } else {
                $soluce["meilleur"] = false;
            }
            $listeArrets["tableau"][] = $soluce;
        }

        $listeArrets["meilleurProfit"] = $meilleurProfit;
        return $listeArrets;
    }

    function sommeArrets(){
        $somme=0;
        foreach($this->listeTurbines as $turbine){
            if($turbine instanceof TurbineML or $turbine instanceof TurbineOA && $turbine->negatif){
                if($turbine->arretPossible){$somme+=$turbine->tAMaxi;}
                else if($turbine->reductionPossible){$somme+=$turbine->tAMaxi*$turbine->pMini/$turbine->pMaxi;}
            }
        }
        return ceil($somme/60);
    }

    function zoneNegative($tabPrix){
        $debut=-1;
        $fin=-1;
        $valeur=0;
        for($h=0;$h<24;$h++){
            if($tabPrix[$h]<0){
                if($debut==-1){$debut=$h;}
                $fin=$h;
            }
        }
        if($fin!=-1 && $fin!=23){
            $fin++;
        }
        if($debut!=-1){
            $valeur=0;
            for($i=$debut;$i<$fin;$i++){
                $valeur+=$tabPrix[$i];
            }
        }
        return array($debut,$fin,$valeur);
    }

    function calcJournee($tabPrix,$hArret,$hDemarrage,$duree){
        $soluce=array();
        $soluce["arret"]=$hArret;
        $soluce["redemarrage"]=$hDemarrage;
        $ligne=array();
        $somme=0;
        for($h=0; $h<24; $h++){
            $facteur = 1.0;

            // Phase d'arrêt progressif
            if ($h >= $hArret && $h < $hArret + $duree) {
                $progression = $h - $hArret;
                $debut = 1 - ($progression / $duree);
                $fin = 1 - (($progression + 1) / $duree);
                $facteur = max(0, ($debut + $fin) / 2);
            }

            // Arrêt complet
            else if ($h >= $hArret + $duree && $h < $hDemarrage) {
                $facteur = 0;
            }

            // Phase de redémarrage progressif
            else if ($h >= $hDemarrage && $h < $hDemarrage + $duree) {
                $progression = $h - $hDemarrage;
                $debut = $progression / $duree;
                $fin = ($progression + 1) / $duree;
                $facteur = min(1, ($debut + $fin) / 2);
            }

            $profitHeure = $tabPrix[$h] * $facteur;
            $somme += $profitHeure;
            $ligne[] = $profitHeure;
        }
        $soluce["journee"]=$ligne;
        $soluce["profit"]=$somme;
        return $soluce;
    }

    function __construct($data) {
        $this->nom = $data['nom'] ?? '';
        $this->vnf = $data['vnf'] ?? false;
        $this->seuil = $data['seuil'] ?? null;

        foreach ($data['listeTurbines'] ?? [] as $turbineData) {
            switch ($turbineData['modeChoisi'] ?? 'ML') {
                case 'OA':
                case 'OA-':
                    $this->listeTurbines[] = new TurbineOA($turbineData);
                    break;
                case 'ML':
                default:
                    $this->listeTurbines[] = new TurbineML($turbineData);
            }
        }
    }
}

class Systeme {
    public $prixJournalier;
    public $listeCentrales;

    function mailJournalier(){
        $dest="";
        $sujet="Recapitulatif du ".date("d/m/y",strtotime("+1 day"));
        $contenu="Voici la liste des actions suggérées pour cette journée:";
        foreach($this->listeCentrales as $centrale){
            $contenu.=$centrale->traduction();
        }
        mail($dest, $sujet, $contenu);
    }
}

function estDansIntervalleSaisonnier(DateTime $date, DateTime $dateDebut, DateTime $dateFin): bool {
    $cleDate = $date->format('md'); // Mois + jour, ex: "0315" pour le 15 mars
    $cleDebut = $dateDebut->format('md');
    $cleFin = $dateFin->format('md');

    if ($cleDebut <= $cleFin) {
        // Cas normal : ex. 15/03 → 30/09
        return $cleDate >= $cleDebut && $cleDate < $cleFin;
    } else {
        // Cas qui traverse l'année : ex. 15/10 → 15/03
        return $cleDate >= $cleDebut || $cleDate < $cleFin;
    }
}

/////////////////////////////////////
/// Controle de la config système ///
/////////////////////////////////////

function nouvelleCentrale($filePath) {
    $systeme = [];

    // Chargement et décodage du fichier si existant
    if (file_exists($filePath)) {
        $contenu = file_get_contents($filePath);
        $systeme = json_decode($contenu, true);
    }

    // Initialisation du système si vide ou invalide
    if (!is_array($systeme) || !isset($systeme['listeCentrales'])) {
        $systeme = [
            'prixJournalier' => [],
            'listeCentrales' => []
        ];
    }

    // Génération d’un nom unique pour la nouvelle centrale
    $baseNom = "Nouvelle Centrale";
    $nomsExistants = array_map(fn($c) => $c['nom'], $systeme['listeCentrales']);

    $nomFinal = $baseNom;
    $suffixe = 1;
    while (in_array($nomFinal, $nomsExistants)) {
        $nomFinal = "$baseNom ($suffixe)";
        $suffixe++;
    }

    // Création de la centrale avec seulement un nom
    $nouvelleCentrale = [
        'nom' => $nomFinal
    ];

    $systeme['listeCentrales'][] = $nouvelleCentrale;

    // Sauvegarde du fichier JSON
    file_put_contents($filePath, json_encode($systeme, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return ["message" => "Centrale ajoutée avec succès"];
}


function getCentrales($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }

    $json = file_get_contents($filePath);
    $data = json_decode($json, true);

    if (!is_array($data) || !isset($data['listeCentrales'])) {
        return [];
    }

    // Extraction uniquement des noms
    return array_map(fn($centrale) => $centrale['nom'] ?? '', $data['listeCentrales']);
}

function getCentrale($filePath, $nomRecherche) {
    if (!file_exists($filePath)) {
        return ["error" => "Fichier non trouvé"];
    }

    $json = file_get_contents($filePath);
    $data = json_decode($json, true);

    if (!isset($data['listeCentrales'])) {
        return ["error" => "Aucune centrale trouvée"];
    }

    foreach ($data['listeCentrales'] as $centrale) {
        if ($centrale['nom'] === $nomRecherche) {
            return $centrale;
        }
    }

    return ["error" => "Centrale introuvable"];
}

function deleteCentrale($filePath, $nomRecherche) {
    if (!file_exists($filePath)) {
        return ["error" => "Fichier non trouvé"];
    }

    $json = file_get_contents($filePath);
    $data = json_decode($json, true);

    if (!isset($data['listeCentrales']) || !is_array($data['listeCentrales'])) {
        return ["error" => "Structure du fichier invalide"];
    }

    $originalCount = count($data['listeCentrales']);

    // Supprimer la centrale ayant le nom donné
    $data['listeCentrales'] = array_values(array_filter($data['listeCentrales'], function ($centrale) use ($nomRecherche) {
        return $centrale['nom'] !== $nomRecherche;
    }));

    if (count($data['listeCentrales']) === $originalCount) {
        return ["error" => "Centrale introuvable"];
    }

    // Réécriture du fichier
    $success = file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($success === false) {
        return ["error" => "Erreur lors de l'écriture du fichier"];
    }

    return ["success" => true];
}

function updateCentrale($filePath) {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['ancienneNom']) || !isset($data['centrale']) || !isset($data['centrale']['nom'])) {
        http_response_code(400);
        return ["error" => "Paramètres manquants (ancienneNom ou centrale invalide)"];
    }

    $ancienneNom = $data['ancienneNom'];
    $centraleModifiee = $data['centrale'];

    if (!file_exists($filePath)) {
        http_response_code(404);
        return ["error" => "Fichier non trouvé"];
    }

    $json = file_get_contents($filePath);
    $systeme = json_decode($json, true);

    foreach ($systeme['listeCentrales'] as $i => $centrale) {
        if ($centrale['nom'] === $ancienneNom) {
            $systeme['listeCentrales'][$i] = $centraleModifiee;
            file_put_contents($filePath, json_encode($systeme, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return ["message" => "Centrale mise à jour"];
        }
    }

    http_response_code(404);
    return ["error" => "Centrale '$ancienneNom' non trouvée"];
}

function loadCentrale($filePath, $nomRecherche) {
    $json = file_get_contents($filePath);
    $systeme = json_decode($json, true);
    foreach ($systeme['listeCentrales'] ?? [] as $centraleData) {
        if ($centraleData['nom'] === $nomRecherche) {
            return new Centrale($centraleData);
        }
    }
    return null;
}

///////////////////////////
/// Programme principal ///
///////////////////////////

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

updatePrices($credentials, 'centrales.json');

// Création du système
$systeme = new Systeme();
$systeme->prixJournalier = getPricesJson('centrales.json');


$action = $_GET['action'] ?? '';

$filePath = 'centrales.json';

switch ($action) {
    case 'TableAD':
    case 'TablePrio':
        $nomCentrale = $_GET['nom'] ?? '';
        $centrale = loadCentrale($filePath, $nomCentrale);
        if (!$centrale) {
            http_response_code(404);
            $result = ["error" => "Centrale non trouvée"];
            break;
        }
        $result = $action === 'TableAD'
            ? $centrale->calcArretDemarrage($systeme->prixJournalier)
            : $centrale->calcPrio($systeme->prixJournalier);
        break;
    case 'NouvelleCentrale':
        $result = nouvelleCentrale($filePath);
        break;
    case 'GetCentrales':
        $result = getCentrales($filePath);
        break;
    case 'GetCentrale':
        if (isset($_GET['nom'])) { $result = getCentrale($filePath, $_GET['nom']); } 
        else { $result = ["error" => "Nom de centrale manquant"];}
        break;
    case 'DeleteCentrale':
        if (isset($_GET['nom'])) { $result = deleteCentrale($filePath, $_GET['nom']); }
        else { $result = ["error" => "Nom de centrale manquant"];}
        break;
    case 'UpdateCentrale':
        $result = updateCentrale($filePath);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Action invalide']);
        exit;
}

echo json_encode($result);
?>

