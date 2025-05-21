<?php

namespace App\Controllers;

class BaseController
{
    // Armazena os dados da requisição
    protected $data = [];

    // Construtor para capturar dados da requisição
    public function __construct()
    {

        $this->data = array_merge($this->data, $_POST);
        $this->data = array_merge($this->data, $_GET);

        // Captura dados de JSON (raw ou application/json)
        if ($this->isJsonRequest()) {
            $jsonData = $this->getDataFromJson();
            $this->data = array_merge($this->data, $jsonData);  // Adiciona os dados JSON
        }

        // Captura dados de formato `application/x-www-form-urlencoded`
        if ($this->isUrlEncoded()) {
            $this->data = array_merge($this->data, $_POST);  // Adiciona dados de formulário URL-encoded
        }

        // Captura dados de conteúdo raw (quando não for JSON, nem formulário)
        if ($this->isRawData()) {
            $rawData = $this->getDataFromRaw();
            $this->data = array_merge($this->data, $rawData);  // Adiciona dados raw
        }
    }

    // Verifica se a requisição tem conteúdo JSON
    private function isJsonRequest()
    {
        return strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
    }

    // Verifica se o conteúdo é application/x-www-form-urlencoded
    private function isUrlEncoded()
    {
        return strpos($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded') !== false;
    }

    // Verifica se a requisição tem conteúdo raw (geralmente usado em APIs com corpo)
    private function isRawData()
    {
        return empty($_POST) && empty($_GET) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false;
    }

    // Recupera dados do corpo da requisição em JSON
    private function getDataFromJson()
    {
        return json_decode(file_get_contents('php://input'), true);
    }

    // Recupera dados do corpo da requisição raw
    private function getDataFromRaw()
    {
        return json_decode(file_get_contents('php://input'), true);
    }

    // Método para acessar os dados capturados
    public function getData()
    {
        return $this->data;
    }

    // Função para formatar o telefone
    public static function formatarTelefone($telefone)
    {
        // // Remover caracteres não numéricos
        // $telefone = preg_replace('/\D/', '', $telefone);

        // // Verificar o tamanho do telefone
        // $tamanho = strlen($telefone);

        $telefone = filter_var($telefone, FILTER_SANITIZE_NUMBER_INT);
        // Isso mantém o sinal de mais "+" também (útil para DDI). Se não quiser, remova com str_replace
        $telefone = str_replace('+', '', $telefone);
        // Verificar o tamanho do telefone
        $tamanho = strlen($telefone);

        // Verificar a quantidade de dígitos e aplicar a formatação conforme necessário
        switch ($tamanho) {
            case 10: // 10 dígitos (sem DDI e sem nono dígito)
                $ddd = substr($telefone, 0, 2);
                $resto = substr($telefone, 2);

                if ($ddd < 28) {
                    $telefone = "55" . $ddd . "9" . $resto; // Adiciona o DDI e o nono dígito
                } else {
                    $telefone = "55" . $telefone; // Apenas adiciona o DDI
                }
                break;

            case 11: // 11 dígitos (sem DDI e com nono dígito)
                $ddd = substr($telefone, 0, 2);
                $resto = substr($telefone, 3); // Pula o nono dígito

                if ($ddd < 28) {
                    $telefone = "55" . $telefone; // Adiciona o DDI e mantém o nono dígito
                } else {
                    $telefone = "55" . $ddd . $resto; // Adiciona o DDI e remove o nono dígito
                }
                break;

            case 12: // 12 dígitos (com DDI e sem nono dígito)
                $ddi = substr($telefone, 0, 2);
                $ddd = substr($telefone, 2, 2);
                $resto = substr($telefone, 4);

                if ($ddi === "55" && $ddd < 28) {
                    $telefone = $ddi . $ddd . "9" . $resto; // Adiciona o nono dígito
                }
                break;

            case 13: // 13 dígitos (com DDI e com nono dígito)
                $ddi = substr($telefone, 0, 2);
                $ddd = substr($telefone, 2, 2);
                $resto = substr($telefone, 5); // Pula o nono dígito

                if ($ddi === "55" && $ddd >= 28) {
                    $telefone = $ddi . $ddd . $resto; // Remove o nono dígito
                }
                break;
        }

        return $telefone;
    }
}

// Exemplo de Controller que herda a classe BaseController
class IndexController extends BaseController
{
    public function handleRequest()
    {
        // Função para enviar a resposta como JSON
        function sendJsonResponse($status, $message)
        {
            echo json_encode([
                'status' => $status,
                'message' => $message
            ]);
        }

        // Verifica os parâmetros 'page', 'per_page', 'filter_key', 'filter_value', 'filter_operator'
        $missing_params = [];

        if (empty($_GET['fila_id'])) {
            $missing_params[] = "'fila_id'";
        }

        // if (empty($_GET['empreendimento_id'])) {
        //     $missing_params[] = "'empreendimento_id'";
        // }

        // Se houver parâmetros faltando, gera a mensagem e envia a resposta
        if (!empty($missing_params)) {
            $missing_params_str = implode(", ", $missing_params);
            sendJsonResponse(false, "$missing_params_str é obrigatório!");
            return;
        }

        // Pega todos dados ddo body da requisicao
        $data = $this->getData();

        // Monta payload do CVCRM
        $payloadCvCrm = CvCrm::builderCvCrm($data);

        // Adiciona lead ao CVCRM
        $responseCvCrm = CvCrm::sendToCvCrm($payloadCvCrm);

        // Descodifica resposta do CVCRM
        $responseCvCrm = json_decode($responseCvCrm, true);

        $data_log = ["response_cvcrm" => $responseCvCrm, "dados_rd_statation" => $data];

        // Envia Log para webhook gabriel quando der ERROR
        if (isset($responseCvCrm["codigo"]) && $responseCvCrm["codigo"] === 400) {

            // Webhook Monitoramento de Log de Error
            $this->sendToWebhookLogError($data_log);
        }

        // Webhook Monitoramento de Log de Error
        $data['response_cvcrm'] = $data_log;
        $this->sendToWebhookLogCvCrm($data);

        sendJsonResponse(true, $responseCvCrm);
        return;
    }

