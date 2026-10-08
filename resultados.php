<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/x-icon" href="./images/favicon.png">
<!-- font awesome icones -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
<link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css">
<!-- biblioteca de animação on scroll -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
<!-- css comum -->
<link rel="stylesheet" href="./css/style.css">

<title>Resultado</title>
</head>
<body>
<?php
require_once __DIR__ . '/services/deliveryMuchService.php';
require_once __DIR__ . '/services/aiqfomeService.php';

$delivery = new DeliveryMuchService();
$aiqfome = new AiqfomeService();

$prato = trim((string) ($_REQUEST['prato'] ?? ''));
$local = trim((string) ($_REQUEST['location'] ?? ''));
$categoria = trim((string) ($_REQUEST['categorias'] ?? ''));

$filtro_preco = isset($_REQUEST['slide-preco']) ? (float) $_REQUEST['slide-preco'] : 100;
$tamanho = !empty($_REQUEST['tamanho']) ? trim((string) $_REQUEST['tamanho']) : null;
$ordenacao = !empty($_REQUEST['ordem']) ? trim((string) $_REQUEST['ordem']) : null;

$coordenadasLocal = $delivery->coordenadasPorTexto($local);
$latBusca = $coordenadasLocal['lat'] ?? null;
$lngBusca = $coordenadasLocal['lng'] ?? null;

$resultadosDelivery = [];
if ($latBusca !== null && $lngBusca !== null) {
    $resultadosDelivery = $delivery->buscarProdutosPorBusca($latBusca, $lngBusca, $prato, $categoria, 15, 60);
}

$resultadosAiqfome = $aiqfome->buscarProdutosPorBusca($prato, $categoria, $local);
$resultados = array_merge($resultadosDelivery, $resultadosAiqfome);
$modoIntegracao = !empty($resultados);

if (isset($_REQUEST['btn-filtrar'])) {
    $resultados = array_values(array_filter($resultados, function ($item) use ($filtro_preco) {
        $preco = isset($item['menorPreco']) && is_numeric($item['menorPreco']) ? (float) $item['menorPreco'] : null;
        return $preco === null || $preco <= $filtro_preco;
    }));
}

usort($resultados, function ($a, $b) {
    $pa = isset($a['menorPreco']) && is_numeric($a['menorPreco']) ? (float) $a['menorPreco'] : PHP_FLOAT_MAX;
    $pb = isset($b['menorPreco']) && is_numeric($b['menorPreco']) ? (float) $b['menorPreco'] : PHP_FLOAT_MAX;
    return $pa <=> $pb;
});

$count = count($resultados);

echo "<div style='display: flex; justify-content: space-between; align-items: center; padding-top: 20px;'>
        <a href='index.html' class='logo-result' style='width: fit-content;'><img id='logo' src='./images/LogoLight2.png' alt='' style='margin-left: 3rem;'></a>
        <label style='margin-right: 20px' class='switch'>
            <input id='btnDarkMode' type='checkbox'>
            <span class='slider'></span>
        </label>
        </div>";

if ($latBusca !== null && $lngBusca !== null) {
    echo "<p style='text-align:center; color:#666; margin-top:10px;'>Localização convertida para: " . number_format($latBusca, 6, ',', '.') . ", " . number_format($lngBusca, 6, ',', '.') . "</p>";
}

