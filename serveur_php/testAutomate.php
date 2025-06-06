<?php

function testAgenda() {
    $url = 'http://7f84-2a0d-ee00-8013-7d00-1c66-85d0-5345-4b40.ngrok-free.app/agenda';

    // 1. Événements à insérer
    $events = [
        [
            "timestamp" => time() + 10,
            "function" => "sample_function",
            "args" => [123]
        ],
        [
            "timestamp" => time() + 20,
            "function" => "other_function",
            "args" => ["hello", 42]
        ]
    ];

    // 2. Requête PUT pour remplacer l'agenda
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($events));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Réponse PUT ($httpCode): $response\n";

    // 3. Requête GET pour vérifier l'agenda
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $agenda = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Agenda actuel ($httpCode): $agenda\n";
}

testAgenda()

?>