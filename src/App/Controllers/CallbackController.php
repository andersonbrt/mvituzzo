<?php

namespace App\Controllers;

class CallbackController extends RdStationController
{
    public function handleRequest()
    {
        // Monta payload para RD Station com dados CVCRM
        $payload = $this->payloadCvCrm($this->data);

        // Envia dados para requisição CONVERSION RD STATION
        echo $this->conversion($payload);
    }
}