if ($modoIntegracao) {
    echo "<h1 style='padding: 10px; margin-top: 1rem; text-align: center;'>Exibindo $count resultados para '$prato' em '$local'</h1>";
} else {
    echo "<h1 style='padding: 10px; margin-top: 1rem; text-align: center;'>Não foi possível localizar a cidade informada. Tente outra localidade.</h1>";
}
?>
<section class="results">
    <div class="results-filtros">
        <div id="btn-filtros">
            <i class="fa-solid fa-filter"></i> Filtros
        </div>
        <div class="filtros">
            <h3>Filtros</h3>
            <form action="./resultados.php" method="post">
                <input type="hidden" name="prato" value="<?php echo htmlspecialchars($prato, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="location" value="<?php echo htmlspecialchars($local, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="categorias" value="<?php echo htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="results-slide">
                    <h3>Valor Máximo</h3>
                    <input type="range" min="10" max="100" step="10.00" value="<?php echo htmlspecialchars((string) $filtro_preco, ENT_QUOTES, 'UTF-8'); ?>" name="slide-preco">
                    <div class="results-slide-numbers">
                        <h4>R$10,00</h4>
                        <h4>R$100,00</h4>
                    </div>
                </div>
                <div class="filtro-ordem">
                    <h3 style="margin-top: 20px;">Ordem</h3>
                    <div class="inputsOrdem">
                        <div class="input">
                            <input type="radio" name="ordem" id="ord_P" value="1" <?php echo ($ordenacao === '1') ? 'checked' : ''; ?>>
                            <label for="ord_P">Preço</label>
                        </div>
                        <div class="input">
                            <input type="radio" name="ordem" id="ord_A" value="2" <?php echo ($ordenacao === '2') ? 'checked' : ''; ?>>
                            <label for="ord_A">Avaliação</label>
                        </div>
                    </div>
                </div>
                <input type="submit" name="btn-filtrar" class="btn" value="Filtrar" style="margin: 1.5rem; align-self: center; width: 100px; padding: .5rem;">
            </form>
        </div>
    </div>
    <div class="results-cards">
        <?php
        if ($count <= 0) {
            echo "<div>
                    <h3 style='text-align: center; font-size:3rem'>Não foram encontrados resultados!<br>
                    Tente novamente utilizando outros termos</h3>
                </div>";
        } else {
            foreach ($resultados as $campo) {
                $nomeProduto = htmlspecialchars((string) ($campo['proNome'] ?? 'Produto'), ENT_QUOTES, 'UTF-8');
                $nomeEstabelecimento = htmlspecialchars((string) ($campo['estNome'] ?? 'Delivery Much'), ENT_QUOTES, 'UTF-8');
                $tamProduto = htmlspecialchars((string) ($campo['tamNome'] ?? 'Padrão'), ENT_QUOTES, 'UTF-8');
                $imagemProduto = !empty($campo['proImagem']) ? (string) $campo['proImagem'] : './images/default-product.png';
                $precoProduto = isset($campo['menorPreco']) && is_numeric($campo['menorPreco']) ? 'R$' . number_format((float) $campo['menorPreco'], 2, ',', '.') : 'Consulte';
                $avaliacao = isset($campo['avaliacao_media']) && is_numeric($campo['avaliacao_media']) ? (float) $campo['avaliacao_media'] : 0;
                $idProduto = isset($campo['proId']) ? (string) $campo['proId'] : '';
                $companyUuid = isset($campo['company_uuid']) ? (string) $campo['company_uuid'] : '';
                $companyName = isset($campo['estNome']) ? (string) $campo['estNome'] : '';
                $linkProduto = $idProduto !== '' ? './produto.php?id=' . rawurlencode($idProduto) : './produto.php';
                $linkTarget = '';
                if (($campo['source'] ?? '') === 'aiqfome') {
                    $linkExterno = !empty($campo['linkExterno']) ? (string) $campo['linkExterno'] : '';
                    $linkProduto = './produto.php?source=aiqfome';
                    if ($linkExterno !== '') {
                        $linkProduto .= '&link=' . rawurlencode($linkExterno);
                    }
                    if ($nomeProduto !== '') {
                        $linkProduto .= '&nome=' . rawurlencode($nomeProduto);
                    }
                    if (isset($campo['menorPreco']) && is_numeric($campo['menorPreco'])) {
                        $linkProduto .= '&preco=' . rawurlencode((string) $campo['menorPreco']);
                    }
                    $linkTarget = "";
                } else {
                    if ($companyUuid !== '') {
                        $linkProduto .= '&company=' . rawurlencode($companyUuid);
                    }
                    if ($companyName !== '') {
                        $linkProduto .= '&company_name=' . rawurlencode($companyName);
                    }
                }

                echo "<a href='{$linkProduto}' {$linkTarget}>
                        <div class='card'>
                            <p id='nota_media'>" . number_format($avaliacao, 1, ',', '.') . "<i class='fas fa-star'></i>(0)</p>
                            <div class='card-img'>
                                <img src='{$imagemProduto}' alt='{$nomeProduto}' onerror=\"this.onerror=null;this.src='./images/default-product.png';\">
                            </div>
                            <div class='card-info'>
                                <p style='display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;' class='text-title'>{$nomeProduto} ({$tamProduto})</p>
                                <h3 style='color: #808080; text-overflow: ellipsis; white-space: nowrap; overflow-x: hidden;'>{$nomeEstabelecimento}</h3>
                            </div>
                            <div class='card-footer'>
                                <span class='text-title'>A partir de <br>{$precoProduto}</span>
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