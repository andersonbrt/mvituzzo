<?php

namespace App\Controllers;

class RdStationController extends IndexController
{
    public static function conversion($payload)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.rd.services/platform/conversions?api_key=6046cf524f99df01e11d6208850288c3",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
    public static function payloadCvCrm($data)
    {
        $payload = [
            "event_type" => "CONVERSION",
            "event_family" => "CDP",
            "payload" => [
                "legal_bases" => [
                    [
                        "category" => "communications",
                        "type" => "consent",
                        "status" => "granted"
                    ]
                ],
                "name" => $data["nome"],
                "conversion_identifier" => "Etapa: " . $data["situacao"]["nome"],
                "email" => $data["email"],
                "mobile_phone" => $data["telefone"],
                "cf_origem" => $data["origem"],
                "cf_midia_principal" => $data["midia_principal"],
                //"cf_empreendimento" => $data["empreendimento"], falta tratar dados converter array para string
                "cf_data_de_criacao" => $data["data_cad"]
            ]
        ];

        if (isset($data["corretor"]["nome"]) && !empty($data["corretor"]["nome"])) {
            $payload["payload"]["cf_corretor"] = $data["corretor"]["nome"];
        }

        if (isset($data["valor_negocio"]) && !empty($data["valor_negocio"])) {
            $payload["payload"]["cf_valor_negocio"] = $data["valor_negocio"];

            $payload["payload"]["cf_venda_wavemaker"] = "NAO";

            if (isset($data['tags']) && !empty($data['tags'])) {

                foreach ($data['tags'] as $tag) {
                    if (isset($tag) && $tag === 'Wavemaker') {
                        $payload["payload"]["cf_venda_wavemaker"] = "SIM";
                    }
                }
            }
        }

        if (isset($data["data_venda"]) && !empty($data["data_venda"])) {
            $payload["payload"]["cf_data_venda"] = $data["data_venda"];
        }

        if (isset($data["motivo_cancelamento"]["nome"]) && !empty($data["motivo_cancelamento"]["nome"])) {
            $payload["payload"]["cf_motivo_cancelamento"] = $data["motivo_cancelamento"]["nome"];
        }

        if (isset($data["submotivo_cancelamento"]["nome"]) && !empty($data["submotivo_cancelamento"]["nome"])) {
            $payload["payload"]["cf_submotivo_cancelamento"] = $data["submotivo_cancelamento"]["nome"];
        }

        return $payload;
    }
}
