<?php 
session_start();

$id_decriptado = isset($_GET['id']) ? base64_decode($_GET['id']) : '';

// Busca o produto na sessão gravada pelo crawler
$lancheDetalhe = null;
if (isset($_SESSION['produtos_crawler'][$id_decriptado])) {
    $lancheDetalhe = $_SESSION['produtos_crawler'][$id_decriptado];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="./images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <link rel="stylesheet" href="./css/style.css">
    <title><?php echo $lancheDetalhe ? htmlspecialchars($lancheDetalhe['proNome']) : 'Produto Não Encontrado'; ?></title>
</head>
<body class="produto-body">
    <header class="header">
        <a href="index.html" class="logo"><img id="logo" src="./images/LogoLight2.png" alt=""></a>
        <div id="menu-btn" class="fas fa-bars icons"></div>       
        <nav class="navbar">
            <a href="index.html">Home</a>
            <a href="./contato.html">Contato</a>
            <a href="./sobreNos.html">Sobre Nós</a>
            <label class="switch">
                <input id="btnDarkMode" type="checkbox">
                <span class="slider"></span>
            </label>
        </nav>
    </header>

    <section class="produto">
        <div class="conteudos">
            <?php 
                if (!$lancheDetalhe) {
                    echo "<div style='text-align: center; width: 100%; padding: 50px;'>
                            <h2>Produto não encontrado ou sessão expirada.</h2>
                            <a href='index.html' class='btn' style='margin-top:20px;'>Realizar Nova Busca</a>
                          </div>";
                } else {
                    echo "<div class='produto-desc'>
                    <p id='nota_media'>".$lancheDetalhe["avaliacao_media"]."<i class='fas fa-star'></i>(Ao vivo)</p>
                    <h3>".htmlspecialchars($lancheDetalhe["proNome"])." (".htmlspecialchars($lancheDetalhe["tamNome"]).")</h3>
                    <p id='desc-prod'>".htmlspecialchars($lancheDetalhe["proDescricao"])."</p>
                    <p>A partir de</p>
                    <h1> R$".$lancheDetalhe["menorPreco"]."</h1>
                    <h4> Atualizado em: ".$lancheDetalhe["dataAtualizacao"]."</h4>
                    <div class='desc-links'>
                        <h2>Peça já!</h2>
                        <div class='links'>";
                        
                        if (!empty($lancheDetalhe["lnk_much"])){
                            echo "<div class='deliveryPreco'>    
                                      <a href='".$lancheDetalhe['lnk_much']."' target='_blank'><img src='./images/DeliveryMuch LogoLight.svg' alt='Delivery Much'></a>
                                      <p>R$".$lancheDetalhe["preco_del_much"]."</p>
                                  </div>";
                        }
                        if (!empty($lancheDetalhe["lnk_aiqfome"])){
                            echo "<div class='deliveryPreco'>    
                                      <a href='".$lancheDetalhe['lnk_aiqfome']."' target='_blank'><img src='./images/logo_aiqfome.png' alt='AiQFome'></a>
                                      <p>R$".$lancheDetalhe["preco_aiqfome"]."</p>
                                  </div>";
                        }
                        
                    echo "</div>
                    </div>

                    <div class='produto-info'>
                        <div class='lanchonete-info mobile'>
                            <h2 class='nome-lanchonete'>".htmlspecialchars($lancheDetalhe["estNome"])."</h2>
                            <p class='end-lanchonete'>".htmlspecialchars($lancheDetalhe["estEndereco"])."</p>
                        </div>
                    </div>
                </div>

                <div class='produto-info' id='info-telaCheia'>
                    <p id='nota_media'>".$lancheDetalhe["avaliacao_media"]."<i class='fas fa-star'></i>(Ao vivo)</p>
                    <img src='./images/favicon.png' alt=''>
                    <div class='lanchonete-info cheia'>
                        <h2 class='nome-lanchonete'>".htmlspecialchars($lancheDetalhe["estNome"])."</h2>
                        <p class='end-lanchonete'>".htmlspecialchars($lancheDetalhe["estEndereco"])."</p>
                    </div>
                </div>";
            }
        ?> 
        </div>
    </section>
    <script src="./js/script.js"></script>
    <script src="./js/darkMode.js"></script>
</body>
</html>