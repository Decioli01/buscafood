import sys
import re
import json
import asyncio
from playwright.async_api import async_playwright

def sanitizar_preco(texto_preco):
    if not texto_preco:
        return 0.0
    texto_limpo = texto_preco.replace('\xa0', ' ').replace('&nbsp;', ' ')
    match = re.search(r'[\d\.,]+', texto_limpo)
    if match:
        valor_str = match.group(0).replace('.', '').replace(',', '.')
        try:
            return float(valor_str)
        except ValueError:
            return 0.0
    return 0.0

def formatar_slug_localizacao(local):
    local = local.lower().strip()
    substituicoes = {
        'á': 'a', 'à': 'a', 'ã': 'a', 'â': 'a', 'é': 'e', 'ê': 'e', 
        'í': 'i', 'ó': 'o', 'õ': 'o', 'ô': 'o', 'ú': 'u', 'ç': 'c'
    }
    for orig, dest in substituicoes.items():
        local = local.replace(orig, dest)
    
    local = re.sub(r'[^a-z0-9\s-]', '', local)
    local = re.sub(r'\s+', '-', local)
    return local

# Mapeia o ID da categoria enviado pelo formulário HTML do BuscaFood
MAPA_CATEGORIAS = {
    "1": "hamburguer",
    "2": "cachorro-quente",
    "3": "pizza",
    "4": "porcao"
}

async def buscar_produtos_delivery_much(page, cidade_estado, termo_busca, nome_categoria):
    produtos_encontrados = []
    
    try:
        # 1. Acessa a Home do Delivery Much
        await page.goto("https://www.deliverymuch.com.br/", wait_until="domcontentloaded", timeout=25000)

        # 2. Localiza e preenche o campo de cidade
        input_cidade = page.locator("input#city")
        await input_cidade.wait_for(state="visible", timeout=10000)
        
        nome_cidade_digitar = cidade_estado.replace('-', ' ')
        await input_cidade.fill("")
        await input_cidade.type(nome_cidade_digitar, delay=100)

        # 3. Seleciona a opção na lista de sugestões
        try:
            sugestao = page.locator("li, div[data-testid='city-item'], .suggestion-item, div[class*='suggestion']").filter(has_text=re.compile(nome_cidade_digitar, re.IGNORECASE)).first
            await sugestao.wait_for(state="visible", timeout=3000)
            await sugestao.click()
        except Exception:
            pass

        # 4. Clica em "Encontrar Restaurantes"
        botao_busca = page.locator("button[data-testid='search-button']")
        await botao_busca.wait_for(state="visible", timeout=5000)
        await botao_busca.click()

        await page.wait_for_load_state("domcontentloaded", timeout=15000)
        await page.wait_for_timeout(2000)

        # 5. Aplica o Filtro de Categoria na listagem (se disponível)
        if nome_categoria:
            try:
                # Tenta clicar no botão/chip da categoria correspondente
                chip_categoria = page.locator(f"button, a, div[data-testid*='category']").filter(has_text=re.compile(nome_categoria, re.IGNORECASE)).first
                if await chip_categoria.is_visible():
                    await chip_categoria.click()
                    await page.wait_for_timeout(1500)
            except Exception:
                pass

        url_busca = page.url

        # Extrai os links dos estabelecimentos filtrados
        links_lojas = await page.eval_on_selector_all('a[href*="/lista-lojas/"]', 'elements => elements.map(e => e.href)')
        links_lojas = list(set([l for l in links_lojas if l != url_busca]))

        # Varre os estabelecimentos pertencentes à categoria
        for index, url_loja in enumerate(links_lojas[:4]):
            try:
                await page.goto(url_loja, wait_until="domcontentloaded", timeout=15000)
                
                el_nome_est = await page.query_selector('h1.company__name')
                nome_est = (await el_nome_est.inner_text()).strip() if el_nome_est else "Delivery Much"

                seletor_card = 'div[data-testid^="product-card--"]'
                try:
                    await page.wait_for_selector(seletor_card, timeout=3000)
                except:
                    continue

                itens = await page.query_selector_all(seletor_card)

                for idx_p, item in enumerate(itens):
                    el_nome = await item.query_selector('h3.product-card__title')
                    el_desc = await item.query_selector('p.product-card__description')
                    el_preco = await item.query_selector('p.product-card__price')

                    nome = (await el_nome.inner_text()).strip() if el_nome else ""
                    descricao = (await el_desc.inner_text()).strip() if el_desc else ""
                    preco_raw = await el_preco.inner_text() if el_preco else "0"
                    preco = sanitizar_preco(preco_raw)

                    # Verifica se o produto corresponde ao termo pesquisado
                    if termo_busca.lower() in nome.lower() or termo_busca.lower() in descricao.lower():
                        if nome and preco > 0:
                            produtos_encontrados.append({
                                "proId": f"dm_{index}_{idx_p}",
                                "proNome": nome,
                                "proDescricao": descricao if descricao else "Sem descrição disponível",
                                "estNome": nome_est,
                                "estEndereco": cidade_estado.upper().replace('-', ' '),
                                "menorPreco": f"{preco:.2f}".replace('.', ','),
                                "tamNome": "Único",
                                "avaliacao_media": "4.8",
                                "dataAtualizacao": "Hoje",
                                "proImagem": "default_food.png",
                                "plataforma": "Delivery Much",
                                "lnk_much": url_loja,
                                "preco_del_much": f"{preco:.2f}".replace('.', ','),
                                "lnk_ifood": "",
                                "preco_ifood": "0.00",
                                "lnk_aiqfome": "",
                                "preco_aiqfome": "0.00"
                            })
            except Exception:
                continue
    except Exception:
        pass

    return produtos_encontrados


