<?php

class DeliveryMuchService
{
    private $baseUrl = 'https://bffnewsite.devmuch.com.br';

    /**
     * Busca estabelecimentos próximos na Delivery Much.
     */
    public function buscarCompanhias(
        $latitude,
        $longitude,
        $range = 15,
        $size = 40,
        $from = 0
    ) {
        $query = http_build_query([
            'name' => '',
            'payment_methods' => '',
            'badges' => '',
            'type' => 'null',
            'categories' => '',
            'size' => (int) $size,
            'range' => (int) $range,
            'sort' => 'distance',
            'from' => (int) $from,
            'lat' => (float) $latitude,
            'lng' => (float) $longitude
        ]);

        $url = $this->baseUrl
            . '/v1/companies/?'
            . $query;

        return $this->request($url);
    }

    /**
     * Busca o cardápio de uma companhia.
     *
     * Endpoint:
     * /companies/{uuid}/products
     */
    public function buscarProdutos($companyUuid)
    {
        $companyUuid = trim((string) $companyUuid);

        if (!$this->uuidValido($companyUuid)) {
            throw new Exception(
                'UUID da companhia inválido.'
            );
        }

        $url = $this->baseUrl
            . '/companies/'
            . rawurlencode($companyUuid)
            . '/products';

        error_log('Delivery Much - buscarProdutos: ' . $url);

        $response = $this->request($url);

        $produtos = $this->normalizarProdutos($response);

        if (empty($produtos)) {
            error_log('Delivery Much - resposta sem produtos para UUID: ' . $companyUuid);
            error_log('Delivery Much - raw response: ' . json_encode($response));
        }

        return $produtos;
    }

    /**
     * Busca produtos de todas as empresas próximas pelo termo e categoria
     * informados na tela inicial.
     */
    public function buscarProdutosPorBusca(
        $latitude,
        $longitude,
        $termo = '',
        $categoria = '',
        $range = 15,
        $size = 40
    ) {
        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if (!is_finite($latitude) || !is_finite($longitude)) {
            return [];
        }

        $ranges = array_values(array_unique([$range, 25, 50, 100, 200]));
        $empresas = [];

        foreach ($ranges as $raio) {
            $resp = $this->buscarCompanhias(
                $latitude,
                $longitude,
                $raio,
                $size,
                0
            );

            $lista = $this->normalizarEmpresas($resp);
            if (!empty($lista)) {
                $empresas = $lista;
                break;
            }
        }

        if (empty($empresas)) {
            return [];
        }

        $termoNormalizado = $this->normalizarTexto($termo);
        $categoriaNormalizada = $this->normalizarTexto(
            $this->categoriaParaTexto($categoria)
        );

        $resultado = [];

        foreach ($empresas as $empresa) {
            if (!is_array($empresa)) {
                continue;
            }

            $companyUuid = isset($empresa['uuid'])
                ? (string) $empresa['uuid']
                : (isset($empresa['id']) ? (string) $empresa['id'] : '');

            if ($companyUuid === '') {
                $companyUuid = isset($empresa['company']['uuid'])
                    ? (string) $empresa['company']['uuid']
                    : (isset($empresa['company']['id']) ? (string) $empresa['company']['id'] : '');
            }

            if ($companyUuid === '' || !$this->uuidValido($companyUuid)) {
                continue;
            }

            try {
                $categorias = $this->buscarProdutos($companyUuid);
            } catch (Exception $e) {
                continue;
            }

            foreach ($categorias as $categoriaItem) {
                if (!is_array($categoriaItem)) {
                    continue;
                }

                $produtos = isset($categoriaItem['products']) && is_array($categoriaItem['products'])
                    ? $categoriaItem['products']
                    : [];

                $nomeCategoria = $this->normalizarTexto(
                    isset($categoriaItem['name']) ? $categoriaItem['name'] : ''
                );

                foreach ($produtos as $produto) {
                    if (!is_array($produto)) {
                        continue;
                    }

                    $nomeProduto = $this->normalizarTexto(
                        isset($produto['name']) ? $produto['name'] : ''
                    );
                    $descricaoProduto = $this->normalizarTexto(
                        isset($produto['description']) ? $produto['description'] : ''
                    );

                    $acertouTermo = $termoNormalizado === ''
                        || strpos($nomeProduto, $termoNormalizado) !== false
                        || strpos($descricaoProduto, $termoNormalizado) !== false;

                    $acertouCategoria = $categoriaNormalizada === ''
                        || $this->categoriaCorresponde(
                            $categoriaNormalizada,
                            $nomeCategoria,
                            $nomeProduto,
                            $descricaoProduto
                        );

                    if (!$acertouTermo || !$acertouCategoria) {
                        continue;
                    }

                    $menorPreco = $this->obterMenorPreco($produto);

                    $resultado[] = [
                        'proId' => isset($produto['id']) ? (string) $produto['id'] : null,
                        'proNome' => isset($produto['name']) ? (string) $produto['name'] : '',
                        'tamNome' => isset($produto['size_options']['label'])
                            ? $produto['size_options']['label']
                            : 'Padrão',
                        'estNome' => isset($empresa['name']) ? (string) $empresa['name'] : 'Delivery Much',
                        'proImagem' => isset($produto['image']) ? (string) $produto['image'] : '',
                        'menorPreco' => $menorPreco,
                        'avaliacao_media' => 0,
                        'company_uuid' => $companyUuid,
                        'category' => isset($categoriaItem['name']) ? (string) $categoriaItem['name'] : ''
                    ];
                }
            }
        }

        return $resultado;
    }