    public function sendToWebhookLogError($item)
    {
        // Inicializa a comunicação cURL
        $ch = curl_init("https://n8n.waveconnection.com.br/webhook/112cdafa-7ada-43b5-9918-3460f713f3e3");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($item));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        // Executa o cURL e captura a resposta
        $response = curl_exec($ch);

        // Verifica se ocorreu algum erro
        if (curl_errno($ch)) {
            echo 'Erro cURL: ' . curl_error($ch);
        }

        curl_close($ch);
        return $response;
    }
    public function sendToWebhookLogCvCrm($item)
    {
        // Inicializa a comunicação cURL
        $ch = curl_init("https://n8n.waveconnection.com.br/webhook/9caadc90-0960-43ff-b4fa-a82c6c21a381");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($item));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        // Executa o cURL e captura a resposta
        $response = curl_exec($ch);

        // Verifica se ocorreu algum erro
        if (curl_errno($ch)) {
            echo 'Erro cURL: ' . curl_error($ch);
        }

        curl_close($ch);
        return $response;
    }
    public function sendToWebhook($item)
    {
        // Inicializa a comunicação cURL
        $ch = curl_init("https://workflows.1v1connect.com/webhook/e068d10f-a7a4-4df3-9e79-0f519b80c6e6");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($item));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        // Executa o cURL e captura a resposta
        $response = curl_exec($ch);

        // Verifica se ocorreu algum erro
        if (curl_errno($ch)) {
            echo 'Erro cURL: ' . curl_error($ch);
        }

        curl_close($ch);
        return $response;
    }

    public function sendToWebhookTest($item)
    {
        // Inicializa a comunicação cURL
        $ch = curl_init("https://workflows.wavemaker.com.br/webhook-test/ef2518b0-9684-4f1b-b3bf-c3a5719be17a");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($item));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        // Executa o cURL e captura a resposta
        $response = curl_exec($ch);

        // Verifica se ocorreu algum erro
        if (curl_errno($ch)) {
            echo 'Erro cURL: ' . curl_error($ch);
        }

        curl_close($ch);
        return $response;
    }
}

