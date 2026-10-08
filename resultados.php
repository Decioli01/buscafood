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
   include("conexao.php");

   // Faz a requisição das informações que foram preenchidas na tela inicial e guarda em variáveis
   $prato = mysqli_real_escape_string($conn, $_REQUEST['prato']);
   $local = mysqli_real_escape_string($conn, $_REQUEST['location']);
   $categoria = mysqli_real_escape_string($conn, $_REQUEST['categorias']);
   
   // Realiza a verificação se o botão 'Filtrar' foi clicado
   if (@$_REQUEST['btn-filtrar']) {
       $filtro_preco = mysqli_real_escape_string($conn, $_REQUEST['slide-preco']);
       $tamanho = !empty($_REQUEST['tamanho']) ? mysqli_real_escape_string($conn, $_REQUEST['tamanho']) : NULL;
       $ordenacao = !empty($_REQUEST['ordem']) ? mysqli_real_escape_string($conn, $_REQUEST['ordem']) : NULL;
   
       // Monta a chamada da stored procedure quando botão clicado
        $stmt = $conn->prepare("CALL BuscarProdutosComFiltro(?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiii", $prato, $local, $categoria, $filtro_preco, $tamanho, $ordenacao);
        $stmt->execute();
        $result = $stmt->get_result();

   } else {
       // Monta a chamada da stored procedure com os dados iniciais
        $stmt = $conn->prepare("CALL BuscarProdutos(?, ?, ?)");
        $stmt->bind_param("ssi", $prato, $local, $categoria);
        $stmt->execute();
        $result = $stmt->get_result();
   }
    // Guarda a quantidade de itens encontrados na busca em uma variável
    $count = mysqli_num_rows($result);

    echo "<div style='display: flex; justify-content: space-between; align-items: center; padding-top: 20px;'>
            <a href='index.html' class='logo-result' style='width: fit-content;'><img id='logo' src='./images/LogoLight2.png' alt='' style='margin-left: 3rem;'></a>
            <label style='margin-right: 20px' class='switch'>
                <input id='btnDarkMode' type='checkbox'>
                <span class='slider'></span>
            </label>
            </div>";  
    // Mostra a quantidade de itens encontrados com a busca
    echo "<h1 style='padding: 10px; margin-top: 1rem; text-align: center;'>Exibindo $count resultados para '$prato' em '$local'</h1>";
?>
<section class="results">
    <div class="results-filtros">
        <div id="btn-filtros">
            <i class="fa-solid fa-filter"></i> Filtros
        </div>
        <div class="filtros">
            <h3>Filtros</h3>
            <!-- Guarda as informações dos campos de busca vindo da tela inicial para quando o botão 'Filtrar' for clicado não forem perdidas -->
            <?php echo '<form action="./resultados.php" method="post">
                <input type="hidden" name="prato" value='.$prato.'> 
                <input type="hidden" name="location" value='.$local.'> 
                <input type="hidden" name="categorias" value='.$categoria.'>' 
            ?>
                <div class="results-slide">
                    <h3>Valor Máximo</h3>
                    <input type="range" min="10" max="100" step="10.00" value="<?php echo $filtro_preco?>" name="slide-preco">
                    <div class="results-slide-numbers">
                        <h4>R$10,00</h4>
                        <h4>R$100,00</h4>
                    </div>
                </div>
                <div class="filtro-tamanhos">
                    <h3 style="margin-top: 20px;">Tamanhos</h3>
                    <div class="inputsTamanhos">
                        <div class="input">
                            <input type="radio" name="tamanho" id="tam_P" value="1">
                            <label for="tam_P">P</label>
                        </div>
                        <div class="input">
                            <input type="radio" name="tamanho" id="tam_M" value="2">
                            <label for="tam_M">M</label>
                        </div>
                        <div class="input">
                            <input type="radio" name="tamanho" id="tam_G" value="3">
                            <label for="tam_G">G</label>
                        </div>
                    </div>
                </div>
                <div class="filtro-ordem">
                    <h3 style="margin-top: 20px;">Ordem</h3>
                    <div class="inputsOrdem">
                        <div class="input">
                            <input type="radio" name="ordem" id="ord_P" value="1">
                            <label for="ord_P">Preço</label>
                        </div>
                        <div class="input">
                            <input type="radio" name="ordem" id="ord_A" value="2">
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
        // Faz a verificação para quando a busca não retorna nenhum resultado, imprimindo a mensagem na tela, caso contrário percorre o array de resultados
            if ($count <= 0){
                echo "<div>
                        <h3 style='text-align: center; font-size:3rem'>Não foram encontrados resultados!<br>
                        Tente novamente utilizando outros termos</h3>
                    </div>";
            }
            else{
                $nota_conn = new mysqli($host, $usuario, $senha, $bd);
                
                // Percorre o array de resultados, imprimindo cada índice com suas informações em um card
                while($campo = $result->fetch_assoc()){           
                    $id_criptado = base64_encode($campo["proId"]);
                    
                    $nota_query = "SELECT COUNT(nota_avaliada) as total_notas FROM avaliacao WHERE id_prod = '".$campo['proId']."'";

                    $nota_result = $nota_conn->query($nota_query);
                    
                    if ($nota_result) {
                        $nota_media = mysqli_fetch_assoc($nota_result);
                        if ($nota_media) {
                            $total_notas = $nota_media["total_notas"];
                        } else {
                            $total_notas = 0;
                        }
                    } else {
                        $total_notas = 0;
                        echo "Erro na query de notas: " . mysqli_error($conn);
                    }
                
                    echo "<a href='./produto.php?id=".$id_criptado."'>
                            <div class='card'>
                            <p id='nota_media'>".$campo["avaliacao_media"]."<i class='fas fa-star'></i>(".$total_notas.")</p>
                                <div class='card-img'>";
                    echo "<img src='./ctrl-buscafood/images/produtos/".$campo["proImagem"]."' alt=''>";
                    echo "      </div>
                                <div class='card-info'>
                                    <p style='display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;' class='text-title'>".$campo["proNome"]." (".$campo["tamNome"].")</p>
                                    <h3 style='color: #808080; text-overflow: ellipsis; white-space: nowrap; overflow-x: hidden;'>".$campo["estNome"]."</h3>
                                </div>
                                <div class='card-footer'>
                                    <span class='text-title'>A partir de <br>R$".$campo["menorPreco"]."</span>
                                </div>
                            </div> 
                        </a>";
                } 
                $stmt->close();
                $nota_conn->close();
                mysqli_close($conn);
            }           
        ?>            
    </div>
</section> 
    <script src="./js/darkMode.js"></script>
    <script src="./js/scriptFiltros.js"></script>
</body>
</html>