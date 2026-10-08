<?php

class AiqfomeService
{
    private $baseUrl = 'https://www.aiqfome.com';

    /**
     * Busca o HTML de um estabelecimento do AIQFome.
     */
    public function buscarPaginaRestaurante($url)
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        return $this->requestHtml($url);
    }

    /**
     * Busca produtos de uma página de restaurante AIQFome.
     */
    public function buscarProdutosPorUrl($url)
    {
        $html = $this->buscarPaginaRestaurante($url);

        if ($html === null || $html === '') {
            return [];
        }

        $itens = $this->extrairProdutos($html);

        if (empty($itens)) {
            $htmlNormalizado = preg_replace('/\s+/', ' ', $html);
            $this->log('Aiqfome - sem itens parseados para URL: ' . $url);
            $this->log('Aiqfome - preview: ' . substr($htmlNormalizado, 0, 400));
        }

        return $itens;
    }

    /**
     * Busca detalhes do restaurante a partir da URL.
     */
    public function buscarDetalhesRestaurante($url)
    {
        $html = $this->buscarPaginaRestaurante($url);

        if ($html === null || $html === '') {
            return [];
        }

        preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $titulo);
        preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/is', $html, $ogTitulo);
        preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/is', $html, $ogDescricao);
        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/is', $html, $descricao);

        $tituloEmpresa = $this->normalizarNomeEmpresa($this->obterNomeRestaurante($url, $html));

        if ($tituloEmpresa === '' || $tituloEmpresa === 'AiQFome') {
            $tituloEmpresa = $this->normalizarNomeEmpresa(
                isset($ogTitulo[1]) ? $ogTitulo[1] : (isset($titulo[1]) ? $titulo[1] : '')
            );
        }

        return [
            'title' => $tituloEmpresa,
            'description' => $this->limparTexto(isset($ogDescricao[1]) ? $ogDescricao[1] : (isset($descricao[1]) ? $descricao[1] : '')),
            'url' => trim((string) $url)
        ];
    }

    /**
     * Busca produtos do AIQFome via banco legado e URLs cadastradas.
     */
    public function buscarProdutosPorBusca($termo = '', $categoria = '', $local = '')
    {
        $termo = trim((string) $termo);
        $categoria = trim((string) $categoria);
        $local = trim((string) $local);

        $urlsBusca = $this->montarUrlsBusca($termo, $categoria, $local);
        $resultados = [];
        $visitados = [];

        foreach ($urlsBusca as $urlBusca) {
            $htmlBusca = $this->requestHtml($urlBusca);

            if ($htmlBusca === null || $htmlBusca === '') {
                continue;
            }

            $linksRestaurante = $this->extrairLinksRestaurantes($htmlBusca, $local);

            foreach ($linksRestaurante as $linkRestaurante) {
                $linkRestaurante = $this->normalizarUrlRestaurante($linkRestaurante);

                if ($linkRestaurante === '' || isset($visitados[$linkRestaurante])) {
                    continue;
                }

                $visitados[$linkRestaurante] = true;

                $itens = $this->buscarProdutosPorUrl($linkRestaurante);
                foreach ($itens as $item) {
                    $nomeProduto = isset($item['name']) ? trim((string) $item['name']) : '';
                    $precoProduto = isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : null;

                    if ($nomeProduto === '') {
                        continue;
                    }

                    if (!$this->produtoCorrespondente($nomeProduto, $categoria, $termo, $local, $linkRestaurante)) {
                        continue;
                    }

                    $resultados[] = [
                        'proId' => md5($linkRestaurante . '|' . $nomeProduto),
                        'proNome' => $nomeProduto,
                        'tamNome' => 'Padrão',
                        'estNome' => $this->obterNomeRestaurante($linkRestaurante, $htmlBusca),
                        'proImagem' => isset($item['image']) ? trim((string) $item['image']) : './images/default-product.png',
                        'menorPreco' => $precoProduto,
                        'avaliacao_media' => 0,
                        'company_uuid' => '',
                        'category' => $categoria !== '' ? $categoria : 'AiQFome',
                        'source' => 'aiqfome',
                        'linkExterno' => $linkRestaurante,
                    ];
                }
            }
        }

        usort($resultados, function ($a, $b) {
            $pa = isset($a['menorPreco']) && is_numeric($a['menorPreco']) ? (float) $a['menorPreco'] : PHP_FLOAT_MAX;
            $pb = isset($b['menorPreco']) && is_numeric($b['menorPreco']) ? (float) $b['menorPreco'] : PHP_FLOAT_MAX;
            return $pa <=> $pb;
        });

        return $resultados;
    }

    private function montarUrlsBusca($termo, $categoria, $local)
    {
        $urls = [];
        $localNormalizado = trim((string) $local);
        $localSlug = $this->normalizarBuscaTexto($localNormalizado);
        $localSlug = strtolower((string) preg_replace('/\s+/', '-', $localSlug));

        if ($localSlug !== '') {
            $urls[] = 'https://www.aiqfome.com/SP/' . rawurlencode($localSlug);
            $urls[] = 'https://www.aiqfome.com/restaurantes/' . rawurlencode($localSlug) . '-SP';
            $urls[] = 'https://aiqfome.com/SP/' . rawurlencode($localSlug);
            $urls[] = 'https://aiqfome.com/restaurantes/' . rawurlencode($localSlug) . '-SP';
        }

        $termos = array_filter([
            trim((string) $termo),
            trim((string) $categoria),
            trim((string) $local),
        ]);

        $consultas = [];
        foreach ($termos as $valor) {
            $consultas[] = $valor;
        }

        $consulta = implode(' ', $consultas);
        $consulta = trim((string) preg_replace('/\s+/', ' ', $consulta));

        if ($consulta !== '') {
            $codificada = rawurlencode($consulta);
            $urls[] = 'https://www.aiqfome.com/search?q=' . $codificada;
            $urls[] = 'https://www.aiqfome.com/search?query=' . $codificada;
            $urls[] = 'https://www.aiqfome.com/?search=' . $codificada;
            $urls[] = 'https://aiqfome.com/search?q=' . $codificada;
        }

        return array_values(array_unique($urls));
    }

    private function extrairLinksRestaurantes($html, $local = '')
    {
        if (!is_string($html) || $html === '') {
            return [];
        }

        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);
        $links = [];

        foreach ($matches[1] as $link) {
            $link = trim((string) $link);
            if ($link === '') {
                continue;
            }

            $linkSemQuery = preg_replace('/[?#].*$/', '', $link);
            $linkSemQuery = rtrim((string) $linkSemQuery, '/');

            if ($linkSemQuery === '' || strpos($linkSemQuery, '/img/') === 0 || strpos($linkSemQuery, '/js/') === 0 || strpos($linkSemQuery, '/css/') === 0) {
                continue;
            }

            if (preg_match('~^/?(?:https?://)?(?:www\.)?aiqfome\.com(/SP/[^/]+/[^/?#]+)?$~i', $linkSemQuery) !== 1 && preg_match('~^/SP/[^/]+/[^/?#]+$~i', $linkSemQuery) !== 1) {
                continue;
            }

            if ($local !== '' && $this->normalizarBuscaTexto($local) !== '' && strpos(strtolower($linkSemQuery), strtolower($this->normalizarBuscaTexto($local))) === false) {
                continue;
            }

            $links[] = $linkSemQuery;
        }

        return array_values(array_unique($links));
    }

    private function normalizarUrlRestaurante($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $url = preg_replace('/[?#].*$/', '', $url);
        $url = rtrim((string) $url, '/');

        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }

        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }

        if (strpos($url, '/') === 0) {
            return 'https://www.aiqfome.com' . $url;
        }

        if (strpos($url, 'aiqfome.com') !== false) {
            return 'https://' . $url;
        }

        return 'https://www.aiqfome.com/' . $url;
    }

    private function produtoCorrespondente($nomeProduto, $categoria, $termo, $local, $linkRestaurante)
    {
        $nomeProdutoNormalizado = $this->normalizarBuscaTexto($nomeProduto);
        $termoNormalizado = $this->normalizarBuscaTexto($termo);
        $categoriaNormalizada = $this->normalizarBuscaTexto($categoria);
        $localNormalizado = $this->normalizarBuscaTexto($local);

        if ($termoNormalizado !== '') {
            $termoEmNome = strpos(strtolower($nomeProdutoNormalizado), strtolower($termoNormalizado)) !== false;
            if (!$termoEmNome) {
                return false;
            }
        }

        if ($categoriaNormalizada !== '') {
            $variantes = $this->categoriaParaVariantes($categoria);
            $okCategoria = false;
            foreach ($variantes as $variacao) {
                $variacaoNormalizada = strtolower($this->normalizarBuscaTexto($variacao));
                if ($variacaoNormalizada === '') {
                    continue;
                }

                if (strpos(strtolower($nomeProdutoNormalizado), $variacaoNormalizada) !== false) {
                    $okCategoria = true;
                    break;
                }
            }

            if (!$okCategoria) {
                return false;
            }
        }

        if ($localNormalizado !== '') {
            $localNoLink = strpos(strtolower($this->normalizarBuscaTexto($linkRestaurante)), strtolower($localNormalizado)) !== false;
            if (!$localNoLink) {
                return false;
            }
        }

        return true;
    }

    private function obterNomeRestaurante($linkRestaurante, $htmlBusca)
    {
        if ($linkRestaurante !== '') {
            $partes = explode('/', rtrim((string) preg_replace('/[?#].*$/', '', $linkRestaurante), '/'));
            $ultimo = end($partes);
            if ($ultimo !== false && $ultimo !== '') {
                $ultimo = preg_replace('/[-_]+/', ' ', $ultimo);
                $ultimo = trim((string) $ultimo);
                if ($ultimo !== '') {
                    return ucwords($ultimo);
                }
            }
        }

        if (is_string($htmlBusca) && preg_match('/<title[^>]*>(.*?)<\/title>/is', $htmlBusca, $titulo)) {
            $tituloTexto = trim(strip_tags($titulo[1] ?? ''));
            if ($tituloTexto !== '') {
                return $tituloTexto;
            }
        }

        return 'AiQFome';
    }

    /**
     * Extrai os produtos a partir do HTML da página do restaurante.
     */
    private function extrairProdutos($html)
    {
        if (!is_string($html) || $html === '') {
            return [];
        }

        $produtos = [];

        preg_match_all('/<li\b[^>]*class="[^"]*(?:margin-bottom|menu-item)[^"]*"[^>]*>(.*?)<\/li>/is', $html, $blocos, PREG_SET_ORDER);

        if (!empty($blocos)) {
            foreach ($blocos as $bloco) {
                $itemHtml = $bloco[1] ?? '';

                if ($itemHtml === '') {
                    continue;
                }

                preg_match('/<h3\b[^>]*>\s*(.*?)\s*<\/h3>/is', $itemHtml, $tituloMatch);
                $nome = trim(strip_tags(html_entity_decode($tituloMatch[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $nome = preg_replace('/\s+/', ' ', $nome);
                $nome = trim((string) $nome);

                if ($nome === '' || strlen($nome) < 3) {
                    continue;
                }

                $preco = null;
                preg_match('/<a\b[^>]*>\s*R\$\s*([^<]+)\s*<\/a>/is', $itemHtml, $precoMatch);
                if (empty($precoMatch[1])) {
                    preg_match('/(?:\bR\$\s*|R\$\s*)(\d{1,3}(?:[.,]\d{2})?)/i', $itemHtml, $precoMatch);
                }

                if (!empty($precoMatch[1])) {
                    $valor = trim((string) $precoMatch[1]);
                    $valor = str_replace('.', '', $valor);
                    $valor = str_replace(',', '.', $valor);
                    $preco = (float) $valor;
                }

                $imagem = null;
                if (preg_match('/(?:data-url|src)=["\']([^"\']+)["\']/i', $itemHtml, $imgMatch)) {
                    $imagem = trim($imgMatch[1]);
                }

                $produtos[] = [
                    'name' => $nome,
                    'price' => $preco,
                    'image' => $imagem,
                    'source' => 'aiqfome'
                ];
            }
        }

        if (!empty($produtos)) {
            return $produtos;
        }

        preg_match_all('/<h3\b[^>]*>\s*(.*?)\s*<\/h3>/is', $html, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as $match) {
            $nome = trim(strip_tags(html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $nome = preg_replace('/\s+/', ' ', $nome);
            $nome = trim((string) $nome);

            if ($nome === '' || strlen($nome) < 3) {
                continue;
            }

            $offset = (int) $match[1];
            $snippet = substr($html, $offset, 2000);

            preg_match('/(?:\bR\$\s*|R\$\s*)(\d{1,3}(?:[.,]\d{2})?)/i', $snippet, $precoMatch);
            $preco = null;
            if (!empty($precoMatch[1])) {
                $valor = trim((string) $precoMatch[1]);
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
                $preco = (float) $valor;
            }

            $imagem = null;
            if (preg_match('/<img\b[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $snippet, $imgMatch)) {
                $imagem = trim($imgMatch[1]);
            }

            $produtos[] = [
                'name' => $nome,
                'price' => $preco,
                'image' => $imagem,
                'source' => 'aiqfome'
            ];
        }

        return $produtos;
    }

    /**
     * Busca o HTML de uma URL externa.
     */
    private function requestHtml($url)
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8',
                'Referer: https://www.aiqfome.com/',
                'Upgrade-Insecure-Requests: 1'
            ]
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            $this->log('Aiqfome - falha ao buscar URL: ' . $url . ' - ' . $error);
            return null;
        }

        return (string) $response;
    }

    private function categoriaParaTexto($categoria)
    {
        $variantes = $this->categoriaParaVariantes($categoria);
        return !empty($variantes) ? $variantes[0] : '';
    }

    private function categoriaParaVariantes($categoria)
    {
        $categoria = trim((string) $categoria);

        if ($categoria === '') {
            return [];
        }

        $mapa = [
            '1' => ['hamburguer', 'hamburgueres', 'lanches', 'lanche'],
            '2' => ['hot dog', 'cachorro quente', 'cachorro-quente', 'dog'],
            '3' => ['pizza'],
            '4' => ['porcao', 'porções', 'porcoes'],
            'hamburguer' => ['hamburguer', 'hamburgueres', 'lanches', 'lanche'],
            'hamburgueres' => ['hamburguer', 'hamburgueres', 'lanches', 'lanche'],
            'hotdog' => ['hot dog', 'cachorro quente', 'cachorro-quente', 'dog'],
            'hot dog' => ['hot dog', 'cachorro quente', 'cachorro-quente', 'dog'],
            'dog' => ['hot dog', 'cachorro quente', 'cachorro-quente', 'dog'],
            'cachorro quente' => ['cachorro quente', 'hot dog', 'dog'],
            'cachorro-quente' => ['cachorro quente', 'hot dog', 'dog'],
            'pizza' => ['pizza'],
            'porcao' => ['porcao', 'porções', 'porcoes'],
            'porções' => ['porcao', 'porções', 'porcoes'],
            'porcoes' => ['porcao', 'porções', 'porcoes'],
            'lanches' => ['hamburguer', 'hamburgueres', 'lanches', 'lanche'],
            'lanche' => ['hamburguer', 'hamburgueres', 'lanches', 'lanche']
        ];

        $chave = strtolower(str_replace(['-', '_'], ' ', $categoria));
        if (isset($mapa[$chave])) {
            return array_values(array_unique($mapa[$chave]));
        }

        $normalizada = $this->normalizarBuscaTexto($categoria);
        foreach ($mapa as $chaveMapa => $variantes) {
            $chaveMapaNormalizada = $this->normalizarBuscaTexto($chaveMapa);
            if ($chaveMapaNormalizada === $normalizada) {
                return array_values(array_unique($variantes));
            }
        }

        return [$categoria];
    }

    private function normalizarBuscaTexto($texto)
    {
        $texto = trim((string) $texto);

        if (function_exists('iconv')) {
            $texto = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        }

        $texto = preg_replace('/[^a-zA-Z0-9\s]/', '', (string) $texto);
        $texto = preg_replace('/\s+/', ' ', (string) $texto);

        return trim((string) $texto);
    }

    private function normalizarNomeEmpresa($texto)
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return '';
        }

        $texto = preg_replace('/\bAIQFome\b/iu', '', $texto);
        $texto = preg_replace('/\s*[-–:]\s*([A-ZÀ-Ža-zà-ÿ0-9].*)$/u', ' $1', $texto);
        $texto = preg_replace('/\s+de\s+[A-ZÀ-Ža-zà-ÿ0-9\- ]+$/u', '', $texto);
        $texto = preg_replace('/\s+\|\s+.*/u', '', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);

        return trim((string) $texto, " \t\n\r-–:");
    }

    private function limparTexto($texto)
    {
        $texto = strip_tags((string) $texto);
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace('/\s+/', ' ', $texto);

        return trim((string) $texto);
    }

    private function log($mensagem)
    {
        if (function_exists('error_log')) {
            error_log($mensagem);
        }
    }
}