    /**
     * Normaliza respostas de empresas vindas da Delivery Much.
     */
    private function normalizarEmpresas($dados)
    {
        if (!is_array($dados)) {
            return [];
        }

        if (isset($dados['data']) && is_array($dados['data'])) {
            return $this->normalizarEmpresas($dados['data']);
        }

        if (isset($dados['companies']) && is_array($dados['companies'])) {
            return $this->normalizarEmpresas($dados['companies']);
        }

        if (isset($dados['items']) && is_array($dados['items'])) {
            return $this->normalizarEmpresas($dados['items']);
        }

        if (isset($dados['results']) && is_array($dados['results'])) {
            return $this->normalizarEmpresas($dados['results']);
        }

        if (!empty($dados) && array_keys($dados) === range(0, count($dados) - 1)) {
            return array_values(array_filter($dados, 'is_array'));
        }

        return [$dados];
    }

    /**
     * Busca produtos de uma companhia pelo termo
     * informado no BuscaFood.
     */
    public function pesquisarProdutos(
        $companyUuid,
        $termo = ''
    ) {
        $categorias = $this->buscarProdutos($companyUuid);

        $termo = $this->normalizarTexto($termo);

        $resultado = [];

        foreach ($categorias as $categoria) {

            if (
                !isset($categoria['products']) ||
                !is_array($categoria['products'])
            ) {
                continue;
            }

            foreach ($categoria['products'] as $produto) {

                $nome = $this->normalizarTexto(
                    isset($produto['name'])
                        ? $produto['name']
                        : ''
                );

                $descricao = $this->normalizarTexto(
                    isset($produto['description'])
                        ? $produto['description']
                        : ''
                );

                if (
                    $termo === '' ||
                    strpos($nome, $termo) !== false ||
                    strpos($descricao, $termo) !== false
                ) {
                    $produto['category'] =
                        isset($categoria['name'])
                            ? $categoria['name']
                            : '';

                    $resultado[] = $produto;
                }
            }
        }

        return $resultado;
    }

