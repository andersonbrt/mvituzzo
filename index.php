<?php
// Incluindo o autoloader e inicializando o roteamento
define('BASE_PATH', __DIR__);  // Defina o caminho base corretamente

// Autoloader
function autoload($className)
{
    $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    $file = __DIR__ . '/src/' . $className . '.php';  // Ajustado para a nova estrutura
    if (file_exists($file)) {
        require_once $file;
    }
}

spl_autoload_register('autoload');

// Roteamento
$requestUri = $_SERVER['REQUEST_URI'];


## CONFIG LOCAL
//$requestUri = str_replace('/mvituzzo', '', $requestUri); // local
## FIM CONFIG LOCAL


## CONFIG PRODULÇÃO
/**
 * /api/clients/mvituzzo é o caminho de onde vai ficar o projeto altere conforme sua hospedagem
 */
$requestUri = str_replace('/api/clients/mvituzzo', '', $requestUri); // production
## FIM CONFIG PRODUÇÃO


// Se houver query string, remove
$requestUri = explode('?', $requestUri)[0];

// Remove a barra final, se houver
$requestUri = rtrim($requestUri, '/');

// Defina a lógica de roteamento (ajuste para a raiz)
switch ($requestUri) {

    case '/cvcrm/leads': // Lead RD Station to CV CRM
        $controller = new \App\Controllers\IndexController();
        $controller->handleRequest();
        break;

    case '/rdstation/callback': // Etapas CV CRM to RD Station
        $controller = new \App\Controllers\CallbackController();
        $controller->handleRequest();
        break;
    
    case '/test/callback': //Test
        echo json_encode(["status" => 200, "message" => "sucesso"]);
        break;
    
 case '/test/callbackdois': //Test 2
        echo json_encode(["status" => 200, "message" => "sucesso 2"]);
        break;
    
    default:
        echo "Página não encontrada!";
        break;
}