class CvCrm extends IndexController
{
    public static function builderCvCrm($data)
    {

        // ORIGEM - NOTIFICALEAD
        if (isset($data["midia"]["channel"])) {
            $payload = [
                "permitir_alteracao" => true,
                "permitir_trocar_atendente" => false,
                "email" => $data["lead"]["email"] ?? null,
                "telefone" => $data["lead"]["phone"] ?? null,
                "modulo" => "gestor",
                "nome" => $data["lead"]["name"] ?? null,
                "midia" => $data["midia"]["channel"] ?? null,
                "conversao" => "Wavemaker",
                "data_cad_conversao" => $data["created"] ?? null,
                "integracao" => "Wavemaker",
                "tags" => ["Wavemaker"],
                "idfila_distribuicao_leads" => (int)$data["fila_id"] ?? null,
                "campos_adicionais" => []
            ];
        }

        // ORIGEM - RD STATION
        if (isset($data["leads"][0]["uuid"])) {

            // Formata telefone
            $telefoneFormatado = CvCrm::formatarTelefone($data["leads"][0]["personal_phone"]);

            // Se o telefone formatado for inválido, tenta com mobile_phone
            if (empty($telefoneFormatado)) {
                $telefone = $data["leads"][0]["mobile_phone"] ?? null;
                $telefone = $telefone ? CvCrm::formatarTelefone($telefone) : null;
            } else {
                $telefone = $telefoneFormatado;
            }

            // Tags do RD Station
            $tags = $data["leads"][0]["tags"];
            $tags[] = "Wavemaker";
            $conversao = $data["leads"][0]["last_conversion"]["content"]["identificador"] ?? null;

            $payload = [
                "permitir_alteracao" => true,
                "permitir_trocar_atendente" => false,
                "email" => $data["leads"][0]["email"] ?? null,
                "telefone" => $telefone,
                "modulo" => "gestor",
                "nome" => $data["leads"][0]["name"] ?? null,
                "midia" => $data["leads"][0]["last_conversion"]["conversion_origin"]["source"],
                "conversao" => "{$conversao} - Wavemaker",
                "data_cad_conversao" => $data['leads'][0]["created_at"] ?? null,
                "integracao" => "Wavemaker",
                "tags" => $tags,
                "idfila_distribuicao_leads" => (int)$data["fila_id"] ?? null
            ];
        }

        // CUSTOM FIELDS RD STATION
        $custom_fields_rd = $data["leads"][0]["custom_fields"];

        foreach ($custom_fields_rd as $key => $value) {
            if (isset($key) && $key === "finalidade") {
                $payload["campos_adicionais"]["{$key}"] = $value;
            }

            if (isset($key) && $key === "valor_investimento") {
                $payload["campos_adicionais"]["{$key}"] = $value;
            }
        }


        if (isset($data["leads"][0]["custom_fields"][""]) && !empty($data["leads"][0]["custom_fields"][""])) {

            $payload["idempreendimento"] = (int)$data["empreendimento_id"];
        }

        // PADRAO NL
        if (isset($data["empreendimento_id"]) && !empty($data["empreendimento_id"])) {
            $payload["idempreendimento"] = (int)$data["empreendimento_id"];
        }

        if (isset($data["midia"]["rule_keyword"]) && !empty($data["midia"]["rule_keyword"])) {
            $payload["idempreendimento"] = (int)$data["midia"]["rule_keyword"];
        }

        return $payload;
    }
    public static function sendToCvCrm($item)
    {
        $auth = CvCrm::auth();

        // Inicializa a comunicação cURL
        $ch = curl_init("{$auth["url"]}/lead");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($item));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'token: ' . $auth["token"]
        ]);

        // Executa o cURL e captura a resposta
        $response = curl_exec($ch);

        // Verifica se ocorreu algum erro
        if (curl_errno($ch)) {
            echo 'Erro cURL: ' . curl_error($ch);
        }

        curl_close($ch);
        return $response;
    }
    public static function auth()
    {
        return [
            "url" => "https://mvituzzo.cvcrm.com.br/api/cvio",
            "token" => "058fb1bf8757e21db6db60ee1ea2312e139a633d"
        ];
    }
}
class NotificaLead extends CvCrm
{
    public static function users($user_id)
    {
        $data = [
            [
                "user_id" => 415,
                "url" => "https://app.notificalead.com.br/webhook_prohibited?token=069bf2e47ad3049b4855e7ad99d20246"
            ],
            [
                "user_id" => 417,
                "url" => "https://app.notificalead.com.br/webhook_prohibited?token=10de1265cdff547f3f813899dfe46163"
            ]
        ];

        foreach ($data as $item) {

            if (isset($item) && !empty($item)) {

                if ($item["user_id"] == $user_id) {
                    return $item;
                }
            }
        }
        return false;
    }
    public static function builderNotificaLead($data, $response)
    {
        return  [
            'type_business' => "undefined",
            'name' => $data["lead"]["name"],
            'email' => $data["lead"]["email"],
            'phone' => $data["lead"]["phone"],
            'property_code' => "{$response["id"]}",
            'origin' => "Wavemaker",
            'origin_crm' => "cvcrm",
            'url' => "",
            'rule_keyword' => "cvcrm_retorno_{$data["user_id"]}",
            'obs' => ""
        ];
    }
    public static function sendToNotificaLead($payload, $user_id)
    {
        $users = NotificaLead::users($user_id);

        if ($users === false) {
            sendJsonResponse(false, "'user_id' não relacionado!");
            exit;
        }
        // Inicializa a comunicação cURL
        $ch = curl_init("{$users["url"]}");

        // Define a requisição POST
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retorna a resposta como string
        curl_setopt($ch, CURLOPT_POST, true); // Define que é uma requisição POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload)); // Envia os dados como JSON
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        // Executa a requisição cURL e captura a resposta
        $response = curl_exec($ch);

        // Verifica se ocorreu algum erro
        if (curl_errno($ch)) {
            echo 'Erro cURL: ' . curl_error($ch);
        }

        // Obtém o código de status HTTP
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Fecha a comunicação cURL
        curl_close($ch);

        // Exibe o status code
        return $status_code;
    }
}