    /**
     * Busca os detalhes de uma companhia.
     */
    public function buscarDetalhesCompanhia($companyUuid)
    {
        $companyUuid = trim((string) $companyUuid);

        if ($companyUuid === '' || !$this->uuidValido($companyUuid)) {
            return null;
        }

        $urls = [
            $this->baseUrl . '/v1/companies/' . rawurlencode($companyUuid) . '/',
            $this->baseUrl . '/companies/' . rawurlencode($companyUuid),
            $this->baseUrl . '/v1/companies/?id=' . rawurlencode($companyUuid)
        ];

        foreach ($urls as $url) {
            try {
                $dados = $this->request($url);
                $normalizado = $this->normalizarCompanhia($dados);

                if (!empty($normalizado)) {
                    return $normalizado;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Procura um produto específico no cardápio.
     */
    public function buscarProdutoPorId(
        $companyUuid,
        $productId
    ) {
        $categorias = $this->buscarProdutos($companyUuid);

        foreach ($categorias as $categoria) {

            if (
                !isset($categoria['products']) ||
                !is_array($categoria['products'])
            ) {
                continue;
            }

            foreach ($categoria['products'] as $produto) {

                if (
                    isset($produto['id']) &&
                    (string) $produto['id']
                        === (string) $productId
                ) {
                    $produto['category'] =
                        isset($categoria['name'])
                            ? $categoria['name']
                            : '';

                    return $produto;
                }
            }
        }

        return null;
    }

    /**
     * Normaliza os dados de uma companhia retornados pela API.
     */
    private function normalizarCompanhia($dados)
    {
        if (!is_array($dados)) {
            return null;
        }

        $empresa = $dados;

        if (isset($empresa['data']) && is_array($empresa['data'])) {
            $empresa = $empresa['data'];
        }

        if (isset($empresa['company']) && is_array($empresa['company'])) {
            $empresa = $empresa['company'];
        }

        if (isset($empresa['results']) && is_array($empresa['results']) && !empty($empresa['results'])) {
            $empresa = $empresa['results'][0];
        }

        if (isset($empresa['items']) && is_array($empresa['items']) && !empty($empresa['items'])) {
            $empresa = $empresa['items'][0];
        }

        $rua = $this->extrairCampo($empresa, ['street', 'street_name', 'logradouro', 'address', 'address_street', 'address_line']);
        $numero = $this->extrairCampo($empresa, ['number', 'street_number', 'numero', 'address_number']);
        $bairro = $this->extrairCampo($empresa, ['neighborhood', 'district', 'bairro', 'area']);
        $cidade = $this->extrairCampo($empresa, ['city', 'city_name', 'cidade']);
        $estado = $this->extrairCampo($empresa, ['state', 'state_name', 'uf', 'estado']);
        $cep = $this->extrairCampo($empresa, ['zipcode', 'postal_code', 'zip_code', 'cep']);
        $telefone = $this->extrairCampo($empresa, ['phone', 'phone_number', 'telephone', 'whatsapp']);
        $nome = $this->extrairCampo($empresa, ['name', 'company_name', 'title']);
        $slugEmpresa = $this->extrairCampo($empresa, ['slug', 'company_slug', 'url_slug', 'path']);
        $tempoEntrega = $this->extrairCampo($empresa, ['delivery_time', 'delivery_time_minutes', 'eta', 'delivery_time_min']);

        if ($nome === null && isset($empresa['company']) && is_array($empresa['company'])) {
            $nome = $this->extrairCampo($empresa['company'], ['name', 'company_name', 'title']);
        }

        $endereco = [];

        if ($rua !== null) {
            $endereco[] = $rua;
        }

        if ($numero !== null) {
            $endereco[] = $numero;
        }

        if ($bairro !== null) {
            $endereco[] = $bairro;
        }

        if ($cidade !== null) {
            $cidadeLabel = $cidade;
            if ($estado !== null) {
                $cidadeLabel .= ' - ' . $estado;
            }
            $endereco[] = $cidadeLabel;
        } elseif ($estado !== null) {
            $endereco[] = $estado;
        }

        if ($cep !== null) {
            $endereco[] = 'CEP ' . $cep;
        }

        $textoEndereco = implode(', ', array_filter(array_map(function ($valor) {
            return is_string($valor) ? trim($valor) : $valor;
        }, $endereco), function ($valor) {
            return $valor !== '' && $valor !== null && !is_array($valor);
        }));

        return [
            'name' => $nome ?? 'Delivery Much',
            'address' => $textoEndereco !== '' ? $textoEndereco : 'Endereço não informado',
            'street' => $rua,
            'number' => $numero,
            'neighborhood' => $bairro,
            'city' => $cidade,
            'state' => $estado,
            'zipcode' => $cep,
            'phone' => $telefone,
            'slug' => $slugEmpresa,
            'delivery_time' => $tempoEntrega,
            'raw' => $empresa
        ];
    }

    /**
     * Normaliza um valor vindo da API para texto seguro.
     */
    private function normalizarValorTexto($valor)
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_array($valor)) {
            foreach ($valor as $item) {
                $resultado = $this->normalizarValorTexto($item);
                if ($resultado !== null) {
                    return $resultado;
                }
            }

            return null;
        }

        if (is_object($valor)) {
            if (method_exists($valor, '__toString')) {
                $texto = (string) $valor;
                return $texto !== '' ? trim($texto) : null;
            }

            $json = json_decode(json_encode($valor), true);
            if (is_array($json)) {
                return $this->normalizarValorTexto($json);
            }

            return null;
        }

        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }

        $texto = trim((string) $valor);

        return $texto !== '' ? $texto : null;
    }

    /**
     * Extrai campo de array de forma tolerante.
     */
    private function extrairCampo($dados, $chaves)
    {
        if (!is_array($dados)) {
            return null;
        }

        foreach ($chaves as $chave) {
            if (array_key_exists($chave, $dados)) {
                $valor = $this->normalizarValorTexto($dados[$chave]);
                if ($valor !== null) {
                    return $valor;
                }
            }
        }

        foreach ($dados as $valor) {
            if (is_array($valor)) {
                $resultado = $this->extrairCampo($valor, $chaves);
                if ($resultado !== null) {
                    return $resultado;
                }
            }
        }

        return null;
    }

    /**
     * Retorna o menor preço disponível para
     * determinado produto.
     */
    public function obterMenorPreco($produto)
    {
        if (
            !isset($produto['size_options']['sizes']) ||
            !is_array(
                $produto['size_options']['sizes']
            )
        ) {
            return null;
        }

        $menorPreco = null;

        foreach (
            $produto['size_options']['sizes']
            as $size
        ) {
            if (
                !isset($size['price']) ||
                !is_numeric($size['price'])
            ) {
                continue;
            }

            $preco = (float) $size['price'];

            if (
                $menorPreco === null ||
                $preco < $menorPreco
            ) {
                $menorPreco = $preco;
            }
        }

        return $menorPreco;
    }

    /**
     * Normaliza o retorno do endpoint de produtos.
     *
     * Aceita categorias, wrapper de dados e respostas simples.
     */
    private function normalizarProdutos($data)
    {
        if (!is_array($data)) {
            return [];
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return $this->normalizarProdutos($data['data']);
        }

        if (isset($data['categories']) && is_array($data['categories'])) {
            return $this->normalizarProdutos($data['categories']);
        }

        if (isset($data['products']) && is_array($data['products'])) {
            return $this->normalizarProdutos([
                [
                    'name' => isset($data['name']) ? (string) $data['name'] : '',
                    'products' => $data['products']
                ]
            ]);
        }

        $categorias = [];

        foreach ($data as $categoria) {
            if (!is_array($categoria)) {
                continue;
            }

            $products = isset($categoria['products']) && is_array($categoria['products'])
                ? $categoria['products']
                : [];

            if (empty($products) && isset($categoria['id']) && !isset($categoria['name'])) {
                $products = [$categoria];
            }

            if (empty($products) && isset($categoria['items']) && is_array($categoria['items'])) {
                $products = $categoria['items'];
            }

            $categoriaNormalizada = [
                'name' => isset($categoria['name'])
                    ? trim((string) $categoria['name'])
                    : '',
                'products' => []
            ];

            foreach ($products as $produto) {
                if (!is_array($produto)) {
                    continue;
                }

                $sizes = [];
                $sizeOptions = isset($produto['size_options']) && is_array($produto['size_options'])
                    ? $produto['size_options']
                    : [];

                if (isset($sizeOptions['sizes']) && is_array($sizeOptions['sizes'])) {
                    foreach ($sizeOptions['sizes'] as $size) {
                        if (!is_array($size)) {
                            continue;
                        }

                        $sizes[] = [
                            'price_id' => isset($size['price_id']) ? $size['price_id'] : null,
                            'price' => isset($size['price']) && is_numeric($size['price']) ? (float) $size['price'] : null,
                            'name' => isset($size['name']) ? (string) $size['name'] : '',
                            'promo_id' => isset($size['promo_id']) ? $size['promo_id'] : null,
                            'promo_has_started' => isset($size['promo_has_started']) ? $size['promo_has_started'] : null
                        ];
                    }
                }

                $produtoUrl = $this->extrairCampo($produto, ['url', 'link', 'site_url', 'product_url', 'external_url', 'website_url']);
                $produtoSlug = $this->extrairCampo($produto, ['slug', 'url_slug', 'path']);
                $companySlug = isset($produto['company']) && is_array($produto['company'])
                    ? $this->extrairCampo($produto['company'], ['slug', 'url_slug', 'path'])
                    : null;

                $categoriaNormalizada['products'][] = [
                    'id' => isset($produto['id']) ? $produto['id'] : null,
                    'name' => isset($produto['name']) ? (string) $produto['name'] : '',
                    'description' => isset($produto['description']) ? (string) $produto['description'] : '',
                    'image' => isset($produto['image']) ? $produto['image'] : null,
                    'brand' => isset($produto['brand']) ? $produto['brand'] : null,
                    'has_beverage' => isset($produto['has_beverage']) ? (bool) $produto['has_beverage'] : false,
                    'slug' => $produtoSlug,
                    'company_slug' => $companySlug,
                    'url' => $produtoUrl,
                    'size_options' => [
                        'label' => isset($sizeOptions['label']) ? $sizeOptions['label'] : null,
                        'sizes' => $sizes
                    ]
                ];
            }

            if (!empty($categoriaNormalizada['products'])) {
                $categorias[] = $categoriaNormalizada;
            }
        }

        return $categorias;
    }

    /**
     * Executa GET para a API.
     */
    private function request($url)
    {
        if (!function_exists('curl_init')) {
            throw new Exception(
                'A extensão cURL do PHP não está habilitada.'
            );
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,

            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,

            /*
             * Permite ao cURL tratar gzip/br quando
             * suportado pela instalação.
             */
            CURLOPT_ENCODING => '',

            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/plain, */*',
                'App-Version: 3.18.0',
                'Authorization: Bearer null',
                'Device-Type: SITE_DESKTOP',
                'Origin: https://www.deliverymuch.com.br',
                'Referer: https://www.deliverymuch.com.br/',
                'x-dm-context: MKTPLACE'
            ]
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            $erro = curl_error($curl);

            curl_close($curl);

            throw new Exception(
                'Erro ao acessar a Delivery Much: '
                . $erro
            );
        }

        $httpCode = curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        curl_close($curl);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception(
                'Delivery Much retornou HTTP '
                . $httpCode
            );
        }

        $data = json_decode($response, true);

        if (
            json_last_error()
            !== JSON_ERROR_NONE
        ) {
            throw new Exception(
                'JSON inválido retornado pela '
                . 'Delivery Much: '
                . json_last_error_msg()
            );
        }

        if (!is_array($data)) {
            throw new Exception(
                'Formato inesperado retornado '
                . 'pela Delivery Much.'
            );
        }

        return $data;
    }

    /**
     * Validação básica do UUID.
     */
    private function uuidValido($uuid)
    {
        return preg_match(
            '/^[0-9a-f]{8}-'
            . '[0-9a-f]{4}-'
            . '[0-9a-f]{4}-'
            . '[0-9a-f]{4}-'
            . '[0-9a-f]{12}$/i',
            $uuid
        ) === 1;
    }

    /**
     * Converte texto de endereço/cidade em latitude e longitude
     * usando geocodificação do servidor. Não usa coordenadas fixas.
     */
    public function coordenadasPorTexto($localizacao)
    {
        $texto = trim((string) $localizacao);

        if ($texto === '') {
            return [
                'lat' => null,
                'lng' => null,
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'lat' => null,
                'lng' => null,
            ];
        }

        $consultas = array_values(array_unique([
            $texto,
            $texto . ', SP',
            $texto . ', São Paulo',
            $texto . ', Brasil',
            $texto . ', SP, Brasil'
        ]));

        foreach ($consultas as $consulta) {
            $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&addressdetails=1&q='
                . rawurlencode($consulta)
                . '&countrycodes=br';

            $dados = $this->geocodificarConsulta($url);
            if (is_array($dados) && !empty($dados)) {
                $lat = isset($dados[0]['lat']) ? (float) $dados[0]['lat'] : null;
                $lng = isset($dados[0]['lon']) ? (float) $dados[0]['lon'] : null;

                if ($lat !== null && $lng !== null) {
                    return ['lat' => $lat, 'lng' => $lng];
                }
            }
        }

        return ['lat' => null, 'lng' => null];
    }

    private function geocodificarConsulta($url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'BuscaFood/1.0 (+https://example.com)',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8'
            ]
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            curl_close($curl);
            return null;
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        $dados = json_decode($response, true);

        if (!is_array($dados) || empty($dados)) {
            return null;
        }

        return $dados;
    }

    /**
     * Mapeia a categoria do BuscaFood para termos de busca úteis.
     */
    private function categoriaParaTexto($categoria)
    {
        $categoria = trim((string) $categoria);

        if ($categoria === '') {
            return '';
        }

        $mapa = [
            '1' => 'hamburguer',
            '2' => 'hot dog',
            '3' => 'pizza',
            '4' => 'porcao',
            'hamburguer' => 'hamburguer',
            'hamburgueres' => 'hamburguer',
            'hotdog' => 'hot dog',
            'hot dog' => 'hot dog',
            'dog' => 'hot dog',
            'pizza' => 'pizza',
            'porcao' => 'porcao',
            'porções' => 'porcao',
            'porcoes' => 'porcao',
            'lanches' => 'hamburguer',
            'lanche' => 'hamburguer'
        ];

        return isset($mapa[$categoria]) ? $mapa[$categoria] : $categoria;
    }

    /**
     * Verifica se a categoria selecionada bate com os nomes reais
     * que a Delivery Much retorna nas categorias e produtos.
     */
    private function categoriaCorresponde($categoriaSelecionada, $nomeCategoria, $nomeProduto, $descricaoProduto)
    {
        $categoriaSelecionada = $this->normalizarTexto($categoriaSelecionada);
        $nomeCategoria = $this->normalizarTexto($nomeCategoria);
        $nomeProduto = $this->normalizarTexto($nomeProduto);
        $descricaoProduto = $this->normalizarTexto($descricaoProduto);

        $aliases = [
            'hamburguer' => ['hamburguer', 'burguer', 'burger', 'lanche', 'lanches', 'mini lanches', 'x tudo', 'master'],
            'hot dog' => ['hot dog', 'hotdog', 'dog', 'cachorro quente', 'cachorro'],
            'pizza' => ['pizza', 'pizzas'],
            'porcao' => ['porcao', 'porcoes', 'porções', 'batata', 'batata frita']
        ];

        $chaves = isset($aliases[$categoriaSelecionada]) ? $aliases[$categoriaSelecionada] : [$categoriaSelecionada];

        foreach ($chaves as $alias) {
            if ($alias === '') {
                continue;
            }

            $categoriaNaCategoria = $this->textoContemPalavra($nomeCategoria, $alias);
            $categoriaNoProduto = $this->textoContemPalavra($nomeProduto, $alias);

            if ($categoriaNaCategoria || $categoriaNoProduto) {
                return true;
            }
        }

        if ($nomeCategoria === '' && $nomeProduto === '') {
            foreach ($chaves as $alias) {
                if ($alias === '') {
                    continue;
                }

                if ($this->textoContemPalavra($descricaoProduto, $alias)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verifica se um termo aparece como palavra isolada, evitando matches no meio de outras palavras.
     */
    private function textoContemPalavra($texto, $termo)
    {
        $texto = trim((string) $texto);
        $termo = trim((string) $termo);

        if ($texto === '' || $termo === '') {
            return false;
        }

        $padrao = '/(^|[^a-z0-9])' . preg_quote($termo, '/') . '($|[^a-z0-9])/i';

        return preg_match($padrao, $texto) === 1;
    }

    /**
     * Normaliza texto para pesquisa.
     */
    private function normalizarTexto($texto)
    {
        $texto = trim((string) $texto);

        if (function_exists('mb_strtolower')) {
            $texto = mb_strtolower(
                $texto,
                'UTF-8'
            );
        } else {
            $texto = strtolower($texto);
        }

        if (function_exists('iconv')) {
            $convertido = @iconv(
                'UTF-8',
                'ASCII//TRANSLIT//IGNORE',
                $texto
            );

            if ($convertido !== false) {
                $texto = $convertido;
            }
        }

        return $texto;
    }
}