async def buscar_produtos_aiqfome(page, cidade_estado, termo_busca, nome_categoria):
    produtos_encontrados = []
    url_busca = f"https://www.aiqfome.com/restaurantes/{cidade_estado}"

    try:
        await page.goto(url_busca, wait_until="domcontentloaded", timeout=20000)
        await page.wait_for_timeout(1500)

        # Filtra a categoria se houver botão correspondente na listagem do Aiqfome
        if nome_categoria:
            try:
                filtro_cat = page.locator("a, button, div.tag").filter(has_text=re.compile(nome_categoria, re.IGNORECASE)).first
                if await filtro_cat.is_visible():
                    await filtro_cat.click()
                    await page.wait_for_timeout(1500)
            except Exception:
                pass

        links_lojas = await page.eval_on_selector_all('a[href*="/"]', 'elements => elements.map(e => e.href)')
        links_lojas = list(set([l for l in links_lojas if f"aiqfome.com/{cidade_estado.replace('-', '/')}" in l]))

        for index, url_loja in enumerate(links_lojas[:4]):
            try:
                await page.goto(url_loja, wait_until="domcontentloaded", timeout=15000)

                el_nome_est = await page.query_selector("h1#nome-restaurante-fix")
                nome_est = (await el_nome_est.inner_text()).strip() if el_nome_est else "Aiqfome"

                itens = await page.query_selector_all(".row")
                for idx_p, item in enumerate(itens):
                    el_nome = await item.query_selector(".nome-item")
                    el_preco = await item.query_selector(".preco, .preco-item, .valor")

                    if el_nome:
                        nome = (await el_nome.inner_text()).strip()
                        preco_raw = await el_preco.inner_text() if el_preco else "0"
                        preco = sanitizar_preco(preco_raw)

                        if termo_busca.lower() in nome.lower():
                            if nome and preco > 0:
                                produtos_encontrados.append({
                                    "proId": f"aiq_{index}_{idx_p}",
                                    "proNome": nome,
                                    "proDescricao": "Sem descrição disponível",
                                    "estNome": nome_est,
                                    "estEndereco": cidade_estado.upper().replace('-', ' '),
                                    "menorPreco": f"{preco:.2f}".replace('.', ','),
                                    "tamNome": "Único",
                                    "avaliacao_media": "4.9",
                                    "dataAtualizacao": "Hoje",
                                    "proImagem": "default_food.png",
                                    "plataforma": "Aiqfome",
                                    "lnk_much": "",
                                    "preco_del_much": "0.00",
                                    "lnk_ifood": "",
                                    "preco_ifood": "0.00",
                                    "lnk_aiqfome": url_loja,
                                    "preco_aiqfome": f"{preco:.2f}".replace('.', ',')
                                })
            except Exception:
                continue
    except Exception:
        pass

    return produtos_encontrados


async def main():
    if len(sys.argv) < 3:
        print(json.dumps([]))
        return

    cidade_bruta = sys.argv[1]
    termo_busca = sys.argv[2].strip()
    cidade_estado = formatar_slug_localizacao(cidade_bruta)

    # Captura o parâmetro opcional de categoria (ARGV 3)
    cat_id = sys.argv[3] if len(sys.argv) > 3 else "1"
    nome_categoria = MAPA_CATEGORIAS.get(str(cat_id), "")

    async with async_playwright() as p:
        browser = await p.chromium.launch(
            channel="chrome",
            headless=False  # Altere para True quando estiver pronto para produção
        )
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        )
        page = await context.new_page()

        produtos_much = await buscar_produtos_delivery_much(page, cidade_estado, termo_busca, nome_categoria)
        produtos_aiq = await buscar_produtos_aiqfome(page, cidade_estado, termo_busca, nome_categoria)

        resultado_total = produtos_much + produtos_aiq

        await browser.close()
        print(json.dumps(resultado_total, ensure_ascii=False))

if __name__ == "__main__":
    asyncio.run(main())