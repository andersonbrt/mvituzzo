<?php

namespace App\Controllers;

class NovaRotaController
{
    // Função para enviar a resposta como JSON
    function sendJsonResponse($status, $message)
    {
        echo json_encode([
            'status' => $status,
            'message' => $message
        ]);
    }
    public function handleRequest()
    {
        // Lógica para a nova rota
        echo "Nova Rota foi acessada!";
    }
}
