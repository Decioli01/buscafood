<?php
session_start();

$prato = isset($_REQUEST['prato']) ? $_REQUEST['prato'] : '';
$local = isset($_REQUEST['location']) ? $_REQUEST['location'] : '';
$categoria = isset($_REQUEST['categorias']) ? $_REQUEST['categorias'] : 1;

$produtos_finais = [];

// DEFINA OS CAMINHOS ABSOLUTOS REAIS DO SEU COMPUTADOR
// Se não estiver usando ambiente virtual (venv), use apenas 'python'
$pythonBin = __DIR__ . "/crawler/venv/Scripts/python.exe";
$scriptPath = __DIR__ . "/crawler/crawler_food.py";

if (!file_exists($scriptPath)) {
    die("<h2 style='color:red;'>Erro: O arquivo crawler_food.py não foi encontrado em: {$scriptPath}</h2>");
}

// 2>&1 redireciona os erros (stderr) para a saída principal (stdout)
$command = sprintf(
    '%s %s %s %s 2>&1',
    escapeshellarg($pythonBin),
    escapeshellarg($scriptPath),
    escapeshellarg($local),
    escapeshellarg($prato)
);

// Executa e captura a saída
$output = shell_exec($command);

// Tenta decodificar o JSON
$produtos_crawler = json_decode($output, true);

// SE DER ERRO NO JSON, MOSTRA O MOTIVO NA TELA
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<div style='background:#f8d7da; color:#721c24; padding:15px; margin:10px; border-radius:5px;'>";
    echo "<h3>Ocorreu um erro ao executar o Crawler Python:</h3>";
    echo "<p><strong>Comando executado:</strong> <code>{$command}</code></p>";
    echo "<p><strong>Saída retornado do terminal:</strong></p>";
    echo "<pre style='background:#fff; padding:10px; border:1px solid #ccc;'>" . htmlspecialchars($output) . "</pre>";
    echo "</div>";
} else if (is_array($produtos_crawler)) {
    $produtos_finais = $produtos_crawler;
}

// Armazena na sessão
$_SESSION['produtos_crawler'] = [];
foreach ($produtos_finais as $prod) {
    $_SESSION['produtos_crawler'][$prod['proId']] = $prod;
}

$count = count($produtos_finais);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/x-icon" href="./images/favicon.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
<link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
<link rel="stylesheet" href="./css/style.css">
<title>Resultado</title>
</head>
<body>

<div style='display: flex; justify-content: space-between; align-items: center; padding-top: 20px;'>
    <a href='index.html' class='logo-result' style='width: fit-content;'><img id='logo' src='./images/LogoLight2.png' alt='' style='margin-left: 3rem;'></a>
    <label style='margin-right: 20px' class='switch'>
        <input id='btnDarkMode' type='checkbox'>
        <span class='slider'></span>
    </label>
</div>  

<h1 style='padding: 10px; margin-top: 1rem; text-align: center;'>Exibindo <?php echo $count; ?> resultados para '<?php echo htmlspecialchars($prato); ?>' em '<?php echo htmlspecialchars($local); ?>'</h1>

<section class="results">
    <div class="results-filtros">
        <div id="btn-filtros">
            <i class="fa-solid fa-filter"></i> Filtros
        </div>
        <div class="filtros">
            <h3>Filtros</h3>
            <form action="./resultados.php" method="post">
                <input type="hidden" name="prato" value="<?php echo htmlspecialchars($prato); ?>"> 
                <input type="hidden" name="location" value="<?php echo htmlspecialchars($local); ?>"> 
                <input type="hidden" name="categorias" value="<?php echo htmlspecialchars($categoria); ?>">

                <div class="results-slide">
                    <h3>Valor Máximo</h3>
                    <input type="range" min="10" max="100" step="10.00" value="100" name="slide-preco">
                    <div class="results-slide-numbers">
                        <h4>R$10,00</h4>
                        <h4>R$100,00</h4>
                    </div>
                </div>
                <input type="submit" name="btn-filtrar" class="btn" value="Filtrar" style="margin: 1.5rem; align-self: center; width: 100px; padding: .5rem;">
            </form>
        </div>   
    </div>

    <div class="results-cards">
        <?php
            if ($count <= 0){
                echo "<div>
                        <h3 style='text-align: center; font-size:3rem'>Não foram encontrados resultados!<br>
                        Tente novamente utilizando outros termos</h3>
                    </div>";
            } else {
                foreach ($produtos_finais as $campo) {
                    $id_criptado = base64_encode($campo["proId"]);
                    $link_card = "./produto.php?id=" . $id_criptado;

                    echo "<a href='".$link_card."'>
                            <div class='card'>
                                <p id='nota_media'>".$campo["avaliacao_media"]." <i class='fas fa-star'></i>(Ao vivo)</p>
                                <div class='card-img'>
                                    <img src='./images/favicon.png' alt=''>
                                </div>
                                <div class='card-info'>
                                    <p style='display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;' class='text-title'>
                                        ".htmlspecialchars($campo["proNome"])." (".htmlspecialchars($campo["tamNome"]).")
                                    </p>
                                    <h3 style='color: #808080; text-overflow: ellipsis; white-space: nowrap; overflow-x: hidden;'>".htmlspecialchars($campo["estNome"])."</h3>
                                </div>
                                <div class='card-footer'>
                                    <span class='text-title'>A partir de <br>R$".$campo["menorPreco"]."</span>
                                </div>
                            </div> 
                        </a>";
                }
            }           
        ?>            
    </div>
</section> 
    <script src="./js/darkMode.js"></script>
    <script src="./js/scriptFiltros.js"></script>
</body>
</html>