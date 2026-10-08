<?php
  session_start();
  $id = $_GET['id'];
    if(!empty($_GET['id_prod'])){
        include_once('./acoes/conexao.php');

        $id_prod = $_GET['id_prod'];

        $sqlSelect = "SELECT * FROM produtos WHERE proId=$id_prod";

        $result = $conn -> query($sqlSelect);

        if($result -> num_rows >0){
            while($user_data = mysqli_fetch_assoc($result)){
                $proNome = $user_data['proNome'];
                $proPrecoifood = $user_data['preco_ifood'];
                $proPrecoDelMuch = $user_data['preco_del_much'];
                $proPrecoAiqFome = $user_data['preco_aiqfome'];
                $tam_Id = $user_data['tam_Id'];
                $cat_Id = $user_data['cat_Id'];
                $proDescricao = $user_data['proDescricao'];
            }
        }
        else{
            header("'Location: listarProd.php?id_estab='$id");
        }
    }
?>

<!DOCTYPE html>
<html lang="pt-br">
    
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css'>
        <link rel="stylesheet" href="./css/cadestab.css">
        <title>Atualiza Produto - BuscaFood®</title>
    </head>

    <body>
        <form method="post" action="./acoes/atualizaProd.php?id_prod=<?php echo $id_prod?>" enctype="multipart/form-data" class="wizard-section">
        <input type="hidden" value="<?php echo $id ?>" name="id">
            <div class="row no-gutters">
                <div class="col-lg-6 col-md-6">
                    <div class="wizard-content-left cad-produto">
                        
                        <div class="text-bg">
                            <h3>Bem Vindo!</h3>
                            <h1>Atualize Seu Produto!</h1>
                        </div>
                        <div class="img-logo">
                            <img src="../images/logoDarkSF.png" alt="BuscaFood®">
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-md-6">
                    <div class="form-wizard">
                        <form action="" method="post" role="form">
                            <div class="form-wizard-header">

                                <header class="header">
                                    <div id="logout" >
                                        <a href="listarProd.php?id_estab=<?php echo $id?>">Voltar</a>
                                    </div>
                                </header>
                                
                                <p>Preencha todos os campos de formulário para dar o próximo passo</p>
                                <ul class="list-unstyled form-wizard-steps clearfix">
                                    <li class="active"><span>1</span></li>
                                    <li><span>2</span></li>
                                    <li><span>3</span></li>
                                    <li><span>4</span></li>
                                </ul>
                            </div>

                            <fieldset class="wizard-fieldset show">
                                <h3>Informações Básicas</h3>
                                <div class="form-group">
                                    <input type="text" name="prodNome" class="form-control" id="pname" value="<?php echo $proNome ?>" required>
                                    <label for="pname" class="wizard-form-text-label"></label>
                                    <div class="wizard-form-error"></div>
                                </div>

                                <div class="form-group">
                                    <textarea name="descricaoProd" id="descricaoProd" required maxlength="50"><?php echo htmlspecialchars($proDescricao);?></textarea>
                                    <label id="labelLogo" for="descricaoProd" class="wizard-form-text-label"></label>
                                    <div class="wizard-form-error"></div>
                                </div>
                                <h5>Categoria</h5>
                                <div class="form-group categorias">
                                    <div>
                                        <input type="radio" name="categoria" id="hamburguer" value="1" <?php echo ($cat_Id == '1') ? 'checked' : ''?> required>
                                        <label for="hamburguer">Hamburguer</label>
                                    </div>
                                    <div>
                                        <input type="radio" name="categoria" id="hotdog" value="2" <?php echo ($cat_Id == '2') ? 'checked' : ''?> required>
                                        <label for="hotdog">Cachorro Quente</label>
                                    </div>
                                    <div>
                                        <input type="radio" name="categoria" id="pizza" value="3" <?php echo ($cat_Id == '3') ? 'checked' : ''?> required>
                                        <label for="pizza">Pizza</label>
                                    </div>
                                    <div>
                                        <input type="radio" name="categoria" id="porcao" value="4" <?php echo ($cat_Id == '4') ? 'checked' : ''?> required>
                                        <label for="porcao">Porção</label>
                                    </div>
                                </div>

                                <div class="form-group clearfix">
                                    <a href="javascript:;" class="form-wizard-next-btn float-right">Próximo</a>
                                </div>
                            </fieldset>

                            <!-- Endereço -->
                            <fieldset class="wizard-fieldset">
                                <h3>Informações Adicionais</h3>
                                <div class="form-group tamanhos">
                                    <div>
                                        <input type="radio" name="tamanho" id="pequeno" value="1" <?php echo ($tam_Id == '1') ? 'checked' : ''?> required>
                                        <label for="pequeno">Pequeno(a)</label>
                                    </div>
                                    <div>
                                        <input type="radio" name="tamanho" id="medio" value="2" <?php echo ($tam_Id == '2') ? 'checked' : ''?> required>
                                        <label for="medio">Médio(a)</label>
                                    </div>
                                    <div>
                                        <input type="radio" name="tamanho" id="grande" value="3" <?php echo ($tam_Id == '3') ? 'checked' : ''?> required>
                                        <label for="grande">Grande</label>
                                    </div>
                                </div>

                                <div class="form-group clearfix">
                                    <a href="javascript:;" class="form-wizard-previous-btn float-left">Voltar</a>
                                    <a href="javascript:;" class="form-wizard-next-btn float-right">Próximo</a>
                                </div>
                            </fieldset>

                            <!-- Redese Social -->
                        
                            <fieldset class="wizard-fieldset">
                                <h3>Disponibilidade</h3>
                                <div class="form-group">
                                    <h4>Qual delivery está publicado o produto?</h4><br>
                                    <div class="deliverys">
                                        <div>
                                            <input type="checkbox" name="ifood" id="ifood" <?php echo ($proPrecoifood > 0) ? 'checked' : ''?>>
                                            <label for="ifood">iFood</label>
                                        </div>
                                        <div>
                                            <input type="checkbox" name="delMuch" id="delMuch" <?php echo ($proPrecoDelMuch > 0) ? 'checked' : ''?>>
                                            <label for="delMuch">Delivery Much</label>
                                        </div>
                                        <div>
                                            <input type="checkbox" name="aiqFome" id="aiqFome" <?php echo ($proPrecoAiqFome > 0) ? 'checked' : ''?>>
                                            <label for="aiqFome">Ai Q Fome</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <h4>Valores</h4>
                                    <div class="valores">
                                        <div>
                                            <label for="valorIfood">iFood</label><br>
                                            <input type="number" step=".01" name="valorIfood" id="campovalorIfood" disabled <?php echo ($proPrecoifood > 0) ? "value=\"$proPrecoifood\"" : ''?>>
                                        </div>
                                        <div>
                                            <label for="valorDelMuch">Delivery Much</label><br>
                                            <input type="number" step=".01" name="valorDelMuch" id="campovalorDelMuch" disabled <?php echo ($proPrecoDelMuch > 0) ? "value=\"$proPrecoDelMuch\"" : ''?>>
                                        </div>
                                        <div>
                                            <label for="valorAiqFome">Ai Q Fome</label><br>
                                            <input type="number" step=".01" name="valorAiqFome" id="campovalorAiqFome" disabled <?php echo ($proPrecoAiqFome > 0) ? "value=\"$proPrecoAiqFome\"" : ''?>>
                                        </div>
                                    </div>

                                </div>

                                <div class="form-group clearfix">
                                    <a href="javascript:;" class="form-wizard-previous-btn float-left">Voltar</a>
                                    <a href="javascript:;" class="form-wizard-next-btn float-right">Próximo</a>
                                </div>
                            </fieldset>
                            <fieldset class="wizard-fieldset endForm">
                                <h1>Deseja Atualizar o produto?</h1>
                                <a href="listarprod.php?id_estab=<?php echo $id_estab?>">Cancelar</a>
                                <input type="submit" name="update" value="Atualizar">

                                <div class="form-group clearfix">
                                    <a href="javascript:;" class="form-wizard-previous-btn float-left">Voltar</a>
                                </div>
                            </fieldset>    
                        </form>
                    </div>
                </div>
            </div>
        </form>
        <script src='https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js'></script>
        <script src="./script/script.js"></script>
    </body>
</html>