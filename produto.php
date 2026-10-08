<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="./images/favicon.ico">
    <!-- font awesome icones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.linearicons.com/free/1.0.0/icon-font.min.css">
    <!-- biblioteca de animação on scroll -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <!-- css comum -->
    <link rel="stylesheet" href="./css/style.css">

    <title>Produto</title>
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
                require_once __DIR__ . '/services/deliveryMuchService.php';
                require_once __DIR__ . '/services/aiqfomeService.php';

                $produtoExterno = null;
                $empresaNome = null;
                $companyUuid = isset($_GET['company']) ? trim((string) $_GET['company']) : '';
                $productId = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
                $source = isset($_GET['source']) ? trim((string) $_GET['source']) : '';
                $linkExternal = isset($_GET['link']) ? trim((string) $_GET['link']) : '';
                $nomeProdutoQuery = isset($_GET['nome']) ? trim((string) $_GET['nome']) : '';

                $empresaDetalhe = null;

                if ($companyUuid !== '' && $productId !== '') {
                    $delivery = new DeliveryMuchService();
                    $empresaNome = !empty($_GET['company_name']) ? trim((string) $_GET['company_name']) : 'Delivery Much';

                    try {
                        $produtoExterno = $delivery->buscarProdutoPorId($companyUuid, $productId);
                        $empresaDetalhe = $delivery->buscarDetalhesCompanhia($companyUuid);
                    } catch (Exception $e) {
                        $produtoExterno = null;
                        $empresaDetalhe = null;
                    }
                }

                if ($source === 'aiqfome' && $linkExternal !== '') {
                    $aiqfome = new AiqfomeService();
                    $empresaDetalhe = $aiqfome->buscarDetalhesRestaurante($linkExternal);
                    $itensAiqfome = $aiqfome->buscarProdutosPorUrl($linkExternal);

                    $produtoExterno = null;
                    $precoBuscado = null;
                    if (isset($_GET['preco']) && is_numeric($_GET['preco'])) {
                        $precoBuscado = (float) $_GET['preco'];
                    }

                    $normalizarNome = function ($valor) {
                        $valor = trim((string) $valor);
                        $valor = strtolower($valor);
                        $valor = preg_replace('/[^a-z0-9]+/u', ' ', $valor);
                        $valor = preg_replace('/\s+/', ' ', $valor);
                        return trim((string) $valor);
                    };

                    $melhorIndice = null;
                    $melhorPontuacao = -1;

                    foreach ($itensAiqfome as $indice => $item) {
                        $nomeItem = (string) ($item['name'] ?? '');
                        $nomeBuscado = $nomeProdutoQuery;
                        $nomeItemNormalizado = $normalizarNome($nomeItem);
                        $nomeBuscadoNormalizado = $normalizarNome($nomeBuscado);
                        $precoItem = isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : null;

                        if ($nomeBuscadoNormalizado === '') {
                            continue;
                        }

                        $pontuacao = 0;
                        if ($nomeItemNormalizado === $nomeBuscadoNormalizado) {
                            $pontuacao += 100;
                        } elseif (str_starts_with($nomeItemNormalizado, $nomeBuscadoNormalizado) || str_starts_with($nomeBuscadoNormalizado, $nomeItemNormalizado)) {
                            $pontuacao += 70;
                        } elseif (strpos($nomeItemNormalizado, $nomeBuscadoNormalizado) !== false || strpos($nomeBuscadoNormalizado, $nomeItemNormalizado) !== false) {
                            $pontuacao += 50;
                        }

                        if ($precoBuscado !== null && $precoItem !== null) {
                            $diferenca = abs($precoItem - $precoBuscado);
                            if ($diferenca <= 5) {
                                $pontuacao += 20;
                            } elseif ($diferenca <= 15) {
                                $pontuacao += 10;
                            }
                        }

                        if ($pontuacao > $melhorPontuacao) {
                            $melhorPontuacao = $pontuacao;
                            $melhorIndice = $indice;
                        }
                    }

                    if ($melhorIndice !== null) {
                        $produtoExterno = $itensAiqfome[$melhorIndice];
                    }

                    if ($produtoExterno === null && !empty($itensAiqfome)) {
                        $produtoExterno = $itensAiqfome[0];
                    }
                }

                if ($source === 'aiqfome' && $produtoExterno !== null) {
                    $nomeProduto = htmlspecialchars((string) ($produtoExterno['name'] ?? ($produtoExterno['proNome'] ?? 'Produto')), ENT_QUOTES, 'UTF-8');
                    $descricaoProduto = htmlspecialchars((string) (($produtoExterno['description'] ?? '') !== '' ? $produtoExterno['description'] : 'Produto disponível no AIQFome.'), ENT_QUOTES, 'UTF-8');
                    $imagemProduto = !empty($produtoExterno['image']) ? (string) $produtoExterno['image'] : (!empty($produtoExterno['proImagem']) ? (string) $produtoExterno['proImagem'] : './images/default-product.png');
                    $precoProduto = isset($produtoExterno['price']) && is_numeric($produtoExterno['price']) ? (float) $produtoExterno['price'] : (isset($produtoExterno['menorPreco']) && is_numeric($produtoExterno['menorPreco']) ? (float) $produtoExterno['menorPreco'] : null);
                    $precoFormatado = is_numeric($precoProduto) ? 'R$' . number_format((float) $precoProduto, 2, ',', '.') : 'Consulte';
                    $tamanhoProduto = isset($produtoExterno['size_options']['label']) ? htmlspecialchars((string) $produtoExterno['size_options']['label'], ENT_QUOTES, 'UTF-8') : (isset($produtoExterno['tamNome']) ? htmlspecialchars((string) $produtoExterno['tamNome'], ENT_QUOTES, 'UTF-8') : 'Padrão');
                    $nomeLoja = htmlspecialchars((string) (($empresaDetalhe['title'] ?? $empresaNome) ?: 'AIQFome'), ENT_QUOTES, 'UTF-8');
                    $enderecoLoja = htmlspecialchars((string) ($empresaDetalhe['description'] ?? 'Endereço não informado'), ENT_QUOTES, 'UTF-8');
                    $telefoneLoja = '';
                    $tempoEntrega = '';
                    $linkProdutoLoja = $linkExternal !== '' ? $linkExternal : 'https://www.aiqfome.com';

                    $detalhesLoja = array_filter([$enderecoLoja, $telefoneLoja, $tempoEntrega], function ($valor) {
                        return $valor !== '';
                    });
                    $textoDetalhesLoja = !empty($detalhesLoja) ? implode('<br>', $detalhesLoja) : 'Produto disponibilizado pela AIQFome';

                    echo "
                    <div class='produto-desc'>
                        <p id='nota_media'>0.0<i class='fas fa-star'></i>(0)</p>
                        <h3>{$nomeProduto} ({$tamanhoProduto})</h3>
                        <p id='desc-prod'>{$descricaoProduto}</p>
                        <p>A partir de</p>
                        <h1>{$precoFormatado}</h1>
                        <h4> Atualizado em: hoje</h4>
                        <div class='desc-links'>
                            <h2>Peça já!</h2>
                            <div class='links'>
                                <div class='deliveryPreco'>
                                    <a href='{$linkProdutoLoja}' target='_blank' rel='noopener noreferrer'><img src='./images/logo_aiqfome.png' alt='AIQFome'></a>
                                    <p>{$precoFormatado}</p>
                                </div>
                            </div>
                        </div>
                        <div class='produto-info'>
                            <div class='lanchonete-info mobile'>
                                <h2 class='nome-lanchonete'>{$nomeLoja}</h2>
                                <p class='end-lanchonete'>{$textoDetalhesLoja}</p>
                            </div>
                        </div>
                    </div>
                    <div class='produto-info' id='info-telaCheia'>
                        <p id='nota_media'>0.0<i class='fas fa-star'></i>(0)</p>
                        <img src='{$imagemProduto}' alt='{$nomeProduto}' onerror=\"this.onerror=null;this.src='./images/default-product.png';\">
                        <div class='lanchonete-info cheia'>
                            <a href='{$linkProdutoLoja}' target='_blank' rel='noopener noreferrer'><h2 class='nome-lanchonete'>{$nomeLoja}</h2></a>
                            <p class='end-lanchonete'>{$textoDetalhesLoja}</p>
                        </div>
                    </div>";
                } elseif ($companyUuid !== '' && $productId !== '' && $produtoExterno !== null) {
                    $nomeProduto = htmlspecialchars((string) ($produtoExterno['name'] ?? 'Produto'), ENT_QUOTES, 'UTF-8');
                    $descricaoProduto = htmlspecialchars((string) ($produtoExterno['description'] ?? 'Sem descrição disponível.'), ENT_QUOTES, 'UTF-8');
                    $imagemProduto = !empty($produtoExterno['image']) ? (string) $produtoExterno['image'] : './images/default-product.png';
                    $precoProduto = $delivery->obterMenorPreco($produtoExterno);
                    $precoFormatado = is_numeric($precoProduto) ? 'R$' . number_format((float) $precoProduto, 2, ',', '.') : 'Consulte';
                    $tamanhoProduto = isset($produtoExterno['size_options']['label']) ? htmlspecialchars((string) $produtoExterno['size_options']['label'], ENT_QUOTES, 'UTF-8') : 'Padrão';
                    $nomeLoja = htmlspecialchars((string) (($empresaDetalhe['name'] ?? $empresaNome) ?: 'Delivery Much'), ENT_QUOTES, 'UTF-8');
                    $enderecoLoja = htmlspecialchars((string) ($empresaDetalhe['address'] ?? 'Endereço não informado'), ENT_QUOTES, 'UTF-8');
                    $telefoneLoja = !empty($empresaDetalhe['phone']) ? 'Tel: ' . htmlspecialchars((string) $empresaDetalhe['phone'], ENT_QUOTES, 'UTF-8') : '';
                    $tempoEntrega = !empty($empresaDetalhe['delivery_time']) ? 'Entrega: ' . htmlspecialchars((string) $empresaDetalhe['delivery_time'] . ' min', ENT_QUOTES, 'UTF-8') : '';
                    $linkProdutoLoja = 'https://www.deliverymuch.com.br';

                    $slugEmpresa = !empty($empresaDetalhe['slug']) ? trim((string) $empresaDetalhe['slug']) : '';
                    if ($slugEmpresa !== '') {
                        $segmentosSlug = array_values(array_filter(explode('/', $slugEmpresa), function ($segmento) {
                            return $segmento !== '';
                        }));

                        if (count($segmentosSlug) >= 2) {
                            $citySlug = rawurlencode((string) $segmentosSlug[0]);
                            $companySlug = rawurlencode((string) $segmentosSlug[1]);
                            $linkProdutoLoja = 'https://www.deliverymuch.com.br/lista-lojas/' . $citySlug . '/' . $companySlug;
                        } elseif (count($segmentosSlug) === 1) {
                            $linkProdutoLoja = 'https://www.deliverymuch.com.br/lista-lojas/' . rawurlencode((string) $segmentosSlug[0]);
                        }
                    }

                    $detalhesLoja = array_filter([$enderecoLoja, $telefoneLoja, $tempoEntrega], function ($valor) {
                        return $valor !== '';
                    });
                    $textoDetalhesLoja = !empty($detalhesLoja) ? implode('<br>', $detalhesLoja) : 'Produto disponibilizado pela Delivery Much';

                    echo "
                    <div class='produto-desc'>
                        <p id='nota_media'>0.0<i class='fas fa-star'></i>(0)</p>
                        <h3>{$nomeProduto} ({$tamanhoProduto})</h3>
                        <p id='desc-prod'>{$descricaoProduto}</p>
                        <p>A partir de</p>
                        <h1>{$precoFormatado}</h1>
                        <h4> Atualizado em: hoje</h4>
                        <div class='desc-links'>
                            <h2>Peça já!</h2>
                            <div class='links'>
                                <div class='deliveryPreco'>
                                    <a href='{$linkProdutoLoja}' target='_blank' rel='noopener noreferrer'><img src='./images/DeliveryMuch LogoLight.svg' alt='Delivery Much'></a>
                                    <p>{$precoFormatado}</p>
                                </div>
                            </div>
                        </div>
                        <div class='produto-info'>
                            <div class='lanchonete-info mobile'>
                                <h2 class='nome-lanchonete'>{$nomeLoja}</h2>
                                <p class='end-lanchonete'>{$textoDetalhesLoja}</p>
                            </div>
                        </div>
                    </div>
                    <div class='produto-info' id='info-telaCheia'>
                        <p id='nota_media'>0.0<i class='fas fa-star'></i>(0)</p>
                        <img src='{$imagemProduto}' alt='{$nomeProduto}' onerror=\"this.onerror=null;this.src='./images/default-product.png';\">
                        <div class='lanchonete-info cheia'>
                            <a href='#'><h2 class='nome-lanchonete'>{$nomeLoja}</h2></a>
                            <p class='end-lanchonete'>{$textoDetalhesLoja}</p>
                        </div>
                    </div>";
                } else {
                    include("conexao.php");
                    // Pega o ID da produto por meio da URL vindo do card clicado
                    $id_decriptado = mysqli_real_escape_string($conn, base64_decode($_GET['id']));
                    
                    // Realiza a busca no banco de dados, trazendo somente informações do produto em especifico
                    $detalhes = "SELECT * , date_format(p.proAtualizacao, '%d %b %Y') as dataAtualizacao, LEAST (
                                    COALESCE(NULLIF(p.preco_ifood, 0), 999999),
                                    COALESCE(NULLIF(p.preco_del_much, 0), 999999),
                                    COALESCE(NULLIF(p.preco_aiqfome, 0), 999999)
                                ) as menorPreco
                                FROM produtos p 
                                INNER JOIN tamanhos t ON t.tamId = p.tam_Id
                                INNER JOIN estabelecimentos e ON e.estId = p.est_Id
                                INNER JOIN cidades c ON c.cidId = e.cid_Id
                                where p.proId = $id_decriptado";

                    $resultadoDetalhes = mysqli_query($conn, $detalhes);                
                    
                    $nota = "SELECT COUNT(nota_avaliada) as total_notas FROM avaliacao WHERE id_prod = $id_decriptado;";
                    $nota_media = mysqli_fetch_array(mysqli_query($conn, $nota));

                    while($lancheDetalhe = mysqli_fetch_array($resultadoDetalhes)){ 
                      $id_loja_criptado = base64_encode($lancheDetalhe["estId"]);
                      $imagemProdutoBanco = !empty($lancheDetalhe["proImagem"]) ? './ctrl-buscafood/images/produtos/' . trim((string) $lancheDetalhe["proImagem"]) : './images/default-product.png';
                      echo "<div class='produto-desc'>
                      <p id='nota_media'>".$lancheDetalhe["avaliacao_media"]."<i class='fas fa-star'></i>(".$nota_media["total_notas"].")</p>
                      <h3>".$lancheDetalhe["proNome"]." (".$lancheDetalhe["tamNome"].")</h3>
                      <p id='desc-prod'>".$lancheDetalhe["proDescricao"].".</p>
                      <p>A partir de</p>
                      <h1> R$".$lancheDetalhe["menorPreco"]."</h1>
                      <h4> Atualizado em: ".$lancheDetalhe["dataAtualizacao"]."</h4>
                      <div class='desc-links'>
                          <h2>Peça já!</h2>
                          <div class='links'>";
                          if (!empty($lancheDetalhe["lnk_much"]) && $lancheDetalhe["preco_del_much"] != 0.00){
                              echo "<div class='deliveryPreco'>    
                                        <a href='".$lancheDetalhe['lnk_much']."' target='_blank'><img src='./images/DeliveryMuch LogoLight.svg' alt='AiQFome'></a>
                                        <p>R$".$lancheDetalhe["preco_del_much"]."</p>
                                    </div>";
                          }
                          if (!empty($lancheDetalhe["lnk_ifood"]) && $lancheDetalhe["preco_ifood"] != 0.00){
                              echo "<div class='deliveryPreco'>    
                                        <a href='".$lancheDetalhe['lnk_ifood']."' target='_blank'><img src='./images/ifood-43 1.svg' alt='AiQFome'></a>
                                        <p>R$".$lancheDetalhe["preco_ifood"]."</p>
                                    </div>";
                          }
                          if (!empty($lancheDetalhe["lnk_aiqfome"]) && $lancheDetalhe["preco_aiqfome"] != 0.00){
                              echo "<div class='deliveryPreco'>    
                                        <a href='".$lancheDetalhe['lnk_aiqfome']."' target='_blank'><img src='./images/logo_aiqfome.png' alt='AiQFome'></a>
                                        <p>R$".$lancheDetalhe["preco_aiqfome"]."</p>
                                    </div>";
                          }
                          
                      echo "</div>
                      </div>

                      <div class='avaliacao-mobile'>
                            <p id='avaliacao-title'> Avalie este produto aqui! </p>
                            <form method='POST' action='avaliacao.php'>
                                <input type='hidden' name='id_prod' value='".base64_encode($id_decriptado)."'>
                                <div class=estrelas>
                                    <input type='radio' id='vazio' name='estrela-mobile' value='' checked>

                                    <label for='estrela_1'><i class='fas fa-star'></i></label>
                                    <input type='radio' id='estrela_1' name='estrela-mobile' value='1'>

                                    <label for='estrela_2'><i class='fas fa-star'></i></label>
                                    <input type='radio' id='estrela_2' name='estrela-mobile' value='2'>

                                    <label for='estrela_3'><i class='fas fa-star'></i></label>
                                    <input type='radio' id='estrela_3' name='estrela-mobile' value='3'>

                                    <label for='estrela_4'><i class='fas fa-star'></i></label>
                                    <input type='radio' id='estrela_4' name='estrela-mobile' value='4'>

                                    <label for='estrela_5'><i class='fas fa-star'></i></label>
                                    <input type='radio' id='estrela_5' name='estrela-mobile' value='5'>
                                </div>
                                <input type='submit' onclick='avaliacao()' class='btn' id='btn-avaliar' value='Avaliar'>
                            </form>
                      </div>
                      <div class='produto-info'>
                          <div class='lanchonete-info mobile'>
                              <a href='./produtos_loja.php?id_loja=".$id_loja_criptado."'>
                                <h2 class='nome-lanchonete'>".$lancheDetalhe["estNome"]."</h2>
                              </a>
                              <p class='end-lanchonete'>
                                  ".$lancheDetalhe["estEndereco"]."<br>"
                                  .$lancheDetalhe["cidNome"].", ".$lancheDetalhe["ufSigla"]."<br>
                                  
                              </p>
                              <div class='social-links'>";
                                if ($lancheDetalhe["estWhatsapp"] <> NULL){
                                    echo "<a href='https://wa.me/55".$lancheDetalhe["estWhatsapp"]."'target='_blank'><i class='fab fa-whatsapp'></i></a>";
                                }                          
                                if ($lancheDetalhe["lnk_inst"] <> NULL){
                                    echo "<a href='".$lancheDetalhe["lnk_inst"]."'target='_blank'><i class='fab fa-instagram'></i></a>";
                                }                          
                                if ($lancheDetalhe["lnk_face"] <> NULL){
                                    echo "<a href='".$lancheDetalhe["lnk_face"]."'target='_blank''><i class='fab fa-facebook'></i></a>";
                                }                                                  
                          echo "</div>
                          </div>
                      </div>
                  </div>
                  <div class='produto-info' id='info-telaCheia'>
                      <p id='nota_media'>".$lancheDetalhe["avaliacao_media"]."<i class='fas fa-star'></i>(".$nota_media["total_notas"].")</p>
                      <img src='{$imagemProdutoBanco}' alt='' onerror=\"this.onerror=null;this.src='./images/default-product.png';\">
                      <div class='lanchonete-info cheia'>";
                      echo 
                      "<div class='avaliacao'>
                            <p id='avaliacao-title'> Avalie este produto aqui! </p>
                            <form method='POST' action='avaliacao.php'>
                                <input type='hidden' name='id_prod' value='".base64_encode($id_decriptado)."'>
                                <div class=estrelas>
                                    <input type='radio' id='vazio' name='estrela' value='' checked>

                                    <label for='estrela_um'><i class='fas fa-star'></i></label>
                                    <input type='radio' id='estrela_um' name='estrela' value='1'>

                                    <label for='estrela_dois'><i class='fas fa-star'></i></label>
                                    <input type='radio' id='estrela_dois' name='estrela' value='2'>

                                    <label for='estrela_tres'><i class='fas fa-star'></i></label>
                                    <input type='radio' id='estrela_tres' name='estrela' value='3'>

                                    <label for='estrela_quatro'><i class='fas fa-star'></i></label>
                                    <input type='radio' id='estrela_quatro' name='estrela' value='4'>

                                    <label for='estrela_cinco'><i class='fas fa-star'></i></label>
                                    <input type='radio' id='estrela_cinco' name='estrela' value='5'>
                                </div>
                                <input type='submit' onclick='avaliacao()' class='btn' id='btn-avaliar' value='Avaliar'>
                            </form>
                      </div>";

                      echo 
                      "<a href='./produtos_loja.php?id_loja=".$id_loja_criptado."'>
                        <h2 class='nome-lanchonete'>".$lancheDetalhe["estNome"]."</h2>
                      </a>
                      <p class='end-lanchonete'>
                         ".$lancheDetalhe["estEndereco"]."<br>
                         ".$lancheDetalhe["cidNome"].", ".$lancheDetalhe["ufSigla"]."<br>
                      </p>
                      <div class='social-links'>";
                        if ($lancheDetalhe["estWhatsapp"] <> NULL){
                            echo "<a href='https://wa.me/55".$lancheDetalhe["estWhatsapp"]."'target='_blank'><i class='fab  fa-whatsapp'></i></a>";
                        }                          
                        if ($lancheDetalhe["lnk_inst"] <> NULL){
                            echo "<a href='".$lancheDetalhe["lnk_inst"]."'target='_blank'><i class='fab fa-instagram'></i></a>";
                        }                          
                        if ($lancheDetalhe["lnk_face"] <> NULL){
                            echo "<a href='".$lancheDetalhe["lnk_face"]."'target='_blank''><i class='fab fa-facebook'></i></a>";
                        }                                                  
                      echo "</div>
                      </div>
                  </div>";
                    }
                }
            ?> 
        </div>
    </section>
    <script src="./js/script.js"></script>
    <script src="./js/darkMode.js"></script>
</body>
</html>